<?php
namespace verbb\comments\search;

use verbb\comments\elements\Comment;

use Craft;
use craft\helpers\ArrayHelper;
use craft\search\SearchQuery;
use craft\search\SearchQueryTermGroup;

/**
 * A search query for public GraphQL schemas. Craft matches a plain term against every indexed attribute,
 * including the commenter's email address, so each plain term only matches the comment text, author name
 * and searchable custom fields here. Craft's parser still handles phrases, exclusions, `OR` groups and
 * wildcards.
 */
class PublicSearchQuery extends SearchQuery
{
    // Static Methods
    // =========================================================================

    /**
     * Creates a search query from an element query's `search` param, the way Craft's search service does.
     */
    public static function fromParam(string|array $search): ?self
    {
        $options = is_array($search) ? $search : ['query' => $search];
        $query = ArrayHelper::remove($options, 'query');

        if (!is_string($query)) {
            return null;
        }

        return new self($query, array_merge(Craft::$app->getConfig()->getGeneral()->defaultSearchTermOptions, $options));
    }


    // Constants
    // =========================================================================

    // The search index attributes for a comment's text and author name. `authoremail` is left out.
    public const ATTRIBUTES = ['rawcomment', 'authorname'];


    // Properties
    // =========================================================================

    private array $_attributes = [];
    private array $_scopedTokens = [];


    // Public Methods
    // =========================================================================

    public function __construct(string $query, array $defaultTermOptions = [])
    {
        parent::__construct($query, $defaultTermOptions);

        $this->_attributes = self::ATTRIBUTES;

        // Craft matches a custom field term by its handle in the same field layouts comment queries use.
        foreach (Craft::$app->getFields()->getLayoutsByType(Comment::class) as $fieldLayout) {
            foreach ($fieldLayout->getCustomFields() as $field) {
                if ($field->searchable) {
                    $this->_attributes[] = $field->handle;
                }
            }
        }

        $this->_attributes = array_values(array_unique($this->_attributes));

        foreach (parent::getTokens() as $token) {
            if ($token instanceof SearchQueryTermGroup) {
                // Within an `OR` group, an excluded term matches when it's missing from any one attribute.
                $this->_scopedTokens[] = new SearchQueryTermGroup($this->_scopeTerms($token->terms));
            } elseif ($token->attribute !== null) {
                $this->_scopedTokens[] = $token;
            } elseif ($token->exclude) {
                // An excluded term must be missing from every attribute, so each one stays a separate term.
                array_push($this->_scopedTokens, ...$this->_scopeTerms([$token]));
            } else {
                // An included term can be in any of the attributes.
                $this->_scopedTokens[] = new SearchQueryTermGroup($this->_scopeTerms([$token]));
            }
        }
    }

    public function getTokens(): array
    {
        return $this->_scopedTokens;
    }


    // Private Methods
    // =========================================================================

    private function _scopeTerms(array $terms): array
    {
        $scoped = [];

        foreach ($terms as $term) {
            if ($term->attribute !== null) {
                $scoped[] = $term;

                continue;
            }

            foreach ($this->_attributes as $attribute) {
                $copy = clone $term;
                $copy->attribute = $attribute;
                $scoped[] = $copy;
            }
        }

        return $scoped;
    }
}
