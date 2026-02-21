# Extensions: Embedding Functions

## Priority: LOW

## Status: TODO

## Description

Python SDK provides embedding function interfaces and implementations for converting text to vectors. These call external APIs or run local models — no zvec C++ dependency.

## Embedding functions in Python SDK

### Dense embeddings
| Class | Backend | Notes |
|-------|---------|-------|
| `OpenAIDenseEmbedding` | OpenAI API | text-embedding-3-small/large |
| `QwenDenseEmbedding` | DashScope API | text-embedding-v4 |
| `DefaultLocalDenseEmbedding` | Sentence Transformers | all-MiniLM-L6-v2 (local) |

### Sparse embeddings
| Class | Backend | Notes |
|-------|---------|-------|
| `BM25EmbeddingFunction` | DashText | BM25 sparse vectors |
| `QwenSparseEmbedding` | DashScope API | API-based sparse |
| `DefaultLocalSparseEmbedding` | Sentence Transformers | SPLADE (local) |

### Rerankers (API-based)
| Class | Backend | Notes |
|-------|---------|-------|
| `QwenReRanker` | DashScope API | Cross-encoder reranking |
| `DefaultLocalReRanker` | Sentence Transformers | Local cross-encoder |

## Changes needed

### PHP implementation
- Define interface `DenseEmbeddingFunction` with `embed(string $input): array`
- Define interface `SparseEmbeddingFunction` with `embed(string $input): array`
- Implement `OpenAIDenseEmbedding` using PHP HTTP client (curl/Guzzle)
- Implement `QwenDenseEmbedding` using DashScope HTTP API
- BM25 would need a PHP BM25 library or custom implementation

### Notes
- All of these are HTTP API calls or local model inference
- No C++ FFI needed
- OpenAI embedding is the most useful for PHP users
- Local models (Sentence Transformers) don't have PHP equivalents — skip or use ONNX Runtime
- Consider implementing OpenAI embedding first as it's most commonly used
- Out of scope if we want to keep the library focused on the FFI binding
