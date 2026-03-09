<?php

declare(strict_types=1);

namespace Core\Plug\Social\Pinterest;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Postable;
use Core\Plug\Response;
use Illuminate\Support\Collection;

/**
 * Pinterest pin publishing.
 */
class Post implements Postable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.pinterest.com/v5';

    public function publish(string $text, Collection $media, array $params = []): Response
    {
        if ($media->isEmpty()) {
            return $this->error('Pinterest requires an image');
        }

        $boardId = $params['board_id'] ?? null;

        if (! $boardId) {
            return $this->error('Board ID is required');
        }

        $image = $media->first();

        // Upload media first
        $mediaUploader = (new Media)->withToken($this->getToken());
        $mediaResult = $mediaUploader->upload($image);

        if ($mediaResult->hasError()) {
            return $mediaResult;
        }

        $mediaId = $mediaResult->get('media_id');

        // Create pin
        $pinData = [
            'board_id' => $boardId,
            'title' => $params['title'] ?? '',
            'description' => $text,
            'media_source' => [
                'source_type' => 'media_id',
                'media_id' => $mediaId,
            ],
        ];

        if (isset($params['link'])) {
            $pinData['link'] = $params['link'];
        }

        $response = $this->http()
            ->withToken($this->accessToken())
            ->post(self::API_URL.'/pins', $pinData);

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['id'],
        ]);
    }

    /**
     * Get external URL to a Pinterest pin.
     */
    public static function externalPostUrl(string $pinId): string
    {
        return "https://www.pinterest.com/pin/{$pinId}";
    }

    /**
     * Get external URL to a Pinterest profile.
     */
    public static function externalAccountUrl(string $username): string
    {
        return "https://www.pinterest.com/{$username}";
    }
}
