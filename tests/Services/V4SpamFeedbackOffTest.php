<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\models\Settings;

/**
 * Moderator spam corrections go to the spam classifier only while protection is on. Requests are answered
 * by a local Guzzle handler, so nothing reaches the classifier.
 */

beforeEach(function() {
    $this->original = Comments::$plugin->getSettings()->getAttributes();
    $this->guzzleFile = Craft::$app->getConfig()->getConfigFilePath('guzzle');
    $this->guzzleBefore = is_file($this->guzzleFile) ? file_get_contents($this->guzzleFile) : null;
    $GLOBALS['commentsFeedbackRequests'] = [];
    file_put_contents($this->guzzleFile, <<<'PHP'
<?php
return [
    'handler' => GuzzleHttp\HandlerStack::create(function(Psr\Http\Message\RequestInterface $request) {
        $GLOBALS['commentsFeedbackRequests'][] = (string)$request->getUri();

        return GuzzleHttp\Promise\Create::promiseFor(new GuzzleHttp\Psr7\Response(200, [], 'Thanks for making the web a better place.'));
    }),
];
PHP);
});

afterEach(function() {
    Comments::$plugin->getSettings()->setAttributes($this->original, false);

    if ($this->guzzleBefore === null) {
        @unlink($this->guzzleFile);
    } else {
        file_put_contents($this->guzzleFile, $this->guzzleBefore);
    }
});

function feedbackRequests(bool $enabled): array
{
    $settings = Comments::$plugin->getSettings();
    $settings->setAttributes([
        'spamClassifier' => Settings::CLASSIFIER_AKISMET,
        'akismetApiKey' => 'test-key',
        'akismetBlogUrl' => 'https://example.test',
        'spamFeedbackEnabled' => true,
    ], false);

    $comment = CommentsFixtureFactory::guestComment(CommentsFixtureFactory::entry('Spam feedback'), 'Buy now');
    $comment->ipAddress = '203.0.113.9';
    $settings->enableSpamChecks = $enabled;
    $GLOBALS['commentsFeedbackRequests'] = [];
    Comments::$plugin->getProtection()->feedback($comment, true);
    Comments::$plugin->getProtection()->feedback($comment, false);

    return $GLOBALS['commentsFeedbackRequests'];
}

it('sends spam corrections while protection is on', function() {
    expect(feedbackRequests(true))->toBe(['https://rest.akismet.com/1.1/submit-spam', 'https://rest.akismet.com/1.1/submit-ham']);
});

it('sends nothing while protection is off', function() {
    expect(feedbackRequests(false))->toBe([]);
});
