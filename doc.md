# LLM Eval Judge - REST API Documentation

## Overview

The LLM Eval Judge system provides a **REST-ful API** based on Symfony, designed for managing Machine Learning Model evaluations. The API follows REST principles and offers complete CRUD operations for all main resources.

## API Architecture

### REST Compliance

The API is fully RESTful and implements:

- **Resource-based URLs** (`/api/{resource}` and `/api/{resource}/{id}`)
- **HTTP verbs** for different operations (GET, POST, PUT, DELETE)
- **Uniform response formats** (JSON)
- **HTTP status codes** for success/error communication
- **Pagination** for list endpoints
- **Filtering and search functions**

### Base Controller

All API controllers inherit from `BaseApiController`, which provides common functionalities:

- JSON response formatting
- Validation
- Pagination
- Error handling
- Entity management

## API Endpoints

### 1. Providers (LLM Providers)

**Base URL:** `/api/providers`

| HTTP Method | Endpoint              | Description                          |
| ----------- | --------------------- | ------------------------------------ |
| GET         | `/api/providers`      | List all providers (with pagination) |
| GET         | `/api/providers/{id}` | Retrieve single provider             |
| POST        | `/api/providers`      | Create new provider                  |
| PUT         | `/api/providers/{id}` | Update provider                      |
| DELETE      | `/api/providers/{id}` | Delete provider                      |

**Example Provider JSON:**

```json
{
  "id": 1,
  "name": "OpenAI",
  "apiKey": "sk-...",
  "apiUrl": "https://api.openai.com/v1"
}
```

### 2. Models (AI Models)

**Base URL:** `/api/models`

| HTTP Method | Endpoint           | Description                       |
| ----------- | ------------------ | --------------------------------- |
| GET         | `/api/models`      | List all models (with pagination) |
| GET         | `/api/models/{id}` | Retrieve single model             |
| POST        | `/api/models`      | Create new model                  |
| PUT         | `/api/models/{id}` | Update model                      |
| DELETE      | `/api/models/{id}` | Delete model                      |

**Example Model JSON:**

```json
{
  "id": 1,
  "name": "GPT-4",
  "provider": {
    "id": 1,
    "name": "OpenAI"
  },
  "createdAt": "2024-01-01T10:00:00Z",
  "updatedAt": "2024-01-01T10:00:00Z"
}
```

### 3. Test Cases

**Base URL:** `/api/test-cases`

| HTTP Method | Endpoint               | Description                           |
| ----------- | ---------------------- | ------------------------------------- |
| GET         | `/api/test-cases`      | List all test cases (with pagination) |
| GET         | `/api/test-cases/{id}` | Retrieve single test case             |
| POST        | `/api/test-cases`      | Create new test case                  |
| PUT         | `/api/test-cases/{id}` | Update test case                      |
| DELETE      | `/api/test-cases/{id}` | Delete test case                      |

### 4. Metrics (Evaluation Metrics)

**Base URL:** `/api/metrics`

| HTTP Method | Endpoint             | Description                                   |
| ----------- | -------------------- | --------------------------------------------- |
| GET         | `/api/metrics`       | List all metrics (with pagination and search) |
| GET         | `/api/metrics/{id}`  | Retrieve single metric                        |
| POST        | `/api/metrics`       | Create new metric                             |
| PUT         | `/api/metrics/{id}`  | Update metric                                 |
| DELETE      | `/api/metrics/{id}`  | Delete metric                                 |
| GET         | `/api/metrics/types` | Available metric types                        |

**Search parameters:**

- `search`: Text search in name and description
- `limit`: Number of results (max. 100)

### 5. Prompts

**Base URL:** `/api/prompts`

| HTTP Method | Endpoint            | Description                        |
| ----------- | ------------------- | ---------------------------------- |
| GET         | `/api/prompts`      | List all prompts (with pagination) |
| GET         | `/api/prompts/{id}` | Retrieve single prompt             |
| POST        | `/api/prompts`      | Create new prompt                  |
| PUT         | `/api/prompts/{id}` | Update prompt                      |
| DELETE      | `/api/prompts/{id}` | Delete prompt                      |

**Filter parameters:**

- `testCase`: Filter prompts by Test Case ID

### 6. Results (Evaluation Results)

**Base URL:** `/api/results`

