<?php

declare(strict_types=1);

namespace Core\Plug\Social\Meta;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Listable;
use Core\Plug\Response;

/**
 * Meta (Facebook/Instagram) pages listing.
 *
 * Lists Facebook Pages and linked Instagram Business accounts.
 */
class Pages implements Listable
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

    public function listEntities(array $params = []): Response
    {
        $response = $this->http()
            ->get(self::API_URL."/{$this->apiVersion}/me/accounts", [
                'access_token' => $this->accessToken(),
                'fields' => 'id,name,access_token,picture{url},instagram_business_account{id,name,username,profile_picture_url}',
            ]);

        return $this->fromHttp($response, function ($data) {
            $pages = [];

            foreach ($data['data'] ?? [] as $page) {
                // Facebook Page
                $pages[] = [
                    'id' => $page['id'],
                    'name' => $page['name'],
                    'username' => '',
                    'image' => $page['picture']['data']['url'] ?? null,
                    'access_token' => $page['access_token'],
                    'type' => 'facebook_page',
                ];

                // Instagram Business Account (if connected)
                if (isset($page['instagram_business_account'])) {
                    $ig = $page['instagram_business_account'];
                    $pages[] = [
                        'id' => $ig['id'],
                        'name' => $ig['name'] ?? $ig['username'],
                        'username' => $ig['username'] ?? '',
                        'image' => $ig['profile_picture_url'] ?? null,
                        'page_access_token' => $page['access_token'],
                        'type' => 'instagram',
                    ];
                }
            }

            return $pages;
        });
    }
}
