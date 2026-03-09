<?php

declare(strict_types=1);

namespace Core\Plug\Social\Meta;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Authenticable;
use Core\Plug\Response;
use Illuminate\Support\Carbon;

/**
 * Meta (Facebook/Instagram) OAuth2 authentication.
 *
 * Supports long-lived token exchange for extended access.
 */
class Auth implements Authenticable
{
    use BuildsResponse;
    use UsesHttp;

    private const API_URL = 'https://graph.facebook.com';

    private string $apiVersion;

    private array $scopes = [
        'business_management',
        'pages_show_list',
        'read_insights',
        'pages_manage_posts',
        'pages_read_engagement',
        'pages_manage_engagement',
        'instagram_basic',
        'instagram_content_publish',
        'instagram_manage_insights',
        'instagram_manage_comments',
    ];

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUrl,
        private readonly array $values = []
    ) {
        $this->apiVersion = config('social.providers.meta.api_version', 'v18.0');
    }

    public static function identifier(): string
    {
        return 'meta';
    }

    public static function name(): string
    {
        return 'Meta';
    }

    public function getAuthUrl(): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUrl,
            'scope' => implode(',', $this->scopes),
            'state' => $this->values['state'] ?? '',
            'response_type' => 'code',
        ];

        return $this->buildUrl(self::API_URL."/{$this->apiVersion}/dialog/oauth", $params);
    }

    public function requestAccessToken(array $params = []): array
    {
        $response = $this->http()
            ->post(self::API_URL."/{$this->apiVersion}/oauth/access_token", [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $this->redirectUrl,
                'code' => $params['code'],
            ])
            ->json();

        if (isset($response['error'])) {
            return [
                'error' => $response['error']['message'] ?? 'Unknown error',
            ];
        }

        // Exchange for long-lived token
        $longLivedResponse = $this->http()
            ->get(self::API_URL."/{$this->apiVersion}/oauth/access_token", [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'fb_exchange_token' => $response['access_token'],
            ])
            ->json();

        $expiresIn = $longLivedResponse['expires_in'] ?? 5184000; // 60 days default

        return [
            'access_token' => $longLivedResponse['access_token'] ?? $response['access_token'],
            'expires_in' => Carbon::now('UTC')->addSeconds($expiresIn)->timestamp,
            'token_type' => $longLivedResponse['token_type'] ?? 'bearer',
        ];
    }

    public function getAccount(): Response
    {
        return $this->error('Use Read class with token for account info');
    }
}
