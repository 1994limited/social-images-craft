<?php

namespace nineteenninetyfour\socialimages\tests\unit;

use nineteenninetyfour\socialimages\services\Camera;
use RuntimeException;
use Throwable;

/**
 * Stands in for the renderer: records each page and returns a one-pixel
 * PNG, or throws what it's told to.
 */
class FakeCamera extends Camera
{
    public const PNG = "\x89PNG\r\n\x1a\n\0\0\0\rIHDR\0\0\0\x01\0\0\0\x01\x08\x06\0\0\0\x1f\x15\xc4\x89\0\0\0\rIDATx\x9cc\xf8\xff\xff?\0\x05\xfe\x02\xfe\xa7\x35\x81\x84\0\0\0\0IEND\xaeB`\x82";

    /** @var array<int, array{html: string, width: int, height: int}> */
    public array $captures = [];

    /** @var array<int, Throwable> */
    public array $failures = [];

    public function capture(string $html, int $width, int $height): string
    {
        if ($failure = array_shift($this->failures)) {
            throw $failure;
        }

        $this->captures[] = compact('html', 'width', 'height');

        return self::PNG;
    }

    public function failWith(Throwable|string $failure, int $times = 1): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->failures[] = is_string($failure) ? new RuntimeException($failure) : $failure;
        }
    }
}
