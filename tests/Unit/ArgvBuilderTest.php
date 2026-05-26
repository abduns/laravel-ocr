<?php

namespace Dunn\LaravelOcr\Tests\Unit;

use Dunn\LaravelOcr\Support\ArgvBuilder;
use Dunn\LaravelOcr\Support\BuilderState;
use PHPUnit\Framework\TestCase;

class ArgvBuilderTest extends TestCase
{
    public function test_builds_basic_argv(): void
    {
        $state = new BuilderState(kind: 'image', source: '/tmp/img.png', languages: ['eng'], psm: 3, oem: 3, timeout: 120);

        $argv = ArgvBuilder::buildImage('/usr/bin/tesseract', $state, '/tmp/img.png', null);

        $this->assertSame([
            '/usr/bin/tesseract',
            '/tmp/img.png',
            'stdout',
            '-l', 'eng',
            '--psm', '3',
            '--oem', '3',
        ], $argv);
    }

    public function test_includes_tessdata_dir_when_set(): void
    {
        $state = new BuilderState(kind: 'image', source: '/tmp/img.png', languages: ['eng'], psm: 6, oem: 1, timeout: 60);

        $argv = ArgvBuilder::buildImage('/usr/bin/tesseract', $state, '/tmp/img.png', '/usr/share/tessdata');

        $this->assertSame([
            '/usr/bin/tesseract',
            '/tmp/img.png',
            'stdout',
            '-l', 'eng',
            '--psm', '6',
            '--oem', '1',
            '--tessdata-dir', '/usr/share/tessdata',
        ], $argv);
    }

    public function test_joins_multiple_languages_with_plus(): void
    {
        $state = new BuilderState(kind: 'image', source: '/tmp/img.png', languages: ['eng', 'ind', 'jpn'], psm: 3, oem: 3, timeout: 120);

        $argv = ArgvBuilder::buildImage('/usr/bin/tesseract', $state, '/tmp/img.png', null);

        $this->assertSame('eng+ind+jpn', $argv[4]);
    }

    public function test_deduplicates_languages_preserving_order(): void
    {
        $result = ArgvBuilder::dedupePreservingOrder(['eng', 'ind', 'eng', 'jpn', 'ind']);

        $this->assertSame(['eng', 'ind', 'jpn'], $result);
    }

    public function test_argv_is_deterministic(): void
    {
        $state = new BuilderState(kind: 'image', source: '/tmp/img.png', languages: ['eng', 'ind'], psm: 6, oem: 2, timeout: 120);

        $argv1 = ArgvBuilder::buildImage('/usr/bin/tesseract', $state, '/tmp/img.png', '/data');
        $argv2 = ArgvBuilder::buildImage('/usr/bin/tesseract', $state, '/tmp/img.png', '/data');

        $this->assertSame($argv1, $argv2);
    }
}
