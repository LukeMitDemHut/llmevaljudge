from fastapi import FastAPI, HTTPException
from evaluator import Evaluator
from models import Prompt, ModelInfo, Metric, EvalRequest
from eval_logger import eval_logger
import traceback

app = FastAPI()

@app.on_event("startup")
async def startup_event():
    eval_logger.info("main", "FastAPI application started successfully")
    print("FastAPI evaluation service is ready to receive requests", flush=True)

@app.on_event("shutdown") 
async def shutdown_event():
    eval_logger.info("main", "FastAPI application shutting down")
    print("FastAPI evaluation service is shutting down", flush=True)

@app.post("/")
def requestEval(eval_request: EvalRequest):
    try:
        eval_logger.info("main", "Received evaluation request", {
            "prompt_input_length": len(eval_request.prompt.input) if eval_request.prompt.input else 0,
            "metric_name": eval_request.metric.name,
            "metric_type": eval_request.metric.type,
            "model_name": eval_request.model.name,
            "has_system_prompt": bool(eval_request.system_prompt)
        })
        
        evaluator = Evaluator(
            prompt=eval_request.prompt,
            metric=eval_request.metric,
            model=eval_request.model,
            system_prompt=eval_request.system_prompt or ""
        )
        
        eval_logger.info("main", "Starting evaluation")
        result = evaluator.evaluate()
        
        eval_logger.info("main", "Evaluation completed successfully", {
            "score": result.get("score"),
            "actual_output_length": len(result.get("actual_output", ""))
        })
        
        return result
        
    except Exception as e:
        # Log the full error with traceback for debugging
        error_traceback = traceback.format_exc()
        eval_logger.log_error("main", f"Evaluation failed: {str(e)}", {
            "error_type": type(e).__name__,
            "error_message": str(e),
            "traceback": error_traceback
        })
        
        # Also print to stderr for immediate visibility
        print(f"ERROR in main: {str(e)}", flush=True)
        print(f"TRACEBACK: {error_traceback}", flush=True)
        
        raise HTTPException(status_code=500, detail=str(e))

if __name__ == "__main__":
    import uvicorn
    
    eval_logger.info("main", "Starting evaluation server", {
        "host": "0.0.0.0",
        "port": 5000
    })
    
    print("Starting evaluation server on 0.0.0.0:5000", flush=True)
    
    uvicorn.run(app, host="0.0.0.0", port=5000)
