<?php

declare(strict_types=1);

namespace Core\Plug\Social\Pinterest;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\MediaUploadable;
use Core\Plug\Response;

/**
 * Pinterest media upload.
 */
class Media implements MediaUploadable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.pinterest.com/v5';

    public function upload(array $item): Response
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->attach('file', file_get_contents($item['path']), $item['name'] ?? 'image.jpg')
            ->post(self::API_URL.'/media');

        return $this->fromHttp($response, fn ($data) => [
            'media_id' => $data['media_id'],
        ]);
    }
}
