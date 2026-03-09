<?php

declare(strict_types=1);

namespace Core\Plug\Social\YouTube;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Postable;
use Core\Plug\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * YouTube video publishing.
 *
 * Uses resumable upload for video content.
 */
class Post implements Postable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://www.googleapis.com/youtube/v3';

    private const UPLOAD_URL = 'https://www.googleapis.com/upload/youtube/v3';

    public function publish(string $text, Collection $media, array $params = []): Response
    {
        if ($media->isEmpty()) {
            return $this->error('YouTube requires video content');
        }

        $video = $media->first();

        // Prepare video metadata
        $metadata = [
            'snippet' => [
                'title' => $params['title'] ?? 'Untitled Video',
                'description' => $text,
                'tags' => $params['tags'] ?? [],
                'categoryId' => $params['category_id'] ?? '22', // 22 = People & Blogs
            ],
            'status' => [
                'privacyStatus' => $params['privacy_status'] ?? 'private',
                'selfDeclaredMadeForKids' => $params['made_for_kids'] ?? false,
            ],
        ];

        // For scheduled publishing
        if (isset($params['publish_at'])) {
            $metadata['status']['publishAt'] = Carbon::parse($params['publish_at'])->toIso8601String();
        }

        // Step 1: Start resumable upload
        $initResponse = $this->http()
            ->withToken($this->accessToken())
            ->withHeaders([
                'Content-Type' => 'application/json; charset=UTF-8',
                'X-Upload-Content-Length' => filesize($video['path']),
                'X-Upload-Content-Type' => $video['mime_type'] ?? 'video/mp4',
            ])
            ->post(self::UPLOAD_URL.'/videos?uploadType=resumable&part=snippet,status', $metadata);

        if (! $initResponse->successful()) {
            return $this->fromHttp($initResponse);
        }

        $uploadUrl = $initResponse->header('Location');

        if (! $uploadUrl) {
            return $this->error('Failed to get upload URL');
        }

        // Step 2: Upload video content
        $uploadResponse = $this->http()
            ->withToken($this->accessToken())
            ->withHeaders([
                'Content-Type' => $video['mime_type'] ?? 'video/mp4',
            ])
            ->withBody(file_get_contents($video['path']), $video['mime_type'] ?? 'video/mp4')
            ->put($uploadUrl);

        return $this->fromHttp($uploadResponse, fn ($data) => [
            'id' => $data['id'],
            'url' => "https://www.youtube.com/watch?v={$data['id']}",
        ]);
    }

    /**
     * Get external URL to a YouTube video.
     */
    public static function externalPostUrl(string $videoId): string
    {
        return "https://www.youtube.com/watch?v={$videoId}";
    }

    /**
     * Get external URL to a YouTube channel.
     */
    public static function externalAccountUrl(string $channelId): string
    {
        return "https://www.youtube.com/channel/{$channelId}";
    }
}
