<?php

declare(strict_types=1);

namespace Core\Plug\Social\Reddit;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Listable;
use Core\Plug\Response;

/**
 * Reddit subreddits listing.
 */
class Subreddits implements Listable
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

    public function listEntities(array $params = []): Response
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->withHeaders($this->redditHeaders())
            ->get(self::API_URL.'/subreddits/mine/subscriber', [
                'limit' => $params['limit'] ?? 100,
            ]);

        return $this->fromHttp($response, function ($data) {
            $subreddits = [];

            foreach ($data['data']['children'] ?? [] as $child) {
                $sub = $child['data'] ?? [];
                $subreddits[] = [
                    'id' => $sub['id'] ?? '',
                    'name' => $sub['display_name'] ?? '',
                    'title' => $sub['title'] ?? '',
                    'subscribers' => $sub['subscribers'] ?? 0,
                    'icon' => $sub['icon_img'] ?? $sub['community_icon'] ?? null,
                ];
            }

            return $subreddits;
        });
    }
}
