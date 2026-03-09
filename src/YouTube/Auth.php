<?php

declare(strict_types=1);

namespace Core\Plug\Social\YouTube;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Authenticable;
use Core\Plug\Contract\Refreshable;
use Core\Plug\Response;
use Illuminate\Support\Carbon;

/**
 * YouTube (Google) OAuth2 authentication.
 *
 * Uses Google's OAuth2 with offline access for refresh tokens.
 */
class Auth implements Authenticable, Refreshable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private array $scope = [
        'https://www.googleapis.com/auth/youtube.upload',
        'https://www.googleapis.com/auth/youtube',
        'https://www.googleapis.com/auth/youtube.readonly',
    ];

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUrl,
        private readonly array $values = []
    ) {}

    public static function identifier(): string
    {
        return 'youtube';
    }

    public static function name(): string
    {
        return 'YouTube';
    }

    public function getAuthUrl(): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUrl,
            'scope' => implode(' ', $this->scope),
            'state' => $this->values['state'] ?? '',
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];

        return $this->buildUrl(self::AUTH_URL, $params);
    }

    public function requestAccessToken(array $params = []): array
    {
        $response = $this->http()
            ->asForm()
            ->post(self::TOKEN_URL, [
                'client_id' => $this->clientId,
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
            'expires_in' => Carbon::now('UTC')->addSeconds($response['expires_in'] ?? 3600)->timestamp,
            'scope' => $response['scope'] ?? '',
        ];
    }

    public function refresh(): Response
    {
        $response = $this->http()
            ->asForm()
            ->post(self::TOKEN_URL, [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->getToken()['refresh_token'] ?? '',
                'grant_type' => 'refresh_token',
            ]);

        return $this->fromHttp($response, fn ($data) => [
            'access_token' => $data['access_token'],
            'expires_in' => Carbon::now('UTC')->addSeconds($data['expires_in'] ?? 3600)->timestamp,
            'refresh_token' => $data['refresh_token'] ?? $this->getToken()['refresh_token'] ?? '',
        ]);
    }

    public function getAccount(): Response
    {
        return $this->error('Use Read class with token for account info');
    }
}
