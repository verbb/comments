<?php
namespace verbb\comments\services;

use verbb\comments\Comments;

use Craft;
use craft\base\Component;

use Throwable;

class Giphy extends Component
{
    // Constants
    // =========================================================================

    public const API_BASE = 'https://api.giphy.com/v1/gifs/';

    // The hosts we allow GIF URLs to be served from. Used both here and when
    // validating a chosen GIF on the comment element, to prevent arbitrary
    // markup/URLs being stored against a comment.
    public const ALLOWED_HOST_PATTERN = '#^https://([a-z0-9-]+\.)?giphy\.com/#i';


    // Public Methods
    // =========================================================================

    public function isEnabled(): bool
    {
        $settings = Comments::$plugin->getSettings();

        return $settings->giphyEnabled && (bool)$settings->getGiphyApiKey();
    }

    public function search(?string $query = null): array
    {
        $settings = Comments::$plugin->getSettings();
        $query = trim((string)$query);

        // With no search term, show what's trending rather than nothing
        $endpoint = $query === '' ? 'trending' : 'search';

        $params = [
            'api_key' => $settings->getGiphyApiKey(),
            'limit' => $settings->giphyLimit,
            'rating' => $settings->giphyRating,
            'bundle' => 'messaging_non_clips',
        ];

        if ($endpoint === 'search') {
            $params['q'] = $query;
        }

        try {
            $client = Craft::createGuzzleClient();
            $response = $client->get(self::API_BASE . $endpoint, ['query' => $params]);
            $data = json_decode((string)$response->getBody(), true);
        } catch (Throwable $e) {
            Comments::error('Giphy request failed: {message}', ['message' => $e->getMessage()]);

            return [];
        }

        return $this->_normalizeResults($data['data'] ?? []);
    }

    public function isValidGifUrl(?string $url): bool
    {
        if (!$url) {
            return false;
        }

        return (bool)preg_match(self::ALLOWED_HOST_PATTERN, $url);
    }


    // Private Methods
    // =========================================================================

    private function _normalizeResults(array $items): array
    {
        $results = [];

        foreach ($items as $item) {
            $images = $item['images'] ?? [];

            // The GIF we'll store and display in the comment - keep it reasonably sized
            $full = $images['downsized']['url'] ?? $images['original']['url'] ?? null;

            // A smaller, animated still for the picker grid
            $preview = $images['fixed_width_small']['url']
                ?? $images['fixed_height_small']['url']
                ?? $images['preview_gif']['url']
                ?? $full;

            if (!$full || !$this->isValidGifUrl($full)) {
                continue;
            }

            $results[] = [
                'id' => $item['id'] ?? '',
                'url' => $full,
                'preview' => $preview,
                'title' => $item['title'] ?? '',
                'width' => (int)($images['downsized']['width'] ?? 0),
                'height' => (int)($images['downsized']['height'] ?? 0),
            ];
        }

        return $results;
    }
}
