<?php

declare(strict_types=1);

namespace Core\Plug\Social\LinkedIn;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Postable;
use Core\Plug\Response;
use Illuminate\Support\Collection;

/**
 * LinkedIn post publishing.
 */
class Post implements Postable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.linkedin.com';

    private string $apiVersion = 'v2';

    protected function httpHeaders(): array
    {
        return [
            'X-Restli-Protocol-Version' => '2.0.0',
            'LinkedIn-Version' => '202507',
        ];
    }

    public function publish(string $text, Collection $media, array $params = []): Response
    {
        $token = $this->getToken();
        $authorUrn = $params['author_urn'] ?? "urn:li:person:{$token['id']}";

        $post = [
            'author' => $authorUrn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [
                        'text' => $text,
                    ],
                    'shareMediaCategory' => 'NONE',
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
        ];

        if ($media->isNotEmpty()) {
            $uploadedMedia = [];
            $mediaUploader = (new Media)->withToken($token)->forAuthor($authorUrn);

            foreach ($media as $item) {
                $result = $mediaUploader->upload($item);

                if ($result->hasError()) {
                    return $result;
                }

                $uploadedMedia[] = $result->context();
            }

            $post['specificContent']['com.linkedin.ugc.ShareContent']['shareMediaCategory'] = 'IMAGE';
            $post['specificContent']['com.linkedin.ugc.ShareContent']['media'] = $uploadedMedia;
        }

        $response = $this->http()
            ->withToken($this->accessToken())
            ->withHeaders($this->httpHeaders())
            ->post(self::API_URL."/{$this->apiVersion}/ugcPosts", $post);

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['id'] ?? '',
        ]);
    }

    /**
     * Get external URL to a LinkedIn post.
     */
    public static function externalPostUrl(string $postId): string
    {
        return "https://linkedin.com/feed/update/{$postId}";
    }

    /**
     * Get external URL to a LinkedIn profile.
     */
    public static function externalAccountUrl(string $username): string
    {
        return "https://www.linkedin.com/in/{$username}";
    }
}
