<?php
namespace verbb\comments\services;

use verbb\comments\Comments as CommentsPlugin;
use verbb\comments\elements\Comment;
use verbb\comments\exceptions\CommentValidationException;

use Craft;
use craft\base\Component;

use verbb\editor\helpers\Giphy as EditorGiphy;

/**
 * GIFs from GIPHY beside a comment's text (SD-52). GIPHY's API terms have each visitor's browser search
 * GIPHY directly and use what it returns unchanged, so Comments only hands the browser the site's key and
 * options, and checks the GIF a comment is saved with comes from GIPHY. The search itself and that check
 * are Editor's, so the plain form and rich authoring share them.
 */
class Giphy extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Whether commenters can add a GIF: GIPHY is on and has a key.
     */
    public function isEnabled(): bool
    {
        $settings = CommentsPlugin::$plugin->getSettings();

        return $settings->giphyEnabled && $settings->getGiphyApiKey() !== null;
    }

    /**
     * What the browser's GIF picker needs to search GIPHY, or null when it's off. Only given to someone
     * who can comment, because the key is GIPHY's public web key and only they need it.
     */
    public function browserConfig(): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $settings = CommentsPlugin::$plugin->getSettings();

        return [
            'apiKey' => $settings->getGiphyApiKey(),
            'rating' => $settings->giphyRating,
            'limit' => $settings->giphyLimit,
            // GIPHY takes a two-letter language for searches in other languages.
            'language' => strtolower(substr(Craft::$app->language, 0, 2)),
        ];
    }

    /**
     * Reads a write's `gif`, as `{ url, width, height }` or null, into the GIF a comment should have, or
     * refuses it. A GIF can only be added while GIPHY is on, and has to come from GIPHY; taking one off is
     * always allowed.
     *
     * @return array{url: string, width: ?int, height: ?int}|null
     */
    public function fromInput(mixed $gif): ?array
    {
        if ($gif === null) {
            return null;
        }

        if (!$this->isEnabled()) {
            throw CommentValidationException::fromErrors(['content' => [Craft::t('comments', 'GIFs aren’t available.')]]);
        }

        $normalized = is_array($gif) ? EditorGiphy::normalize($gif) : null;

        if ($normalized === null) {
            throw CommentValidationException::fromErrors(['content' => [Craft::t('comments', 'Invalid GIF.')]]);
        }

        return $normalized;
    }

    /**
     * Gives a comment the GIF `fromInput()` read, or takes its GIF away.
     *
     * @param array{url: string, width: ?int, height: ?int}|null $gif
     */
    public function apply(Comment $comment, ?array $gif): void
    {
        $comment->gifUrl = $gif['url'] ?? null;
        $comment->gifWidth = $gif['width'] ?? null;
        $comment->gifHeight = $gif['height'] ?? null;
    }

    /**
     * A comment's GIF in the shape writes take it, for its author editing it, or null without one.
     */
    public function forComment(Comment $comment): ?array
    {
        if (!$comment->gifUrl) {
            return null;
        }

        return [
            'url' => $comment->gifUrl,
            'width' => $comment->gifWidth,
            'height' => $comment->gifHeight,
        ];
    }
}
