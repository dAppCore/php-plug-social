<?php

declare(strict_types=1);

namespace Core\Plug\Social\LinkedIn;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Deletable;
use Core\Plug\Response;

/**
 * LinkedIn post deletion.
 */
class Delete implements Deletable
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

    public function delete(string $id): Response
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->withHeaders($this->httpHeaders())
            ->delete(self::API_URL."/{$this->apiVersion}/ugcPosts/{$id}");

        return $this->fromHttp($response, fn () => [
            'deleted' => true,
        ]);
    }
}
