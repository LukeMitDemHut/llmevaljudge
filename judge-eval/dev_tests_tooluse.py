"""
Tests for ToolUseMetric.

Runs inside the judge_eval container (deepeval installed). The LLM interaction
(evaluatee loop + simulation + final evaluation) is mocked — deterministic,
no gateway dependency.

Covered cases:
1. Successful tool call with valid parameters
2. Multiple rounds with dependent simulated results
3. Reaching the configured round limit
4. Ending the interaction with a final answer (no tool calls)
5. Invalid / unparsable tool calls -> abort with score 0
6. Malformed / incomplete judge outputs -> abort with score 0
7. Score + reasoning extraction (incl. markdown tolerance)

Run: docker exec <judge_eval container> python /app/dev_tests_tooluse.py
"""

import json
import sys
from unittest.mock import patch

from toolusemetric import (
    ToolUseMetric,
    ROUND_END_MAX_ROUNDS,
    ROUND_END_FINAL_ANSWER,
    ROUND_END_ERROR,
)

DEF = {
    "tools": [
        {"name": "doc_search",
         "description": "Searches the knowledge base.",
         "parameters": {
             "type": "object",
             "properties": {
                 "query": {"type": "string", "description": "Search term"},
                 "max_results": {"type": "number", "description": "Max results"},
             },
             "required": ["query"],
             "additionalProperties": False,
         }},
        {"name": "open_document",
         "description": "Opens a found document.",
         "parameters": {
             "type": "object",
             "properties": {"document_id": {"type": "string", "description": "Document ID"}},
             "required": ["document_id"],
             "additionalProperties": False,
         }},
    ],
    "simulation_prompt": "Simulate: document only found in round 3.",
    "evaluation_prompt": "Assess tool choice, reaction to results, and when the interaction ends.",
    "max_tool_rounds": 4,
}

class JudgeStub:
    """Mimics the real judge model wrapper (exposes api_base/api_key/model_name)."""
    def __init__(self):
        self.api_base = "http://stub-gateway/v1"
        self.api_key = "stub-key"
        self.model_name = "stub-judge-model"

def make_metric(defn=None, max_rounds=None):
    d = json.loads(json.dumps(DEF))  # deep copy: tests mutate tool entries
    if max_rounds is not None:
        d["max_tool_rounds"] = max_rounds
    return ToolUseMetric(
        model=JudgeStub(),
        evaluatee_url="http://stub-evaluatee/v1",
        evaluatee_key="stub-key",
        evaluatee_name="stub-evaluatee",
        definition=d,
        threshold=0.5,
    )

class FakeCase:
    input = "Find the puzzle doc and answer: how many sisters has a brother of Alice?"
    expected_output = "Three sisters."

def call_response(finish, content="", tool_calls=None):
    return {"finish_reason": finish, "content": content, "tool_calls": tool_calls or []}

# --------------------------------------------------------------------------- #
# 1. Successful tool call with valid parameters + final answer ending
# --------------------------------------------------------------------------- #
def test_success_single_round():
    m = make_metric()
    evaluatee = [
        call_response("tool_calls", tool_calls=[{"id": "c1", "name": "doc_search",
                                                "arguments": json.dumps({"query": "Alice puzzle", "max_results": 5})}]),
        call_response("stop", content="A brother of Alice has three sisters."),
    ]
    sims = ['{"result": "Document found. Content: a brother of Alice has three sisters."}']

    with patch.object(m.evaluatee, "chat", side_effect=evaluatee) as ev_call, \
         patch.object(m.simulation_client, "chat", side_effect=[{"finish_reason": "stop", "content": s, "tool_calls": []} for s in sims]) as sim_call, \
         patch.object(m.evaluation_client, "chat", side_effect=[
             {"finish_reason": "stop", "content": '{"score": 9.0, "reasoning": "Sehr gute Toolnutzung."}', "tool_calls": []}]):
        score = m.measure(FakeCase())

    assert score == 0.9, f"score expected 0.9 got {score}"
    assert m.end_reason == ROUND_END_FINAL_ANSWER
    assert m.final_answer == "A brother of Alice has three sisters."
    assert len(m.rounds) == 1
    assert m.rounds[0]["tool_calls"][0]["arguments"] == {"query": "Alice puzzle", "max_results": 5}
    # Evaluatee call must carry the OpenAI tool schema:
    tools_arg = ev_call.call_args_list[0].kwargs.get("tools") or ev_call.call_args_list[0][2]
    assert tools_arg[0]["function"]["name"] == "doc_search"
    assert tools_arg[0]["function"]["parameters"]["required"] == ["query"]
    assert tools_arg[0]["function"]["parameters"]["additionalProperties"] is False
    # Simulation call must carry the JSON-only instruction:
    sim_msgs = sim_call.call_args[0][1]
    assert '{"result":' in sim_msgs[0]["content"]
    print("TEST 1 OK: success single round -> 0.9, end=final_answer")

