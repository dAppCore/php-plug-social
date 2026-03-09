<?php

declare(strict_types=1);

namespace Core\Plug\Social\VK;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Readable;
use Core\Plug\Response;

/**
 * VK account and post reading.
 */
class Read implements Readable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.vk.com/method';

    private const API_VERSION = '5.199';

    public function get(string $id): Response
    {
        $response = $this->vkRequest('wall.getById', [
            'posts' => $id,
        ]);

        if (isset($response['error'])) {
            return $this->error($response['error']['error_msg'] ?? 'Failed to get post');
        }

        $post = $response['response'][0] ?? null;

        if (! $post) {
            return $this->error('Post not found');
        }

        return $this->ok([
            'id' => $post['id'],
            'text' => $post['text'] ?? '',
            'date' => $post['date'] ?? null,
            'owner_id' => $post['owner_id'] ?? null,
        ]);
    }

    /**
     * Get the authenticated user's account info.
     */
    public function me(): Response
    {
        $response = $this->vkRequest('users.get', [
            'fields' => 'photo_200,screen_name',
        ]);

        if (isset($response['error'])) {
            return $this->error($response['error']['error_msg'] ?? 'Failed to get user information');
        }

        $user = $response['response'][0] ?? null;

        if (! $user) {
            return $this->error('User not found');
        }

        $name = trim(($user['first_name'] ?? '').' '.($user['last_name'] ?? ''));

        return $this->ok([
            'id' => (string) $user['id'],
            'name' => $name ?: 'VK User',
            'username' => $user['screen_name'] ?? '',
            'image' => $user['photo_200'] ?? '',
            'data' => [
                'user_id' => $user['id'],
                'screen_name' => $user['screen_name'] ?? '',
            ],
        ]);
    }

    public function list(array $params = []): Response
    {
        $ownerId = $params['owner_id'] ?? $this->getToken()['data']['user_id'] ?? null;

        if (! $ownerId) {
            return $this->error('owner_id is required');
        }

        $response = $this->vkRequest('wall.get', [
            'owner_id' => $ownerId,
            'count' => $params['limit'] ?? 20,
            'offset' => $params['offset'] ?? 0,
        ]);

        if (isset($response['error'])) {
            return $this->error($response['error']['error_msg'] ?? 'Failed to get posts');
        }

        return $this->ok([
            'posts' => $response['response']['items'] ?? [],
            'count' => $response['response']['count'] ?? 0,
        ]);
    }

    protected function vkRequest(string $method, array $params = []): array
    {
        $params['access_token'] = $this->accessToken();
        $params['v'] = self::API_VERSION;

        $response = $this->http()
            ->get(self::API_URL.'/'.$method, $params);

        return $response->json() ?? [];
    }
}
