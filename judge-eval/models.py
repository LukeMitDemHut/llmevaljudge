from pydantic import BaseModel
from typing import Optional, List

class Prompt(BaseModel):
    input: str
    output: Optional[str] = ""
    expected_output: str
    context: Optional[str] = ""

class ModelInfo(BaseModel):
    name: str
    url: str
    key: str

class Metric(BaseModel):
    type: str
    name: str
    definition: str
    param: List[str]
    model: ModelInfo

class EvalRequest(BaseModel):
    prompt: Prompt
    model: ModelInfo
    metric: Metric
    system_prompt: Optional[str] = ""
