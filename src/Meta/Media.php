<?php

declare(strict_types=1);

namespace Core\Plug\Social\Meta;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\MediaUploadable;
use Core\Plug\Response;

/**
 * Meta (Facebook/Instagram) media upload.
 */
class Media implements MediaUploadable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://graph.facebook.com';

    private string $apiVersion;

    private ?string $pageId = null;

    private ?string $pageToken = null;

    public function __construct()
    {
        $this->apiVersion = config('social.providers.meta.api_version', 'v18.0');
    }

    /**
     * Set the page context for uploads.
     */
    public function forPage(string $pageId, string $pageToken): static
    {
        $this->pageId = $pageId;
        $this->pageToken = $pageToken;

        return $this;
    }

    public function upload(array $item): Response
    {
        if (! $this->pageId || ! $this->pageToken) {
            return $this->error('Page ID and token required. Call forPage() first.');
        }

        $response = $this->http()
            ->attach('source', file_get_contents($item['path']), $item['name'] ?? 'photo.jpg')
            ->post(self::API_URL."/{$this->apiVersion}/{$this->pageId}/photos", [
                'access_token' => $this->pageToken,
                'caption' => $item['alt_text'] ?? '',
                'published' => $item['published'] ?? false, // Unpublished for multi-photo posts
            ]);

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['id'],
        ]);
    }
}
