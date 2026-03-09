<?php

declare(strict_types=1);

namespace Core\Plug\Social\LinkedIn;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\MediaUploadable;
use Core\Plug\Response;

/**
 * LinkedIn media upload.
 *
 * Uses two-step process: register upload, then upload file.
 */
class Media implements MediaUploadable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.linkedin.com';

    private string $apiVersion = 'v2';

    private ?string $authorUrn = null;

    protected function httpHeaders(): array
    {
        return [
            'X-Restli-Protocol-Version' => '2.0.0',
            'LinkedIn-Version' => '202507',
        ];
    }

    /**
     * Set the author URN for uploads.
     */
    public function forAuthor(string $authorUrn): static
    {
        $this->authorUrn = $authorUrn;

        return $this;
    }

    public function upload(array $item): Response
    {
        if (! $this->authorUrn) {
            return $this->error('Author URN required. Call forAuthor() first.');
        }

        // Register upload
        $registerResponse = $this->http()
            ->withToken($this->accessToken())
            ->withHeaders($this->httpHeaders())
            ->post(self::API_URL."/{$this->apiVersion}/assets?action=registerUpload", [
                'registerUploadRequest' => [
                    'recipes' => ['urn:li:digitalmediaRecipe:feedshare-image'],
                    'owner' => $this->authorUrn,
                    'serviceRelationships' => [
                        [
                            'relationshipType' => 'OWNER',
                            'identifier' => 'urn:li:userGeneratedContent',
                        ],
                    ],
                ],
            ]);

        if (! $registerResponse->successful()) {
            return $this->error('Failed to register media upload');
        }

        $registerData = $registerResponse->json();
        $uploadUrl = $registerData['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'] ?? null;
        $asset = $registerData['value']['asset'] ?? null;

        if (! $uploadUrl || ! $asset) {
            return $this->error('Failed to get upload URL');
        }

        // Upload the file
        $uploadResponse = $this->http()
            ->withToken($this->accessToken())
            ->attach('file', file_get_contents($item['path']), $item['name'] ?? 'image')
            ->put($uploadUrl);

        if (! $uploadResponse->successful()) {
            return $this->error('Failed to upload media');
        }

        return $this->ok([
            'status' => 'READY',
            'media' => $asset,
            'title' => [
                'text' => $item['alt_text'] ?? '',
            ],
        ]);
    }
}
