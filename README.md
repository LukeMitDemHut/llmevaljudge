> ⚠️ **Early Beta Notice**
>
> This platform is currently in **early beta** and may contain bugs. Features, APIs, and architecture are subject to change. It is intended for **local testing, experimentation, and research use only**. Not production-ready. Use with caution and do not expose to the public internet.
> Contribution welcome!


# LLM Evaluation Platform

A comprehensive platform for evaluating Large Language Models (LLMs) using LLM-as-a-Judge methodology. This platform enables sophisticated evaluation of model outputs through custom criteria, advanced reasoning metrics, and real-time web-augmented verification.

## Purpose

Traditional LLM evaluation relies on simple string matching or basic metrics that fail to capture the nuanced qualities of AI-generated content. This platform addresses this limitation by implementing **LLM-as-a-Judge** evaluation, where one LLM evaluates the outputs of another based on sophisticated criteria.

### Why LLM-as-a-Judge?

- **Nuanced Assessment**: Evaluate qualities like coherence, style, safety, and domain-specific requirements
- **Flexible Criteria**: Define custom evaluation criteria without needing reference answers
- **Human-like Reasoning**: Leverage LLM reasoning capabilities for more accurate assessments
- **Scalable Evaluation**: Automate complex evaluation tasks that would require human experts

### Key Use Cases

- **Model Comparison**: Compare performance across different LLMs on specific tasks
- **Quality Assurance**: Validate model outputs before deployment
- **Fine-tuning Guidance**: Identify strengths and weaknesses to guide model improvements
- **Domain-specific Evaluation**: Create specialized benchmarks for specific industries or use cases

## Features

### 🎯 Multiple Evaluation Methodologies

- **G-Eval Metrics**: Flexible criteria-based or step-based evaluation
- **DAG Metrics**: Complex decision-tree evaluation with deterministic scoring paths
- **TALE Metrics**: Tool-Augmented LLM Evaluation with real-time web search verification

### 🔧 Comprehensive Management

- **Provider Management**: Support for any OpenAI-compatible API (OpenAI, Anthropic, local models)
- **Model Configuration**: Define both target models (to test) and judge models (for evaluation)
- **Test Case Creation**: Build comprehensive test suites with prompts, context, and expected outputs
- **Benchmark Orchestration**: Combine models, test cases, and metrics into complete evaluation suites

### 📊 Advanced Analysis

- **Result Visualization**: Comprehensive performance analysis and model comparison
- **Automatic Deduplication**: Intelligent handling of overlapping benchmark results
- **Detailed Reasoning**: Access full evaluation reasoning for transparency and debugging
- **Performance Tracking**: Monitor evaluation progress and costs

### 🔍 TALE: Web-Augmented Evaluation

The platform includes a unique **TALE (Tool-Augmented LLM Evaluation)** metric that:

- Performs real-time web searches to verify factual claims
- Synthesizes evidence from multiple sources
- Provides fact-checking without requiring reference answers
- Supports multiple search engines with automatic fallback

## Technical Architecture

### Core Components

- **Web Application**: Symfony 7.3 with Vue.js frontend for intuitive management
- **Evaluation Engine**: Python FastAPI service using DeepEval framework
- **Search Engine**: SearXNG for web-augmented evaluation capabilities
- **Database**: MariaDB for storing models, metrics, test cases, and results
- **Message Queue**: Symfony Messenger for asynchronous benchmark execution

### Supported Providers

The platform supports any service following OpenAI API standards:

- OpenAI (GPT-4, GPT-3.5, etc.)
- Anthropic Claude (via compatible endpoints)
- Local models (Ollama, LM Studio, etc.)
- Custom API endpoints

### Evaluation Metrics

#### G-Eval Metrics

- **Criteria-based**: Define evaluation criteria in natural language
- **Step-based**: Break down evaluation into specific reasoning steps
- **Use cases**: Quality assessment, style evaluation, coherence checking

#### DAG Metrics

- **Task Nodes**: Data processing and transformation
- **Judgment Nodes**: Binary and multi-path decision making
- **Verdict Nodes**: Final scoring with configurable thresholds
- **Use cases**: Complex validation, format checking, multi-step reasoning

#### TALE Metrics

- **Web Search Integration**: Real-time information retrieval
- **Evidence Synthesis**: Automatic evidence collection and summarization
- **Iterative Refinement**: Multi-round search and reflection
- **Use cases**: Factual verification, current events, knowledge validation

## Installation

### Prerequisites

- Docker and Docker Compose
- Git

### Setup

1. **Clone the repository**:

   ```bash
   git clone <repository-url>
   cd llmevaljudge
   ```