# --------------------------------------------------------------------------- #
# 2. Multiple rounds with dependent simulated results
# --------------------------------------------------------------------------- #
def test_multi_round_dependency():
    m = make_metric()
    evaluatee = [
        call_response("tool_calls", tool_calls=[{"id": "c1", "name": "doc_search", "arguments": '{"query": "puzzle"}'}]),
        call_response("tool_calls", tool_calls=[{"id": "c2", "name": "open_document", "arguments": '{"document_id": "doc_42"}'}]),
        call_response("stop", content="Answer: three sisters."),
    ]
    sims = [
        '{"result": "Match: doc_42 (Alice puzzle)."}',
        '{"result": "Content of doc_42: a brother has three sisters."}',
    ]
    with patch.object(m.evaluatee, "chat", side_effect=evaluatee), \
         patch.object(m.simulation_client, "chat", side_effect=[{"finish_reason": "stop", "content": s, "tool_calls": []} for s in sims]) as sim_call, \
         patch.object(m.evaluation_client, "chat", side_effect=[
             {"finish_reason": "stop", "content": '{"score": 10, "reasoning": "Vorbildlich."}', "tool_calls": []}]):
        score = m.measure(FakeCase())

    assert score == 1.0
    assert len(m.rounds) == 2
    # Round 2 simulation must receive the round 1 history (context propagation):
    second_sim_prompt = sim_call.call_args_list[1][0][1][1]["content"]
    assert "PREVIOUSLY SIMULATED RESULTS" in second_sim_prompt and "puzzle" in second_sim_prompt
    assert "doc_42" in second_sim_prompt  # round 1 result present in context
    print("TEST 2 OK: 2 dependent rounds, context propagation works")

# --------------------------------------------------------------------------- #
# 3. Round limit reached
# --------------------------------------------------------------------------- #
def test_max_rounds():
    m = make_metric(max_rounds=2)
    # model keeps calling tools (4x, more than the limit)
    forever = [{"id": f"c{i}", "name": "doc_search", "arguments": '{"query": "q%d"}' % i} for i in range(1, 5)]
    evaluatee = [call_response("tool_calls", tool_calls=[tc]) for tc in forever]
    sims = ['{"result": "nothing found."}'] * 4
    with patch.object(m.evaluatee, "chat", side_effect=evaluatee), \
         patch.object(m.simulation_client, "chat", side_effect=[{"finish_reason": "stop", "content": s, "tool_calls": []} for s in sims]), \
         patch.object(m.evaluation_client, "chat", side_effect=[
             {"finish_reason": "stop", "content": json.dumps({"score": 4, "reasoning": "Max reached, fine behaviour until then."}), "tool_calls": []}]):
        score = m.measure(FakeCase())

    assert score == 0.4
    assert m.end_reason == ROUND_END_MAX_ROUNDS
    assert len(m.rounds) == 2, "loop MUST stop after max_tool_rounds (=2)"
    assert "keine finale Antwort" in m.reason or "Max" in m.reason
    print("TEST 3 OK: loop hard-stops at max_tool_rounds; evaluation still runs")

# --------------------------------------------------------------------------- #
# 4. Error: unparsable tool arguments -> 0
# --------------------------------------------------------------------------- #
def test_unparsable_tool_call():
    m = make_metric()
    evaluatee = [
        call_response("tool_calls", tool_calls=[{"id": "c1", "name": "doc_search", "arguments": '{query: alice, unterminated'}]),
    ]
    with patch.object(m.evaluatee, "chat", side_effect=evaluatee), \
         patch.object(m.evaluation_client, "chat", side_effect=[]) as eval_call:
        score = m.measure(FakeCase())

    assert score == 0.0, f"unparsable args MUST score 0, got {score}"
    assert m.end_reason == ROUND_END_ERROR
    assert "unparsable" in m.reason
    assert eval_call.call_count == 0, "no final evaluation may run after hard error"
    print("TEST 4 OK: unparsable tool arguments -> 0.0, hard stop, no final eval")

