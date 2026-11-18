<?php
use Tests\Support\Fixtures\CommentsFixtureFactory;
use verbb\comments\Comments;
use verbb\comments\elements\Comment;
use verbb\comments\enums\HeldReason;
use verbb\comments\enums\ModerationState;
use verbb\comments\helpers\KeywordRules;

/**
 * The keyword filters' rules: plain words, `[match: …]` with AND, OR and parentheses, `[ip: …]` with
 * addresses, ranges and CIDR blocks, and notes.
 */

beforeEach(function() {
    $settings = Comments::$plugin->getSettings();
    $this->original = $settings->getAttributes();
    $settings->securityMatchExact = false;
});

afterEach(function() {
    Comments::$plugin->getSettings()->setAttributes($this->original, false);
});

function keywordMatch(string $rules, string $text, string $ip = '192.0.2.8', bool $exact = false, string $name = 'Pat', string $email = 'pat@example.test'): ?string
{
    return KeywordRules::findMatch($rules, [$text, $name, $email], $ip, $exact);
}

it('keeps plain lines working as before, in any case and inside longer words', function() {
    expect(keywordMatch('casino', 'Visit our CASINOS'))->toBe('casino')
        ->and(keywordMatch('casino', 'Nothing here'))->toBeNull()
        ->and(keywordMatch('casino', 'Visit our casinos', exact: true))->toBeNull()
        ->and(keywordMatch('cheap watches', 'Buy Cheap Watches now'))->toBe('cheap watches')
        ->and(keywordMatch('Pat', 'Nothing here'))->toBe('Pat')
        ->and(keywordMatch('192.0.2.8', 'Nothing here'))->toBe('192.0.2.8');
});

it('skips notes and blank lines, but keeps a hashtag as a keyword', function() {
    $rules = "# Common junk\n\n#\n#casino";

    expect(KeywordRules::parse($rules))->toHaveCount(1)
        ->and(keywordMatch($rules, 'Common junk'))->toBeNull()
        ->and(keywordMatch($rules, 'Try #casino tonight'))->toBe('#casino');
});

it('combines words and phrases with AND, OR and parentheses', function() {
    $rule = '[match: (viagra OR casino) AND cheap deal]';

    expect(keywordMatch($rule, 'A cheap deal on Viagra'))->toBe($rule)
        ->and(keywordMatch($rule, 'Casino night, cheap deal'))->toBe($rule)
        ->and(keywordMatch($rule, 'A cheap deal on shoes'))->toBeNull()
        ->and(keywordMatch($rule, 'Viagra, but no deal'))->toBeNull();

    // AND binds tighter than OR.
    $rule = '[match: casino OR viagra AND cheap]';
    expect(keywordMatch($rule, 'Casino'))->toBe($rule)
        ->and(keywordMatch($rule, 'Viagra'))->toBeNull();

    // Lower-case words are part of the phrase rather than operators.
    $rule = '[match: rock and roll]';
    expect(keywordMatch($rule, 'Rock and roll forever'))->toBe($rule)
        ->and(keywordMatch($rule, 'Rock music'))->toBeNull();

    // The prefix can be in any case.
    expect(keywordMatch('[MATCH: casino]', 'casino'))->toBe('[MATCH: casino]');
});

it('looks for each word across the text, name and email, but not the IP address', function() {
    $rule = '[match: crypto AND example.test]';

    expect(keywordMatch($rule, 'Crypto returns'))->toBe($rule)
        ->and(keywordMatch('[match: 192.0.2.8]', 'Nothing here'))->toBeNull();
});

it('honours exact matching inside a match rule', function() {
    $rule = '[match: spam AND bulk]';

    expect(keywordMatch($rule, 'Spammy bulky', exact: false))->toBe($rule)
        ->and(keywordMatch($rule, 'Spammy bulky', exact: true))->toBeNull()
        ->and(keywordMatch($rule, 'Some SPAM in BULK', exact: true))->toBe($rule);
});

