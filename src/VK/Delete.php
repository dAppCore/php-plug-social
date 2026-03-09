<?php

declare(strict_types=1);

namespace Core\Plug\Social\VK;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Deletable;
use Core\Plug\Response;

/**
 * VK wall post deletion.
 */
class Delete implements Deletable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.vk.com/method';

    private const API_VERSION = '5.199';

    private ?string $ownerId = null;

    /**
     * Set the owner context for deletion.
     */
    public function forOwner(string $ownerId): static
    {
        $this->ownerId = $ownerId;

        return $this;
    }

    public function delete(string $id): Response
    {
        $ownerId = $this->ownerId ?? $this->getToken()['data']['user_id'] ?? '';

        $response = $this->http()
            ->get(self::API_URL.'/wall.delete', [
                'owner_id' => $ownerId,
                'post_id' => (int) $id,
                'access_token' => $this->accessToken(),
                'v' => self::API_VERSION,
            ])
            ->json();

        if (isset($response['error'])) {
            return $this->error($response['error']['error_msg'] ?? 'Failed to delete post');
        }

        return $this->ok([
            'deleted' => true,
        ]);
    }
}
