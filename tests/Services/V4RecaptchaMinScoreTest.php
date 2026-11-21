<?php
use verbb\comments\Comments;
use verbb\comments\models\Settings;

/**
 * The Spam Protection page's Minimum Score select shows the saved score, so saving the page unchanged
 * keeps it.
 */

beforeEach(function() {
    $this->original = Comments::$plugin->getSettings()->getAttributes();
});

afterEach(function() {
    Comments::$plugin->getSettings()->setAttributes($this->original, false);
});

function minScoreSelect(float $score): string
{
    $settings = Comments::$plugin->getSettings();
    $settings->challengeProvider = Settings::CHALLENGE_RECAPTCHA;
    $settings->recaptchaMinScore = $score;
    $view = Craft::$app->getView();
    $mode = $view->getTemplateMode();
    $view->setTemplateMode($view::TEMPLATE_MODE_CP);

    try {
        $html = $view->renderTemplate('comments/spam-protection/_fields', ['settings' => $settings, 'secrets' => []]);
    } finally {
        $view->setTemplateMode($mode);
    }

    preg_match('/<select[^>]*name="recaptchaMinScore"[^>]*>(.*?)<\/select>/s', $html, $select);

    return $select[1] ?? '';
}

function minScoreSelected(string $select): array
{
    preg_match_all('/<option value="([^"]*)"[^>]*\bselected\b/', $select, $matches);

    return $matches[1];
}

it('selects a saved score of 1.0 and 0.0', function() {
    expect(minScoreSelected(minScoreSelect(1.0)))->toBe(['1.0'])
        ->and(minScoreSelected(minScoreSelect(0.0)))->toBe(['0.0']);
});

it('still selects a listed score such as 0.5', function() {
    expect(minScoreSelected(minScoreSelect(0.5)))->toBe(['0.5']);
});

it('offers and selects a saved score between the listed ones', function() {
    $select = minScoreSelect(0.75);

    expect(minScoreSelected($select))->toBe(['0.75'])
        ->and(substr_count($select, '<option'))->toBe(12);
});