2. **Configure environment**:

   ```bash
   cp .env.example .env
   cp site/.env.example site/.env
   ```

   Edit both `.env` files to configure:

   - Database credentials
   - Port mappings (it is recommended to keep port mappings as is since internal parts depend on it)
   - Timezone settings

3. **Setup the project**:

   ```bash
   chmod +x ./dev
   ./dev setup
   ```

4. **Start the platform**:

   ```bash
   ./dev up
   ```

5. **Access the application**:
   - Web Interface: `http://localhost` (or configured WEB_PORT)
   - phpMyAdmin: `http://localhost:8081` (or configured PHPMYADMIN_PORT)
   - Search Engine: `http://localhost:8888` (or configured SEARCH_PORT)

## Usage

### Quick Start

1. **Configure Providers**: Add your API keys and endpoints in Settings → Providers
2. **Set up Models**: Define your target and judge models in Settings → Models
3. **Create Metrics**: Design evaluation criteria using G-Eval, DAG, or TALE metrics
4. **Build Test Cases**: Create prompts and scenarios for testing
5. **Run Benchmarks**: Combine everything into comprehensive evaluation suites
6. **Analyze Results**: Review detailed performance analysis and model comparisons

### Container Management

- **Start containers**: `./dev up`
- **Stop containers**: `./dev down`
- **Restart containers**: `./dev restart`
- **View logs**: `./dev logs`

### Development Commands

- **Interactive shell**: `./dev` (opens bash in web container)
- **Symfony commands**: `./dev console <command>`
- **Run tests**: `./dev test` or `./dev phpunit`
- **Build assets**: `./dev build`

### Example Workflow

```bash
# Start the platform
./dev up

# Access the web interface and:
# 1. Add OpenAI provider with your API key
# 2. Configure GPT-4 as judge model, GPT-3.5 as target model
# 3. Create a G-Eval metric for "response helpfulness"
# 4. Add test cases with customer service scenarios
# 5. Run benchmark to compare model performance
# 6. Analyze results with automatic deduplication
```

## Configuration

### Environment Variables

Key configuration options in `.env`:

```bash
# Port Configuration (leave as is)
WEB_PORT=80              # Web application port
DB_PORT=3306             # Database port
PHPMYADMIN_PORT=8081     # phpMyAdmin port
SEARCH_PORT=8888         # Search engine port

# Database (change)
MYSQL_ROOT_PASSWORD=changeme

# Timezone
TZ=Europe/Berlin
```

### Security Notice

⚠️ **Important**: This application is designed for local development and research use only.

- No authentication or authorization is implemented
- Database and API endpoints are not secured
- Do not expose this application to the internet
- Use only in trusted, isolated environments

## API Documentation

The evaluation engine exposes a REST API for programmatic access:

- **Evaluation Endpoint**: `POST http://localhost:5000/`
- **Health Check**: `GET http://localhost:5000/health`

Example evaluation request:

```json
{
  "prompt": {
    "input": "What is the capital of France?",
    "expected_output": "Paris",
    "context": ""
  },
  "metric": {
    "name": "Accuracy Check",
    "type": "g-eval",
    "definition": { "criteria": "Check if the answer is factually correct" }
  },
  "model": {
    "name": "gpt-4",
    "url": "https://api.openai.com/v1",
    "key": "your-api-key"
  }
}
```

## Architecture Details

### Service Architecture

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Web App       │    │  Evaluation      │    │  Search Engine  │
│   (Symfony)     │◄──►│  Engine          │◄──►│   (SearXNG)     │
│                 │    │  (FastAPI)       │    │                 │
└─────┬───────────┘    └──────────────────┘    └─────────────────┘
      │
      ▼
┌─────────────────┐    ┌──────────────────┐
│   Database      │    │  Message Queue   │
│   (MariaDB)     │    │  (Symfony        │
│                 │    │   Messenger)     │
└─────────────────┘    └──────────────────┘
```

### Data Flow

1. **Benchmark Creation**: Web interface defines models, metrics, and test cases
2. **Queue Processing**: Symfony Messenger handles asynchronous benchmark execution
3. **Evaluation Request**: FastAPI service receives evaluation tasks
4. **LLM Interaction**: Evaluation engine calls configured model APIs
5. **Result Storage**: Scores and reasoning stored in database
6. **Analysis**: Web interface provides visualization and deduplication

## Contributing

This project is open for contributions. Key areas for development:

- Additional evaluation metrics
- Enhanced visualization capabilities
- Performance optimizations
- Security improvements for production use

## Thanks

Special thanks to the [DeepEval](https://github.com/confident-ai/deepeval) project for providing the core evaluation framework used in this platform.

## License

This project is provided for research and educational use. See license file for details.

## Support

For detailed usage instructions, visit the platform's built-in manual at the home page after starting the application.
