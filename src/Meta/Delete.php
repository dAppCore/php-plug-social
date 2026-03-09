<?php

declare(strict_types=1);

namespace Core\Plug\Social\Meta;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Deletable;
use Core\Plug\Response;

/**
 * Meta (Facebook/Instagram) post deletion.
 */
class Delete implements Deletable
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

    public function delete(string $id): Response
    {
        $response = $this->http()
            ->delete(self::API_URL."/{$this->apiVersion}/{$id}", [
                'access_token' => $this->accessToken(),
            ]);

        return $this->fromHttp($response, fn ($data) => [
            'deleted' => $data['success'] ?? true,
        ]);
    }
}
