# Extensions: Embedding Functions

## Priority: LOW

## Status: DONE

## Implementation

### Files Created
- `php/embeddings.php` - Main loader file
- `php/embeddings/EmbeddingInterfaces.php` - Interface definitions:
  - `DenseEmbeddingFunction` - Interface for dense embeddings
  - `SparseEmbeddingFunction` - Interface for sparse embeddings  
  - `ApiEmbeddingFunction` - Base class for API-based implementations
- `php/embeddings/OpenAIDenseEmbedding.php` - OpenAI API implementation
  - Supports text-embedding-3-small, text-embedding-3-large, text-embedding-ada-002
  - Batch embedding support (up to 2048 inputs)
  - Customizable dimensions for v3 models
- `php/embeddings/QwenDenseEmbedding.php` - DashScope API implementation
  - Supports text-embedding-v4, v3, v2, v1
  - Batch embedding support (up to 25 inputs)

### Tests Created
- `tests/test_embeddings_interfaces.phpt` - Validates interface structure
- `tests/test_embeddings_integration.phpt` - Integration tests with mock embedding

### Examples Created
- `examples/embeddings_basic.php` - Basic embedding usage with mock
  - Single and batch embedding
  - Similarity comparison
  - Usage examples for real APIs
  
- `examples/embeddings_with_zvec.php` - Full ZVec integration
  - Creating collection with vector field
  - Generating and storing embeddings
  - Similarity search
  
- `examples/README.md` - Documentation for all examples

### Usage Example
```php
require_once __DIR__ . '/php/embeddings.php';

// OpenAI embedding
$embedder = new OpenAIDenseEmbedding(
    apiKey: 'sk-your-key',
    model: OpenAIDenseEmbedding::MODEL_SMALL
);
$vector = $embedder->embed('Hello world');

// Batch embedding
$vectors = $embedder->embedBatch(['Text 1', 'Text 2', 'Text 3']);

// Qwen/DashScope embedding
$embedder = new QwenDenseEmbedding(
    apiKey: 'sk-your-key',
    model: QwenDenseEmbedding::MODEL_V4
);
```

### Running Examples
```bash
# Basic embedding example
php examples/embeddings_basic.php

# ZVec integration example
php examples/embeddings_with_zvec.php
```

## Description

PHP SDK provides embedding function interfaces and implementations for converting text to vectors. These call external APIs or run local models — no zvec C++ dependency.

## Embedding functions in Python SDK

### Dense embeddings
| Class | Backend | Notes |
|-------|---------|-------|
| `OpenAIDenseEmbedding` | OpenAI API | text-embedding-3-small/large |
| `QwenDenseEmbedding` | DashScope API | text-embedding-v4 |

### Sparse embeddings
| Class | Backend | Notes |
|-------|---------|-------|
| `BM25EmbeddingFunction` | DashText | BM25 sparse vectors |
| `QwenSparseEmbedding` | DashScope API | API-based sparse |
| `DefaultLocalSparseEmbedding` | Sentence Transformers | SPLADE (local) |

## Notes
- All implementations are HTTP API calls - no C++ FFI needed
- Local models (Sentence Transformers) not implemented - would require ONNX Runtime
- Sparse embeddings (BM25) not implemented - would need PHP BM25 library
- Tested and passing: tests/test_embeddings_interfaces.phpt, tests/test_embeddings_integration.phpt
