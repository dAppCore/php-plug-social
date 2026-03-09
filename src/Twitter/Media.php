<?php

declare(strict_types=1);

namespace Core\Plug\Social\Twitter;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\MediaUploadable;
use Core\Plug\Response;

/**
 * Twitter/X media upload.
 *
 * Supports both simple upload and chunked upload for large files.
 */
class Media implements MediaUploadable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const UPLOAD_URL = 'https://upload.twitter.com';

    private const CHUNK_SIZE = 5 * 1024 * 1024; // 5MB

    public function upload(array $item): Response
    {
        $filePath = $item['path'];
        $fileSize = filesize($filePath);

        // Use chunked upload for files > 5MB
        if ($fileSize > self::CHUNK_SIZE) {
            return $this->uploadChunked($filePath, $fileSize, mime_content_type($filePath));
        }

        return $this->uploadSimple($filePath);
    }

    /**
     * Simple upload for small files.
     */
    private function uploadSimple(string $filePath): Response
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->attach('media', file_get_contents($filePath), basename($filePath))
            ->post(self::UPLOAD_URL.'/1.1/media/upload.json');

        return $this->fromHttp($response, fn ($data) => [
            'media_id' => (string) $data['media_id'],
            'media_id_string' => $data['media_id_string'],
        ]);
    }

    /**
     * Chunked upload for large files.
     */
    private function uploadChunked(string $filePath, int $fileSize, string $mimeType): Response
    {
        // INIT
        $initResponse = $this->http()
            ->withToken($this->accessToken())
            ->asForm()
            ->post(self::UPLOAD_URL.'/1.1/media/upload.json', [
                'command' => 'INIT',
                'total_bytes' => $fileSize,
                'media_type' => $mimeType,
            ]);

        if (! $initResponse->successful()) {
            return $this->fromHttp($initResponse);
        }

        $mediaId = $initResponse->json('media_id_string');

        // APPEND chunks
        $handle = fopen($filePath, 'rb');
        $segmentIndex = 0;

        while (! feof($handle)) {
            $chunk = fread($handle, self::CHUNK_SIZE);

            $appendResponse = $this->http()
                ->withToken($this->accessToken())
                ->attach('media', $chunk, 'chunk')
                ->post(self::UPLOAD_URL.'/1.1/media/upload.json', [
                    'command' => 'APPEND',
                    'media_id' => $mediaId,
                    'segment_index' => $segmentIndex,
                ]);

            if (! $appendResponse->successful()) {
                fclose($handle);

                return $this->fromHttp($appendResponse);
            }

            $segmentIndex++;
        }

        fclose($handle);

        // FINALIZE
        $finalizeResponse = $this->http()
            ->withToken($this->accessToken())
            ->asForm()
            ->post(self::UPLOAD_URL.'/1.1/media/upload.json', [
                'command' => 'FINALIZE',
                'media_id' => $mediaId,
            ]);

        return $this->fromHttp($finalizeResponse, fn ($data) => [
            'media_id' => $data['media_id_string'],
            'media_id_string' => $data['media_id_string'],
        ]);
    }
}
