"""
ToolUseMetric — Evaluation der Werkzeugnutzung eines LLMs.

Ablauf (siehe Arbeitsauftrag §2.3):
1. Das zu evaluierende Modell erhält Aufgabe + Werkzeuge (OpenAI function calling).
2. Rundenloop: tool_calls -> Simulations-Judge (simulation_prompt + Verlauf) -> role:tool Message -> nächste Runde.
   Abbruch: max_tool_rounds erreicht, keine Tool-Calls in der Antwort (finale Antwort), technischer Fehler.
3. Finale Bewertung: separates Evaluate-LLM mit evaluation_prompt und dem vollständigen Verlauf.
   Intern 0-10 (DeepEval-G-Eval-Konvention), Ergebnis 0-1 (Prozent).

Wichtig (Gateway-Erkenntnis 15.10.26): vLLM 0.22 am Gateway verliert tool_calls im
NON-STREAMING-Modus bei gpt-oss-120b (content:""). Es wird daher IMMER gestreamt
(sowohl für das Evaluatee als auch bei Tool-Emission), Qwen funktioniert in beiden
Modi — Streaming ist hier der gemeinsame, zuverlässige Pfad.
"""

import json
import re
from typing import Optional

from deepeval.metrics import BaseMetric
from deepeval.test_case import LLMTestCase
from eval_logger import eval_logger

# Abbruchgründe für den Rundenloop
ROUND_END_MAX_ROUNDS = "max_rounds_reached"
ROUND_END_FINAL_ANSWER = "final_answer_no_tool_calls"
ROUND_END_ERROR = "error"

ERROR_SCORE = 0.0  # Fehler führen zu 0 Punkten (Arbeitsauftrag §2.2)


