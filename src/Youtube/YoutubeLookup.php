<?php

namespace Webtack\Ecms\Youtube;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\FieldTypes\YoutubeType;
use Webtack\Ecms\Contracts\EcmsUser;
use Webtack\Ecms\Files\FileStorage;
use Webtack\Ecms\Models\File;

/**
 * Reads the title, description and largest available screenshot of a YouTube video from the
 * YouTube Data API (`videos.list`) and stores the screenshot in the media library (`image` profile).
 *
 * Nothing is created unless every step succeeds: the screenshot is stored last. The key comes from
 * `config('services.youtube.key')`. The screenshot address is taken from the API answer only and
 * must be `https` on a YouTube image host.
 *
 * @example
 *   $video = app(YoutubeLookup::class)->lookup('https://youtu.be/dQw4w9WgXcQ', $user);
 *   $video['url'];         // 'https://youtu.be/dQw4w9WgXcQ'
 *   $video['title'];       // 'Never Gonna Give You Up'
 *   $video['screen'];      // Webtack\Ecms\Models\File of the `image` profile
 *
 * @throws YoutubeLookupException
 */
class YoutubeLookup
{
    private const ENDPOINT = 'https://www.googleapis.com/youtube/v3/videos';

    /** Hosts a screenshot may be downloaded from. */
    private const IMAGE_HOSTS = ['i.ytimg.com', 'img.youtube.com'];

    /** Thumbnail sizes, the largest first. */
    private const SIZES = ['maxres', 'standard', 'high', 'medium', 'default'];

    private const TIMEOUT = 10;

    public function __construct(private readonly FileStorage $storage) {}

    /**
     * @return array{url: string, title: string, description: string, screen: File}
     *
     * @throws YoutubeLookupException
     */
    public function lookup(string $link, ?EcmsUser $user = null): array
    {
        $parsed = YoutubeType::parse($link);

        if ($parsed === null) {
            throw new YoutubeLookupException(YoutubeLookupException::INVALID_URL);
        }

        $key = config('services.youtube.key');

        if (! is_string($key) || $key === '') {
            throw new YoutubeLookupException(YoutubeLookupException::NOT_CONFIGURED);
        }

        $snippet = $this->snippet($parsed['id'], $key);

        return [
            'url' => $parsed['url'],
            'title' => (string) ($snippet['title'] ?? ''),
            'description' => (string) ($snippet['description'] ?? ''),
            'screen' => $this->storeScreen($snippet['thumbnails'] ?? [], $parsed['id'], $user),
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws YoutubeLookupException
     */
    private function snippet(string $id, string $key): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)->acceptJson()->get(self::ENDPOINT, ['part' => 'snippet', 'id' => $id, 'key' => $key]);
        } catch (ConnectionException) {
            throw new YoutubeLookupException(YoutubeLookupException::UNAVAILABLE);
        }

        if (! $response->successful()) {
            throw new YoutubeLookupException(YoutubeLookupException::UNAVAILABLE);
        }

        $snippet = $response->json('items.0.snippet');

        if (! is_array($snippet)) {
            throw new YoutubeLookupException(YoutubeLookupException::NOT_FOUND);
        }

        return $snippet;
    }

    /**
     * Download the largest thumbnail that works (a smaller one when a larger is missing or rejected)
     * and store it in the media library.
     *
     * @param  array<string, mixed>  $thumbnails
     *
     * @throws YoutubeLookupException
     */
    private function storeScreen(array $thumbnails, string $id, ?EcmsUser $user): File
    {
        foreach (self::SIZES as $size) {
            $address = $thumbnails[$size]['url'] ?? null;

            if (! is_string($address) || ! $this->isAllowedImage($address)) {
                continue;
            }

            try {
                $response = Http::timeout(self::TIMEOUT)->get($address);
            } catch (ConnectionException) {
                continue;
            }

            if (! $response->successful() || $response->body() === '') {
                continue;
            }

            $path = tempnam(sys_get_temp_dir(), 'yt');

            try {
                file_put_contents($path, $response->body());

                return $this->storage->store(new UploadedFile($path, "{$id}.jpg", null, null, true), 'image', $user);
            } catch (ValidationException) {
                continue;
            } finally {
                @unlink($path);
            }
        }

        throw new YoutubeLookupException(YoutubeLookupException::UNAVAILABLE);
    }

    private function isAllowedImage(string $address): bool
    {
        $parts = parse_url($address);

        return ($parts['scheme'] ?? '') === 'https' && in_array(strtolower($parts['host'] ?? ''), self::IMAGE_HOSTS, true);
    }
}
