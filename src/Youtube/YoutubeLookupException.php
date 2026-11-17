<?php

namespace Webtack\Ecms\Youtube;

use RuntimeException;

/**
 * Why `YoutubeLookup` could not get the data of a video.
 *
 * `reason` is one of `invalid_url` and `not_found` (the link is the problem, HTTP 422) or
 * `not_configured` and `unavailable` (the integration is the problem, HTTP 503).
 *
 * @example
 *   throw new YoutubeLookupException(YoutubeLookupException::NOT_FOUND);
 */
class YoutubeLookupException extends RuntimeException
{
    public const INVALID_URL = 'invalid_url';

    public const NOT_FOUND = 'not_found';

    public const NOT_CONFIGURED = 'not_configured';

    public const UNAVAILABLE = 'unavailable';

    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }

    /** The HTTP status of the answer: 422 for a problem with the link, 503 for the integration. */
    public function status(): int
    {
        return in_array($this->reason, [self::INVALID_URL, self::NOT_FOUND], true) ? 422 : 503;
    }

    /** The user-facing text of the reason (translated). */
    public function userMessage(): string
    {
        return match ($this->reason) {
            self::INVALID_URL => __('The link is not a YouTube video link.'),
            self::NOT_FOUND => __('The video was not found or it is private.'),
            self::NOT_CONFIGURED => __('YouTube integration is not configured.'),
            default => __('YouTube is temporarily unavailable. Try again later.'),
        };
    }
}
