<?php

namespace App\Support;

class YouTubeVideoId
{
    /**
     * Extract the video id from a YouTube watch, short, embed, or share URL.
     */
    public static function fromUrl(string $url): ?string
    {
        $url = trim($url);

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url) === 1) {
            return $url;
        }

        $parts = parse_url(str_contains($url, '://') ? $url : 'https://'.$url);
        $host = strtolower(preg_replace('/^(www\.|m\.|music\.)/', '', $parts['host'] ?? '') ?? '');
        $path = $parts['path'] ?? '';

        $candidate = match (true) {
            $host === 'youtu.be' => explode('/', ltrim($path, '/'))[0],
            in_array($host, ['youtube.com', 'youtube-nocookie.com'], true) && $path === '/watch' => self::queryParameter($parts['query'] ?? '', 'v'),
            in_array($host, ['youtube.com', 'youtube-nocookie.com'], true) => preg_match('#^/(?:shorts|embed|live|v)/([^/?]+)#', $path, $matches) === 1 ? $matches[1] : null,
            default => null,
        };

        return is_string($candidate) && preg_match('/^[A-Za-z0-9_-]{11}$/', $candidate) === 1
            ? $candidate
            : null;
    }

    private static function queryParameter(string $query, string $name): ?string
    {
        parse_str($query, $parameters);

        return is_string($parameters[$name] ?? null) ? $parameters[$name] : null;
    }
}
