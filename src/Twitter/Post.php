<?php

declare(strict_types=1);

namespace Core\Plug\Social\Twitter;

use Core\Plug\Concern\BuildsResponse;
use Core\Plug\Concern\ManagesTokens;
use Core\Plug\Concern\UsesHttp;
use Core\Plug\Contract\Postable;
use Core\Plug\Response;
use Illuminate\Support\Collection;

/**
 * Twitter/X post publishing.
 */
class Post implements Postable
{
    use BuildsResponse;
    use ManagesTokens;
    use UsesHttp;

    private const API_URL = 'https://api.twitter.com';

    public function publish(string $text, Collection $media, array $params = []): Response
    {
        $tweetData = ['text' => $text];

        // Handle media attachments
        if ($media->isNotEmpty()) {
            $mediaUploader = (new Media)->withToken($this->token);
            $mediaIds = [];

            foreach ($media as $item) {
                $result = $mediaUploader->upload($item);

                if ($result->hasError()) {
                    return $result;
                }

                $mediaIds[] = $result->get('media_id');
            }

            if (! empty($mediaIds)) {
                $tweetData['media'] = ['media_ids' => $mediaIds];
            }
        }

        // Handle reply
        if (! empty($params['reply_to'])) {
            $tweetData['reply'] = ['in_reply_to_tweet_id' => $params['reply_to']];
        }

        // Handle quote tweet
        if (! empty($params['quote_tweet_id'])) {
            $tweetData['quote_tweet_id'] = $params['quote_tweet_id'];
        }

        $response = $this->http()
            ->withToken($this->accessToken())
            ->post(self::API_URL.'/2/tweets', $tweetData);

        return $this->fromHttp($response, fn ($data) => [
            'id' => $data['data']['id'],
            'text' => $data['data']['text'],
        ]);
    }

    /**
     * Get external URL to a tweet.
     */
    public static function externalPostUrl(string $username, string $postId): string
    {
        return "https://x.com/{$username}/status/{$postId}";
    }
}
