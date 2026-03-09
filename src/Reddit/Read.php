<?php

declare(strict_types=1);

namespace Core\Plug\Social\Reddit;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Readable;
use Core\Plug\Response;

/**
 * Reddit account and post reading.
 */
class Read implements Readable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://oauth.reddit.com';

    protected function redditHeaders(): array
    {
        return [
            'User-Agent' => config('app.name').'/1.0',
        ];
    }

    public function get(string $id): Response
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->withHeaders($this->redditHeaders())
            ->get(self::API_URL.'/api/info', [
                'id' => $id,
            ]);

        return $this->fromHttp($response, function ($data) {
            $post = $data['data']['children'][0]['data'] ?? null;

            if (! $post) {
                return ['error' => 'Post not found'];
            }

            return [
                'id' => $post['id'],
                'title' => $post['title'] ?? '',
                'text' => $post['selftext'] ?? '',
                'url' => $post['url'] ?? null,
                'subreddit' => $post['subreddit'] ?? null,
            ];
        });
    }

    /**
     * Get the authenticated user's account info.
     */
    public function me(): Response
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->withHeaders($this->redditHeaders())
            ->get(self::API_URL.'/api/v1/me');

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['id'],
            'name' => $data['name'],
            'username' => $data['name'],
            'image' => $data['icon_img'] ?? $data['snoovatar_img'] ?? null,
        ]);
    }

    public function list(array $params = []): Response
    {
        $username = $params['username'] ?? null;

        if (! $username) {
            return $this->error('username is required');
        }

        $response = $this->http()
            ->withToken($this->accessToken())
            ->withHeaders($this->redditHeaders())
            ->get(self::API_URL."/user/{$username}/submitted", [
                'limit' => $params['limit'] ?? 25,
            ]);

        return $this->fromHttp($response, fn ($data) => [
            'posts' => array_map(fn ($child) => [
                'id' => $child['data']['id'] ?? '',
                'title' => $child['data']['title'] ?? '',
                'subreddit' => $child['data']['subreddit'] ?? '',
            ], $data['data']['children'] ?? []),
            'after' => $data['data']['after'] ?? null,
        ]);
    }
}
