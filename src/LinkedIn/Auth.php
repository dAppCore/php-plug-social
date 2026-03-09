<?php

declare(strict_types=1);

namespace Core\Plug\Social\LinkedIn;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Authenticable;
use Core\Plug\Contract\Refreshable;
use Core\Plug\Response;
use Illuminate\Support\Carbon;

/**
 * LinkedIn OAuth2 authentication.
 *
 * Supports multiple product types: sign_share, sign_open_id_share, community_management.
 */
class Auth implements Authenticable, Refreshable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.linkedin.com';

    private const OAUTH_URL = 'https://www.linkedin.com/oauth';

    private string $apiVersion = 'v2';

    private array $scope = [];

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUrl,
        private readonly array $values = []
    ) {
        $this->setScope();
    }

    public static function identifier(): string
    {
        return 'linkedin';
    }

    public static function name(): string
    {
        return 'LinkedIn';
    }

    protected function setScope(): void
    {
        $product = config('social.providers.linkedin.product', 'community_management');

        $this->scope = match ($product) {
            'sign_share' => ['r_liteprofile', 'r_emailaddress', 'w_member_social'],
            'sign_open_id_share' => ['openid', 'profile', 'w_member_social'],
            'community_management' => [
                'w_member_social',
                'w_member_social_feed',
                'r_basicprofile',
                'r_organization_social',
                'r_organization_social_feed',
                'w_organization_social',
                'w_organization_social_feed',
                'rw_organization_admin',
            ],
            default => []
        };
    }

    protected function httpHeaders(): array
    {
        return [
            'X-Restli-Protocol-Version' => '2.0.0',
            'LinkedIn-Version' => '202507',
        ];
    }

    public function getAuthUrl(): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUrl,
            'scope' => urlencode(implode(' ', $this->scope)),
            'state' => $this->values['state'] ?? '',
            'response_type' => 'code',
        ];

        $url = self::OAUTH_URL."/{$this->apiVersion}/authorization";

        return str_replace('%2B', '%20', $this->buildUrl($url, $params));
    }

    public function requestAccessToken(array $params = []): array
    {
        $response = $this->http()
            ->withHeaders($this->httpHeaders())
            ->asForm()
            ->post(self::OAUTH_URL."/{$this->apiVersion}/accessToken", [
                'grant_type' => 'authorization_code',
                'code' => $params['code'],
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $this->redirectUrl,
            ])
            ->json();

        if (isset($response['serviceErrorCode']) || isset($response['error'])) {
            return [
                'error' => $response['message'] ?? $response['error_description'] ?? 'Unknown error',
            ];
        }

        return [
            'access_token' => $response['access_token'],
            'expires_in' => Carbon::now('UTC')->addSeconds($response['expires_in'])->timestamp,
            'refresh_token' => $response['refresh_token'] ?? '',
        ];
    }

    public function refresh(): Response
    {
        $response = $this->http()
            ->withHeaders($this->httpHeaders())
            ->asForm()
            ->post(self::OAUTH_URL."/{$this->apiVersion}/accessToken", [
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->getToken()['refresh_token'] ?? '',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

        return $this->fromHttp($response, fn ($data) => [
            'access_token' => $data['access_token'],
            'expires_in' => Carbon::now('UTC')->addSeconds($data['expires_in'])->timestamp,
            'refresh_token' => $data['refresh_token'] ?? '',
        ]);
    }

    public function getAccount(): Response
    {
        return $this->error('Use Read class with token for account info');
    }
}