class OpenAIClientStreaming:
    """
    Minimaler OpenAI-kompatibler Streaming-Client für Chat-Completions.

    Warum eigener Client statt openai-Paket: das openai-Paket abstrahiert
    Streaming sauber, aber wir brauchen explizite Kontrolle über Tool-Call-Deltas
    und die Gateway-Quirks (non-streaming verliert tool_calls bei gpt-oss).
    """

    def __init__(self, base_url: str, api_key: str):
        self.base_url = base_url.rstrip("/")
        self.api_key = api_key

    def chat(self, model: str, messages: list, tools: Optional[list] = None) -> dict:
        """
        Führt einen (immer gestreamten) Chat-Completion-Call aus.

        Returns: {"finish_reason": str|None, "content": str,
                  "tool_calls": [{"id", "name", "arguments"}], "raw": <letzter chunk>}
        """
        import requests as _requests

        payload = {"model": model, "messages": messages, "stream": True}
        if tools:
            payload["tools"] = tools

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
        "max_tool_rounds": int,
        "expected_behavior": Optional[str]  # freiwilliger Ergänzungsblock — wird an den Bewertungsprompt angehängt
    }
    """

    def __init__(
        self,
        model,           # Judge/DeepEval model object (Judge wrapper) — final evaluation
        evaluatee_url: str,
        evaluatee_key: str,
        evaluatee_name: str,
        definition: dict,
        threshold: float = 0.5,
        include_reason: bool = True,
        strict_mode: bool = False,
        async_mode: bool = True,
        system_prompt: str = "",
    ):
        self.model = model
        self.threshold = threshold
        self.include_reason = include_reason
        self.strict_mode = strict_mode
        self.async_mode = async_mode

        # Evaluatee (zu evaluierendes Modell)
        self.evaluatee = OpenAIClientStreaming(evaluatee_url, evaluatee_key)
        self.evaluatee_name = evaluatee_name

        # Simulation client + finale Bewertung: separater Judge-Wrapper ist vorgesehen
        # (Simulation vs. Bewertung sind zwei getrennte Prompts/Aufgaben — §2.3). Beide
        # sprechen dieselbe API an (Judge-Gateway), sind aber eigenständige Clients, damit
        # Test-Mocks (und spätere abweichende Judge-Modelle) getrennt bleiben.
        self._judge_api = self._extract_judge_api_info(model)
        self.simulation_client = OpenAIClientStreaming(self._judge_api["url"], self._judge_api["key"])
        self.evaluation_client = OpenAIClientStreaming(self._judge_api["url"], self._judge_api["key"])

        # Definition
        self.tools = definition.get("tools", [])
        self.simulation_prompt = definition.get("simulation_prompt", "")
        self.evaluation_prompt = definition.get("evaluation_prompt", "")
        self.expected_behavior = definition.get("expected_behavior") or ""
        self.max_tool_rounds = int(definition.get("max_tool_rounds", 5))
        self.system_prompt = system_prompt

        # Ergebnis-Metadaten (UI-Verlauf)
        self.rounds: list = []          # pro Runde: {"round", "tool_calls", "simulated_result", "finish_reason"}
        self.final_answer: str = ""
        self.end_reason: str = ""
        self.evaluation_notes: str = ""

        eval_logger.info("tool_use_metric", "Initialized ToolUseMetric", {
            "evaluatee": evaluatee_name,
            "tools": [t.get("name") for t in self.tools],
            "max_tool_rounds": self.max_tool_rounds,
            "has_expected_behavior": bool(self.expected_behavior),
            "simulation_prompt_length": len(self.simulation_prompt),
            "evaluation_prompt_length": len(self.evaluation_prompt),
        })

    def _extract_judge_api_info(self, judge_model) -> dict:
        """Judge-Wrapper-Objekt -> API-Daten (Judge speichert api_base/api_key)."""
        return {
            "url": judge_model.api_base,
            "key": judge_model.api_key,
            "name": judge_model.model_name,
        }

    # ------------------------------------------------------------------ #
    # Tool-Schema -> OpenAI tools-Parameter
    # ------------------------------------------------------------------ #

    def _tools_for_openai(self) -> list:
        """Wandelt die Tool-Definitionen in das OpenAI function-calling-Format."""
        out = []
        for tool in self.tools:
            params = tool.get("parameters") or []
            properties = {}
            required = []
            for p in params:
                ptype = p.get("type", "string")
                # number -> integer nur wenn explizit gewünscht; JSON-Schema kennt beides.
                prop = {"type": ptype}
                if p.get("description"):
                    prop["description"] = p["description"]
                properties[p["name"]] = prop
                if p.get("required"):
                    required.append(p["name"])
            out.append({
                "type": "function",
                "function": {
                    "name": tool["name"],
                    "description": tool.get("description", ""),
                    "parameters": {
                        "type": "object",
                        "properties": properties,
                        "required": required,
                    },
                },
            })
        return out

    # ------------------------------------------------------------------ #
    # Simulation
    # ------------------------------------------------------------------ #

    def _build_simulation_messages(self, task_input: str, round_index: int,
                                   tool_call: dict, history_summary: str) -> list:
        """Baut den Judge-Prompt simulierter Werkzeugergebnisse (vom Bewertungsprompt GETRENNT)."""
        system = (
            "Du bist ein Werkzeug-Simulator in einer LLM-Evaluation. "
            "Du simulierst die Ergebnisse von Werkzeugaufrufen eines zu evaluierenden Sprachmodells. "
            "Antworte AUSSCHLIESSLICH mit JSON: {\"result\": \"<simuliertes Werkzeugergebnis als Klartext>\"}. "
            "Der Klartext ist das, was das Modell als Tool-Antwort sieht — formuliere ihn realistisch und inhaltlich wie ein echtes Werkzeug."
        )

        tool_schema_str = json.dumps(self.tools, ensure_ascii=False, indent=2)

        user = (
            f"SIMULATIONSREGELN (vom Evaluations-Benutzer definiert):\n{self.simulation_prompt}\n\n"
            f"VERFÜGBARE WERKZEUGE (Schemata):\n{tool_schema_str}\n\n"
            f"ANGRUFENES WERKZEUG: {tool_call['name']}\n"
            f"PARAMETER: {tool_call['arguments']}\n"
            f"AKTUELLE RUNDE: {round_index} von max. {self.max_tool_rounds}\n\n"
            f"BISHERIGE SIMULIERTE ERGEBNISSE (Verlauf):\n{history_summary or '(keine — dies ist die erste Runde)'}\n\n"
            f"ORIGINAL-AUFGABE DES MODELLS:\n{task_input}\n\n"
            "Erzeuge das simulierte Werkzeugergebnis für diese Runde. Beachte: frühere Ergebnisse und "
            "die Aufgabe sind zusammen zu berücksichtigen (kontextabhängige Simulation)."
        )
        return [{"role": "system", "content": system}, {"role": "user", "content": user}]

    def _parse_simulation_response(self, response: str) -> str:
        """Extrahiert das 'result'-Feld aus der JSON-Antwort des Simulators. Fehlt es -> Fehler."""
        text = (response or "").strip()
        m = re.search(r"\{.*\}", text, re.DOTALL)
        if not m:
            raise ValueError(f"Simulator response is not JSON: {text[:200]}")
        try:
            data = json.loads(m.group(0))
        except json.JSONDecodeError as e:
            raise ValueError(f"Simulator JSON is invalid: {e}: {text[:200]}")
        result = data.get("result")
        if not isinstance(result, str) or not result.strip():
            raise ValueError(f"Simulator JSON misses non-empty 'result': {text[:200]}")
        return result.strip()

    def _simulate_tool_result(self, task_input: str, round_index: int,
                              tool_call: dict, history_summary: str) -> str:
        """Ruft den Judge als Werkzeug-Simulator."""
        messages = self._build_simulation_messages(task_input, round_index, tool_call, history_summary)
        resp = self.simulation_client.chat(self._judge_api["name"], messages, tools=None)
        return self._parse_simulation_response(resp["content"])

    # ------------------------------------------------------------------ #
    # Finale Bewertung (G-Eval-artig)
    # ------------------------------------------------------------------ #

    def _build_evaluation_messages(self, test_case: LLMTestCase, conversation: list) -> list:
        """Finale Bewertung: separater Prompt mit task, tools, beiden Prompts, vollem Verlauf."""
        system = (
            "Du bist ein präziser LLM-Evaluierungs-Richter (LLM-as-a-Judge). "
            "Bewerte die Werkzeugnutzung eines Sprachmodells streng nach den Kriterien, "
            "die dir der Evaluations-Benutzer vorgibt. Antworte AUSSCHLIESSLICH mit JSON: "
            "{\"score\": <zahl zwischen 0 und 10>, \"reasoning\": \"<detaillierte Begründung>\"}. "
            "Der Score 0 bedeutet: völlig unzureichend; 10: exzellente Werkzeugnutzung."
        )

        tool_schema_str = json.dumps(self.tools, ensure_ascii=False, indent=2)
        conversation_str = self._format_conversation(conversation)

        expected_block = ""
        if self.expected_behavior and self.expected_behavior.strip():
            expected_block = (
                f"\nERWARTUNGSHORIZONT / ZIELVERHALTEN (zusätzlich zu den Kriterien):\n"
                f"{self.expected_behavior.strip()}\n"
            )

        user = (
            f"AUFGABE (Original-Nutzerprompt):\n{test_case.input}\n\n"
            f"VERFÜGBARE WERKZEUGE (Schemata):\n{tool_schema_str}\n\n"
            f"SIMULATIONSREGELN (wie die Werkzeugergebnisse entstanden):\n{self.simulation_prompt}\n\n"
            f"BEWERTUNGSKRITERIEN (vom Evaluations-Benutzer definiert):\n{self.evaluation_prompt}"
            f"{expected_block}\n"
            f"VOLLSTÄNDIGER INTERAKTIONSVERLAUF (alle Tool-Runden chronologisch):\n{conversation_str}\n\n"
            "Bewerte anhand der Bewertungskriterien, wie gut das Modell seine Werkzeuge eingesetzt hat. "
            "Beziehe den Weg (Runden, Parameter, Reaktionen auf Zwischenergebnisse, Beendigungszeitpunkt) "
            "ein, nicht nur das finale Antwortergebnis."
        )
        return [{"role": "system", "content": system}, {"role": "user", "content": user}]

    def _format_conversation(self, conversation: list) -> str:
        """Konversation -> lesbarer Verlauf mit Rundenmarkierung."""
        lines = []
        round_no = 0
        for msg in conversation:
            role = msg.get("role")
            if role == "system":
                continue
            if role == "user":
                lines.append(f"[AUFGABE]\n{msg.get('content', '')}")
            elif role == "assistant":
                tcs = msg.get("tool_calls") or []
                if tcs:
                    round_no += 1
                    calls = "; ".join(
                        f"{t['function']['name']}({t['function'].get('arguments','')})"
                        for t in tcs
                    )
                    lines.append(f"[RUNDE {round_no} — MODELL-RUF]")
                    lines.append(calls)
                else:
                    lines.append(f"[FINALE ANTWORT]\n{msg.get('content','')}")
            elif role == "tool":
                lines.append(f"[SIMULIERTES WERKZEUGERGEBNIS für Runde {round_no}]\n{msg.get('content','')}")
        return "\n\n".join(lines)

    def _parse_evaluation_response(self, response: str) -> dict:
        """Extrahiert score(0-10) + reasoning aus der Bewertungsantwort; tolerant gegen Markdown."""
        text = (response or "").strip()

        # Fallback-Extraktion (vor JSON-Versuch): SCORE:-Stil, auch mit Markdown-Fettung
        # wie "**SCORE:** 7.5" (bekanntes Judge-Modell-Verhalten, vgl. TALE-Parser-Fix)
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
                            reasoning = "(keine Begründung geliefert)"
                        return {"score": score, "reasoning": reasoning.strip()}
                    raise ValueError(f"Evaluation score out of range 0-10: {score}")
            except json.JSONDecodeError:
                pass  # fällt auf den SCORE-Fallback unten durch

        if sm:
            return {"score": float(sm.group(1)), "reasoning": text}

        raise ValueError(f"Evaluation response has no extractable score: {text[:300]}")

    # ------------------------------------------------------------------ #
    # measure() — der Hauptloop
    # ------------------------------------------------------------------ #

    def measure(self, test_case: LLMTestCase, *args, **kwargs) -> float:
        eval_logger.info("tool_use_metric", "Starting tool-use evaluation", {
            "input": (test_case.input or "")[:200],
            "expected_output": bool(getattr(test_case, "expected_output", None)),
        })

        try:
            conversation = []
            sys_msg = self.system_prompt or (
                "Nutze die verfügbaren Werkzeuge, um die Aufgabe zu lösen. "
                "Rufe Werkzeuge auf, wenn sie dir weiterhelfen; beantworte die Aufgabe "
                "abschließend in normaler Sprache, wenn du genug Information hast."
            )
            conversation.append({"role": "system", "content": sys_msg})
            conversation.append({"role": "user", "content": test_case.input})

            tools_openai = self._tools_for_openai()
            history_summary = ""       # kontextabhängige Simulation
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

                # Fall: Tool-Call(s) -> simulieren
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
                        # Ungültiger, nicht parsbarer Tool-Call -> Abbruch mit 0 Punkten (§2.2)
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
                    history_summary += f"- Runde {round_no} ({tc['name']}({json.dumps(parsed_args, ensure_ascii=False)})): {sim[:300]}\n"

                self.rounds.append(round_record)

            # --- Loop beendet: finale Bewertung ---
            return self._finalize_evaluation(test_case, conversation)

        except Exception as e:
            # Generischer Abbruch: unbemerkter Verlust ist ausgeschlossen (§2.2)
            err = f"Evaluation aborted: {type(e).__name__}: {str(e)}"
            eval_logger.log_error("tool_use_metric", err, {"traceback": str(e)})
            self.end_reason = ROUND_END_ERROR
            self.evaluation_notes = err
            try:
                self._finish_with_error(test_case, getattr(self, "rounds", []), err)
            except Exception:
                self.score = ERROR_SCORE
                self.reason = f"Evaluation fehlgeschlagen: {err}"
                self.success = False
            return self.score

    def _finish_with_error(self, test_case: LLMTestCase, conversation: list, error: str):
        """Setzt Score 0 + Begründung mit Verlaufsinformationen (Fehlerfall §2.3)."""
        self.score = ERROR_SCORE
        self.success = False
        conv_str = self._format_conversation(self._as_conversation(conversation))
        self.reason = (
            f"Evaluation mit Fehler 0 Punkte beendet. FEHLER: {error}\n\n"
            f"ENDGRUND: {self.end_reason}\n\n"
            f"VERLAUF BIS ZUM FEHLER:\n{conv_str[:3000]}"
        )
        self.evaluation_notes = error

    def _as_conversation(self, conversation_or_rounds):
        """Akzeptiert echte Konversation (list of messages) und gibt sie unverändert zurück."""
        return conversation_or_rounds

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

        internal_score = parsed["score"]           # 0-10
        final_score = round(internal_score / 10.0, 4)  # 0-1 Prozent
        reason = parsed["reasoning"]

        # Begründung um Verlaufsmetadaten ergänzen
        rounds_summary = "; ".join(
            f"R{r['round']}: " + ", ".join(
                f"{tc['name']}({json.dumps(tc['arguments'], ensure_ascii=False)[:60]})"
                for tc in r["tool_calls"]
            )
            for r in self.rounds
        )
        self.reason = (
            f"Score (intern 0-10): {internal_score} -> Prozent: {final_score}\n"
            f"Endgrund: {self.end_reason}; Tool-Runden: {len(self.rounds)}"
            f"{' (max ' + str(self.max_tool_rounds) + ')' if self.end_reason == ROUND_END_MAX_ROUNDS else ''}.\n"
            f"Rundenübersicht: {rounds_summary or '(keine)'}\n\n"
            f"BEWERTUNG DES JUDGES:\n{reason}"
        )
        if self.final_answer:
            self.reason += f"\n\nFINALE MODELLANTWORT:\n{self.final_answer[:1500]}"
        if self.evaluation_notes:
            self.reason += f"\n\nHINWEISE: {self.evaluation_notes}"

        self.score = final_score
        self.success = final_score >= self.threshold
        eval_logger.decision("tool_use_metric", "Evaluation completed", {
            "score_0_10": internal_score,
            "score_0_1": final_score,
            "threshold": self.threshold,
            "success": self.success,
        })
        return self.score