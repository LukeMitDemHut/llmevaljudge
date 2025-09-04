from models import Prompt, Metric, ModelInfo
from openai import OpenAI
import os
import json
import hashlib
from datetime import datetime, timedelta
from eval_logger import eval_logger

class LlmRequestor:
    def __init__(self, prompt: Prompt, model: ModelInfo, system_prompt: str = ""):
        self.prompt = prompt
        self.model = model
        self.system_prompt = system_prompt
        
        # Get cache directory relative to the project root
        project_root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        self.cache_dir = os.path.join(project_root, "/app/cache")
        
        # Ensure cache directory exists
        os.makedirs(self.cache_dir, exist_ok=True)
        
        eval_logger.info("llm_requestor", "Initialized LLM requestor", {
            "model_name": model.name,
            "model_url": model.url,
            "has_system_prompt": bool(system_prompt),
            "prompt_length": len(prompt.input),
            "cache_dir": self.cache_dir
        })

    def _generate_cache_key(self):
        """Generate a unique cache key based on prompt, model, and system prompt"""
        cache_data = {
            "prompt_input": self.prompt.input,
            "model_name": self.model.name,
            "model_url": self.model.url,
            "system_prompt": self.system_prompt
        }
        cache_string = json.dumps(cache_data, sort_keys=True)
        return hashlib.md5(cache_string.encode()).hexdigest()

    def _get_cache_file_path(self, cache_key):
        """Get the file path for a cache key"""
        return os.path.join(self.cache_dir, f"{cache_key}.json")

    def _is_cache_valid(self, cache_file_path):
        """Check if cache file exists and is less than 24 hours old"""
        if not os.path.exists(cache_file_path):
            return False
        
        # Get file modification time
        file_mtime = datetime.fromtimestamp(os.path.getmtime(cache_file_path))
        now = datetime.now()
        
        # Check if file is older than 24 hours
        if now - file_mtime > timedelta(hours=24):
            eval_logger.info("llm_requestor", "Cache expired, removing file", {
                "cache_file": cache_file_path,
                "age_hours": (now - file_mtime).total_seconds() / 3600
            })
            try:
                os.remove(cache_file_path)
            except OSError as e:
                eval_logger.log_error("llm_requestor", f"Failed to remove expired cache file: {e}")
            return False
        
        return True

    def _load_from_cache(self, cache_file_path):
        """Load response from cache file"""
        try:
            with open(cache_file_path, 'r', encoding='utf-8') as f:
                cache_data = json.load(f)
                eval_logger.info("llm_requestor", "Loaded response from cache", {
                    "cache_file": cache_file_path,
                    "response_length": len(cache_data['response'])
                })
                return cache_data['response']
        except (json.JSONDecodeError, KeyError, OSError) as e:
            eval_logger.log_error("llm_requestor", f"Failed to load from cache: {e}")
            return None

    def _save_to_cache(self, cache_file_path, response):
        """Save response to cache file"""
        try:
            cache_data = {
                "response": response,
                "timestamp": datetime.now().isoformat(),
                "prompt_input": self.prompt.input,
                "model_name": self.model.name,
                "system_prompt": self.system_prompt
            }
            with open(cache_file_path, 'w', encoding='utf-8') as f:
                json.dump(cache_data, f, ensure_ascii=False, indent=2)
            eval_logger.info("llm_requestor", "Saved response to cache", {
                "cache_file": cache_file_path,
                "response_length": len(response)
            })
        except OSError as e:
            eval_logger.log_error("llm_requestor", f"Failed to save to cache: {e}")

    def request(self):
        # Generate cache key
        cache_key = self._generate_cache_key()
        cache_file_path = self._get_cache_file_path(cache_key)
        
        eval_logger.info("llm_requestor", "Checking cache for request", {
            "cache_key": cache_key,
            "cache_file": cache_file_path
        })
        
        # Check if cache is valid and load from cache if available
        if self._is_cache_valid(cache_file_path):
            cached_response = self._load_from_cache(cache_file_path)
            if cached_response is not None:
                eval_logger.info("llm_requestor", "Using cached response")
                return cached_response
        
        # Cache miss or invalid cache - make API request
        eval_logger.info("llm_requestor", "Cache miss, making API request")
        
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
        
        # Save response to cache
        self._save_to_cache(cache_file_path, response_content)
        
        return response_content