<?php

declare(strict_types=1);

namespace Core\Plug\Social\Reddit;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Postable;
use Core\Plug\Response;
use Illuminate\Support\Collection;

/**
 * Reddit post publishing.
 *
 * Supports text posts, link posts, and image posts.
 */
class Post implements Postable
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

    public function publish(string $text, Collection $media, array $params = []): Response
    {
        $subreddit = $params['subreddit'] ?? null;

        if (! $subreddit) {
            return $this->error('Subreddit is required');
        }

        $postData = [
            'api_type' => 'json',
            'kind' => 'self', // Text post
            'sr' => $subreddit,
            'title' => $params['title'] ?? substr($text, 0, 300),
            'text' => $text,
            'nsfw' => $params['nsfw'] ?? false,
            'spoiler' => $params['spoiler'] ?? false,
        ];

        // Handle link post
        if (isset($params['url'])) {
            $postData['kind'] = 'link';
            $postData['url'] = $params['url'];
            unset($postData['text']);
        }

        // Handle image post
        if ($media->isNotEmpty() && ! isset($params['url'])) {
            $image = $media->first();
            $mediaUploader = (new Media)->withToken($this->getToken());
            $uploadResult = $mediaUploader->upload($image);

            if ($uploadResult->hasError()) {
                return $uploadResult;
            }

            $postData['kind'] = 'image';
            $postData['url'] = $uploadResult->get('url');
            unset($postData['text']);
        }

        $response = $this->http()
            ->withToken($this->accessToken())
            ->withHeaders($this->redditHeaders())
            ->asForm()
            ->post(self::API_URL.'/api/submit', $postData);

        return $this->fromHttp($response, function ($data) {
            $postData = $data['json']['data'] ?? [];

            return [
                'id' => $postData['id'] ?? $postData['name'] ?? '',
                'url' => $postData['url'] ?? '',
            ];
        });
    }

    /**
     * Get external URL to a Reddit post.
     */
    public static function externalPostUrl(string $subreddit, string $postId): string
    {
        return "https://www.reddit.com/r/{$subreddit}/comments/{$postId}";
    }

    /**
     * Get external URL to a Reddit profile.
     */
    public static function externalAccountUrl(string $username): string
    {
        return "https://www.reddit.com/user/{$username}";
    }
}