# --------------------------------------------------------------------------- #
# 5. Error: simulation fails (unintelligible judge output) -> 0
# --------------------------------------------------------------------------- #
def test_simulation_failure():
    m = make_metric()
    evaluatee = [call_response("tool_calls", tool_calls=[{"id": "c1", "name": "doc_search", "arguments": '{"query": "x"}'}])]
    with patch.object(m.evaluatee, "chat", side_effect=evaluatee), \
         patch.object(m.simulation_client, "chat", side_effect=[
             {"finish_reason": "stop", "content": "Sorry, ich bin verwirrt.", "tool_calls": []}]) as sim_call, \
         patch.object(m.evaluation_client, "chat", side_effect=[]) as eval_call:
        score = m.measure(FakeCase())

    assert score == 0.0
    assert m.end_reason == ROUND_END_ERROR
    assert "simulation failed" in m.reason
    # exactly one simulation call, then hard abort (no retry loop, no silent loss)
    assert sim_call.call_count == 1
    assert eval_call.call_count == 0
    print("TEST 5 OK: broken judge simulation output -> 0.0, no silent loss")

# --------------------------------------------------------------------------- #
# 6. Error: final evaluation unparsable / score out of range -> 0
# --------------------------------------------------------------------------- #
def test_bad_final_evaluation():
    m = make_metric()
    evaluatee = [call_response("stop", content="direct answer without tools")]
    with patch.object(m.evaluatee, "chat", side_effect=evaluatee), \
         patch.object(m.evaluation_client, "chat", side_effect=[
             {"finish_reason": "stop", "content": "TOTAL GIBBERISH no json", "tool_calls": []}]):
        score = m.measure(FakeCase())

    assert score == 0.0
    assert m.end_reason == ROUND_END_ERROR
    assert "unparsable" in m.reason.lower()
    print("TEST 6a OK: garbage final evaluation -> 0.0")

    # Score out of range (11 of 10):
    m2 = make_metric()
    evaluatee2 = [call_response("stop", content="Antwort.")]
    with patch.object(m.evaluatee, "chat", side_effect=evaluatee2), \
         patch.object(m2.evaluation_client, "chat", side_effect=[
             {"finish_reason": "stop", "content": '{"score": 11, "reasoning": "over range"}', "tool_calls": []}]):
        score2 = m2.measure(FakeCase())
    assert score2 == 0.0, "score 11 is invalid -> evaluation must fail to 0"
    print("TEST 6b OK: out-of-range score -> 0.0")

# --------------------------------------------------------------------------- #
# 7. Markdown-tolerant score extraction (**SCORE:**-style judge answer)
# --------------------------------------------------------------------------- #
def test_markdown_score_extraction():
    m = make_metric()
    evaluatee = [call_response("stop", content="finale Antwort ohne Tool.")]
    markdown_reply = "The score: **SCORE:** 7.5\n\n**REASONING:**\nSolid work."
    with patch.object(m.evaluatee, "chat", side_effect=evaluatee), \
         patch.object(m.evaluation_client, "chat", side_effect=[
             {"finish_reason": "stop", "content": markdown_reply, "tool_calls": []}]):
        score = m.measure(FakeCase())

    assert score == 0.75, f"markdown 7.5/10 -> 0.75, got {score}"
    assert "Solid work" in m.reason
    assert m.end_reason == ROUND_END_FINAL_ANSWER
    print("TEST 7 OK: markdown-fallback extraction works (7.5 -> 0.75)")

# --------------------------------------------------------------------------- #
# 8. Tool schema handover: 'number' parameter mapped correctly to the JSON schema
# --------------------------------------------------------------------------- #
def test_schema_type_mapping():
    m = make_metric()
    tools = m._tools_for_openai()
    f = tools[0]["function"]
    params = f["parameters"]
    assert params["type"] == "object"
    assert params["properties"]["max_results"]["type"] == "number"
    assert params["properties"]["query"]["type"] == "string"
    assert params["required"] == ["query"]
    assert params["additionalProperties"] is False
    # open_document:
    f2 = tools[1]["function"]
    assert f2["parameters"]["required"] == ["document_id"]
    print("TEST 8 OK: schema passthrough + strict formatting correct")


