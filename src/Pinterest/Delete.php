<?php

declare(strict_types=1);

namespace Core\Plug\Social\Pinterest;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Deletable;
use Core\Plug\Response;

/**
 * Pinterest pin deletion.
 */
class Delete implements Deletable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.pinterest.com/v5';

    public function delete(string $id): Response
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->delete(self::API_URL."/pins/{$id}");

        return $this->fromHttp($response, fn () => [
            'deleted' => true,
        ]);
    }
}
