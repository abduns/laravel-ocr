<?php

namespace Dunn\LaravelOcr\Support;

final class JobIdGenerator
{
    public function next(): string
    {
        return 'ocr-'.bin2hex(random_bytes(14));
    }
}
