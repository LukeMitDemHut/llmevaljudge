"""
Development-Tests für ToolUseMetric (Schritt 4).

Läuft im judge_eval-Container (deepeval installiert). Die LLM-Interaktion
(Evaluatee-Loop + Simulation + Bewertung) wird über Mock-Clients getestet —
deterministisch, ohne Gateway-Abhängigkeit. Der Live-Gateway-Test erfolgt separat.

Abdeckung (Arbeitsauftrag §4):
1. Erfolgreicher Tool-Call mit gültigen Parametern
2. Mehrere Runden mit abhängigen simulierte Ergebnissen
3. Max-Runden erreicht (keinerlei unabsichtlichem Verlust)
4. Beenden durch finale Antwort ohne Tool-Calls
5. Ungültige / nicht parsbare Tool-Calls -> 0-Punkte-Abbruch
6. Fehlerhafte/unvollständige Judge-Ausgaben -> 0-Punkte-Abbruch
7. Erfolgreiche Score-/Begründungs-Extraktion (inkl. Markdown-Toleranz)

Ausführung: docker exec llm-eval-judge-judge_eval-1 python /app/dev_tests_tooluse.py
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
         "description": "Durchsucht die Wissensdatenbank.",
         "parameters": [
             {"name": "query", "type": "string", "description": "Suchbegriff", "required": True},
             {"name": "max_results", "type": "number", "description": "Max Treffer", "required": False},
         ]},
        {"name": "open_document",
         "description": "Öffnet ein gefundenes Dokument.",
         "parameters": [
             {"name": "document_id", "type": "string", "description": "Dokument-ID", "required": True},
         ]},
    ],
    "simulation_prompt": "Simuliere: Dokument erst in Runde 3 gefunden.",
    "evaluation_prompt": "Bewerte Werkzeugauswahl, Reaktion auf Ergebnisse, Beendigungszeitpunkt.",
    "max_tool_rounds": 4,
}

class JudgeStub:
    """Vorbild des echten Judge-Wrappers (hat api_base/api_key/model_name)."""
    def __init__(self):
        self.api_base = "http://stub-gateway/v1"
        self.api_key = "stub-key"
        self.model_name = "stub-judge-model"

def make_metric(defn=None, max_rounds=None):
    d = dict(DEF)
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
# 1. Erfolgreicher Tool-Call mit gültigen Parametern + Ende durch finale Antwort
# --------------------------------------------------------------------------- #
def test_success_single_round():
    m = make_metric()
    evaluatee = [
        call_response("tool_calls", tool_calls=[{"id": "c1", "name": "doc_search",
                                                "arguments": json.dumps({"query": "Alice puzzle", "max_results": 5})}]),
        call_response("stop", content="Ein Bruder von Alice hat drei Schwestern."),
    ]
    sims = ['{"result": "Dokument gefunden: Inhalt: ein Bruder hat 3 Schwestern."}']

    with patch.object(m.evaluatee, "chat", side_effect=evaluatee) as ev_call, \
         patch.object(m.simulation_client, "chat", side_effect=[{"finish_reason": "stop", "content": s, "tool_calls": []} for s in sims]) as sim_call, \
         patch.object(m.evaluation_client, "chat", side_effect=[
             {"finish_reason": "stop", "content": '{"score": 9.0, "reasoning": "Sehr gute Toolnutzung."}', "tool_calls": []}]):
        score = m.measure(FakeCase())

    assert score == 0.9, f"score expected 0.9 got {score}"
    assert m.end_reason == ROUND_END_FINAL_ANSWER
    assert m.final_answer == "Ein Bruder von Alice hat drei Schwestern."
    assert len(m.rounds) == 1
    assert m.rounds[0]["tool_calls"][0]["arguments"] == {"query": "Alice puzzle", "max_results": 5}
    # Evaluatee-Call bekommt tools-Schema:
    tools_arg = ev_call.call_args_list[0].kwargs.get("tools") or ev_call.call_args_list[0][2]
    assert tools_arg[0]["function"]["name"] == "doc_search"
    assert tools_arg[0]["function"]["parameters"]["required"] == ["query"]
    # Sim-Call bekam JSON-only-Anweisung:
    sim_msgs = sim_call.call_args[0][1]
    assert '{"result":' in sim_msgs[0]["content"]
    print("TEST 1 OK: success single round -> 0.9, end=final_answer")

# --------------------------------------------------------------------------- #
# 2. Mehrere Runden mit abhängigen simulierten Ergebnissen
# --------------------------------------------------------------------------- #
def test_multi_round_dependency():
    m = make_metric()
    evaluatee = [
        call_response("tool_calls", tool_calls=[{"id": "c1", "name": "doc_search", "arguments": '{"query": "puzzle"}'}]),
        call_response("tool_calls", tool_calls=[{"id": "c2", "name": "open_document", "arguments": '{"document_id": "doc_42"}'}]),
        call_response("stop", content="Antwort: drei Schwestern."),
    ]
    sims = [
        '{"result": "Treffer: doc_42 (Alice-Rätsel)."}',
        '{"result": "Inhalt von doc_42: ein Bruder hat drei Schwestern."}',
    ]
    with patch.object(m.evaluatee, "chat", side_effect=evaluatee), \
         patch.object(m.simulation_client, "chat", side_effect=[{"finish_reason": "stop", "content": s, "tool_calls": []} for s in sims]) as sim_call, \
         patch.object(m.evaluation_client, "chat", side_effect=[
             {"finish_reason": "stop", "content": '{"score": 10, "reasoning": "Vorbildlich."}', "tool_calls": []}]):
        score = m.measure(FakeCase())

    assert score == 1.0
    assert len(m.rounds) == 2
    # Runde 2 Simulation bekam Verlauf von Runde 1 (kontextabhängig):
    second_sim_prompt = sim_call.call_args_list[1][0][1][1]["content"]
    assert "BISHERIGE SIMULIERTE ERGEBNISSE" in second_sim_prompt and "puzzle" in second_sim_prompt
    assert "doc_42" in second_sim_prompt  # runde-1-Ergebnis im Kontext
    print("TEST 2 OK: 2 dependent rounds, context propagation works")

# --------------------------------------------------------------------------- #
# 3. Max-Runden erreicht
# --------------------------------------------------------------------------- #
def test_max_rounds():
    m = make_metric(max_rounds=2)
    # Modell ruft IMMER wieder Tools auf (4x, mehr als max)
    forever = [{"id": f"c{i}", "name": "doc_search", "arguments": '{"query": "q%d"}' % i} for i in range(1, 5)]
    evaluatee = [call_response("tool_calls", tool_calls=[tc]) for tc in forever]
    sims = ['{"result": "nichts gefunden."}'] * 4
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
# 4. Fehler: nicht-parsbare Tool-Arguments -> 0
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
# 5. Fehler: Simulation schlägt fehl (Judge-Ausgabe unverständlich) -> 0
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
    # Retry-Verhalten: 1 Sim-Call, dann Abbruch (keine Endlosschleife, kein stiller Verlust)
    assert sim_call.call_count == 1
    assert eval_call.call_count == 0
    print("TEST 5 OK: broken judge simulation output -> 0.0, no silent loss")

# --------------------------------------------------------------------------- #
# 6. Fehler: finale Bewertung unparsbar / Score out of range -> 0
# --------------------------------------------------------------------------- #
def test_bad_final_evaluation():
    m = make_metric()
    evaluatee = [call_response("stop", content="direkte Antwort ohne Tool.")]  # keine Tools nötig für diesen Test
    with patch.object(m.evaluatee, "chat", side_effect=evaluatee), \
         patch.object(m.evaluation_client, "chat", side_effect=[
             {"finish_reason": "stop", "content": "TOTAL GIBBERISH no json", "tool_calls": []}]):
        score = m.measure(FakeCase())

    assert score == 0.0
    assert m.end_reason == ROUND_END_ERROR
    assert "unparsable" in m.reason.lower()
    print("TEST 6a OK: garbage final evaluation -> 0.0")

    # Score out of range (11 von 10):
    m2 = make_metric()
    evaluatee2 = [call_response("stop", content="Antwort.")]
    with patch.object(m.evaluatee, "chat", side_effect=evaluatee2), \
         patch.object(m2.evaluation_client, "chat", side_effect=[
             {"finish_reason": "stop", "content": '{"score": 11, "reasoning": "over range"}', "tool_calls": []}]):
        score2 = m2.measure(FakeCase())
    assert score2 == 0.0, "score 11 is invalid -> evaluation must fail to 0"
    print("TEST 6b OK: out-of-range score -> 0.0")

# --------------------------------------------------------------------------- #
# 7. Markdown-tolerante Score-Extraktion (Judge antwortet mit **SCORE:**-Stil)
# --------------------------------------------------------------------------- #
def test_markdown_score_extraction():
    m = make_metric()
    evaluatee = [call_response("stop", content="finale Antwort ohne Tool.")]
    markdown_reply = "Der Score: **SCORE:** 7.5\n\n**REASONING:**\nSolide Arbeit."
    with patch.object(m.evaluatee, "chat", side_effect=evaluatee), \
         patch.object(m.evaluation_client, "chat", side_effect=[
             {"finish_reason": "stop", "content": markdown_reply, "tool_calls": []}]):
        score = m.measure(FakeCase())

    assert score == 0.75, f"markdown 7.5/10 -> 0.75, got {score}"
    assert "Solide Arbeit" in m.reason
    assert m.end_reason == ROUND_END_FINAL_ANSWER
    print("TEST 7 OK: markdown-fallback extraction works (7.5 -> 0.75)")

# --------------------------------------------------------------------------- #
# 8. Tool-Schema-Übergabe: 'number' Parameter korrekt im JSON-Schema (Bonus)
# --------------------------------------------------------------------------- #
def test_schema_type_mapping():
    m = make_metric()
    tools = m._tools_for_openai()
    f = tools[0]["function"]
    assert f["parameters"]["properties"]["max_results"]["type"] == "number"
    assert f["parameters"]["properties"]["query"]["type"] == "string"
    assert f["parameters"]["required"] == ["query"]
    # open_document:
    f2 = tools[1]["function"]
    assert f2["parameters"]["required"] == ["document_id"]
    print("TEST 8 OK: schema type mapping + required lists correct")

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