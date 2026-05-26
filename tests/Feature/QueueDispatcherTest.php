<?php

namespace Dunn\LaravelOcr\Tests\Feature;

use Dunn\LaravelOcr\Builders\QueueDispatcher;
use Dunn\LaravelOcr\Facades\Ocr;
use Dunn\LaravelOcr\Tests\TestCase;

class QueueDispatcherTest extends TestCase
{
    public function test_on_queue_returns_dispatcher(): void
    {
        $dispatcher = Ocr::image('/tmp/x.png')->language('eng')->onQueue('ocr-jobs');
        $this->assertInstanceOf(QueueDispatcher::class, $dispatcher);
    }

    public function test_on_queue_rejects_empty_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Ocr::image('/tmp/x.png')->onQueue('');
    }

    public function test_on_queue_rejects_whitespace_only(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Ocr::image('/tmp/x.png')->onQueue('   ');
    }
}
