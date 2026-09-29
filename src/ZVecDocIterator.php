<?php

declare(strict_types=1);

namespace CrazyGoat\ZVec;

use FFI;

if (extension_loaded('zvec')) return;

/**
 * Forward-only iterator over every document of a collection.
 *
 * Backed by an isolated snapshot taken when {@see ZVec::iterDocs()} was called,
 * so documents written afterwards are not visible to it. Created by
 * {@see ZVec::iterDocs()}; use it with foreach:
 *
 *     foreach ($collection->iterDocs() as $pk => $doc) { ... }
 *
 * While an iterator is open, `close()`, `destroy()`, schema DDL and
 * `optimize()` on the collection throw ZVecException with code 5
 * (FAILED_PRECONDITION) and the collection stays open and usable. Writes, flush
 * and queries are unaffected. The iterator closes itself once exhausted, so a
 * completed foreach needs no cleanup; call {@see close()} explicitly when
 * breaking out early.
 *
 * @implements \Iterator<string, ZVecDoc>
 */
class ZVecDocIterator implements \Iterator
{
    private ?FFI\CData $handle;
    private ZVec $collection;
    private ?ZVecDoc $current = null;
    private int $position = -1;
    private bool $closed = false;

    /**
     * @internal Create through {@see ZVec::iterDocs()}.
     */
    public function __construct(ZVec $collection, FFI\CData $handle)
    {
        $this->collection = $collection;
        $this->handle = $handle;
    }

    public function __destruct()
    {
        try {
            $this->close();
        } catch (\Throwable) {
            // A destructor must not throw; the handle is released by the
            // parent's teardown if this fails.
        }
    }

    private function __clone()
    {
    }

    /**
     * Releases the snapshot and lets the collection close or run DDL again.
     *
     * Idempotent. Called automatically when the iterator is exhausted.
     */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        ZVec::ffi()->zvec_doc_iterator_free($this->handle);
        $this->handle = null;
        $this->current = null;
        $this->closed = true;
    }

    /** Whether the native handle has already been released. */
    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * @throws ZVecException If the iterator has already been advanced
     */
    public function rewind(): void
    {
        if ($this->position > 0) {
            throw new ZVecException(
                'ZVecDocIterator is forward-only and cannot be rewound; call ZVec::iterDocs() again'
            );
        }
        if ($this->position < 0) {
            $this->fetchNext();
            $this->position = 0;
        }
    }

    public function valid(): bool
    {
        if ($this->position < 0) {
            $this->rewind();
        }
        return $this->current !== null;
    }

    /** @throws ZVecException If the iterator is not positioned on a document */
    public function current(): ZVecDoc
    {
        if ($this->position < 0) {
            $this->rewind();
        }
        if ($this->current === null) {
            throw new ZVecException('No current document');
        }
        return $this->current;
    }

    /** @throws ZVecException If the iterator is not positioned on a document */
    public function key(): string
    {
        return $this->current()->getPk();
    }

    public function next(): void
    {
        if ($this->position < 0) {
            $this->rewind();
            return;
        }
        $this->fetchNext();
        $this->position++;
    }

    /**
     * Advances to the next document, closing the iterator at end of iteration.
     *
     * @throws ZVecException On FFI error
     */
    private function fetchNext(): void
    {
        if ($this->closed) {
            $this->current = null;
            return;
        }
        $ffi = ZVec::ffi();
        $out = $ffi->new('zvec_doc_t');
        try {
            ZVec::checkStatus($ffi->zvec_doc_iterator_next($this->handle, FFI::addr($out)));
        } catch (ZVecException $e) {
            $this->close();
            throw $e;
        }
        if (FFI::isNull($out)) {
            $this->close();
            $this->current = null;
            return;
        }
        $this->current = new ZVecDoc($out, true);
    }
}
