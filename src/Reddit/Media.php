<?php

declare(strict_types=1);

namespace Core\Plug\Social\Reddit;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\MediaUploadable;
use Core\Plug\Response;

/**
 * Reddit media upload.
 *
 * Two-step process: get S3 lease, upload to S3.
 */
class Media implements MediaUploadable
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

    public function upload(array $item): Response
    {
        // Get upload lease
        $leaseResponse = $this->http()
            ->withToken($this->accessToken())
            ->withHeaders($this->redditHeaders())
            ->asForm()
            ->post(self::API_URL.'/api/media/asset.json', [
                'filepath' => $item['name'] ?? 'image.jpg',
                'mimetype' => mime_content_type($item['path']),
            ]);

        if (! $leaseResponse->successful()) {
            return $this->fromHttp($leaseResponse);
        }

        $leaseData = $leaseResponse->json();
        $uploadUrl = $leaseData['args']['action'] ?? null;

        if (! $uploadUrl) {
            return $this->error('Failed to get upload URL');
        }

        // Build S3 upload fields
        $fields = [];
        foreach ($leaseData['args']['fields'] ?? [] as $field) {
            $fields[$field['name']] = $field['value'];
        }

        // Upload to S3
        $s3Response = $this->http()
            ->attach('file', file_get_contents($item['path']), $item['name'] ?? 'image.jpg')
            ->post("https:{$uploadUrl}", $fields);

        if (! $s3Response->successful()) {
            return $this->error('Failed to upload media');
        }

        return $this->ok([
            'url' => "https:{$uploadUrl}/{$fields['key']}",
        ]);
    }
}