| HTTP Method | Endpoint            | Description                        |
| ----------- | ------------------- | ---------------------------------- |
| GET         | `/api/results`      | List all results (with pagination) |
| GET         | `/api/results/{id}` | Retrieve single result             |
| POST        | `/api/results`      | Create new result                  |
| PUT         | `/api/results/{id}` | Update result                      |
| DELETE      | `/api/results/{id}` | Delete result                      |

**Filter parameters:**

- `prompt`: Filter by Prompt ID
- `metric`: Filter by Metric ID
- `model`: Filter by Model ID
- `benchmark`: Filter by Benchmark ID

### 7. Benchmarks

**Base URL:** `/api/benchmarks`

| HTTP Method | Endpoint                       | Description                           |
| ----------- | ------------------------------ | ------------------------------------- |
| GET         | `/api/benchmarks`              | List all benchmarks (with pagination) |
| GET         | `/api/benchmarks/{id}`         | Retrieve single benchmark             |
| POST        | `/api/benchmarks`              | Create new benchmark                  |
| PUT         | `/api/benchmarks/{id}`         | Update benchmark                      |
| DELETE      | `/api/benchmarks/{id}`         | Delete benchmark                      |
| GET         | `/api/benchmarks/{id}/results` | Results of a benchmark                |

### 8. Settings (System Settings)

**Base URL:** `/api/settings`

| HTTP Method | Endpoint               | Description                             |
| ----------- | ---------------------- | --------------------------------------- |
| GET         | `/api/settings`        | All settings as key-value pairs         |
| GET         | `/api/settings/{name}` | Retrieve single setting                 |
| PUT         | `/api/settings/{name}` | Update setting                          |
| POST        | `/api/settings`        | Update multiple settings simultaneously |

**Example Settings Response:**

```json
{
  "app_name": "LLM Eval Judge",
  "max_concurrent_evaluations": "5",
  "default_timeout": "300"
}
```

### 9. Evaluation (Evaluation Logic)

**Base URL:** `/api/evaluation`

| HTTP Method | Endpoint          | Description                                     |
| ----------- | ----------------- | ----------------------------------------------- |
| GET         | `/api/evaluation` | Evaluation data with grouping and deduplication |

**Query parameters:**

- `group`: Group by model, metric, test_case, benchmark, prompt
- `dedupe`: Deduplication strategy (all, avg, latest)

## Pagination

All list endpoints support pagination:

**Parameters:**

- `page`: Page number (default: 1)
- `limit`: Number of elements per page (default: 50, maximum: 100)

**Response Format:**

```json
{
  "data": [...],
  "pagination": {
    "currentPage": 1,
    "totalPages": 10,
    "totalItems": 500,
    "itemsPerPage": 50
  }
}
```

## HTTP Status Codes

| Code | Description                                        |
| ---- | -------------------------------------------------- |
| 200  | OK - Successful GET request                        |
| 201  | Created - Resource successfully created            |
| 204  | No Content - Successful DELETE                     |
| 400  | Bad Request - Invalid request or validation errors |
| 404  | Not Found - Resource not found                     |
| 500  | Internal Server Error - Server error               |

## Serialization Groups

The API uses Symfony Serializer Groups for consistent JSON outputs:

- `api`: Standard API group for all endpoints
- `settings`: Special group for settings data
- `results`: Group for result data
- `metrics`: Group for metric data
- `benchmarks`: Group for benchmark data
- `test_cases`: Group for test case data
- `prompts`: Group for prompt data

## Validation

All POST and PUT endpoints use Symfony Validator for data validation. In case of validation errors, a 400 status with detailed error messages is returned:

```json
{
  "error": "Validation failed",
  "violations": {
    "name": "This field is required",
    "email": "This is not a valid email address"
  }
}
```

## Authentication

_Note: Based on the code, the API currently does not seem to implement authentication. This should be added for production environments._

## Frontend Integration

The API is consumed by a JavaScript `ApiService` that provides a uniform interface for frontend components. The service abstracts:

- HTTP requests
- Error handling
- Pagination
- CRUD operations for all resources

## Conclusion

The LLM Eval Judge API is a fully RESTful implemented API that follows all common REST principles. It offers:

✅ **Resource-oriented URLs**  
✅ **HTTP verbs for CRUD operations**  
✅ **Uniform JSON responses**  
✅ **Proper HTTP status codes**  
✅ **Pagination and filtering**  
✅ **Validation and error handling**  
✅ **Consistent API structure through Base Controller**

The API is well-structured and follows modern Symfony best practices for REST API development.
