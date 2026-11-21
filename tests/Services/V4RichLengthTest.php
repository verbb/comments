<?php
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\ContentFormat;
use verbb\editor\RichText;

/**
 * Maximum Comment Length counts a rich comment as Editor's character limit does, without image alt
 * text, so a comment the editor allows isn't refused.
 */

beforeEach(function() {
    $this->original = Comments::$plugin->getSettings()->getAttributes();
    Comments::$plugin->getSettings()->securityMaxLength = 30;
});

afterEach(function() {
    Comments::$plugin->getSettings()->setAttributes($this->original, false);
});

function richLengthComment(string $text, ?string $alt = null): Comment
{
    $content = [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]];

    if ($alt !== null) {
        $content[] = ['type' => 'editorAsset', 'attrs' => ['id' => 1, 'alt' => $alt]];
    }

    $document = json_encode(['type' => 'doc', 'content' => $content]);

    // As `Comments::create()` builds it: `comment` is the document's plain text, alt text included.
    return new Comment([
        'contentFormat' => ContentFormat::Rich->value,
        'bodyContent' => $document,
        'comment' => trim(RichText::from($document)->toPlainText()),
    ]);
}

it('accepts a rich comment whose text fits, whatever its image alt text', function() {
    $comment = richLengthComment('Planting dates for next year', 'Seedlings in the greenhouse');

    expect(mb_strlen($comment->comment))->toBeGreaterThan(30)
        ->and(Comments::$plugin->getSecurity()->checkCommentLength($comment))->toBeTrue()
        ->and(Comments::$plugin->getSecurity()->checkCommentLength(richLengthComment('Planting dates for next year!!', 'Seedlings in the greenhouse')))->toBeTrue();
});

it('refuses a rich comment whose text is over the limit', function() {
    expect(Comments::$plugin->getSecurity()->checkCommentLength(richLengthComment('Planting dates for the next year', 'Seedlings')))->toBeFalse()
        ->and(Comments::$plugin->getSecurity()->checkCommentLength(richLengthComment('Planting dates for the next year')))->toBeFalse();
});

it('counts plain comments as before', function() {
    expect(Comments::$plugin->getSecurity()->checkCommentLength(new Comment(['comment' => str_repeat('a', 30)])))->toBeTrue()
        ->and(Comments::$plugin->getSecurity()->checkCommentLength(new Comment(['comment' => str_repeat('a', 31)])))->toBeFalse();
});
