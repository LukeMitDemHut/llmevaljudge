from deepeval.models.base_model import DeepEvalBaseLLM
import requests
from eval_logger import eval_logger

class Judge(DeepEvalBaseLLM):
    def __init__(self, api_base: str, api_key: str, model_name: str):
        self.api_base = api_base
        self.api_key = api_key
        self.model_name = model_name
        
        eval_logger.info("judge", "Initialized judge", {
            "api_base": api_base,
            "model_name": model_name,
            "has_api_key": bool(api_key)
        })

    def get_model_name(self):
        return f"Judge ({self.model_name})"

    def load_model(self):
        # Placeholder as the HTTP endpoint acts as the model
        return None

    def generate(self, prompt: str) -> str:
        eval_logger.info("judge", "Starting judge evaluation")
        
        payload = {
            "model": self.model_name,
            "messages": [{"role": "user", "content": prompt}]
        }
        headers = {"Authorization": f"Bearer {self.api_key}"}
        
        eval_logger.log_llm_request("judge", 
                                   prompt=prompt,
                                   model_info={
                                       "name": self.model_name,
                                       "api_base": self.api_base,
                                       "endpoint": f"{self.api_base}/chat/completions"
                                   })
        
        eval_logger.decision("judge", "Making evaluation request to judge model", {
            "prompt_length": len(prompt),
            "model": self.model_name,
            "endpoint": f"{self.api_base}/chat/completions"
        })
        
        try:
            resp = requests.post(f"{self.api_base}/chat/completions", json=payload, headers=headers)
            resp.raise_for_status()
            
            response_data = resp.json()
            response_content = response_data["choices"][0]["message"]["content"]
            
            eval_logger.log_llm_response("judge", 
                                        response=response_content,
                                        metadata={
                                            "response_length": len(response_content),
                                            "status_code": resp.status_code,
                                            "finish_reason": response_data["choices"][0].get("finish_reason")
                                        })
            
            eval_logger.decision("judge", "Judge evaluation completed successfully", {
                "response_length": len(response_content),
                "status_code": resp.status_code
            })
            
            return response_content
            
        except requests.exceptions.RequestException as e:
            eval_logger.decision("judge", "Judge evaluation failed", {
                "error": str(e),
                "error_type": type(e).__name__
            })
            raise

    async def a_generate(self, prompt: str) -> str:
        eval_logger.info("judge", "Starting async judge evaluation")
        # For now, use the sync version - could be enhanced later for true async
        return self.generate(prompt)
