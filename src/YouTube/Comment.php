<?php

declare(strict_types=1);

namespace Core\Plug\Social\YouTube;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Commentable;
use Core\Plug\Response;

/**
 * YouTube video commenting.
 */
class Comment implements Commentable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://www.googleapis.com/youtube/v3';

    public function comment(string $text, string $postId, array $params = []): Response
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->post(self::API_URL.'/commentThreads?part=snippet', [
                'snippet' => [
                    'videoId' => $postId,
                    'topLevelComment' => [
                        'snippet' => [
                            'textOriginal' => $text,
                        ],
                    ],
                ],
            ]);

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['id'] ?? '',
        ]);
    }
}
