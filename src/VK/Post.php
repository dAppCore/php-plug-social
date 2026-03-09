<?php

declare(strict_types=1);

namespace Core\Plug\Social\VK;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Postable;
use Core\Plug\Response;
use Illuminate\Support\Collection;

/**
 * VK wall post publishing.
 */
class Post implements Postable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.vk.com/method';

    private const API_VERSION = '5.199';

    public function publish(string $text, Collection $media, array $params = []): Response
    {
        $groupId = $params['group_id'] ?? null;
        $ownerId = $groupId ? "-{$groupId}" : ($this->getToken()['data']['user_id'] ?? '');

        $postParams = [
            'owner_id' => $ownerId,
            'message' => $text,
            'from_group' => $groupId ? 1 : 0,
            'access_token' => $this->accessToken(),
            'v' => self::API_VERSION,
        ];

        // Handle media attachments
        if ($media->isNotEmpty()) {
            $attachments = [];
            $mediaUploader = (new Media)->withToken($this->getToken());

            if ($groupId) {
                $mediaUploader->forGroup((int) $groupId);
            }

            foreach ($media as $item) {
                $result = $mediaUploader->upload($item);

                if ($result->hasError()) {
                    continue; // Skip failed uploads
                }

                $attachments[] = $result->get('attachment');
            }

            if (! empty($attachments)) {
                $postParams['attachments'] = implode(',', $attachments);
            }
        }

        // Handle link attachment
        if (! empty($params['link'])) {
            $existingAttachments = $postParams['attachments'] ?? '';
            $postParams['attachments'] = $existingAttachments
                ? $existingAttachments.','.$params['link']
                : $params['link'];
        }

        // Handle scheduled publishing
        if (! empty($params['publish_date'])) {
            $postParams['publish_date'] = $params['publish_date'];
        }

        $response = $this->http()
            ->get(self::API_URL.'/wall.post', $postParams)
            ->json();

        if (isset($response['error'])) {
            $errorCode = $response['error']['error_code'] ?? 0;

            // Handle rate limiting (error code 9)
            if ($errorCode === 9) {
                return $this->rateLimit(60);
            }

            // Handle captcha required (error code 14)
            if ($errorCode === 14) {
                return $this->error('Captcha required. Please post from VK directly.');
            }

            return $this->error($response['error']['error_msg'] ?? 'Failed to publish post');
        }

        $postId = $response['response']['post_id'] ?? null;

        if (! $postId) {
            return $this->error('Post created but no ID returned');
        }

        return $this->ok([
            'id' => (string) $postId,
            'owner_id' => $ownerId,
            'post_id' => $postId,
        ]);
    }

    /**
     * Get external URL to a VK post.
     */
    public static function externalPostUrl(string $ownerId, string $postId): string
    {
        return "https://vk.com/wall{$ownerId}_{$postId}";
    }

    /**
     * Get external URL to a VK profile.
     */
    public static function externalAccountUrl(string $accountId, ?string $screenName = null): string
    {
        if ($screenName) {
            return "https://vk.com/{$screenName}";
        }

        return "https://vk.com/id{$accountId}";
    }
}