it('matches IP rules against the address only, by address, range and CIDR block', function() {
    expect(keywordMatch('[ip: 192.0.2.8]', 'Nothing', '192.0.2.8'))->not->toBeNull()
        ->and(keywordMatch('[ip: 192.0.2.8]', 'Nothing', '192.0.2.80'))->toBeNull()
        ->and(keywordMatch('[ip: 192.0.2.8]', 'Mentions 192.0.2.8', '203.0.113.1'))->toBeNull()
        ->and(keywordMatch('[ip: 10.0.0.1, 192.0.2.8]', 'Nothing', '192.0.2.8'))->not->toBeNull()
        ->and(keywordMatch('[ip: 192.0.2.1-192.0.2.20]', 'Nothing', '192.0.2.8'))->not->toBeNull()
        ->and(keywordMatch('[ip: 192.0.2.1-192.0.2.20]', 'Nothing', '192.0.2.21'))->toBeNull()
        ->and(keywordMatch('[ip: 203.0.113.0/24]', 'Nothing', '203.0.113.200'))->not->toBeNull()
        ->and(keywordMatch('[ip: 203.0.113.0/24]', 'Nothing', '203.0.114.1'))->toBeNull()
        ->and(keywordMatch('[ip: 0.0.0.0/0]', 'Nothing', '198.51.100.1'))->not->toBeNull()
        ->and(keywordMatch('[ip: 2001:db8::/32]', 'Nothing', '2001:0db8:0:0::1'))->not->toBeNull()
        ->and(keywordMatch('[ip: 2001:db8::/32]', 'Nothing', '2001:db9::1'))->toBeNull()
        ->and(keywordMatch('[ip: 2001:db8::1]', 'Nothing', '2001:DB8:0::1'))->not->toBeNull()
        ->and(keywordMatch('[ip: 192.0.2.0/24]', 'Nothing', '2001:db8::1'))->toBeNull()
        ->and(keywordMatch('[ip: 192.0.2.0/24]', 'Nothing', ''))->toBeNull();
});

it('explains each rule it can’t read, by line, and never matches one', function() {
    $rules = implode("\n", [
        '[match: casino',
        '[match: casino AND]',
        '[match: (casino OR viagra]',
        '[match: casino)]',
        '[match: ]',
        '[ip: 192.0.2.300]',
        '[ip: 192.0.2.20-192.0.2.1]',
        '[ip: 192.0.2.0/33]',
        '[ip: 192.0.2.1-2001:db8::1]',
        'casino',
    ]);

    expect(array_keys(KeywordRules::errors($rules)))->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9])
        ->and(keywordMatch($rules, 'casino AND viagra', '192.0.2.5'))->toBe('casino')
        ->and(keywordMatch(implode("\n", array_slice(explode("\n", $rules), 0, 9)), 'casino viagra', '192.0.2.5'))->toBeNull();
});

it('refuses to save a filter with a rule it can’t read', function() {
    $settings = Comments::$plugin->getSettings();
    $settings->securitySpamlist = "casino\n[match: (casino OR viagra]";
    $settings->securityBanned = '[ip: 203.0.113.0/24, 198.51.100.7]';

    expect($settings->validate(['securitySpamlist', 'securityBanned']))->toBeFalse()
        ->and($settings->getErrors('securitySpamlist'))->toHaveCount(1)
        ->and($settings->getFirstError('securitySpamlist'))->toStartWith('Line 2:')
        ->and($settings->getErrors('securityBanned'))->toBe([]);
});

it('holds, marks as spam or refuses a comment with the new rules', function() {
    $entry = CommentsFixtureFactory::entry('Keyword rules');
    $settings = Comments::$plugin->getSettings();
    $security = Comments::$plugin->getSecurity();
    $candidate = static fn(string $text, string $ip = '192.0.2.8') => new Comment(['comment' => $text, 'name' => 'Pat', 'email' => 'pat@example.test', 'ipAddress' => $ip, 'status' => Comment::STATUS_APPROVED, 'contentFormat' => 'plain', 'ownerId' => $entry->id]);

    $settings->securityModeration = '[match: refund AND urgent]';
    $settings->securitySpamlist = null;
    $settings->securityBanned = '[ip: 203.0.113.0/24]';

    $held = $candidate('Urgent: I need a refund');
    expect($security->checkSecurityPolicy($held))->toBeTrue()
        ->and($held->moderationState)->toBe(ModerationState::Pending->value)
        ->and($held->heldReason)->toBe(HeldReason::Keyword->value);

    $fine = $candidate('A refund, no rush');
    expect($security->checkSecurityPolicy($fine))->toBeTrue()
        ->and($fine->status)->toBe(Comment::STATUS_APPROVED);

    expect($security->checkSecurityPolicy($candidate('Hello', '203.0.113.9')))->toBeFalse()
        ->and($security->checkSecurityPolicy($candidate('Hello', '203.0.114.9')))->toBeTrue();

    $settings->securitySpamlist = '[match: crypto OR forex]';
    $spam = $candidate('Forex tips');
    expect($security->checkSecurityPolicy($spam))->toBeTrue()
        ->and($spam->moderationState)->toBe(ModerationState::Spam->value);
});
