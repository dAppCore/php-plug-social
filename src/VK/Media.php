<?php

declare(strict_types=1);

namespace Core\Plug\Social\VK;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\MediaUploadable;
use Core\Plug\Response;

/**
 * VK media upload.
 *
 * Two-step process: get upload server, upload to server, save photo.
 */
class Media implements MediaUploadable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.vk.com/method';

    private const API_VERSION = '5.199';

    private ?int $groupId = null;

    /**
     * Set the group context for uploads.
     */
    public function forGroup(int $groupId): static
    {
        $this->groupId = $groupId;

        return $this;
    }

    public function upload(array $item): Response
    {
        $type = $item['type'] ?? 'image';

        if ($type === 'video') {
            return $this->error('Video upload not yet implemented');
        }

        return $this->uploadPhoto($item['path']);
    }

    protected function uploadPhoto(string $path): Response
    {
        // Get upload server
        $serverParams = [
            'access_token' => $this->accessToken(),
            'v' => self::API_VERSION,
        ];

        if ($this->groupId) {
            $serverParams['group_id'] = $this->groupId;
        }

        $serverResponse = $this->http()
            ->get(self::API_URL.'/photos.getWallUploadServer', $serverParams)
            ->json();

        if (! isset($serverResponse['response']['upload_url'])) {
            return $this->error('Failed to get upload server');
        }

        $uploadUrl = $serverResponse['response']['upload_url'];

        // Upload the file
        try {
            $uploadResponse = $this->http()
                ->attach('photo', file_get_contents($path), basename($path))
                ->post($uploadUrl)
                ->json();

            if (empty($uploadResponse['photo']) || $uploadResponse['photo'] === '[]') {
                return $this->error('Failed to upload photo');
            }
        } catch (\Exception $e) {
            return $this->error('Upload failed: '.$e->getMessage());
        }

        // Save the photo
        $saveParams = [
            'server' => $uploadResponse['server'],
            'photo' => $uploadResponse['photo'],
            'hash' => $uploadResponse['hash'],
            'access_token' => $this->accessToken(),
            'v' => self::API_VERSION,
        ];

        if ($this->groupId) {
            $saveParams['group_id'] = $this->groupId;
        }

        $saveResponse = $this->http()
            ->get(self::API_URL.'/photos.saveWallPhoto', $saveParams)
            ->json();

        if (! isset($saveResponse['response'][0])) {
            return $this->error('Failed to save photo');
        }

        $photo = $saveResponse['response'][0];

        return $this->ok([
            'attachment' => "photo{$photo['owner_id']}_{$photo['id']}",
            'owner_id' => $photo['owner_id'],
            'id' => $photo['id'],
        ]);
    }
}
