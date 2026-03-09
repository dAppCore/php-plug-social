<?php

declare(strict_types=1);

namespace Core\Plug\Social\LinkedIn;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Listable;
use Core\Plug\Response;

/**
 * LinkedIn organization pages listing.
 */
class Pages implements Listable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.linkedin.com';

    private string $apiVersion = 'v2';

    protected function httpHeaders(): array
    {
        return [
            'X-Restli-Protocol-Version' => '2.0.0',
            'LinkedIn-Version' => '202507',
        ];
    }

    public function listEntities(array $params = []): Response
    {
        $response = $this->http()
            ->withToken($this->accessToken())
            ->withHeaders($this->httpHeaders())
            ->get(self::API_URL."/{$this->apiVersion}/organizationAcls", [
                'q' => 'roleAssignee',
                'projection' => '(elements*(organization~(id,localizedName,vanityName,logoV2(cropped~:playableStreams))))',
            ]);

        return $this->fromHttp($response, function ($data) {
            $pages = [];

            foreach ($data['elements'] ?? [] as $element) {
                $org = $element['organization~'] ?? null;
                if (! $org) {
                    continue;
                }

                $logo = null;
                if (isset($org['logoV2']['cropped~']['elements'][0]['identifiers'][0]['identifier'])) {
                    $logo = $org['logoV2']['cropped~']['elements'][0]['identifiers'][0]['identifier'];
                }

                $pages[] = [
                    'id' => $org['id'],
                    'name' => $org['localizedName'] ?? '',
                    'username' => $org['vanityName'] ?? '',
                    'image' => $logo,
                ];
            }

            return $pages;
        });
    }
}
