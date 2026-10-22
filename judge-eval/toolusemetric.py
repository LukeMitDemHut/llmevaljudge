"""
ToolUseMetric — evaluates how well an LLM uses provided (simulated) tools.

Flow:
1. The evaluated model receives the task plus tool definitions (OpenAI function calling).
2. Round loop: tool_calls -> simulation judge (simulation_prompt + conversation history)
   -> role:"tool" message -> next round. The loop ends when the configured round
   limit is reached, when the model answers without tool calls, or on technical errors
   (errors always score 0 — a silently dropped evaluation must not happen).
3. Final evaluation: a separate evaluation call with the user-defined evaluation_prompt,
   the full transcript and an optional expected-behavior block. Internal scale is
   0-10, the reported score is 0-1.

Note: all completion calls use streaming. The gateway's non-streaming endpoint
drops `tool_calls` for gpt-oss-120b (vLLM aggregation quirk); Qwen works in both
modes, streaming is the reliable common path.
"""

import json
import re
from typing import Optional

from deepeval.metrics import BaseMetric
from deepeval.test_case import LLMTestCase
from eval_logger import eval_logger

# Reasons why the tool interaction loop ended
ROUND_END_MAX_ROUNDS = "max_rounds_reached"
ROUND_END_FINAL_ANSWER = "final_answer_no_tool_calls"
ROUND_END_ERROR = "error"

ERROR_SCORE = 0.0


class OpenAIClientStreaming:
    """
    Minimal OpenAI-compatible streaming client for chat completions.

    A dedicated client is used instead of the openai package because tool-call
    deltas need explicit handling and the gateway drops tool_calls in
    non-streaming completions for gpt-oss-120b.
    """

    def __init__(self, base_url: str, api_key: str):
        self.base_url = base_url.rstrip("/")
        self.api_key = api_key

    def chat(self, model: str, messages: list, tools: Optional[list] = None,
             response_format: Optional[dict] = None) -> dict:
        """
        Executes a streaming chat completion call.

        Returns: {"finish_reason": str|None, "content": str,
                  "tool_calls": [{"id", "name", "arguments"}]}
        """
        import requests as _requests

        payload = {"model": model, "messages": messages, "stream": True}
        if tools:
            payload["tools"] = tools
        if response_format:
            payload["response_format"] = response_format

        eval_logger.log_llm_request("tool_use_metric",
            prompt=json.dumps(messages, ensure_ascii=False)[:2000],
            model_info={"name": model, "url": self.base_url, "streaming": True})

        r = _requests.post(
            f"{self.base_url}/chat/completions",
            json=payload,
            headers={"Authorization": f"Bearer {self.api_key}"},
            stream=True,
            timeout=180,
        )
        r.raise_for_status()

        finish_reason = None
        content_parts = []
        tool_calls_acc = {}  # index -> {"id", "name", "arguments"}

        for line in r.iter_lines(decode_unicode=True):
            if not line or not line.startswith("data: "):
                continue
            body = line[len("data: "):]
            if body == "[DONE]":
                break
            try:
                chunk = json.loads(body)
            except json.JSONDecodeError:
                continue

            choices = chunk.get("choices") or []
            if not choices:
                continue
            choice = choices[0]
            if choice.get("finish_reason"):
                finish_reason = choice["finish_reason"]

            delta = choice.get("delta") or {}
            if delta.get("content"):
                content_parts.append(delta["content"])

            for tcd in delta.get("tool_calls") or []:
                idx = tcd.get("index", 0)
                acc = tool_calls_acc.setdefault(idx, {"id": None, "name": "", "arguments": ""})
                if tcd.get("id"):
                    acc["id"] = tcd["id"]
                fn = tcd.get("function") or {}
                if fn.get("name"):
                    acc["name"] += fn["name"]
                if fn.get("arguments"):
                    acc["arguments"] += fn["arguments"]

        tool_calls = [tool_calls_acc[k] for k in sorted(tool_calls_acc)]
        result = {
            "finish_reason": finish_reason,
            "content": "".join(content_parts),
            "tool_calls": tool_calls,
        }
        eval_logger.log_llm_response("tool_use_metric",
            response=result["content"][:1000] or json.dumps(
                [{"name": t["name"], "args": t["arguments"][:200]} for t in tool_calls],
                ensure_ascii=False),
            metadata={"finish_reason": finish_reason, "tool_call_count": len(tool_calls)})
        return result


