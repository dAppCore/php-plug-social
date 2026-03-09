<?php

declare(strict_types=1);

namespace Core\Plug\Social\Twitter;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Authenticable;
use Core\Plug\Contract\Refreshable;
use Core\Plug\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Twitter/X OAuth2 PKCE authentication.
 */
class Auth implements Authenticable, Refreshable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.twitter.com';

    private const AUTH_URL = 'https://twitter.com/i/oauth2/authorize';

    protected string $scope = 'tweet.read tweet.write users.read offline.access';

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUrl,
        private readonly ?string $state = null
    ) {}

    public static function identifier(): string
    {
        return 'twitter';
    }

    public static function name(): string
    {
        return 'X';
    }

    public function getAuthUrl(): string
    {
        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->generateCodeChallenge($codeVerifier);
        $state = $this->state ?? Str::random(40);

        Cache::put("twitter_pkce_{$state}", $codeVerifier, now()->addMinutes(10));

        return $this->buildUrl(self::AUTH_URL, [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUrl,
            'scope' => $this->scope,
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    public function requestAccessToken(array $params): array
    {
        $state = $params['state'] ?? '';
        $codeVerifier = Cache::pull("twitter_pkce_{$state}");

        if (! $codeVerifier) {
            return ['error' => 'Invalid or expired authorisation code'];
        }

        $response = $this->http()
            ->asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->post(self::API_URL.'/2/oauth2/token', [
                'code' => $params['code'],
                'grant_type' => 'authorization_code',
                'client_id' => $this->clientId,
                'redirect_uri' => $this->redirectUrl,
                'code_verifier' => $codeVerifier,
            ])
            ->json();

        if (isset($response['error'])) {
            return ['error' => $response['error_description'] ?? $response['error'] ?? 'Unknown error'];
        }

        $expiresIn = $response['expires_in'] ?? 7200;

        return [
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'] ?? null,
            'expires_in' => Carbon::now('UTC')->addSeconds($expiresIn)->timestamp,
            'token_type' => $response['token_type'] ?? 'bearer',
            'scope' => $response['scope'] ?? $this->scope,
        ];
    }

    public function getAccount(): Response
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->get(self::API_URL.'/2/users/me', [
                'user.fields' => 'id,name,username,profile_image_url',
            ]);

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['data']['id'],
            'name' => $data['data']['name'],
            'username' => $data['data']['username'],
            'image' => $data['data']['profile_image_url'] ?? null,
        ]);
    }

    public function refresh(): Response
    {
        $token = $this->getToken();

        if (! isset($token['refresh_token'])) {
            return $this->unauthorized('No refresh token available');
        }

        $response = $this->http()
            ->asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->post(self::API_URL.'/2/oauth2/token', [
                'refresh_token' => $token['refresh_token'],
                'grant_type' => 'refresh_token',
                'client_id' => $this->clientId,
            ]);

        return $this->fromHttp($response, fn ($data) => [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $token['refresh_token'],
            'expires_in' => Carbon::now('UTC')->addSeconds($data['expires_in'] ?? 7200)->timestamp,
            'token_type' => $data['token_type'] ?? 'bearer',
        ]);
    }

    /**
     * Generate a cryptographically secure code verifier for PKCE.
     */
    private function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * Generate a code challenge from the code verifier using SHA256.
     */
    private function generateCodeChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }

    /**
     * Get external URL to a user's profile.
     */
    public static function externalAccountUrl(string $username): string
    {
        return "https://x.com/{$username}";
    }
}
