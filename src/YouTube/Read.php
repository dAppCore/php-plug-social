<?php

declare(strict_types=1);

namespace Core\Plug\Social\YouTube;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Readable;
use Core\Plug\Response;

/**
 * YouTube channel and video reading.
 */
class Read implements Readable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://www.googleapis.com/youtube/v3';

    public function get(string $id): Response
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->get(self::API_URL.'/videos', [
                'part' => 'snippet,statistics',
                'id' => $id,
            ]);

        return $this->fromHttp($response, function ($data) {
            $video = $data['items'][0] ?? null;

            if (! $video) {
                return ['error' => 'Video not found'];
            }

            return [
                'id' => $video['id'],
                'title' => $video['snippet']['title'] ?? '',
                'description' => $video['snippet']['description'] ?? '',
                'thumbnail' => $video['snippet']['thumbnails']['default']['url'] ?? null,
                'views' => $video['statistics']['viewCount'] ?? 0,
            ];
        });
    }

    /**
     * Get the authenticated user's channel info.
     */
    public function me(): Response
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->get(self::API_URL.'/channels', [
                'part' => 'snippet,statistics',
                'mine' => 'true',
            ]);

        return $this->fromHttp($response, function ($data) {
            $channel = $data['items'][0] ?? null;

            if (! $channel) {
                return ['error' => 'No channel found'];
            }

            return [
                'id' => $channel['id'],
                'name' => $channel['snippet']['title'] ?? '',
                'username' => $channel['snippet']['customUrl'] ?? '',
                'image' => $channel['snippet']['thumbnails']['default']['url'] ?? null,
                'subscribers' => $channel['statistics']['subscriberCount'] ?? 0,
            ];
        });
    }

    public function list(array $params = []): Response
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->get(self::API_URL.'/search', [
                'part' => 'snippet',
                'forMine' => 'true',
                'type' => 'video',
                'maxResults' => $params['limit'] ?? 25,
                'pageToken' => $params['page_token'] ?? null,
            ]);

        return $this->fromHttp($response, fn ($data) => [
            'videos' => array_map(fn ($item) => [
                'id' => $item['id']['videoId'] ?? '',
                'title' => $item['snippet']['title'] ?? '',
                'description' => $item['snippet']['description'] ?? '',
                'thumbnail' => $item['snippet']['thumbnails']['default']['url'] ?? null,
            ], $data['items'] ?? []),
            'next_page_token' => $data['nextPageToken'] ?? null,
        ]);
    }
}