class ToolUseMetric(BaseMetric):
    """
    Evaluates how well an LLM uses provided (simulated) tools to solve a task.

    Definition structure (from metric.definition JSON):
    {
        "tools": [{"name", "description", "parameters": [{"name","type","description","required"}]}],
        "simulation_prompt": str,
        "evaluation_prompt": str,
        "max_tool_rounds": int
    }
    """

    def __init__(
        self,
        model,           # judge model wrapper (DeepEvalBaseLLM) used for simulation + final evaluation
        evaluatee_url: str,
        evaluatee_key: str,
        evaluatee_name: str,
        definition: dict,
        threshold: float = 0.5,
        system_prompt: str = "",
    ):
        self.model = model
        self.threshold = threshold

        # Model under evaluation
        self.evaluatee = OpenAIClientStreaming(evaluatee_url, evaluatee_key)
        self.evaluatee_name = evaluatee_name

        # Simulation and final evaluation are separate judge tasks (separate prompts).
        # Both go through the same judge API but use independent client instances.
        self._judge_api = self._extract_judge_api_info(model)
        self.simulation_client = OpenAIClientStreaming(self._judge_api["url"], self._judge_api["key"])
        self.evaluation_client = OpenAIClientStreaming(self._judge_api["url"], self._judge_api["key"])

        # Definition
        self.tools = definition.get("tools", [])
        self.simulation_prompt = definition.get("simulation_prompt", "")
        self.evaluation_prompt = definition.get("evaluation_prompt", "")
        self.max_tool_rounds = int(definition.get("max_tool_rounds", 5))
        self.system_prompt = system_prompt

        # Result metadata (surfaced in the UI)
        self.rounds: list = []          # one entry per round: {"round", "tool_calls", "simulated", "finish_reason"}
        self.final_answer: str = ""
        self.end_reason: str = ""
        self.evaluation_notes: str = ""

        eval_logger.info("tool_use_metric", "Initialized ToolUseMetric", {
            "evaluatee": evaluatee_name,
            "tools": [t.get("name") for t in self.tools],
            "max_tool_rounds": self.max_tool_rounds,
            "simulation_prompt_length": len(self.simulation_prompt),
            "evaluation_prompt_length": len(self.evaluation_prompt),
        })

    def _extract_judge_api_info(self, judge_model) -> dict:
        """Extracts API connection data from the judge model wrapper."""
        return {
            "url": judge_model.api_base,
            "key": judge_model.api_key,
            "name": judge_model.model_name,
        }

    # ------------------------------------------------------------------ #
    # Tool schema -> OpenAI tools parameter
    # ------------------------------------------------------------------ #

    def _default_parameters_schema(self) -> dict:
        return {"type": "object", "properties": {}, "required": [], "additionalProperties": False}

    def _tools_for_openai(self) -> list:
        """Converts the tool definitions into OpenAI function-calling format.

        tools[].parameters is a JSON Schema object (built in the UI with the
        schema editor); it is passed through after enforcing the OpenAI strict
        requirements. Legacy list-format definitions are migrated.
        """
        out = []
        for tool in self.tools:
            parameters = tool.get("parameters")

            # Legacy migration: parameters was a plain list of simple fields
            if (
                isinstance(parameters, list)
                and parameters
                and all(isinstance(p, dict) and isinstance(p.get("name"), str) for p in parameters)
            ):
                properties = {}
                required = []
                for p in parameters:
                    prop = {"type": p.get("type") or "string"}
                    if p.get("description"):
                        prop["description"] = p["description"]
                    properties[p["name"]] = prop
                    if p.get("required"):
                        required.append(p["name"])
                parameters = {
                    "type": "object",
                    "properties": properties,
                    "required": required,
                    "additionalProperties": False,
                }

            if not isinstance(parameters, dict):
                parameters = self._default_parameters_schema()

            out.append({
                "type": "function",
                "function": {
                    "name": tool["name"],
                    "description": tool.get("description", ""),
                    "parameters": parameters,
                },
            })
        return out

    # ------------------------------------------------------------------ #
    # Simulation
    # ------------------------------------------------------------------ #

    def _response_format_for(self, tool_name: str) -> Optional[dict]:
        """
        Builds the response_format (json_schema) for the judge simulation call
        from the tool's response_schema, if one is configured.
        """
        tool = next((t for t in self.tools if t.get("name") == tool_name), None)
        schema = (tool or {}).get("response_schema")
        if not isinstance(schema, dict) or not schema:
            return None
        return {
            "type": "json_schema",
            "json_schema": {
                "name": "tool_result_" + re.sub(r"[^a-zA-Z0-9_]", "_", tool_name)[:40],
                "strict": True,
                "schema": schema,
            },
        }

    def _build_simulation_messages(self, task_input: str, round_index: int,
                                   tool_call: dict, history_summary: str) -> list:
        """Builds the judge prompt for simulated tool results (separate from the evaluation prompt)."""
        system = (
            "You are a tool simulator in an LLM evaluation. "
            "You simulate the results of tool calls made by the model under evaluation. "
            "Respond ONLY with JSON: {\"result\": \"<simulated tool result as plain text>\"}. "
            "The plain text is what the model sees as the tool response — write it realistically, "
            "as a real tool would respond."
        )

        tool_schema_str = json.dumps(self.tools, ensure_ascii=False, indent=2)

        user = (
            f"SIMULATION RULES (defined by the evaluation user):\n{self.simulation_prompt}\n\n"
            f"AVAILABLE TOOLS (schemas):\n{tool_schema_str}\n\n"
            f"INVOKED TOOL: {tool_call['name']}\n"
            f"ARGUMENTS: {tool_call['arguments']}\n"
            f"CURRENT ROUND: {round_index} of max. {self.max_tool_rounds}\n\n"
            f"PREVIOUSLY SIMULATED RESULTS (history):\n{history_summary or '(none — this is the first round)'}\n\n"
            f"ORIGINAL TASK OF THE MODEL:\n{task_input}\n\n"
            "Produce the simulated tool result for this round. Take into account both previous "
            "results and the original task (context-dependent simulation)."
        )
        return [{"role": "system", "content": system}, {"role": "user", "content": user}]

    def _parse_simulation_response(self, response: str, tool_name: str = "") -> str:
        """
        Serializes the simulated tool result into the text used for the tool
        response message.

        String values are used as-is; any other schema-conform value (object,
        array, number, boolean) is JSON-encoded — real tools also return plain
        structured data. Resolution order: configured schema property, then a
        "result" key, then the first value in the object.
        """
        text = (response or "").strip()
        m = re.search(r"\{.*\}", text, re.DOTALL)
        if not m:
            raise ValueError(f"Simulator response is not JSON: {text[:200]}")
        try:
            data = json.loads(m.group(0))
        except json.JSONDecodeError as e:
            raise ValueError(f"Simulator JSON is invalid: {e}: {text[:200]}")
        if not isinstance(data, dict):
            raise ValueError(f"Simulator JSON is not an object: {text[:200]}")

        candidate_keys = ["result"]
        if tool_name:
            tool = next((t for t in self.tools if t.get("name") == tool_name), None)
            props = ((tool or {}).get("response_schema") or {}).get("properties") or {}
            candidate_keys += list(props.keys())

        for key in candidate_keys:
            if key in data and data[key] is not None:
                value = data[key]
                if isinstance(value, str) and value.strip():
                    return value.strip()
                return json.dumps(value, ensure_ascii=False)

        # Fallback: first non-empty entry in the object
        for value in data.values():
            if value is not None:
                if isinstance(value, str) and value.strip():
                    return value.strip()
                return json.dumps(value, ensure_ascii=False)

        raise ValueError(f"Simulator JSON has no usable result field (tool={tool_name!r}): {text[:200]}")

    def _simulate_tool_result(self, task_input: str, round_index: int,
                              tool_call: dict, history_summary: str) -> str:
        """Requests one simulated tool result from the judge (schema-enforced when configured)."""
        messages = self._build_simulation_messages(task_input, round_index, tool_call, history_summary)
        response_format = self._response_format_for(tool_call["name"])
        resp = self.simulation_client.chat(
            self._judge_api["name"], messages, tools=None,
            response_format=response_format,
        )
        return self._parse_simulation_response(resp["content"], tool_call["name"])

    # ------------------------------------------------------------------ #
    # Final evaluation
    # ------------------------------------------------------------------ #

    def _build_evaluation_messages(self, test_case: LLMTestCase, conversation: list) -> list:
        """Builds the final evaluation prompt: task, tools, prompts, system prompt, transcript."""
        system = (
            "You are a precise LLM-as-a-Judge grader. "
            "Assess how well a language model used its available tools, strictly following the "
            "criteria defined by the evaluation user. Respond ONLY with JSON: "
            "{\"score\": <number between 0 and 10>, \"reasoning\": \"<detailed reasoning>\"}. "
            "A score of 0 means completely insufficient, 10 means excellent tool usage."
        )

        tool_schema_str = json.dumps(self.tools, ensure_ascii=False, indent=2)
        conversation_str = self._format_conversation(conversation)

        # The system prompt the evaluated model actually received (contains the
        # injected {context}); taken from the conversation, not self.system_prompt,
        # because the fallback prompt lives there. Omitted when none exists.
        system_prompt = ""
        if conversation and conversation[0].get("role") == "system":
            system_prompt = conversation[0].get("content", "")

        system_section = (
            f"SYSTEM PROMPT GIVEN TO THE EVALUATED MODEL:\n{system_prompt}\n\n"
            if system_prompt.strip()
            else ""
        )

        user = (
            f"TASK (original user prompt):\n{test_case.input}\n\n"
            f"AVAILABLE TOOLS (schemas):\n{tool_schema_str}\n\n"
            f"SIMULATION RULES (how the tool results were generated):\n{self.simulation_prompt}\n\n"
            f"EVALUATION CRITERIA (defined by the evaluation user):\n{self.evaluation_prompt}\n"
            f"FULL INTERACTION TRANSCRIPT (all tool rounds, chronological):\n{conversation_str}\n\n"
            f"{system_section}"
            "Evaluate how well the model used its tools based on the evaluation criteria. "
            "Consider the path as well (rounds, parameters, reactions to intermediate results, "
            "when the interaction was ended), not just the final answer."
        )
        return [{"role": "system", "content": system}, {"role": "user", "content": user}]

    def _format_conversation(self, conversation: list) -> str:
        """Formats the conversation as a readable transcript with round markers."""
        lines = []
        round_no = 0
        for msg in conversation:
            role = msg.get("role")
            if role == "system":
                lines.append(f"[SYSTEM PROMPT]\n{msg.get('content', '')}")
            if role == "user":
                lines.append(f"[TASK]\n{msg.get('content', '')}")
            elif role == "assistant":
                tcs = msg.get("tool_calls") or []
                if tcs:
                    round_no += 1
                    calls = "; ".join(
                        f"{t['function']['name']}({t['function'].get('arguments','')})"
                        for t in tcs
                    )
                    lines.append(f"[ROUND {round_no} — MODEL CALL]")
                    lines.append(calls)
                else:
                    lines.append(f"[FINAL ANSWER]\n{msg.get('content','')}")
            elif role == "tool":
                lines.append(f"[SIMULATED TOOL RESULT for round {round_no}]\n{msg.get('content','')}")
        return "\n\n".join(lines)

    def _parse_evaluation_response(self, response: str) -> dict:
        """Extracts score (0-10) and reasoning from the evaluation response (markdown-tolerant)."""
        text = (response or "").strip()

        # Fallback pattern first: SCORE-style lines, also with markdown bolding
        # like "**SCORE:** 7.5" (observed judge model behaviour, cf. TALE parser fix)
        sm = re.search(r"(?:\*\*)?\s*SCORE:?\s*(?:\*\*)?\s*:?\s*([0-9]+(?:\.[0-9]+)?)", text, re.IGNORECASE)
        sm_json = re.search(r"\{.*\}", text, re.DOTALL)

        if sm_json:
            try:
                data = json.loads(sm_json.group(0))
                raw_score = data.get("score")
                reasoning = data.get("reasoning") or data.get("reason") or ""
                if raw_score is None and sm:
                    raw_score = sm.group(1)
                if raw_score is not None:
                    score = float(raw_score)
                    if 0 <= score <= 10:
                        if not reasoning.strip():
                            reasoning = "(no reasoning provided)"
                        return {"score": score, "reasoning": reasoning.strip()}
                    raise ValueError(f"Evaluation score out of range 0-10: {score}")
            except json.JSONDecodeError:
                pass  # falls through to the SCORE fallback below

        if sm:
            return {"score": float(sm.group(1)), "reasoning": text}

        raise ValueError(f"Evaluation response has no extractable score: {text[:300]}")

    # ------------------------------------------------------------------ #
    # measure() — the main loop
    # ------------------------------------------------------------------ #

    def measure(self, test_case: LLMTestCase, *args, **kwargs) -> float:
        eval_logger.info("tool_use_metric", "Starting tool-use evaluation", {
            "input": (test_case.input or "")[:200],
            "expected_output": bool(getattr(test_case, "expected_output", None)),
        })

        conversation = []
        try:
            sys_msg = self.system_prompt or (
                "Use the available tools to solve the task. "
                "Call tools whenever they help you make progress; once you have enough "
                "information, provide your final answer in natural language."
            )
            conversation.append({"role": "system", "content": sys_msg})
            conversation.append({"role": "user", "content": test_case.input})

            tools_openai = self._tools_for_openai()
            history_summary = ""       # feeds context-dependent simulation
            round_no = 0
            self.rounds = []

            while True:
                round_no += 1
                if round_no > self.max_tool_rounds:
                    self.end_reason = ROUND_END_MAX_ROUNDS
                    eval_logger.decision("tool_use_metric",
                        f"Max rounds reached ({self.max_tool_rounds}); ending tool interaction",
                        {"rounds_used": round_no - 1})
                    break

                eval_logger.info("tool_use_metric", f"Round {round_no}: requesting model response",
                                 {"max_rounds": self.max_tool_rounds})

                resp = self.evaluatee.chat(self.evaluatee_name, conversation, tools=tools_openai)
                finish = resp["finish_reason"]
                tc_list = resp["tool_calls"]

                # Fall: keine Tool-Calls -> finale Antwort -> Interaction endet
                if not tc_list:
                    self.final_answer = resp["content"]
                    self.end_reason = ROUND_END_FINAL_ANSWER
                    conversation.append({"role": "assistant", "content": resp["content"]})
                    eval_logger.decision("tool_use_metric",
                        "Model emitted no tool calls; final answer given",
                        {"round": round_no, "answer_length": len(self.final_answer)})
                    break

                if finish != "tool_calls":
                    eval_logger.info("tool_use_metric",
                        f"Tool calls present but finish_reason={finish}; continuing as tool round",
                        {"round": round_no})

                assistant_msg = {
                    "role": "assistant",
                    "content": resp["content"] or None,
                    "tool_calls": [
                        {"id": tc.get("id") or f"call_{round_no}_{i}",
                         "type": "function",
                         "function": {"name": tc["name"], "arguments": tc["arguments"]}}
                        for i, tc in enumerate(tc_list)
                    ],
                }
                conversation.append(assistant_msg)

                round_record = {"round": round_no, "tool_calls": [], "simulated": [], "finish_reason": finish}

                for i, tc in enumerate(tc_list):
                    try:
                        parsed_args = json.loads(tc["arguments"]) if tc["arguments"].strip() else {}
                    except json.JSONDecodeError:
                        # Unparsable tool call -> hard stop with 0 points
                        err = f"Round {round_no}, call {i+1}: unparsable tool arguments: {tc['arguments'][:200]}"
                        eval_logger.decision("tool_use_metric", "Tool call not parseable; scoring 0", {"error": err})
                        self.end_reason = ROUND_END_ERROR
                        self.evaluation_notes = err
                        self._finish_with_error(test_case, conversation, err)
                        return self.score

                    round_record["tool_calls"].append({"name": tc["name"], "arguments": parsed_args})

                    try:
                        sim = self._simulate_tool_result(
                            test_case.input, round_no,
                            {"name": tc["name"], "arguments": json.dumps(parsed_args, ensure_ascii=False)},
                            history_summary,
                        )
                    except Exception as e:
                        err = f"Round {round_no}, call {i+1}: simulation failed: {str(e)}"
                        eval_logger.decision("tool_use_metric", "Simulation failed; scoring 0", {"error": err})
                        self.end_reason = ROUND_END_ERROR
                        self.evaluation_notes = err
                        self._finish_with_error(test_case, conversation, err)
                        return self.score

                    round_record["simulated"].append(sim)
                    tool_msg = {
                        "role": "tool",
                        "tool_call_id": assistant_msg["tool_calls"][i]["id"],
                        "content": sim,
                    }
                    conversation.append(tool_msg)
                    history_summary += f"- Round {round_no} ({tc['name']}({json.dumps(parsed_args, ensure_ascii=False)})): {sim[:300]}\n"

                self.rounds.append(round_record)

            # Loop ended: run the final evaluation
            return self._finalize_evaluation(test_case, conversation)

        except Exception as e:
            # Generic abort: never lose an evaluation silently
            err = f"Evaluation aborted: {type(e).__name__}: {str(e)}"
            eval_logger.log_error("tool_use_metric", err, {"traceback": str(e)})
            self.end_reason = ROUND_END_ERROR
            self.evaluation_notes = err
            self.score = ERROR_SCORE
            self.success = False
            try:
                self._finish_with_error(test_case, conversation, err)
            except Exception:
                self.reason = f"Evaluation failed: {err}"
            return self.score

    def _finish_with_error(self, test_case: LLMTestCase, conversation: list, error: str):
        """Sets score 0 and a reason containing the error plus the transcript so far."""
        self.score = ERROR_SCORE
        self.success = False
        conv_str = self._format_conversation(conversation)
        self.reason = (
            f"Evaluation ended with an error and scored 0 points. ERROR: {error}\n\n"
            f"END REASON: {self.end_reason}\n\n"
            f"TRANSCRIPT UP TO THE ERROR:\n{conv_str[:3000]}"
        )
        self.evaluation_notes = error

    def _finalize_evaluation(self, test_case: LLMTestCase, conversation: list) -> float:
        eval_logger.info("tool_use_metric", "Starting final evaluation", {
            "end_reason": self.end_reason,
            "rounds": len(self.rounds),
            "final_answer_present": bool(self.final_answer),
        })

        messages = self._build_evaluation_messages(test_case, conversation)
        resp = self.evaluation_client.chat(self._judge_api["name"], messages, tools=None)

        try:
            parsed = self._parse_evaluation_response(resp["content"])
        except Exception as e:
            err = f"Final evaluation unparsable: {str(e)}"
            eval_logger.decision("tool_use_metric", "Final evaluation failed; scoring 0", {"error": err})
            self.end_reason = ROUND_END_ERROR
            self.evaluation_notes = err
            self._finish_with_error(test_case, conversation, err)
            return self.score

        internal_score = parsed["score"]           # 0-10 scale
        final_score = round(internal_score / 10.0, 4)  # 0-1 percentage
        reason = parsed["reasoning"]

        # Prefix the judge reasoning with structured transcript metadata
        rounds_summary = "; ".join(
            f"R{r['round']}: " + ", ".join(
                f"{tc['name']}({json.dumps(tc['arguments'], ensure_ascii=False)[:60]})"
                for tc in r["tool_calls"]
            )
            for r in self.rounds
        )
        self.reason = (
            f"Score (internal 0-10): {internal_score} -> Percentage: {final_score}\n"
            f"End reason: {self.end_reason}; Tool rounds: {len(self.rounds)}"
            f"{' (max ' + str(self.max_tool_rounds) + ')' if self.end_reason == ROUND_END_MAX_ROUNDS else ''}.\n"
            f"Round overview: {rounds_summary or '(none)'}\n\n"
            f"JUDGE ASSESSMENT:\n{reason}"
        )
        if self.final_answer:
            self.reason += f"\n\nFINAL MODEL ANSWER:\n{self.final_answer[:1500]}"
        if self.evaluation_notes:
            self.reason += f"\n\nNOTES: {self.evaluation_notes}"

        self.score = final_score
        self.success = final_score >= self.threshold
        eval_logger.decision("tool_use_metric", "Evaluation completed", {
            "score_0_10": internal_score,
            "score_0_1": final_score,
            "threshold": self.threshold,
            "success": self.success,
        })
        return self.score