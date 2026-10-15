from models import Prompt, Metric, ModelInfo
from deepeval.test_case import LLMTestCase
from metric_creator import MetricCreator
from llmrequestor import LlmRequestor
from eval_logger import eval_logger
import json

class Evaluator:
    def __init__(self, prompt: Prompt, metric: Metric, model: ModelInfo, system_prompt: str = ""):
        self.prompt = prompt
        self.metric = metric
        self.model = model
        self.system_prompt = system_prompt
        
        # Reset logger for this evaluation
        eval_logger.reset()
        eval_logger.info("evaluator", "Initialized evaluator", {
            "prompt_input": prompt.input[:100] + "..." if len(prompt.input) > 100 else prompt.input,
            "metric_name": metric.name,
            "metric_type": metric.type,
            "model_name": model.name,
            "has_system_prompt": bool(system_prompt)
        })

    def evaluate(self):
        eval_logger.info("evaluator", "Starting evaluation process")

        if self.metric.type == "tool-use":
            # Tool-use: der Metrik-Loop erzeugt actual_output selbst (Konversation mit
            # Tool-Runden); der klassische Einzel-Request entfällt.
            eval_logger.info("evaluator", "Tool-use metric: skipping direct model request (loop builds output)")

            metric_creator = MetricCreator(self.metric, model=self.model, system_prompt=self.system_prompt)
            metric_instance = metric_creator.create_metric()

            context_list = [self.prompt.context] if self.prompt.context else []
            test_case = LLMTestCase(
                input=self.prompt.input,
                actual_output="",  # wird vom Metrik-Loop erzeugt
                expected_output=self.prompt.expected_output,
                context=context_list
            )

            eval_logger.info("evaluator", "Starting metric measurement for tool-use")
            metric_instance.measure(test_case)

            eval_logger.info("evaluator", "Evaluation completed", {
                "score": metric_instance.score,
                "reason_length": len(metric_instance.reason) if metric_instance.reason else 0
            })

            # Der Metrik-Loop liefert final_answer = eigentliche Modellantwort:
            actual_output = getattr(metric_instance, "final_answer", "") or "(keine finale Antwort — Abbruch/Max-Runden)"

            logs = eval_logger.get_logs()
            # Tool-Runden-Verlauf als strukturierte Zusatzinformation für die UI:
            rounds_info = getattr(metric_instance, "rounds", [])
            end_reason = getattr(metric_instance, "end_reason", "")
            logs = logs + [
                {"timestamp": "", "level": "SUMMARY", "component": "tool_use_metric",
                 "message": f"END: {end_reason}", "data": {"rounds": rounds_info}}
            ]

            result = {
                'actual_output': actual_output,
                'score': metric_instance.score,
                'reason': metric_instance.reason,
                'logs': json.dumps(logs)
            }
            return result

        # Generate actual output using the model
        eval_logger.info("evaluator", "Requesting LLM response for evaluation")
        requestor = LlmRequestor(self.prompt, self.model, self.system_prompt)
        actual_output = requestor.request()

        eval_logger.info("evaluator", "Creating test case", {
            "has_expected_output": bool(self.prompt.expected_output),
            "has_context": bool(self.prompt.context),
            "actual_output_length": len(actual_output)
        })

        # Create test case with actual output
        context_list = [self.prompt.context] if self.prompt.context else [] #context is usually one string from eval gui
        test_case = LLMTestCase(
            input=self.prompt.input,
            actual_output=actual_output,
            expected_output=self.prompt.expected_output,
            context=context_list
        )

        # Create metric instance and evaluate
        eval_logger.info("evaluator", "Creating metric instance")
        metric_instance = MetricCreator(self.metric).create_metric()
        
        eval_logger.info("evaluator", "Starting metric measurement")
        # Measure the test case
        metric_instance.measure(test_case)
        
        eval_logger.info("evaluator", "Evaluation completed", {
            "score": metric_instance.score,
            "reason_length": len(metric_instance.reason) if metric_instance.reason else 0
        })
        
        result = {
            'actual_output': actual_output,
            'score': metric_instance.score,
            'reason': metric_instance.reason,
            'logs': json.dumps(eval_logger.get_logs())  # Convert logs array to JSON string
        }
        
        eval_logger.info("evaluator", "Returning evaluation result with logs", {
            "total_log_entries": len(eval_logger.get_logs())
        })
        
        return result