<?php
namespace verbb\comments\compatibility\twig;

use verbb\comments\services\Compatibility;

use Craft;
use craft\web\View;

use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\CoreExtension;
use Twig\TwigFunction;

/**
 * Comments 3's Twig functions, registered only in compatibility mode. Comments 3's own templates and a
 * site's overrides of them used these, and the thread no longer has templates, so `commentsInclude()`
 * outputs nothing. `commentsSiteInclude()` still includes a site template, which it always did.
 */
class LegacyExtension extends AbstractExtension
{
    // Public Methods
    // =========================================================================

    public function getFunctions(): array
    {
        return [
            new TwigFunction('commentsInclude', [$this, 'commentsInclude'], ['needs_environment' => true, 'needs_context' => true, 'is_safe' => ['all']]),
            new TwigFunction('commentsSiteInclude', [$this, 'commentsSiteInclude'], ['needs_environment' => true, 'needs_context' => true, 'is_safe' => ['all']]),
        ];
    }

    public function commentsInclude(Environment $env, array $context, mixed $template, array $variables = [], bool $withContext = true, bool $ignoreMissing = false, bool $sandboxed = false): string
    {
        Compatibility::requireFunction('commentsInclude', '`commentsInclude()` has been removed. Comments 4 draws the thread with JavaScript, so there are no thread templates to include. Remove the call, and move what the template changed as the upgrade guide explains.');

        return '';
    }

    public function commentsSiteInclude(Environment $env, array $context, mixed $template, array $variables = [], bool $withContext = true, bool $ignoreMissing = false, bool $sandboxed = false): string
    {
        Compatibility::requireFunction('commentsSiteInclude', '`commentsSiteInclude()` has been removed. Use Twig’s own `include()` for site templates.');

        // Comments 3 included from the site's templates whatever mode the page was in, as `include()`
        // does from a site template.
        $view = Craft::$app->getView();
        $mode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        try {
            return CoreExtension::include($env, $context, $template, $variables, $withContext, $ignoreMissing, $sandboxed);
        } finally {
            $view->setTemplateMode($mode);
        }
    }
}
