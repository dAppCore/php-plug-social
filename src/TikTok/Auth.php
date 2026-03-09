<?php

declare(strict_types=1);

namespace Core\Plug\Social\TikTok;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Authenticable;
use Core\Plug\Contract\Refreshable;
use Core\Plug\Response;
use Illuminate\Support\Carbon;

/**
 * TikTok OAuth2 authentication.
 *
 * Uses client_key instead of client_id.
 */
class Auth implements Authenticable, Refreshable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://open.tiktokapis.com/v2';

    private const AUTH_URL = 'https://www.tiktok.com/v2/auth/authorize';

    private array $scope = ['user.info.basic', 'video.publish', 'video.upload'];

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUrl,
        private readonly array $values = []
    ) {}

    public static function identifier(): string
    {
        return 'tiktok';
    }

    public static function name(): string
    {
        return 'TikTok';
    }

    public function getAuthUrl(): string
    {
        $params = [
            'client_key' => $this->clientId,
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
            ->asForm()
            ->post(self::API_URL.'/oauth/token/', [
                'client_key' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $params['code'],
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirectUrl,
            ])
            ->json();

        if (isset($response['error'])) {
            return [
                'error' => $response['error_description'] ?? $response['error'],
            ];
        }

        return [
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'] ?? '',
            'expires_in' => Carbon::now('UTC')->addSeconds($response['expires_in'] ?? 86400)->timestamp,
            'open_id' => $response['open_id'] ?? '',
        ];
    }

    public function refresh(): Response
    {
        $response = $this->http()
            ->asForm()
            ->post(self::API_URL.'/oauth/token/', [
                'client_key' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->getToken()['refresh_token'] ?? '',
            ]);

        return $this->fromHttp($response, fn ($data) => [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? '',
            'expires_in' => Carbon::now('UTC')->addSeconds($data['expires_in'] ?? 86400)->timestamp,
            'open_id' => $data['open_id'] ?? '',
        ]);
    }

    public function getAccount(): Response
    {
        return $this->error('Use Read class with token for account info');
    }
}
