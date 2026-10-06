<?php

use App\Support\YouTubeVideoId;

test('it extracts the video id from supported YouTube urls', function (string $url) {
    expect(YouTubeVideoId::fromUrl($url))->toBe('dQw4w9WgXcQ');
})->with([
    'watch' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    'watch with extra parameters' => 'https://youtube.com/watch?feature=share&v=dQw4w9WgXcQ&t=42s',
    'mobile' => 'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
    'share' => 'https://youtu.be/dQw4w9WgXcQ?si=abc',
    'shorts' => 'https://www.youtube.com/shorts/dQw4w9WgXcQ',
    'embed' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
    'without scheme' => 'youtube.com/watch?v=dQw4w9WgXcQ',
    'bare id' => 'dQw4w9WgXcQ',
]);

test('it rejects urls that are not YouTube videos', function (string $url) {
    expect(YouTubeVideoId::fromUrl($url))->toBeNull();
})->with([
    'other host' => 'https://vimeo.com/watch?v=dQw4w9WgXcQ',
    'lookalike host' => 'https://notyoutube.com/watch?v=dQw4w9WgXcQ',
    'channel' => 'https://www.youtube.com/@wildforce',
    'malformed id' => 'https://youtu.be/short',
    'empty' => '',
]);
