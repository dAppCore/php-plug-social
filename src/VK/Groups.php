<?php

declare(strict_types=1);

namespace Core\Plug\Social\VK;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Listable;
use Core\Plug\Response;

/**
 * VK groups listing.
 */
class Groups implements Listable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.vk.com/method';

    private const API_VERSION = '5.199';

    public function listEntities(array $params = []): Response
    {
        $requestParams = [
            'extended' => 1,
            'filter' => 'admin,editor,moder',
            'fields' => 'photo_200,screen_name',
            'access_token' => $this->accessToken(),
            'v' => self::API_VERSION,
        ];

        $response = $this->http()
            ->get(self::API_URL.'/groups.get', $requestParams)
            ->json();

        if (isset($response['error'])) {
            return $this->error($response['error']['error_msg'] ?? 'Failed to get groups');
        }

        $groups = [];

        foreach ($response['response']['items'] ?? [] as $group) {
            $groups[] = [
                'id' => $group['id'],
                'name' => $group['name'],
                'screen_name' => $group['screen_name'] ?? '',
                'image' => $group['photo_200'] ?? '',
                'is_admin' => $group['is_admin'] ?? false,
                'admin_level' => $group['admin_level'] ?? 0,
            ];
        }

        return $this->ok($groups);
    }
}
