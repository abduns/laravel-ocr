<?php

namespace Dunn\LaravelOcr\Events;

final class OcrFailed
{
    public function __construct(
        public readonly string $jobId,
        public readonly \Throwable $exception,
    ) {}
}
