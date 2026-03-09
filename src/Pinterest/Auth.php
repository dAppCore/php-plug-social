<?php

declare(strict_types=1);

namespace Core\Plug\Social\Pinterest;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Authenticable;
use Core\Plug\Contract\Refreshable;
use Core\Plug\Response;
use Illuminate\Support\Carbon;

/**
 * Pinterest OAuth2 authentication.
 *
 * Uses Basic Auth for token requests.
 */
class Auth implements Authenticable, Refreshable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.pinterest.com/v5';

    private const AUTH_URL = 'https://www.pinterest.com/oauth';

    private array $scope = ['boards:read', 'boards:write', 'pins:read', 'pins:write', 'user_accounts:read'];

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUrl,
        private readonly array $values = []
    ) {}

    public static function identifier(): string
    {
        return 'pinterest';
    }

    public static function name(): string
    {
        return 'Pinterest';
    }

    public function getAuthUrl(): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUrl,
            'scope' => implode(',', $this->scope),
            'state' => $this->values['state'] ?? '',
            'response_type' => 'code',
        ];

        return $this->buildUrl(self::AUTH_URL, $params);
    }

    public function requestAccessToken(array $params = []): array
    {
        $response = $this->http()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->post(self::API_URL.'/oauth/token', [
                'grant_type' => 'authorization_code',
                'code' => $params['code'],
                'redirect_uri' => $this->redirectUrl,
            ])
            ->json();

        if (isset($response['error'])) {
            return [
                'error' => $response['error_description'] ?? $response['message'] ?? 'Unknown error',
            ];
        }

        return [
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'] ?? '',
            'expires_in' => Carbon::now('UTC')->addSeconds($response['expires_in'] ?? 86400)->timestamp,
        ];
    }

    public function refresh(): Response
    {
        $response = $this->http()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->post(self::API_URL.'/oauth/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->getToken()['refresh_token'] ?? '',
            ]);

        return $this->fromHttp($response, fn ($data) => [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? '',
            'expires_in' => Carbon::now('UTC')->addSeconds($data['expires_in'] ?? 86400)->timestamp,
        ]);
    }

    public function getAccount(): Response
    {
        return $this->error('Use Read class with token for account info');
    }
}
