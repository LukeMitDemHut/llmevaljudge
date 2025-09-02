from models import Prompt, Metric, ModelInfo
from openai import OpenAI
import os
from eval_logger import eval_logger

class LlmRequestor:
    def __init__(self, prompt: Prompt, model: ModelInfo, system_prompt: str = ""):
        self.prompt = prompt
        self.model = model
        self.system_prompt = system_prompt
        
        eval_logger.info("llm_requestor", "Initialized LLM requestor", {
            "model_name": model.name,
            "model_url": model.url,
            "has_system_prompt": bool(system_prompt),
            "prompt_length": len(prompt.input)
        })

    def request(self):
        eval_logger.info("llm_requestor", "Creating OpenAI client")
        client = OpenAI(
            base_url=self.model.url,
            api_key=self.model.key
        )
        messages = []

        if self.system_prompt:
            messages.append({"role": "system", "content": self.system_prompt})
            eval_logger.debug("llm_requestor", "Added system prompt", {
                "system_prompt_length": len(self.system_prompt)
            })
            
        messages.append({"role": "user", "content": self.prompt.input})
        
        eval_logger.log_llm_request("llm_requestor", 
                                   prompt=self.prompt.input,
                                   model_info={
                                       "name": self.model.name,
                                       "url": self.model.url,
                                       "total_messages": len(messages)
                                   })

        eval_logger.info("llm_requestor", "Making API request to model")
        completion = client.chat.completions.create(
            model=self.model.name,
            messages=messages
        )
        
        response_content = completion.choices[0].message.content
        
        eval_logger.log_llm_response("llm_requestor", 
                                    response=response_content,
                                    metadata={
                                        "response_length": len(response_content),
                                        "finish_reason": completion.choices[0].finish_reason if completion.choices else None
                                    })
        
        return response_content