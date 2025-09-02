from deepeval.metrics import BaseMetric
from deepeval.test_case import LLMTestCase
from typing import Optional
from eval_logger import eval_logger

class TALEMetric(BaseMetric):
    def __init__(
        self,
        model,
        threshold: float = 0.5,
        evaluation_model: Optional[str] = None,
        include_reason: bool = True,
        strict_mode: bool = False,
        async_mode: bool = True,
        task: str = "Evaluate the factual accuracy using web search",
        max_search_results: int = 5,
        max_iterations: int = 3,
        search_engines: list = ["google", "bing", "duckduckgo"],
        search_engine_url: str = "http://judge_searxng:80",
        time_range: str = "all"
    ):
        self.model = model
        self.threshold = threshold
        self.evaluation_model = evaluation_model
        self.include_reason = include_reason
        self.strict_mode = strict_mode
        self.async_mode = async_mode
        
        # TALE specific properties
        self.task = task
        self.max_search_results = max_search_results
        self.max_iterations = max_iterations
        self.search_engines = search_engines
        self.search_engine_url = search_engine_url
        self.time_range = time_range

        # Initialize state variables
        self.collected_evidence = {}
        self._last_search_query = ""
        
        eval_logger.info("tale_metric", "Initialized TALE metric", {
            "task": task,
            "threshold": threshold,
            "max_search_results": max_search_results,
            "max_iterations": max_iterations
        })

    def measure(self, test_case: LLMTestCase) -> float:
        """
        Main evaluation method implementing the TALE (Tool-Augmented LLM Evaluation) approach.
        """
        try:
            eval_logger.info("tale_metric", "Starting TALE evaluation", {
                "input": test_case.input,
                "actual_output": test_case.actual_output,
                "expected_output": test_case.expected_output,
                "context": test_case.context
            })

            # Validate required inputs
            if not test_case.input or not test_case.actual_output:
                error_msg = "TALE metric requires both input and actual_output to be provided"
                eval_logger.decision("tale_metric", "Validation failed", {"error": error_msg})
                raise ValueError(error_msg)

            if not self.task:
                error_msg = "TALE metric requires a task to be specified"
                eval_logger.decision("tale_metric", "Validation failed", {"error": error_msg})
                raise ValueError(error_msg)

            # enter the evaluation loop
            reflection = {}
            memory = {}
            total_search_attempts = 0
            successful_searches = 0
            search_engine_failures = 0
            all_unresponsive_engines = []  # Track unresponsive engines across iterations
            
            for i in range(self.max_iterations):
                eval_logger.info("tale_metric", f"Starting evaluation iteration {i + 1}", {
                    "input": test_case.input,
                    "actual_output": test_case.actual_output,
                    "expected_output": test_case.expected_output,
                    "context": test_case.context
                })

                # Generate search query with error handling
                try:
                    search_query = self._generate_search_query(test_case.input, test_case.actual_output, test_case.context, self.task, reflection)
                    self._last_search_query = search_query  # Store for reflection
                    
                    if not search_query or search_query.strip() == "":
                        error_msg = f"Failed to generate valid search query at iteration {i + 1}"
                        eval_logger.decision("tale_metric", "Search query generation failed", {"error": error_msg})
                        raise ValueError(error_msg)
                        
                except Exception as e:
                    error_msg = f"Failed to generate search query at iteration {i + 1}: {str(e)}"
                    eval_logger.decision("tale_metric", "Search query generation failed", {"error": error_msg})
                    raise ValueError(error_msg)

                # Perform web search using the generated query
                total_search_attempts += 1
                try:
                    search_results, unresponsive_engines = self._search_engine(search_query, self.search_engines, self.time_range)
                    successful_searches += 1
                    
                    # Track unresponsive engines across iterations
                    if unresponsive_engines:
                        all_unresponsive_engines.extend(unresponsive_engines)
                    
                    if search_results:
                        eval_logger.info("tale_metric", f"Search successful with results", {
                            "query": search_query,
                            "results_count": len(search_results),
                            "unresponsive_engines_count": len(unresponsive_engines) if unresponsive_engines else 0
                        })
                    else:
                        if unresponsive_engines:
                            unresponsive_summary = "; ".join([
                                f"{engine_info[0]}: {engine_info[1]}" if isinstance(engine_info, list) and len(engine_info) >= 2 
                                else str(engine_info) for engine_info in unresponsive_engines
                            ])
                            eval_logger.info("tale_metric", f"Search successful but returned no results (unresponsive engines: {unresponsive_summary})", {
                                "query": search_query,
                                "iteration": i + 1,
                                "unresponsive_engines": unresponsive_engines
                            })
                        else:
                            eval_logger.info("tale_metric", f"Search successful but returned no results", {
                                "query": search_query,
                                "iteration": i + 1
                            })
                        
                except Exception as e:
                    search_engine_failures += 1
                    error_msg = f"Search engine failed at iteration {i + 1}: {str(e)}"
                    eval_logger.decision("tale_metric", "Search engine failure", {
                        "error": error_msg,
                        "query": search_query,
                        "iteration": i + 1
                    })
                    
                    # If all iterations fail due to search engine issues, raise an error
                    if search_engine_failures >= self.max_iterations:
                        raise ValueError(f"Search engine failed for all {self.max_iterations} iterations. Last error: {str(e)}")
                    
                    # Continue to next iteration for this specific failure
                    continue

                # Process search results
                iteration_evidence_count = 0
                for result in search_results:
                    eval_logger.info("tale_metric", "Processing search result", {
                        "title": result.get("title"),
                        "url": result.get("url"),
                        "snippet": result.get("snippet")
                    })
                    
                    # Extract website content with error handling
                    website_content = self._extract_website_content(result.get("url"))
                    if website_content and website_content.strip():
                        memory[result.get("url")] = website_content
                        iteration_evidence_count += 1
                    else:
                        eval_logger.debug("tale_metric", "Failed to extract content from URL", {
                            "url": result.get("url")
                        })

                eval_logger.info("tale_metric", f"Iteration {i + 1} evidence collection", {
                    "new_evidence_sources": iteration_evidence_count,
                    "total_evidence_sources": len(memory)
                })

                # Reflect on the collected evidence to determine if more search is needed
                try:
                    reflection = self._reflect_on_evidence(memory, test_case, i + 1)
                    if not reflection.get("continue_iterate", False):
                        self.collected_evidence = memory
                        break
                except Exception as e:
                    error_msg = f"Failed to reflect on evidence at iteration {i + 1}: {str(e)}"
                    eval_logger.decision("tale_metric", "Reflection failed", {"error": error_msg})
                    raise ValueError(error_msg)

            # Check if we have any evidence at all after all iterations
            if not memory:
                # Prepare unresponsive engines summary for error message
                unresponsive_summary = ""
                if all_unresponsive_engines:
                    # Deduplicate unresponsive engines and create summary
                    unique_engines = {}
                    for engine_info in all_unresponsive_engines:
                        if isinstance(engine_info, list) and len(engine_info) >= 2:
                            engine_name = engine_info[0]
                            engine_error = engine_info[1]
                            unique_engines[engine_name] = engine_error
                        else:
                            unique_engines[str(engine_info)] = "unknown error"
                    
                    unresponsive_details = [f"{name}: {error}" for name, error in unique_engines.items()]
                    unresponsive_summary = f" Unresponsive engines: {'; '.join(unresponsive_details)}."
                
                if search_engine_failures == total_search_attempts:
                    error_msg = f"All search engine requests failed during {self.max_iterations} iterations. Search engine failures: {search_engine_failures}/{total_search_attempts}.{unresponsive_summary}"
                elif successful_searches == 0:
                    error_msg = f"No successful search requests completed during {self.max_iterations} iterations. Search attempts: {total_search_attempts}, failures: {search_engine_failures}.{unresponsive_summary}"
                else:
                    error_msg = f"No evidence was collected after {self.max_iterations} search iterations. Search attempts: {total_search_attempts}, successful: {successful_searches}, failures: {search_engine_failures}.{unresponsive_summary}"
                
                eval_logger.decision("tale_metric", "No evidence collected", {
                    "error": error_msg,
                    "total_search_attempts": total_search_attempts,
                    "successful_searches": successful_searches,
                    "search_engine_failures": search_engine_failures,
                    "all_unresponsive_engines": all_unresponsive_engines
                })
                raise ValueError(error_msg)

            eval_logger.info("tale_metric", "Evidence collection completed", {
                "total_evidence_sources": len(memory),
                "total_search_attempts": total_search_attempts,
                "successful_searches": successful_searches,
                "search_engine_failures": search_engine_failures,
                "total_unresponsive_engines": len(all_unresponsive_engines),
                "unresponsive_engines": all_unresponsive_engines
            })
            
            # finally judge the result with error handling
            try:
                judgement = self._judge_result(test_case, self.collected_evidence)
                self.score = judgement.get("score", 0.0)
                self.reason = judgement.get("reason", "")
            except Exception as e:
                error_msg = f"Failed to make final judgment: {str(e)}"
                eval_logger.decision("tale_metric", "Final judgment failed", {"error": error_msg})
                raise ValueError(error_msg)

            # Validate final results
            if self.score is None or not isinstance(self.score, (int, float)):
                error_msg = f"Invalid score returned from judgment: {self.score}"
                eval_logger.decision("tale_metric", "Invalid score", {"error": error_msg})
                raise ValueError(error_msg)

            if not self.reason or not isinstance(self.reason, str):
                error_msg = f"Invalid or empty reason returned from judgment: {self.reason}"
                eval_logger.decision("tale_metric", "Invalid reason", {"error": error_msg})
                raise ValueError(error_msg)

            # Determine success based on threshold
            self.success = self.score >= self.threshold
            
            eval_logger.decision("tale_metric", "TALE evaluation completed successfully", {
                "score": self.score,
                "reason_length": len(self.reason),
                "success": self.success,
                "evidence_sources": len(self.collected_evidence)
            })
            
            return self.score
            
        except Exception as e:
            self.error = str(e)
            eval_logger.decision("tale_metric", f"TALE evaluation failed with error: {str(e)}", {
                "error_type": type(e).__name__,
                "error_message": str(e)
            })
            raise

    async def a_measure(self, test_case: LLMTestCase) -> float:
        """
        Asynchronous implementation of measure().
        For now, just calls the synchronous method.
        """
        return self.measure(test_case)

    def is_successful(self) -> bool:
        """
        Determine if the metric evaluation was successful.
        """
        if hasattr(self, 'error') and self.error is not None:
            self.success = False
        return getattr(self, 'success', False)

    @property
    def __name__(self):
        return "TALE Metric"
    
    # utility methods
    from typing import Optional

    def _generate_search_query(
        self,
        input: str,
        output: str,
        context: str,
        task: str,
        reflection: Optional[dict] = None
    ) -> str:
        """
        Build a prompt for the LLM that generates a single improved search query
        for evaluating an LLM output against a given task.
        """
        
        if not self.model:
            raise ValueError("No model available for search query generation")

        prompt = (
            "You are part of an LLM evaluation suite.\n"
            "Your job is to generate **one** search engine query for a website search engine that will help assess "
            "how well an LLM's output meets the evaluation task.\n\n"
            f"Evaluation task: {task}\n"
            f"Original input: {input}\n"
            f"LLM output: {output}\n"
        )

        if context:
            prompt += f"Additional context: {context}\n"

        if reflection and reflection.get('previous_query'):
            prompt += (
                f"Previous query: {reflection.get('previous_query','')}\n"
                f"Reflection on previous query: {reflection.get('reflection','')}\n"
                "Refine and improve the query accordingly.\n"
            )

        prompt += (
            "\nReply with ONLY the search query string itself. "
            "Do not add explanations, punctuation, or extra text."
        )

        try:
            eval_logger.conversation("tale_metric", "Generating search query", {
                "prompt_length": len(prompt),
                "has_reflection": bool(reflection and reflection.get('previous_query'))
            })
            
            query = self.model.generate(prompt)
            
            eval_logger.conversation("tale_metric", "Search query generated", {
                "query": query,
                "query_length": len(query) if query else 0
            })
            
            if not query or not isinstance(query, str):
                raise ValueError(f"Model returned invalid search query: {type(query)} - {query}")
                
            query = query.strip()
            if not query:
                raise ValueError("Model returned empty search query")
                
            return query
            
        except Exception as e:
            eval_logger.debug("tale_metric", f"Search query generation failed: {str(e)}")
            raise ValueError(f"Failed to generate search query: {str(e)}")

    def _search_engine(self, query: str, engines: list, time_range: str) -> tuple:
        """Search using SearXNG search engine for relevant web pages.
        
        Returns:
            tuple: (results, unresponsive_engines) where results is a list of search results
                   and unresponsive_engines is a list of engine failures
        """
        import requests

        if not query or query.strip() == "":
            raise ValueError("Empty search query provided")

        if not self.search_engine_url:
            raise ValueError("No search engine URL configured")

        if time_range == 'all':
            time_range = ''  # Reset to empty string for all time

        # Join engines as a comma-separated string for the API
        engines_param = ",".join(engines) if engines else "google"
        
        eval_logger.info("tale_metric", "Making search request", {
            "query": query,
            "engines": engines_param,
            "time_range": time_range,
            "url": self.search_engine_url
        })
        
        try:
            response = requests.get(
                f"{self.search_engine_url}/search",
                params={"q": query, "format": "json", "engines": engines_param, "time_range": time_range},
                timeout=10
            )
            response.raise_for_status()
            
        except requests.exceptions.Timeout as e:
            error_msg = f"Search engine request timed out after 10 seconds: {str(e)}"
            eval_logger.debug("tale_metric", error_msg, {
                "query": query,
                "url": self.search_engine_url,
                "timeout": 100
            })
            raise ValueError(error_msg)
            
        except requests.exceptions.ConnectionError as e:
            error_msg = f"Search engine connection failed: {str(e)}"
            eval_logger.debug("tale_metric", error_msg, {
                "query": query,
                "url": self.search_engine_url
            })
            raise ValueError(error_msg)
            
        except requests.exceptions.HTTPError as e:
            status_code = getattr(e.response, 'status_code', 'unknown') if hasattr(e, 'response') else 'unknown'
            error_msg = f"Search engine HTTP error (status {status_code}): {str(e)}"
            eval_logger.debug("tale_metric", error_msg, {
                "query": query,
                "url": self.search_engine_url,
                "status_code": status_code
            })
            raise ValueError(error_msg)
            
        except Exception as e:
            error_msg = f"Search engine request failed with unexpected error: {str(e)}"
            eval_logger.debug("tale_metric", error_msg, {
                "query": query,
                "url": self.search_engine_url,
                "error_type": type(e).__name__
            })
            raise ValueError(error_msg)

        # If we get here, the request was successful, now parse the response
        try:
            results_data = response.json()
            results = results_data.get("results", [])
            unresponsive_engines = results_data.get("unresponsive_engines", [])
            
            # Log unresponsive engines if any
            if unresponsive_engines:
                unresponsive_info = []
                for engine_info in unresponsive_engines:
                    if isinstance(engine_info, list) and len(engine_info) >= 2:
                        engine_name = engine_info[0]
                        engine_error = engine_info[1]
                        unresponsive_info.append(f"{engine_name}: {engine_error}")
                    else:
                        unresponsive_info.append(str(engine_info))
                
                eval_logger.info("tale_metric", "Search completed with unresponsive engines", {
                    "status_code": response.status_code,
                    "results_count": len(results),
                    "query": query,
                    "unresponsive_engines": unresponsive_engines,
                    "unresponsive_engines_summary": unresponsive_info
                })
            else:
                eval_logger.info("tale_metric", "Search request completed successfully", {
                    "status_code": response.status_code,
                    "results_count": len(results),
                    "query": query
                })
            
            if not results:
                # Include unresponsive engines info in the "no results" log
                if unresponsive_engines:
                    unresponsive_summary = "; ".join([
                        f"{engine_info[0]}: {engine_info[1]}" if isinstance(engine_info, list) and len(engine_info) >= 2 
                        else str(engine_info) for engine_info in unresponsive_engines
                    ])
                    eval_logger.info("tale_metric", "Search engine returned no results (with unresponsive engines)", {
                        "query": query,
                        "requested_engines": engines_param,
                        "unresponsive_engines": unresponsive_engines,
                        "unresponsive_engines_summary": unresponsive_summary,
                        "response_data": results_data
                    })
                else:
                    eval_logger.info("tale_metric", "Search engine returned no results", {
                        "query": query,
                        "requested_engines": engines_param,
                        "response_data": results_data
                    })
            
            return results[:self.max_search_results], unresponsive_engines
            
        except ValueError as e:
            error_msg = f"Search engine response parsing failed: {str(e)}"
            eval_logger.debug("tale_metric", error_msg, {
                "query": query,
                "url": self.search_engine_url
            })
            raise ValueError(error_msg)
    
    def _extract_website_content(self, url: str) -> str:
        """Extract content from a web page URL."""
        try:
            from bs4 import BeautifulSoup
            from urllib.request import Request, urlopen
            
            req = Request(url, headers={'User-Agent': 'Mozilla/5.0'})
            html = urlopen(req)
            soup = BeautifulSoup(html, "html.parser")
            return soup.get_text()
        except Exception as e:
            eval_logger.debug("tale_metric", f"Web scraping failed: {str(e)}")
            return ""

    def _reflect_on_evidence(self, memory: dict, test_case: LLMTestCase, iteration: int) -> dict:
        """
        Reflect on the collected evidence to determine if more search is needed.
        
        Args:
            memory: Dictionary of collected evidence from web searches
            test_case: The test case being evaluated
            iteration: Current iteration number
            
        Returns:
            Dict with reflection results including whether to continue iterating
        """
        eval_logger.info("tale_metric", f"Reflecting on evidence (iteration {iteration})", {
            "evidence_sources": len(memory),
            "iteration": iteration,
            "max_iterations": self.max_iterations
        })
        
        # If we've reached max iterations, stop searching
        if iteration >= self.max_iterations:
            eval_logger.decision("tale_metric", "Stopping search: max iterations reached", {
                "iteration": iteration,
                "max_iterations": self.max_iterations
            })
            return {
                "continue_iterate": False,
                "source_critique": "Maximum iterations reached",
                "evidence_quality": "final"
            }
        
        # If no evidence collected, continue searching
        if not memory:
            eval_logger.decision("tale_metric", "Continuing search: no evidence collected", {
                "iteration": iteration
            })
            return {
                "continue_iterate": True,
                "source_critique": "No evidence found, need to search with different terms",
                "evidence_quality": "insufficient"
            }
        
        # Prepare evidence summary for LLM reflection
        evidence_summary = self._summarize_evidence(memory)
        
        # Build reflection prompt
        reflection_prompt = self._build_reflection_prompt(test_case, evidence_summary, iteration)
        
        # Get LLM reflection
        eval_logger.conversation("tale_metric", "Requesting reflection from LLM", {
            "prompt_length": len(reflection_prompt),
            "evidence_sources": len(memory)
        })
        
        try:
            reflection_response = self.model.generate(reflection_prompt)
            
            if not reflection_response or not isinstance(reflection_response, str):
                raise ValueError(f"Model returned invalid reflection response: {type(reflection_response)} - {reflection_response}")
                
            reflection_response = reflection_response.strip()
            if not reflection_response:
                raise ValueError("Model returned empty reflection response")
                
        except Exception as e:
            eval_logger.debug("tale_metric", f"Reflection generation failed: {str(e)}")
            raise ValueError(f"Failed to generate reflection: {str(e)}")
        
        eval_logger.conversation("tale_metric", "Received reflection response", {
            "response": reflection_response,
            "iteration": iteration
        })
        
        # Parse reflection response to determine if more search is needed
        should_continue = self._parse_reflection_response(reflection_response)
        
        eval_logger.decision("tale_metric", "Reflection decision made", {
            "should_continue": should_continue,
            "iteration": iteration,
            "evidence_sources": len(memory)
        })
        
        return {
            "continue_iterate": should_continue,
            "source_critique": reflection_response,
            "evidence_quality": "sufficient" if not should_continue else "needs_more",
            "previous_query": getattr(self, '_last_search_query', ''),
            "reflection": reflection_response
        }

    def _judge_result(self, test_case: LLMTestCase, memory: dict) -> dict:
        """
        Final judgment based on all collected evidence.
        
        Args:
            test_case: The test case being evaluated
            memory: Dictionary of all collected evidence
            
        Returns:
            Dict with score (0.0-1.0) and reasoning
        """
        eval_logger.info("tale_metric", "Starting final judgment", {
            "evidence_sources": len(memory),
            "task": self.task
        })
        
        # If no evidence was collected, this is an error condition
        if not memory:
            error_msg = "No evidence was collected to evaluate the response against the task requirements"
            eval_logger.decision("tale_metric", "No evidence available for judgment", {
                "error": error_msg,
                "task": self.task
            })
            raise ValueError(error_msg)
        
        # Prepare evidence summary for judgment
        evidence_summary = self._summarize_evidence(memory)
        
        # Build judgment prompt
        judgment_prompt = self._build_judgment_prompt(test_case, evidence_summary)
        
        eval_logger.conversation("tale_metric", "Requesting final judgment from LLM", {
            "prompt_length": len(judgment_prompt),
            "evidence_sources": len(memory),
            "task": self.task
        })
        
        # Get LLM judgment
        try:
            judgment_response = self.model.generate(judgment_prompt)
            
            if not judgment_response or not isinstance(judgment_response, str):
                raise ValueError(f"Model returned invalid judgment response: {type(judgment_response)} - {judgment_response}")
                
            judgment_response = judgment_response.strip()
            if not judgment_response:
                raise ValueError("Model returned empty judgment response")
                
        except Exception as e:
            eval_logger.debug("tale_metric", f"Judgment generation failed: {str(e)}")
            raise ValueError(f"Failed to generate judgment: {str(e)}")
        
        eval_logger.conversation("tale_metric", "Received judgment response", {
            "response_length": len(judgment_response)
        })
        
        # Parse judgment response to extract score and reasoning
        try:
            parsed_judgment = self._parse_judgment_response(judgment_response)
            
            # Validate parsed judgment
            if not isinstance(parsed_judgment, dict):
                raise ValueError(f"Judgment parsing returned invalid type: {type(parsed_judgment)}")
                
            if 'score' not in parsed_judgment or 'reason' not in parsed_judgment:
                raise ValueError(f"Judgment parsing missing required fields: {parsed_judgment}")
                
            score = parsed_judgment['score']
            if not isinstance(score, (int, float)) or score < 0.0 or score > 1.0:
                raise ValueError(f"Invalid score in judgment: {score}")
                
            reason = parsed_judgment['reason']
            if not isinstance(reason, str) or not reason.strip():
                raise ValueError(f"Invalid or empty reason in judgment: {reason}")
                
        except Exception as e:
            eval_logger.debug("tale_metric", f"Judgment parsing failed: {str(e)}")
            raise ValueError(f"Failed to parse judgment response: {str(e)}")
        
        eval_logger.decision("tale_metric", "Final judgment completed", {
            "score": parsed_judgment["score"],
            "reason_length": len(parsed_judgment["reason"])
        })
        
        return parsed_judgment

    def _summarize_evidence(self, memory: dict) -> str:
        """
        Summarize collected evidence for LLM processing.
        
        Args:
            memory: Dictionary of URL -> content mappings
            
        Returns:
            Formatted evidence summary string
        """
        if not memory:
            return "No evidence collected."
        
        summary_parts = []
        for i, (url, content) in enumerate(memory.items(), 1):
            # Truncate content to avoid overly long prompts
            truncated_content = content[:1000] + "..." if len(content) > 1000 else content
            summary_parts.append(f"Source {i} ({url}):\n{truncated_content}\n")
        
        return "\n".join(summary_parts)

    def _build_reflection_prompt(self, test_case: LLMTestCase, evidence_summary: str, iteration: int) -> str:
        """
        Build prompt for LLM to reflect on collected evidence.
        
        Args:
            test_case: The test case being evaluated
            evidence_summary: Summary of collected evidence
            iteration: Current iteration number
            
        Returns:
            Reflection prompt string
        """
        prompt = f"""You are evaluating whether sufficient evidence has been collected to assess an LLM's response.

EVALUATION TASK: {self.task}

ORIGINAL INPUT: {test_case.input}

LLM RESPONSE TO EVALUATE: {test_case.actual_output}

EVIDENCE COLLECTED (Iteration {iteration}):
{evidence_summary}

Based on the evidence collected so far, determine if you have enough information to reliably evaluate the LLM's response against the task requirements.

Consider:
1. Is the evidence relevant to the evaluation task?
2. Is there sufficient information to make a confident judgment?
3. Are there obvious gaps in the evidence that more searching could fill?

Respond with ONLY one of:
- SUFFICIENT: If you have enough evidence to make a reliable evaluation
- INSUFFICIENT: If you need more evidence (provide brief reason why)

Your response:"""
        
        return prompt

    def _build_judgment_prompt(self, test_case: LLMTestCase, evidence_summary: str) -> str:
        """
        Build prompt for final LLM judgment.
        
        Args:
            test_case: The test case being evaluated
            evidence_summary: Summary of all collected evidence
            
        Returns:
            Judgment prompt string
        """
        prompt = f"""You are an expert evaluator tasked with scoring an LLM's response based on collected evidence.

EVALUATION TASK: {self.task}

ORIGINAL INPUT: {test_case.input}

LLM RESPONSE ACTUAL_OUTPUT TO EVALUATE: {test_case.actual_output}"""

        if test_case.expected_output:
            prompt += f"\n\nEXPECTED OUTPUT: {test_case.expected_output}"

        if test_case.context:
            context_str = test_case.context[0] if isinstance(test_case.context, list) else str(test_case.context)
            prompt += f"\n\nCONTEXT: {context_str}"

        prompt += f"""

COLLECTED EVIDENCE:
{evidence_summary}

Based on the evidence, evaluate how well the LLM's response meets the evaluation task requirements.

Provide your evaluation in the following format:
SCORE: [number between 0.0 and 1.0]
REASONING: [detailed explanation of your evaluation based on the evidence]

Your evaluation:"""
        
        return prompt

    def _parse_reflection_response(self, response: str) -> bool:
        """
        Parse LLM reflection response to determine if more search is needed.
        
        Args:
            response: LLM reflection response
            
        Returns:
            True if more search is needed, False if sufficient evidence
        """
        response_lower = response.lower().strip()
        
        # Check for explicit sufficient/insufficient indicators
        if "sufficient" in response_lower and "insufficient" not in response_lower:
            return False
        elif "insufficient" in response_lower:
            return True
        
        # Fallback: look for other indicators
        continue_indicators = ["need more", "not enough", "insufficient", "continue", "search more"]
        stop_indicators = ["enough", "sufficient", "adequate", "complete"]
        
        has_continue = any(indicator in response_lower for indicator in continue_indicators)
        has_stop = any(indicator in response_lower for indicator in stop_indicators)
        
        # If both or neither, default to sufficient (stop searching)
        if has_continue and not has_stop:
            return True
        else:
            return False

    def _parse_judgment_response(self, response: str) -> dict:
        """
        Parse LLM judgment response to extract score and reasoning.
        
        Args:
            response: LLM judgment response
            
        Returns:
            Dict with 'score' (float 0.0-1.0) and 'reason' (string)
        """
        if not response or not isinstance(response, str):
            raise ValueError(f"Invalid response for parsing: {type(response)} - {response}")
            
        lines = response.strip().split('\n')
        score = None
        reason = ""
        
        for line in lines:
            line = line.strip()
            if line.startswith('SCORE:'):
                score_str = line.replace('SCORE:', '').strip()
                try:
                    score = float(score_str)
                    # Ensure score is in valid range
                    score = max(0.0, min(1.0, score))
                except ValueError:
                    eval_logger.debug("tale_metric", f"Could not parse score: {score_str}")
                    # Don't set score to 0.0 here, let it remain None to trigger error below
            elif line.startswith('REASONING:'):
                reason = line.replace('REASONING:', '').strip()
        
        # If no explicit reasoning found, use the whole response
        if not reason:
            reason = response.strip()
        
        # If no score was found or parsed, this is an error
        if score is None:
            raise ValueError(f"Could not extract valid score from judgment response: {response}")
            
        # If reason is still empty, this is an error
        if not reason:
            raise ValueError(f"Could not extract reasoning from judgment response: {response}")
        
        return {
            "score": score,
            "reason": reason
        }