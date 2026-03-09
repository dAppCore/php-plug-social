<?php

declare(strict_types=1);

namespace Core\Plug\Social\VK;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Authenticable;
use Core\Plug\Response;

/**
 * VK OAuth2 authentication.
 *
 * Uses offline scope for long-lived tokens (no refresh needed).
 */
class Auth implements Authenticable
{
    use BuildsResponse;
    use UsesHttp;

    private const API_VERSION = '5.199';

    private array $scope = ['wall', 'groups', 'photos', 'video', 'offline'];

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUrl,
        private readonly array $values = []
    ) {}

    public static function identifier(): string
    {
        return 'vk';
    }

    public static function name(): string
    {
        return 'VK';
    }

    public function getAuthUrl(): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUrl,
            'display' => 'page',
            'scope' => implode(',', $this->scope),
            'response_type' => 'code',
            'v' => self::API_VERSION,
            'state' => $this->values['state'] ?? '',
        ];

        return 'https://oauth.vk.com/authorize?'.http_build_query($params);
    }

    public function requestAccessToken(array $params = []): array
    {
        $response = $this->http()
            ->get('https://oauth.vk.com/access_token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $this->redirectUrl,
                'code' => $params['code'] ?? '',
            ])
            ->json();

        if (isset($response['error'])) {
            return [
                'error' => $response['error_description'] ?? $response['error'] ?? 'Unknown error',
            ];
        }

        return [
            'access_token' => $response['access_token'] ?? '',
            'expires_in' => isset($response['expires_in'])
                ? now('UTC')->addSeconds($response['expires_in'])->timestamp
                : null,
            'data' => [
                'user_id' => $response['user_id'] ?? null,
                'email' => $response['email'] ?? null,
            ],
        ];
    }

    public function getAccount(): Response
    {
        return $this->error('Use Read class with token for account info');
    }
}
