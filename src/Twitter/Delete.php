<?php

declare(strict_types=1);

namespace Core\Plug\Social\Twitter;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Deletable;
use Core\Plug\Response;

/**
 * Twitter/X post deletion.
 */
class Delete implements Deletable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.twitter.com';

    public function delete(string $id): Response
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->delete(self::API_URL."/2/tweets/{$id}");

        return $this->fromHttp($response, fn ($data) => [
            'deleted' => $data['data']['deleted'] ?? false,
        ]);
    }
}