# --------------------------------------------------------------------------- #
# 9. Response schema: enforced via response_format on the simulation call
# --------------------------------------------------------------------------- #
def test_response_schema_enforcement():
    m = make_metric()
    m.tools[0]["response_schema"] = {
        "type": "object",
        "properties": {"summary": {"type": "string"}},
        "required": ["summary"],
        "additionalProperties": False,
    }
    evaluatee = [
        call_response("tool_calls", tool_calls=[{"id": "c1", "name": "doc_search", "arguments": '{"query": "q"}'}]),
        call_response("stop", content="final answer."),
    ]
    with patch.object(m.evaluatee, "chat", side_effect=evaluatee), \
         patch.object(m.simulation_client, "chat", side_effect=[
             {"finish_reason": "stop", "content": '{"summary": "Simulated result text."}', "tool_calls": []}
         ]) as sim_call, \
         patch.object(m.evaluation_client, "chat", side_effect=[
             {"finish_reason": "stop", "content": '{"score": 8, "reasoning": "ok"}', "tool_calls": []}]):
        score = m.measure(FakeCase())

    assert score == 0.8
    rf = sim_call.call_args_list[0].kwargs.get("response_format")
    assert rf is not None and rf["type"] == "json_schema"
    assert rf["json_schema"]["strict"] is True
    assert list(rf["json_schema"]["schema"]["properties"].keys()) == ["summary"]
    # extractor resolved the 'summary' property
    assert m.rounds[0]["simulated"][0] == "Simulated result text."
    print("TEST 9 OK: response_schema -> json_schema response_format on simulation call")


# --------------------------------------------------------------------------- #
# 10. No response schema: no response_format is sent, plain {result} parsing
# --------------------------------------------------------------------------- #
def test_no_response_schema():
    m = make_metric()
    evaluatee = [
        call_response("tool_calls", tool_calls=[{"id": "c1", "name": "doc_search", "arguments": '{"query": "q"}'}]),
        call_response("stop", content="done."),
    ]
    with patch.object(m.evaluatee, "chat", side_effect=evaluatee), \
         patch.object(m.simulation_client, "chat", side_effect=[
             {"finish_reason": "stop", "content": '{"result": "plain simulated text."}', "tool_calls": []}
         ]) as sim_call, \
         patch.object(m.evaluation_client, "chat", side_effect=[
             {"finish_reason": "stop", "content": '{"score": 7, "reasoning": "fine"}', "tool_calls": []}]):
        score = m.measure(FakeCase())

    assert score == 0.7
    assert sim_call.call_args_list[0].kwargs.get("response_format") is None
    assert m.rounds[0]["simulated"][0] == "plain simulated text."
    print("TEST 10 OK: without response_schema no response_format is sent")


# --------------------------------------------------------------------------- #
# 11. Legacy parameters list is migrated to a JSON schema object
# --------------------------------------------------------------------------- #
def test_legacy_parameters_migration():
    m = make_metric()
    m.tools[0]["parameters"] = [
        {"name": "query", "type": "string", "description": "Search term", "required": True},
        {"name": "limit", "type": "number", "description": "Limit", "required": False},
    ]
    tools = m._tools_for_openai()
    f = tools[0]["function"]
    assert f["parameters"]["type"] == "object"
    assert f["parameters"]["required"] == ["query"]
    assert f["parameters"]["properties"]["limit"]["type"] == "number"
    assert f["parameters"]["additionalProperties"] is False
    print("TEST 11 OK: legacy parameter list migrated to schema")

if __name__ == "__main__":
    tests = [
        test_success_single_round,
        test_multi_round_dependency,
        test_max_rounds,
        test_unparsable_tool_call,
        test_simulation_failure,
        test_bad_final_evaluation,
        test_markdown_score_extraction,
        test_schema_type_mapping,
        test_response_schema_enforcement,
        test_no_response_schema,
        test_legacy_parameters_migration,
    ]
    failed = []
    for t in tests:
        try:
            t()
        except AssertionError as e:
            print(f"TEST FAIL: {t.__name__}: {e}")
            failed.append(t.__name__)
        except Exception as e:
            print(f"TEST ERROR: {t.__name__}: {type(e).__name__}: {e}")
            failed.append(t.__name__)
    print("\n===", "ALL TESTS PASSED" if not failed else f"FAILED: {failed}")
    sys.exit(0 if not failed else 1)