<?php

declare(strict_types=1);

namespace Core\Plug\Social\Reddit;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Deletable;
use Core\Plug\Response;

/**
 * Reddit post deletion.
 */
class Delete implements Deletable
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

    public function delete(string $id): Response
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->withHeaders($this->redditHeaders())
            ->asForm()
            ->post(self::API_URL.'/api/del', [
                'id' => $id,
            ]);

        return $this->fromHttp($response, fn () => [
            'deleted' => true,
        ]);
    }
}
