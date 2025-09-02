import json
from models import Metric, ModelInfo
from deepeval.metrics import GEval, DAGMetric
from deepeval.metrics.dag import (
    DeepAcyclicGraph,
    TaskNode,
    BinaryJudgementNode,
    NonBinaryJudgementNode,
    VerdictNode,
)
from deepeval.test_case import LLMTestCaseParams
from talemetric import TALEMetric
import json
import os
from eval_logger import eval_logger
from judge import Judge

class MetricCreator:
    """
    Creates DeepEval metrics from frontend metric definitions.
    
    Supports both G-Eval and DAG metric types with proper parameter mapping
    and structure conversion from frontend JSON to DeepEval objects.
    """
    def __init__(self, metric: Metric):
        self.metric = metric
        eval_logger.info("metric_creator", "Initialized metric creator", {
            "metric_name": metric.name,
            "metric_type": metric.type,
            "metric_params": metric.param,
            "judge_model": metric.model.name
        })

    def create_metric(self):
        """
        Creates the appropriate DeepEval metric based on the metric type.
        
        Returns:
            Union[GEval, DAGMetric]: The created metric instance
            
        Raises:
            ValueError: If metric type is unknown or definition is invalid
        """
        eval_logger.info("metric_creator", "Starting metric creation")
        
        metric_definition = json.loads(self.metric.definition) if isinstance(self.metric.definition, str) else self.metric.definition
        eval_logger.debug("metric_creator", "Parsed metric definition", {
            "definition": metric_definition
        })

        # Create custom judge model for deepeval
        judge_model = Judge(
            api_base=self.metric.model.url,
            api_key=self.metric.model.key,
            model_name=self.metric.model.name
        )
        eval_logger.info("metric_creator", "Created custom judge model", {
            "judge_model_name": self.metric.model.name,
            "judge_api_base": self.metric.model.url
        })
        
        match self.metric.type:
            case "g-eval":
                eval_logger.decision("metric_creator", "Creating G-Eval metric", {
                    "metric_type": "g-eval",
                    "definition_type": metric_definition.get('type', 'criteria')
                })
                
                # Get evaluation params to determine which fields to include
                evaluation_params = self._get_metric_params()
                eval_logger.info("metric_creator", "Determined evaluation parameters", {
                    "evaluation_params": [str(param) for param in evaluation_params]
                })
                
                # Build GEval kwargs dynamically based on evaluation params
                geval_kwargs = {
                    'evaluation_params': evaluation_params,
                    'name': self.metric.name,
                    'model': judge_model  # Use our custom judge model
                }
                
                if metric_definition.get('type') == 'steps':
                    geval_kwargs['evaluation_steps'] = metric_definition.get('steps', [])
                    eval_logger.decision("metric_creator", "Using steps-based G-Eval", {
                        "steps": metric_definition.get('steps', []),
                        "steps_count": len(metric_definition.get('steps', []))
                    })
                    return GEval(**geval_kwargs)
                elif metric_definition.get('type') == 'criteria':
                    geval_kwargs['criteria'] = metric_definition.get('criteria', '')
                    eval_logger.decision("metric_creator", "Using criteria-based G-Eval", {
                        "criteria": metric_definition.get('criteria', ''),
                        "criteria_length": len(metric_definition.get('criteria', ''))
                    })
                    return GEval(**geval_kwargs)
                else:
                    # Default to criteria if no type specified
                    default_criteria = 'Evaluate the response quality'
                    geval_kwargs['criteria'] = metric_definition.get('criteria', default_criteria)
                    eval_logger.decision("metric_creator", "Using default criteria-based G-Eval", {
                        "criteria": geval_kwargs['criteria'],
                        "reason": "No type specified in metric definition"
                    })
                    return GEval(**geval_kwargs)

            case "tale":
                eval_logger.decision("metric_creator", "Creating TALE metric", {
                    "metric_type": "tale"
                })
                
                # TALE metric configuration from definition
                task = metric_definition.get('task')
                if task is None:
                    raise ValueError("Task must be specified for TALE metric")

                # Build kwargs dynamically, only including non-None values to preserve defaults
                tale_kwargs = {
                    'model': judge_model,
                    'threshold': getattr(self.metric, 'threshold', 0.5),
                    'task': task
                }
                
                # Only add optional parameters if they are explicitly provided (not None)
                if metric_definition.get('max_search_results') is not None:
                    tale_kwargs['max_search_results'] = metric_definition.get('max_search_results')
                
                if metric_definition.get('max_iterations') is not None:
                    tale_kwargs['max_iterations'] = metric_definition.get('max_iterations')
                
                if metric_definition.get('search_engines') is not None:
                    tale_kwargs['search_engines'] = metric_definition.get('search_engines')
                
                if metric_definition.get('search_engine_url') is not None:
                    tale_kwargs['search_engine_url'] = metric_definition.get('search_engine_url')
                
                if metric_definition.get('time_range') is not None:
                    tale_kwargs['time_range'] = metric_definition.get('time_range')

                eval_logger.info("metric_creator", "TALE metric configuration", {
                    "provided_params": list(tale_kwargs.keys()),
                    "task": task
                })
                
                tale_metric = TALEMetric(**tale_kwargs)
                
                eval_logger.decision("metric_creator", "TALE metric created successfully", {
                    "metric_name": self.metric.name,
                    "threshold": getattr(self.metric, 'threshold', 0.5)
                })
                
                return tale_metric
            
            case "dag":
                eval_logger.decision("metric_creator", "Creating DAG metric", {
                    "metric_type": "dag"
                })
                
                # Get evaluation params for all nodes
                evaluation_params = self._get_metric_params()
                eval_logger.info("metric_creator", "Determined evaluation parameters for DAG", {
                    "evaluation_params": [str(param) for param in evaluation_params]
                })
                
                # Create the DAG from definition
                dag_definition = metric_definition
                if dag_definition is None:
                    raise ValueError("DAG definition cannot be None")
                    
                eval_logger.debug("metric_creator", "Processing DAG definition", {
                    "root_node_type": dag_definition.get('node'),
                    "has_children": bool(dag_definition.get('children'))
                })
                
                # Convert frontend DAG definition to DeepEval DAG
                # This handles the recursive conversion of our Vue.js node structure
                # to DeepEval's node classes with proper parameter mapping
                root_node = self._create_dag_node(dag_definition, evaluation_params)
                dag = DeepAcyclicGraph(root_nodes=[root_node])
                
                eval_logger.info("metric_creator", "Created DAG structure", {
                    "root_node_type": type(root_node).__name__,
                    "total_nodes": self._count_nodes(dag_definition)
                })
                
                # Create DAGMetric with appropriate threshold
                # Note: Frontend uses 0-1 scores, DeepEval uses 0-10 internally,
                # but final metric score is returned as 0-1
                dag_metric = DAGMetric(
                    name=self.metric.name,
                    dag=dag,
                    model=judge_model,
                    threshold=getattr(self.metric, 'threshold', 0.5)  # Use metric threshold or default
                )
                
                eval_logger.decision("metric_creator", "DAG metric created successfully", {
                    "metric_name": self.metric.name,
                    "threshold": getattr(self.metric, 'threshold', 0.5)
                })
                
                return dag_metric
            
            case _:
                eval_logger.decision("metric_creator", "Unknown metric type encountered", {
                    "metric_type": self.metric.type,
                    "error": f"Unknown metric type: {self.metric.type}"
                })
                raise ValueError(f"Unknown metric type: {self.metric.type}")

    def _get_metric_params(self):
        eval_logger.info("metric_creator", "Determining metric parameters")
        evaluation_params = []
        for param in self.metric.param:
            match param:
                case 'input':
                    evaluation_params.append(LLMTestCaseParams.INPUT)
                    eval_logger.debug("metric_creator", "Added INPUT parameter")
                case 'actual_output':
                    evaluation_params.append(LLMTestCaseParams.ACTUAL_OUTPUT)
                    eval_logger.debug("metric_creator", "Added ACTUAL_OUTPUT parameter")
                case 'expected_output':
                    evaluation_params.append(LLMTestCaseParams.EXPECTED_OUTPUT)
                    eval_logger.debug("metric_creator", "Added EXPECTED_OUTPUT parameter")
                case 'context':
                    evaluation_params.append(LLMTestCaseParams.CONTEXT)
                    eval_logger.debug("metric_creator", "Added CONTEXT parameter")
                case _:
                    eval_logger.debug("metric_creator", f"Unknown parameter ignored: {param}")
        
        eval_logger.decision("metric_creator", "Final evaluation parameters determined", {
            "total_params": len(evaluation_params),
            "param_types": [str(param) for param in evaluation_params],
            "original_params": self.metric.param
        })
        
        return evaluation_params

    def _create_dag_node(self, node_definition, evaluation_params):
        """
        Recursively create DAG nodes from frontend definition.
        
        Converts our Vue.js DAG node structure to DeepEval node classes:
        - tasknode -> TaskNode
        - binaryjudge -> BinaryJudgementNode  
        - nonbinaryjudge -> NonBinaryJudgementNode
        - verdict -> VerdictNode (string verdict)
        - boolverdict -> VerdictNode (boolean verdict)
        
        Args:
            node_definition (dict): Frontend node definition with structure:
                {
                    "node": "tasknode|binaryjudge|nonbinaryjudge|verdict|boolverdict",
                    "instructions": "..." (for tasknode),
                    "criteria": "..." (for judge nodes),
                    "outputLabel": "..." (for non-verdict nodes),
                    "verdict": str|bool (for verdict nodes),
                    "score": float 0-1 (for verdict nodes),
                    "children": [...]
                }
            evaluation_params (List[LLMTestCaseParams]): Global evaluation parameters
            
        Returns:
            BaseNode: Appropriate DeepEval node instance
            
        Raises:
            ValueError: If node type is unknown or validation fails
        """
        eval_logger.debug("metric_creator", "Creating DAG node", {
            "node_type": node_definition.get('node'),
            "has_children": bool(node_definition.get('children'))
        })
        
        node_type = node_definition.get('node')
        children = node_definition.get('children', [])
        
        # Recursively create children first
        child_nodes = []
        for child_def in children:
            child_node = self._create_dag_node(child_def, evaluation_params)
            child_nodes.append(child_node)
        
        match node_type:
            case "tasknode":
                eval_logger.debug("metric_creator", "Creating TaskNode", {
                    "instructions": node_definition.get('instructions', ''),
                    "output_label": node_definition.get('outputLabel', ''),
                    "children_count": len(child_nodes)
                })
                
                # TaskNode processes data and passes results to children
                # Uses outputLabel as both output_label and label for consistency
                return TaskNode(
                    instructions=node_definition.get('instructions', ''),
                    output_label=node_definition.get('outputLabel', ''),
                    children=child_nodes,
                    evaluation_params=evaluation_params,  # Global params for all nodes
                    label=node_definition.get('label', node_definition.get('outputLabel', ''))
                )
            
            case "binaryjudge":
                eval_logger.debug("metric_creator", "Creating BinaryJudgementNode", {
                    "criteria": node_definition.get('criteria', ''),
                    "children_count": len(child_nodes)
                })
                
                # Validate that we have exactly 2 verdict children
                if len(child_nodes) != 2:
                    raise ValueError(f"BinaryJudgementNode must have exactly 2 children, got {len(child_nodes)}")
                
                return BinaryJudgementNode(
                    criteria=node_definition.get('criteria', ''),
                    children=child_nodes,
                    evaluation_params=evaluation_params,
                    label=node_definition.get('outputLabel', '')
                )
            
            case "nonbinaryjudge":
                eval_logger.debug("metric_creator", "Creating NonBinaryJudgementNode", {
                    "criteria": node_definition.get('criteria', ''),
                    "children_count": len(child_nodes)
                })
                
                if len(child_nodes) == 0:
                    raise ValueError("NonBinaryJudgementNode must have at least 1 child")
                
                return NonBinaryJudgementNode(
                    criteria=node_definition.get('criteria', ''),
                    children=child_nodes,
                    evaluation_params=evaluation_params,
                    label=node_definition.get('outputLabel', '')
                )
            
            case "verdict":
                eval_logger.debug("metric_creator", "Creating VerdictNode (string)", {
                    "verdict": node_definition.get('verdict', ''),
                    "has_score": 'score' in node_definition,
                    "has_children": len(child_nodes) > 0
                })
                
                # Only include score if explicitly provided in frontend definition
                verdict_kwargs = {
                    "verdict": str(node_definition.get('verdict', ''))
                }
                
                eval_logger.debug("metric_creator", "VerdictNode decision logic", {
                    "has_explicit_score": 'score' in node_definition,
                    "child_nodes_count": len(child_nodes),
                    "will_use_score": 'score' in node_definition,
                    "will_use_child": 'score' not in node_definition and len(child_nodes) > 0
                })
                
                if 'score' in node_definition:
                    # Convert score from 0-1 range to 0-10 range for DeepEval
                    frontend_score = node_definition.get('score')
                    deepeval_score = int(frontend_score * 10) if isinstance(frontend_score, (int, float)) else 0
                    verdict_kwargs['score'] = deepeval_score
                    eval_logger.debug("metric_creator", "Using score for VerdictNode", {"score": deepeval_score})
                elif len(child_nodes) > 0:
                    # If no score but has children, this is an intermediate VerdictNode
                    verdict_kwargs['child'] = child_nodes[0]  # VerdictNode can only have one child
                    eval_logger.debug("metric_creator", "Using child for VerdictNode", {"child_type": type(child_nodes[0]).__name__})
                else:
                    raise ValueError("VerdictNode must have either a score or a child")
                
                return VerdictNode(**verdict_kwargs)
            
            case "boolverdict":
                eval_logger.debug("metric_creator", "Creating VerdictNode (boolean)", {
                    "verdict": node_definition.get('verdict', False),
                    "has_score": 'score' in node_definition,
                    "has_children": len(child_nodes) > 0,
                    "explicit_score": node_definition.get('score') if 'score' in node_definition else None
                })
                
                # Only include score if explicitly provided in frontend definition
                verdict_kwargs = {
                    "verdict": bool(node_definition.get('verdict', False))
                }
                
                eval_logger.debug("metric_creator", "VerdictNode decision logic", {
                    "has_explicit_score": 'score' in node_definition,
                    "child_nodes_count": len(child_nodes),
                    "will_use_score": 'score' in node_definition,
                    "will_use_child": 'score' not in node_definition and len(child_nodes) > 0
                })
                
                if 'score' in node_definition:
                    # Convert score from 0-1 range to 0-10 range for DeepEval
                    frontend_score = node_definition.get('score')
                    deepeval_score = int(frontend_score * 10) if isinstance(frontend_score, (int, float)) else 0
                    verdict_kwargs['score'] = deepeval_score
                    eval_logger.debug("metric_creator", "Using score for VerdictNode", {"score": deepeval_score})
                elif len(child_nodes) > 0:
                    # If no score but has children, this is an intermediate VerdictNode
                    verdict_kwargs['child'] = child_nodes[0]  # VerdictNode can only have one child
                    eval_logger.debug("metric_creator", "Using child for VerdictNode", {"child_type": type(child_nodes[0]).__name__})
                else:
                    raise ValueError("VerdictNode must have either a score or a child")
                
                return VerdictNode(**verdict_kwargs)
            
            case _:
                eval_logger.decision("metric_creator", "Unknown DAG node type", {
                    "node_type": node_type,
                    "available_types": ["tasknode", "binaryjudge", "nonbinaryjudge", "verdict", "boolverdict"],
                    "error": f"Unknown DAG node type: {node_type}"
                })
                raise ValueError(f"Unknown DAG node type: {node_type}")

    def _count_nodes(self, node_definition):
        """
        Count total number of nodes in the DAG
        """
        if not node_definition:
            return 0
        
        count = 1  # Count current node
        children = node_definition.get('children', [])
        
        for child in children:
            count += self._count_nodes(child)
        
        return count
