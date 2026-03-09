<?php

declare(strict_types=1);

namespace Core\Plug\Social\YouTube;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Deletable;
use Core\Plug\Response;

/**
 * YouTube video deletion.
 */
class Delete implements Deletable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://www.googleapis.com/youtube/v3';

    public function delete(string $id): Response
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->delete(self::API_URL.'/videos', [
                'id' => $id,
            ]);

        return $this->fromHttp($response, fn () => [
            'deleted' => true,
        ]);
    }
}
