<?php

namespace Dunn\LaravelOcr\Tests\Unit;

use Dunn\LaravelOcr\Support\JobIdGenerator;
use Dunn\LaravelOcr\Support\TempPathFactory;
use PHPUnit\Framework\TestCase;

class SupportTest extends TestCase
{
    public function test_job_id_generator_produces_unique_ids(): void
    {
        $gen = new JobIdGenerator;
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = $gen->next();
        }

        $this->assertCount(100, array_unique($ids));
    }

    public function test_job_id_format(): void
    {
        $gen = new JobIdGenerator;
        $id = $gen->next();

        $this->assertStringStartsWith('ocr-', $id);
        $this->assertSame(32, strlen($id)); // 'ocr-' (4) + 28 hex chars
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $id);
    }

    public function test_temp_path_factory_produces_unique_paths(): void
    {
        $factory = new TempPathFactory('local', 'ocr/tmp');
        $paths = [];
        for ($i = 0; $i < 100; $i++) {
            $paths[] = $factory->unique('png', 'p1');
        }

        $this->assertCount(100, array_unique($paths));
    }

    public function test_temp_path_factory_includes_prefix_and_extension(): void
    {
        $factory = new TempPathFactory('local', 'ocr/tmp');
        $path = $factory->unique('png', 'src');

        $this->assertStringStartsWith('ocr/tmp/src-', $path);
        $this->assertStringEndsWith('.png', $path);
    }

    public function test_temp_path_factory_get_disk_name(): void
    {
        $factory = new TempPathFactory('local', 'ocr/tmp');
        $this->assertSame('local', $factory->getDiskName());
    }
}
