"""
Global evaluation logging system.

This module provides a thread-safe global logging system to capture
decision-making, parameters, and conversations during evaluation.
"""

import threading
import time
import sys
import json
from typing import Dict, List, Any, Optional
from dataclasses import dataclass, field
from datetime import datetime


@dataclass
class LogEntry:
    """Represents a single log entry."""
    timestamp: datetime
    level: str  # 'info', 'debug', 'decision', 'conversation'
    component: str  # 'judge', 'evaluator', 'metric_creator', 'llm_requestor'
    message: str
    data: Optional[Dict[str, Any]] = None


class EvalLogger:
    """
    Thread-safe global logger for evaluation processes.
    
    This logger captures important decision-making, parameters, and conversations
    during the evaluation process. It's designed to be reset for each request
    and provide comprehensive logs alongside evaluation results.
    
    Also outputs logs to terminal/stdout for Docker container visibility.
    """
    
    def __init__(self, enable_terminal_output: bool = True, verbose_terminal: bool = False):
        self._lock = threading.Lock()
        self._logs: List[LogEntry] = []
        self._request_id: Optional[str] = None
        self._enable_terminal_output = enable_terminal_output
        self._verbose_terminal = verbose_terminal
        
    def reset(self, request_id: Optional[str] = None):
        """Reset logs for a new evaluation request."""
        with self._lock:
            self._logs.clear()
            self._request_id = request_id or f"eval_{int(time.time() * 1000)}"
            # Add initial log entry directly to avoid recursive lock
            entry = LogEntry(
                timestamp=datetime.now(),
                level="info",
                component="eval_logger",
                message=f"Started new evaluation session: {self._request_id}",
                data={}
            )
            self._logs.append(entry)
            
            # Also output to terminal for Docker visibility
            self._output_to_terminal(entry)
    
    def _output_to_terminal(self, entry: LogEntry):
        """Output log entry to terminal/stdout for Docker visibility."""
        if not self._enable_terminal_output:
            return
            
        try:
            # Format timestamp for readability
            timestamp_str = entry.timestamp.strftime("%Y-%m-%d %H:%M:%S.%f")[:-3]
            
            # Create base log message
            log_message = f"[{timestamp_str}] [{entry.level.upper()}] [{entry.component}] {entry.message}"
            
            # Add data if present - show all data but format it nicely
            if entry.data:
                # For terminal output, format the data in a readable way
                formatted_data = {}
                for k, v in entry.data.items():
                    if isinstance(v, str):
                        # Truncate very long strings based on verbosity setting
                        max_length = 1000 if self._verbose_terminal else 500
                        if len(v) > max_length:
                            formatted_data[k] = v[:max_length] + "...[truncated]"
                        else:
                            formatted_data[k] = v
                    elif isinstance(v, dict):
                        # For nested dictionaries, show based on verbosity
                        if self._verbose_terminal or len(str(v)) <= 300:
                            formatted_data[k] = v
                        else:
                            formatted_data[k] = f"{{...{len(v)} items...}}"
                    elif isinstance(v, list):
                        # For lists, show based on verbosity
                        if self._verbose_terminal or len(str(v)) <= 300:
                            formatted_data[k] = v
                        else:
                            formatted_data[k] = f"[...{len(v)} items...]"
                    else:
                        formatted_data[k] = v
                
                # Convert to JSON and add to log message
                try:
                    if self._verbose_terminal:
                        # Pretty print with indentation for verbose mode
                        data_json = json.dumps(formatted_data, default=str, indent=2)
                        # For verbose mode, put data on new lines for readability
                        log_message += f"\n  Data:\n{data_json}"
                    else:
                        # Compact format for normal mode
                        data_json = json.dumps(formatted_data, default=str, indent=None, separators=(',', ':'))
                        log_message += f" | Data: {data_json}"
                except Exception:
                    # Fallback if JSON serialization fails
                    log_message += f" | Data: {str(formatted_data)}"
            
            # Output to stdout (which Docker captures)
            print(log_message, flush=True)
            
        except Exception as e:
            # Fallback to basic output if formatting fails
            print(f"[LOG ERROR] Failed to format log: {e}", flush=True)
            print(f"[{entry.level.upper()}] [{entry.component}] {entry.message}", flush=True)
            if entry.data:
                print(f"[LOG ERROR] Raw data: {entry.data}", flush=True)
    
    def _add_log(self, level: str, component: str, message: str, data: Optional[Dict[str, Any]] = None):
        """Internal method to add a log entry."""
        with self._lock:
            entry = LogEntry(
                timestamp=datetime.now(),
                level=level,
                component=component,
                message=message,
                data=data or {}
            )
            self._logs.append(entry)
            
            # Also output to terminal for Docker visibility
            self._output_to_terminal(entry)
    
    def info(self, component: str, message: str, data: Optional[Dict[str, Any]] = None):
        """Log general information."""
        self._add_log("info", component, message, data)
    
    def debug(self, component: str, message: str, data: Optional[Dict[str, Any]] = None):
        """Log debug information."""
        self._add_log("debug", component, message, data)
    
    def decision(self, component: str, message: str, data: Optional[Dict[str, Any]] = None):
        """Log important decision-making information."""
        self._add_log("decision", component, message, data)
    
    def conversation(self, component: str, message: str, data: Optional[Dict[str, Any]] = None):
        """Log conversation/communication with LLMs."""
        self._add_log("conversation", component, message, data)
    
    def log_parameters(self, component: str, parameters: Dict[str, Any]):
        """Log parameters used in a component."""
        self.info(component, "Parameters used", {"parameters": parameters})
    
    def log_llm_request(self, component: str, prompt: str, model_info: Dict[str, Any]):
        """Log LLM request details."""
        self.conversation(component, "LLM Request", {
            "prompt": prompt,
            "model": model_info
        })
    
    def log_llm_response(self, component: str, response: str, metadata: Optional[Dict[str, Any]] = None):
        """Log LLM response details."""
        self.conversation(component, "LLM Response", {
            "response": response,
            "metadata": metadata or {}
        })
    
    def log_metric_decision(self, component: str, metric_type: str, decision_factors: Dict[str, Any]):
        """Log metric evaluation decision factors."""
        self.decision(component, f"Metric evaluation ({metric_type})", {
            "decision_factors": decision_factors
        })
    
    def get_logs(self) -> List[Dict[str, Any]]:
        """Get all logs as a list of dictionaries."""
        with self._lock:
            return [
                {
                    "timestamp": entry.timestamp.isoformat(),
                    "level": entry.level,
                    "component": entry.component,
                    "message": entry.message,
                    "data": entry.data
                }
                for entry in self._logs
            ]
    
    def get_logs_by_level(self, level: str) -> List[Dict[str, Any]]:
        """Get logs filtered by level."""
        all_logs = self.get_logs()
        return [log for log in all_logs if log["level"] == level]
    
    def get_conversation_logs(self) -> List[Dict[str, Any]]:
        """Get only conversation logs (LLM interactions)."""
        return self.get_logs_by_level("conversation")
    
    def get_decision_logs(self) -> List[Dict[str, Any]]:
        """Get only decision-making logs."""
        return self.get_logs_by_level("decision")
    
    def enable_terminal_output(self, enable: bool = True):
        """Enable or disable terminal output."""
        self._enable_terminal_output = enable
        if enable:
            print("[EVAL_LOGGER] Terminal output enabled", flush=True)
        
    def disable_terminal_output(self):
        """Disable terminal output."""
        self.enable_terminal_output(False)
    
    def enable_verbose_terminal(self, verbose: bool = True):
        """Enable or disable verbose terminal output (shows full data with formatting)."""
        self._verbose_terminal = verbose
        if verbose:
            print("[EVAL_LOGGER] Verbose terminal output enabled", flush=True)
        else:
            print("[EVAL_LOGGER] Verbose terminal output disabled", flush=True)
    
    def log_error(self, component: str, message: str, data: Optional[Dict[str, Any]] = None):
        """Log error information (outputs as decision level for visibility)."""
        self.decision(component, f"ERROR: {message}", data)
    
    def get_request_id(self) -> Optional[str]:
        """Get the current request ID."""
        return self._request_id


# Global logger instance
eval_logger = EvalLogger(enable_terminal_output=True, verbose_terminal=True)

# Print startup message
print("[EVAL_LOGGER] Evaluation logger initialized with verbose terminal output enabled", flush=True)
print("[EVAL_LOGGER] All log data will be displayed in terminal for debugging", flush=True)
