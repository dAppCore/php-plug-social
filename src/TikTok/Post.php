<?php

declare(strict_types=1);

namespace Core\Plug\Social\TikTok;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Postable;
use Core\Plug\Response;
use Illuminate\Support\Collection;

/**
 * TikTok video publishing.
 *
 * Multi-step process: initialise upload, upload video chunks.
 */
class Post implements Postable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://open.tiktokapis.com/v2';

    private const CHUNK_SIZE = 10 * 1024 * 1024; // 10MB

    public function publish(string $text, Collection $media, array $params = []): Response
    {
        if ($media->isEmpty()) {
            return $this->error('TikTok requires video content');
        }

        $video = $media->first();
        $fileSize = filesize($video['path']);

        // Step 1: Initialise upload
        $initResponse = $this->http()
            ->withToken($this->accessToken())
            ->post(self::API_URL.'/post/publish/video/init/', [
                'post_info' => [
                    'title' => $text,
                    'privacy_level' => $params['privacy_level'] ?? 'SELF_ONLY',
                    'disable_duet' => $params['disable_duet'] ?? false,
                    'disable_stitch' => $params['disable_stitch'] ?? false,
                    'disable_comment' => $params['disable_comment'] ?? false,
                ],
                'source_info' => [
                    'source' => 'FILE_UPLOAD',
                    'video_size' => $fileSize,
                    'chunk_size' => self::CHUNK_SIZE,
                    'total_chunk_count' => (int) ceil($fileSize / self::CHUNK_SIZE),
                ],
            ]);

        if (! $initResponse->successful()) {
            return $this->fromHttp($initResponse);
        }

        $initData = $initResponse->json();
        $publishId = $initData['data']['publish_id'] ?? null;
        $uploadUrl = $initData['data']['upload_url'] ?? null;

        if (! $publishId || ! $uploadUrl) {
            return $this->error('Failed to initialise video upload');
        }

        // Step 2: Upload video
        $uploadResponse = $this->http()
            ->attach('video', file_get_contents($video['path']), $video['name'] ?? 'video.mp4')
            ->put($uploadUrl);

        if (! $uploadResponse->successful()) {
            return $this->error('Failed to upload video');
        }

        return $this->ok([
            'id' => $publishId,
        ]);
    }

    /**
     * Get external URL to a TikTok video.
     */
    public static function externalPostUrl(string $username, string $postId): string
    {
        return "https://www.tiktok.com/@{$username}/video/{$postId}";
    }

    /**
     * Get external URL to a TikTok profile.
     */
    public static function externalAccountUrl(string $username): string
    {
        return "https://www.tiktok.com/@{$username}";
    }
}
