<?php
namespace verbb\comments\helpers;

use Craft;

/**
 * Reads and matches the keyword filters. Each line of a filter is one rule:
 *
 * - A plain line is a word or phrase, found in the comment's text, the author's name or email, or the IP address.
 * - `[match: …]` combines words and phrases with `AND`, `OR` and parentheses, found in the text, name and email
 *   taken together.
 * - `[ip: …]` lists addresses, ranges (`a-b`) and CIDR blocks, compared with the IP address only.
 * - A line starting with `#` and a space, or a line of just `#`, is a note and never matches.
 */
class KeywordRules
{
    // Constants
    // =========================================================================

    public const TYPE_PLAIN = 'plain';
    public const TYPE_MATCH = 'match';
    public const TYPE_IP = 'ip';


    // Static Properties
    // =========================================================================

    /** @var array<string, array> Parsed rules for each filter's text, so a filter is read once a request. */
    private static array $_parsed = [];


    // Static Methods
    // =========================================================================

    /**
     * The first rule in `$text` that the comment matches, as the person wrote it, or null.
     *
     * @param string[] $fields The text, name and email to search.
     */
    public static function findMatch(?string $text, array $fields, ?string $ipAddress, bool $exact): ?string
    {
        $fields = array_values(array_filter(array_map(static fn($value) => is_string($value) ? self::_clean($value) : '', $fields), 'strlen'));
        $ipAddress = is_string($ipAddress) ? trim($ipAddress) : '';

        foreach (self::parse($text) as $rule) {
            if (isset($rule['error'])) {
                continue;
            }

            $matched = match ($rule['type']) {
                self::TYPE_PLAIN => self::_termIn($rule['term'], [...$fields, $ipAddress], $exact),
                self::TYPE_MATCH => self::_evaluate($rule['tree'], $fields, $exact),
                self::TYPE_IP => self::_ipMatches($ipAddress, $rule['ranges']),
            };

            if ($matched) {
                return $rule['line'];
            }
        }

        return null;
    }

    /**
     * What's wrong with each rule in `$text` that can't be read, keyed by its line number.
     *
     * @return array<int, string>
     */
    public static function errors(?string $text): array
    {
        $errors = [];

        foreach (self::parse($text) as $rule) {
            if (isset($rule['error'])) {
                $errors[$rule['number']] = $rule['error'];
            }
        }

        return $errors;
    }

    /**
     * Each rule in `$text`, skipping blank lines and notes. A rule that can't be read has an `error` instead
     * of what it would match.
     *
     * @return array<int, array{line: string, number: int, type: string, term?: string, tree?: array, ranges?: array, error?: string}>
     */
    public static function parse(?string $text): array
    {
        $text = (string)$text;

        if (isset(self::$_parsed[$text])) {
            return self::$_parsed[$text];
        }

        $rules = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $index => $line) {
            $line = trim($line);

            if ($line === '' || $line === '#' || str_starts_with($line, '# ')) {
                continue;
            }

            $rules[] = ['line' => $line, 'number' => $index + 1] + self::_parseLine($line);
        }

