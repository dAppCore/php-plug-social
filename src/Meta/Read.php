<?php

declare(strict_types=1);

namespace Core\Plug\Social\Meta;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Readable;
use Core\Plug\Response;

/**
 * Meta (Facebook/Instagram) account reading.
 */
class Read implements Readable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://graph.facebook.com';

    private string $apiVersion;

    public function __construct()
    {
        $this->apiVersion = config('social.providers.meta.api_version', 'v18.0');
    }

    public function get(string $id): Response
    {
        $response = $this->http()
            ->get(self::API_URL."/{$this->apiVersion}/{$id}", [
                'access_token' => $this->accessToken(),
                'fields' => 'id,name,email',
            ]);

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['id'],
            'name' => $data['name'],
            'username' => $data['email'] ?? '',
            'image' => self::API_URL."/{$this->apiVersion}/{$data['id']}/picture?type=large",
        ]);
    }

    /**
     * Get the authenticated user's account info.
     */
    public function me(): Response
    {
        return $this->get('me');
    }

    public function list(array $params = []): Response
    {
        // For listing posts, use the page ID
        $pageId = $params['page_id'] ?? null;

        if (! $pageId) {
            return $this->error('page_id is required');
        }

        $response = $this->http()
            ->get(self::API_URL."/{$this->apiVersion}/{$pageId}/feed", [
                'access_token' => $this->accessToken(),
                'fields' => 'id,message,created_time,full_picture',
                'limit' => $params['limit'] ?? 10,
            ]);

        return $this->fromHttp($response, fn ($data) => [
            'posts' => $data['data'] ?? [],
            'paging' => $data['paging'] ?? [],
        ]);
    }
}
