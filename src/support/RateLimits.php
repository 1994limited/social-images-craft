<?php

namespace nineteenninetyfour\socialimages\support;

use DateTimeImmutable;
use NineteenNinetyFour\SocialImages\Core\Rendering\Exceptions\QuotaExhausted;
use NineteenNinetyFour\SocialImages\Core\Rendering\Exceptions\RateLimited;
use NineteenNinetyFour\SocialImages\Core\Rendering\Exceptions\RenderException;
use Throwable;

/**
 * Whether a failed render was the screenshot service saying "too many", or
 * "no more today". Renderers say so with RateLimited and QuotaExhausted;
 * a driver that only reports text is matched on that.
 */
class RateLimits
{
    public static function caused(Throwable $exception): bool
    {
        if (self::find($exception, RateLimited::class) || self::find($exception, QuotaExhausted::class)) {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return (bool) preg_match('/(?<![\w-])429(?![\w-])/', $message)
            || str_contains($message, 'rate limit')
            || str_contains($message, 'too many requests')
            || str_contains($message, 'time limit');
    }

    /**
     * Whether an allowance has run out, so trying again soon is no use.
     */
    public static function exhausted(Throwable $exception): bool
    {
        return self::find($exception, QuotaExhausted::class) !== null
            || str_contains(strtolower($exception->getMessage()), 'time limit exceeded');
    }

    /**
     * Seconds the service asked for before the next try, if it said.
     */
    public static function retryAfter(Throwable $exception): ?int
    {
        return self::find($exception, RateLimited::class)?->retryAfter;
    }

    /**
     * When an exhausted allowance comes back, if known.
     */
    public static function resetsAt(Throwable $exception): ?DateTimeImmutable
    {
        return self::find($exception, QuotaExhausted::class)?->resetsAt;
    }

    /**
     * What to do about a failure, when the renderer said.
     */
    public static function fix(Throwable $exception): ?string
    {
        return self::find($exception, RenderException::class)?->fix;
    }

    /**
     * @template T of Throwable
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    protected static function find(Throwable $exception, string $class): ?Throwable
    {
        for ($e = $exception; $e; $e = $e->getPrevious()) {
            if ($e instanceof $class) {
                return $e;
            }
        }

        return null;
    }
}