        return self::$_parsed[$text] = $rules;
    }


    // Private Methods
    // =========================================================================

    private static function _parseLine(string $line): array
    {
        if (!preg_match('/^\[(match|ip):(.*)$/isu', $line, $prefix)) {
            return ['type' => self::TYPE_PLAIN, 'term' => $line];
        }

        $type = strtolower($prefix[1]);
        $body = rtrim($prefix[2]);

        if (!str_ends_with($body, ']')) {
            return ['type' => $type, 'error' => Craft::t('comments', 'It needs a closing “]”.')];
        }

        $body = trim(substr($body, 0, -1));

        if ($body === '') {
            return ['type' => $type, 'error' => Craft::t('comments', 'It’s empty.')];
        }

        return $type === self::TYPE_IP ? self::_parseIps($body) : self::_parseExpression($body);
    }

    private static function _parseExpression(string $body): array
    {
        // Operators are capitals, so a phrase such as “rock and roll” stays one phrase.
        $tokens = preg_split('/(\(|\)|(?<![\p{L}\p{N}_])(?:AND|OR)(?![\p{L}\p{N}_]))/u', $body, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_values(array_filter(array_map('trim', $tokens), 'strlen'));
        $position = 0;

        $tree = self::_parseOr($tokens, $position, $error);

        if ($error === null && $position < count($tokens)) {
            $error = $tokens[$position] === ')'
                ? Craft::t('comments', 'It has a “)” without a “(”.')
                : Craft::t('comments', 'Put AND or OR between “{a}” and “{b}”.', ['a' => $tokens[$position - 1] ?? '', 'b' => $tokens[$position]]);
        }

        return $error === null ? ['type' => self::TYPE_MATCH, 'tree' => $tree] : ['type' => self::TYPE_MATCH, 'error' => $error];
    }

    private static function _parseOr(array $tokens, int &$position, ?string &$error): ?array
    {
        $nodes = [self::_parseAnd($tokens, $position, $error)];

        while ($error === null && ($tokens[$position] ?? null) === 'OR') {
            $position++;
            $nodes[] = self::_parseAnd($tokens, $position, $error);
        }

        return count($nodes) === 1 ? $nodes[0] : ['or' => $nodes];
    }

    private static function _parseAnd(array $tokens, int &$position, ?string &$error): ?array
    {
        $nodes = [self::_parseTerm($tokens, $position, $error)];

        while ($error === null && ($tokens[$position] ?? null) === 'AND') {
            $position++;
            $nodes[] = self::_parseTerm($tokens, $position, $error);
        }

        return count($nodes) === 1 ? $nodes[0] : ['and' => $nodes];
    }

    private static function _parseTerm(array $tokens, int &$position, ?string &$error): ?array
    {
        if ($error !== null) {
            return null;
        }

        $token = $tokens[$position] ?? null;

        if ($token === null || $token === 'AND' || $token === 'OR' || $token === ')') {
            $error = Craft::t('comments', 'A word or phrase is missing.');

            return null;
        }

        $position++;

        if ($token !== '(') {
            return ['term' => $token];
        }

        $node = self::_parseOr($tokens, $position, $error);

        if ($error === null && ($tokens[$position] ?? null) !== ')') {
            $error = Craft::t('comments', 'It has a “(” without a “)”.');

            return null;
        }

        $position++;

        return $node;
    }

    private static function _parseIps(string $body): array
    {
        $ranges = [];

        foreach (array_filter(array_map('trim', explode(',', $body)), 'strlen') as $entry) {
            $range = self::_parseIpEntry($entry);

            if ($range === null) {
                return ['type' => self::TYPE_IP, 'error' => Craft::t('comments', '“{ip}” isn’t an IP address, range or CIDR block.', ['ip' => $entry])];
            }

            $ranges[] = $range;
        }

        return $ranges ? ['type' => self::TYPE_IP, 'ranges' => $ranges] : ['type' => self::TYPE_IP, 'error' => Craft::t('comments', 'It’s empty.')];
    }

    /**
     * The first and last address an entry covers, as packed binary strings of the same length.
     *
     * @return array{0: string, 1: string}|null
     */
    private static function _parseIpEntry(string $entry): ?array
    {
        if (str_contains($entry, '/')) {
            [$address, $bits] = explode('/', $entry, 2);
            $packed = @inet_pton(trim($address));
            $bits = trim($bits);

            if ($packed === false || !ctype_digit($bits) || (int)$bits > strlen($packed) * 8) {
                return null;
            }

            $first = $last = '';
            $remaining = (int)$bits;

            foreach (str_split($packed) as $byte) {
                $keep = max(0, min(8, $remaining));
                $mask = $keep === 0 ? 0 : (0xFF << (8 - $keep)) & 0xFF;
                $first .= chr(ord($byte) & $mask);
                $last .= chr((ord($byte) & $mask) | (~$mask & 0xFF));
                $remaining -= 8;
            }

            return [$first, $last];
        }

        if (str_contains($entry, '-')) {
            [$from, $to] = array_map('trim', explode('-', $entry, 2));
            $first = @inet_pton($from);
            $last = @inet_pton($to);

            if ($first === false || $last === false || strlen($first) !== strlen($last) || strcmp($first, $last) > 0) {
                return null;
            }

            return [$first, $last];
        }

        $packed = @inet_pton($entry);

        return $packed === false ? null : [$packed, $packed];
    }

    private static function _ipMatches(string $ipAddress, array $ranges): bool
    {
        $packed = $ipAddress === '' ? false : @inet_pton($ipAddress);

        if ($packed === false) {
            return false;
        }

        foreach ($ranges as [$first, $last]) {
            if (strlen($packed) === strlen($first) && strcmp($packed, $first) >= 0 && strcmp($packed, $last) <= 0) {
                return true;
            }
        }

        return false;
    }

    private static function _evaluate(array $node, array $fields, bool $exact): bool
    {
        if (isset($node['term'])) {
            return self::_termIn($node['term'], $fields, $exact);
        }

        if (isset($node['and'])) {
            foreach ($node['and'] as $child) {
                if (!self::_evaluate($child, $fields, $exact)) {
                    return false;
                }
            }

            return true;
        }

        foreach ($node['or'] as $child) {
            if (self::_evaluate($child, $fields, $exact)) {
                return true;
            }
        }

        return false;
    }

    private static function _termIn(string $term, array $fields, bool $exact): bool
    {
        foreach ($fields as $field) {
            if ($field === '') {
                continue;
            }

            $found = $exact
                ? preg_match('/(?<![\p{L}\p{N}_])' . preg_quote($term, '/') . '(?![\p{L}\p{N}_])/iu', $field)
                : mb_stripos($field, $term) !== false;

            if ($found) {
                return true;
            }
        }

        return false;
    }

    private static function _clean(string $value): string
    {
        // Control characters could otherwise split a word so that it doesn't match. Line breaks
        // stay, because they're what separates a word at the start of a line from the one before.
        return (string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', trim($value));
    }
}
