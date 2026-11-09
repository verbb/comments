<?php
namespace verbb\comments\web\twig;

use craft\web\View;

use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;
use Twig\Source;

/**
 * Finds a frontend template in a site's override folder first, and otherwise uses the plugin's. A
 * site only copies the templates it changes, so a new partial in an update can't break its copies.
 */
class FrontendTemplateLoader implements LoaderInterface
{
    // Public Methods
    // =========================================================================

    public function __construct(
        private readonly LoaderInterface $fallback,
        private readonly View $view,
        private readonly string $overridePath,
    ) {
    }

    public function getSourceContext(string $name): Source
    {
        $path = $this->_overrideFor($name);

        if ($path === null) {
            return $this->fallback->getSourceContext($name);
        }

        $code = file_get_contents($path);

        if ($code === false) {
            throw new LoaderError("Unable to read the template override at {$path}.");
        }

        return new Source($code, $name, $path);
    }

    public function getCacheKey(string $name): string
    {
        return $this->_overrideFor($name) ?? $this->fallback->getCacheKey($name);
    }

    public function isFresh(string $name, int $time): bool
    {
        $path = $this->_overrideFor($name);

        return $path === null ? $this->fallback->isFresh($name, $time) : filemtime($path) <= $time;
    }

    public function exists(string $name): bool
    {
        return $this->_overrideFor($name) !== null || $this->fallback->exists($name);
    }


    // Private Methods
    // =========================================================================

    private function _overrideFor(string $name): ?string
    {
        // Craft's own lookup, so a site's template extensions apply to its overrides.
        $templatesPath = $this->view->getTemplatesPath();
        $this->view->setTemplatesPath($this->overridePath);

        try {
            return $this->view->resolveTemplate($name) ?: null;
        } finally {
            $this->view->setTemplatesPath($templatesPath);
        }
    }
}
