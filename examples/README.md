# ZVec PHP Examples

This directory contains example scripts demonstrating various features of the ZVec PHP library.

## Embedding Functions Examples

### `embeddings_basic.php`
Basic usage of embedding functions without ZVec collection.

Demonstrates:
- Single text embedding
- Batch embedding
- Cosine similarity comparison
- Mock embedding function implementation
- API usage examples (commented)

**Run:**
```bash
php examples/embeddings_basic.php
```

### `embeddings_with_zvec.php`
Full integration of embedding functions with ZVec vector database.

Demonstrates:
- Creating a collection with vector field
- Generating embeddings for documents
- Storing documents with embeddings
- Similarity search using vector queries
- Filtered vector search (category + vector)
- Using HNSW index for efficient search

**Run:**
```bash
php examples/embeddings_with_zvec.php
```

**Note:** This example uses a mock embedding function for demonstration. To use real embeddings:

```php
require_once 'php/embeddings.php';

// OpenAI
$embedder = new OpenAIDenseEmbedding(
    apiKey: 'sk-your-openai-api-key',
    model: OpenAIDenseEmbedding::MODEL_SMALL  // 1536 dimensions
);

// Or DashScope/Qwen
$embedder = new QwenDenseEmbedding(
    apiKey: 'your-dashscope-api-key',
    model: QwenDenseEmbedding::MODEL_V4  // 1792 dimensions
);

// Generate embedding
$vector = $embedder->embed('Your text here');

// Use with ZVec
$doc = new ZVecDoc('doc_id');
$doc->setVectorFp32('embedding', $vector);
$collection->insert($doc);
```

## Available Embedding Classes

### Dense Embeddings

| Class | API | Models | Dimensions |
|-------|-----|--------|------------|
| `OpenAIDenseEmbedding` | OpenAI | text-embedding-3-small | 1536 |
| | | text-embedding-3-large | 3072 |
| | | text-embedding-ada-002 | 1536 |
| `QwenDenseEmbedding` | DashScope | text-embedding-v4 | 1792 |
| | | text-embedding-v3 | 1024 |
| | | text-embedding-v2 | 1536 |
| | | text-embedding-v1 | 1536 |

### Usage

```php
require_once 'php/embeddings.php';

// Single embed
$vector = $embedder->embed('Hello world');

// Batch embed
$vectors = $embedder->embedBatch(['Text 1', 'Text 2', 'Text 3']);

// Get dimension
echo $embedder->getDimension();  // e.g., 1536
```

## Other Examples

Check the main example file for comprehensive ZVec usage:

```bash
php php/example.php
```

This demonstrates:
- Collection creation and management
- Schema definition
- Document insertion
- Vector queries
- Index creation
- And more...
