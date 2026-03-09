<?php

declare(strict_types=1);

namespace Core\Plug\Social\Meta;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Postable;
use Core\Plug\Response;
use Illuminate\Support\Collection;

/**
 * Meta (Facebook/Instagram) post publishing.
 */
class Post implements Postable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://graph.facebook.com';

    private string $apiVersion;

    public function __construct()
    {
        $this->apiVersion = config('social.providers.meta.api_version', 'v18.0');
    }

    public function publish(string $text, Collection $media, array $params = []): Response
    {
        $pageId = $params['page_id'] ?? null;
        $pageToken = $params['page_access_token'] ?? $this->accessToken();

        if (! $pageId) {
            return $this->error('Page ID is required');
        }

        $postData = [
            'access_token' => $pageToken,
            'message' => $text,
        ];

        // Handle media attachments
        if ($media->isNotEmpty()) {
            $mediaIds = [];
            $mediaUploader = (new Media)->forPage($pageId, $pageToken);

            foreach ($media as $item) {
                $result = $mediaUploader->upload($item);

                if ($result->hasError()) {
                    return $result;
                }

                $mediaIds[] = $result->get('id');
            }

            // For multiple photos, use attached_media
            if (count($mediaIds) > 1) {
                $postData['attached_media'] = array_map(
                    fn ($id) => ['media_fbid' => $id],
                    $mediaIds
                );
            } else {
                // Single photo was already published with the upload
                return $this->ok(['id' => $mediaIds[0]]);
            }
        }

        $response = $this->http()
            ->post(self::API_URL."/{$this->apiVersion}/{$pageId}/feed", $postData);

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['id'],
        ]);
    }

    /**
     * Get external URL to a Facebook post.
     */
    public static function externalPostUrl(string $postId): string
    {
        // Post ID format is usually "pageId_postId"
        $parts = explode('_', $postId);
        if (count($parts) === 2) {
            return "https://www.facebook.com/{$parts[0]}/posts/{$parts[1]}";
        }

        return "https://www.facebook.com/{$postId}";
    }

    /**
     * Get external URL to a Facebook page.
     */
    public static function externalAccountUrl(string $pageId): string
    {
        return "https://www.facebook.com/{$pageId}";
    }
}
