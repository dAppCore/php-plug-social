<?php

declare(strict_types=1);

namespace Core\Plug\Social\Twitter;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Readable;
use Core\Plug\Response;

/**
 * Twitter/X post reading.
 */
class Read implements Readable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.twitter.com';

    public function get(string $id): Response
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->get(self::API_URL."/2/tweets/{$id}", [
                'tweet.fields' => 'id,text,created_at,author_id,public_metrics',
                'expansions' => 'author_id',
                'user.fields' => 'id,name,username,profile_image_url',
            ]);

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['data']['id'],
            'text' => $data['data']['text'],
            'created_at' => $data['data']['created_at'] ?? null,
            'author_id' => $data['data']['author_id'] ?? null,
            'metrics' => $data['data']['public_metrics'] ?? [],
            'author' => $data['includes']['users'][0] ?? null,
        ]);
    }

    public function list(array $params = []): Response
    {
        $userId = $params['user_id'] ?? null;

        if (! $userId) {
            return $this->error('user_id is required');
        }

        $queryParams = [
            'tweet.fields' => 'id,text,created_at,public_metrics',
            'max_results' => $params['limit'] ?? 10,
        ];

        if (! empty($params['pagination_token'])) {
            $queryParams['pagination_token'] = $params['pagination_token'];
        }

        $response = $this->http()
            ->withToken($this->accessToken())
            ->get(self::API_URL."/2/users/{$userId}/tweets", $queryParams);

        return $this->fromHttp($response, fn ($data) => [
            'tweets' => $data['data'] ?? [],
            'meta' => $data['meta'] ?? [],
        ]);
    }
}
