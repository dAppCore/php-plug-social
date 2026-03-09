<?php

declare(strict_types=1);

namespace Core\Plug\Social\Pinterest;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Readable;
use Core\Plug\Response;

/**
 * Pinterest account and pin reading.
 */
class Read implements Readable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.pinterest.com/v5';

    public function get(string $id): Response
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->get(self::API_URL."/pins/{$id}");

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['id'] ?? '',
            'title' => $data['title'] ?? '',
            'description' => $data['description'] ?? '',
            'link' => $data['link'] ?? null,
            'board_id' => $data['board_id'] ?? null,
        ]);
    }

    /**
     * Get the authenticated user's account info.
     */
    public function me(): Response
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->get(self::API_URL.'/user_account');

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['id'] ?? '',
            'name' => $data['business_name'] ?? $data['username'] ?? '',
            'username' => $data['username'] ?? '',
            'image' => $data['profile_image'] ?? null,
        ]);
    }

    public function list(array $params = []): Response
    {
        $boardId = $params['board_id'] ?? null;

        if (! $boardId) {
            return $this->error('board_id is required');
        }

        $response = $this->http()
            ->withToken($this->accessToken())
            ->get(self::API_URL."/boards/{$boardId}/pins", [
                'page_size' => $params['limit'] ?? 25,
            ]);

        return $this->fromHttp($response, fn ($data) => [
            'pins' => $data['items'] ?? [],
            'bookmark' => $data['bookmark'] ?? null,
        ]);
    }
}
