<?php

declare(strict_types=1);

namespace Core\Plug\Social\Pinterest;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Listable;
use Core\Plug\Response;

/**
 * Pinterest boards listing.
 */
class Boards implements Listable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.pinterest.com/v5';

    public function listEntities(array $params = []): Response
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->get(self::API_URL.'/boards', [
                'page_size' => $params['limit'] ?? 25,
            ]);

        return $this->fromHttp($response, function ($data) {
            $boards = [];

            foreach ($data['items'] ?? [] as $board) {
                $boards[] = [
                    'id' => $board['id'],
                    'name' => $board['name'],
                    'description' => $board['description'] ?? '',
                    'privacy' => $board['privacy'] ?? 'PUBLIC',
                ];
            }

            return $boards;
        });
    }
}
