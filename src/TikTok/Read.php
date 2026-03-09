<?php

declare(strict_types=1);

namespace Core\Plug\Social\TikTok;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Readable;
use Core\Plug\Response;

/**
 * TikTok account reading.
 */
class Read implements Readable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://open.tiktokapis.com/v2';

    public function get(string $id): Response
    {
        // TikTok doesn't have a get-by-id endpoint for public videos
        return $this->error('TikTok does not support fetching videos by ID');
    }

    /**
     * Get the authenticated user's account info.
     */
    public function me(): Response
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->get(self::API_URL.'/user/info/', [
                'fields' => 'open_id,union_id,avatar_url,display_name,username',
            ]);

        return $this->fromHttp($response, function ($data) {
            $user = $data['data']['user'] ?? $data['user'] ?? $data;

            return [
                'id' => $user['open_id'] ?? $user['union_id'] ?? '',
                'name' => $user['display_name'] ?? '',
                'username' => $user['username'] ?? '',
                'image' => $user['avatar_url'] ?? null,
            ];
        });
    }

    public function list(array $params = []): Response
    {
        // TikTok doesn't provide a list videos API for creators
        return $this->error('TikTok does not support listing videos via API');
    }
}
