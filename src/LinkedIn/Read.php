<?php

declare(strict_types=1);

namespace Core\Plug\Social\LinkedIn;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Readable;
use Core\Plug\Response;

/**
 * LinkedIn account reading.
 */
class Read implements Readable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.linkedin.com';

    private string $apiVersion = 'v2';

    protected function httpHeaders(): array
    {
        return [
            'X-Restli-Protocol-Version' => '2.0.0',
            'LinkedIn-Version' => '202507',
        ];
    }

    public function get(string $id): Response
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->withHeaders($this->httpHeaders())
            ->get(self::API_URL."/{$this->apiVersion}/userinfo");

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['sub'],
            'name' => $data['name'] ?? trim(($data['given_name'] ?? '').' '.($data['family_name'] ?? '')),
            'username' => $data['email'] ?? '',
            'image' => $data['picture'] ?? null,
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
        // LinkedIn doesn't have a simple feed API
        return $this->error('Post listing not supported via API');
    }
}
