<?php

namespace Dunn\LaravelOcr\Tests\Unit;

use Dunn\LaravelOcr\Exceptions\UnsupportedLanguageException;
use Dunn\LaravelOcr\Support\BuilderState;
use PHPUnit\Framework\TestCase;

class BuilderStateTest extends TestCase
{
    public function test_to_payload_returns_scalar_array(): void
    {
        $state = new BuilderState(
            kind: 'image',
            source: 'test.png',
            languages: ['eng'],
            psm: 3,
            oem: 3,
            timeout: 120,
        );

        $payload = $state->toPayload();

        $this->assertSame('image', $payload['kind']);
        $this->assertSame('test.png', $payload['source']);
        $this->assertSame(['eng'], $payload['languages']);
        $this->assertSame(3, $payload['psm']);
        $this->assertNull($payload['dpi']);
    }

    public function test_from_payload_round_trips(): void
    {
        $state = new BuilderState(
            kind: 'pdf',
            source: 'doc.pdf',
            disk: 's3',
            languages: ['eng', 'ind'],
            psm: 6,
            oem: 1,
            timeout: 60,
            tessdataPath: '/data',
            dpi: 300,
            pages: [1, 3, 5],
        );

        $restored = BuilderState::fromPayload($state->toPayload());

        $this->assertSame($state->kind, $restored->kind);
        $this->assertSame($state->source, $restored->source);
        $this->assertSame($state->disk, $restored->disk);
        $this->assertSame($state->languages, $restored->languages);
        $this->assertSame($state->psm, $restored->psm);
        $this->assertSame($state->oem, $restored->oem);
        $this->assertSame($state->timeout, $restored->timeout);
        $this->assertSame($state->tessdataPath, $restored->tessdataPath);
        $this->assertSame($state->dpi, $restored->dpi);
        $this->assertSame($state->pages, $restored->pages);
    }

    public function test_from_payload_rejects_invalid_psm(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BuilderState::fromPayload(['source' => 'x.png', 'psm' => 14]);
    }

    public function test_from_payload_rejects_invalid_oem(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BuilderState::fromPayload(['source' => 'x.png', 'oem' => 5]);
    }

    public function test_from_payload_rejects_invalid_language(): void
    {
        $this->expectException(UnsupportedLanguageException::class);
        BuilderState::fromPayload(['source' => 'x.png', 'languages' => ['INVALID']]);
    }

    public function test_from_payload_rejects_non_string_language(): void
    {
        $this->expectException(UnsupportedLanguageException::class);
        BuilderState::fromPayload(['source' => 'x.png', 'languages' => [123]]);
    }

    public function test_from_payload_rejects_duplicate_pages(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BuilderState::fromPayload(['source' => 'x.png', 'pages' => [1, 1, 2]]);
    }

    public function test_with_creates_new_instance(): void
    {
        $state = new BuilderState(kind: 'image', source: 'a.png', psm: 3, oem: 3, timeout: 120);
        $new = $state->with(['psm' => 6]);

        $this->assertSame(3, $state->psm);
        $this->assertSame(6, $new->psm);
    }

    public function test_is_language_code_validates_correctly(): void
    {
        $this->assertTrue(BuilderState::isLanguageCode('eng'));
        $this->assertTrue(BuilderState::isLanguageCode('chi_sim'));
        $this->assertFalse(BuilderState::isLanguageCode('EN'));
        $this->assertFalse(BuilderState::isLanguageCode('e'));
        $this->assertFalse(BuilderState::isLanguageCode('english'));
        $this->assertFalse(BuilderState::isLanguageCode(''));
    }
}
