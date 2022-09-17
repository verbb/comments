<?php
namespace verbb\comments\twigextensions;

use verbb\comments\Comments;

use Craft;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\Environment;

class Extension extends AbstractExtension
{
    // Public Methods
    // =========================================================================

    public function getName(): string
    {
        return 'Comments Variables';
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('commentsInclude', [$this, 'commentsInclude'], ['needs_environment' => true, 'needs_context' => true, 'is_safe' => ['all']]),
            new TwigFunction('commentsSiteInclude', [$this, 'commentsSiteInclude'], ['needs_environment' => true, 'needs_context' => true, 'is_safe' => ['all']]),
        ];
    }

    public function commentsInclude(Environment $env, $context, $template, $variables = [], $withContext = true, $ignoreMissing = false, $sandboxed = false): string
    {
        $view = $context['view'];
        $oldTemplatePath = $view->getTemplatesPath();

        $settings = Comments::$plugin->getSettings();

        // Render the provided include depending on form template overrides
        if ($settings->templateFolderOverride) {
            // Handle providing the template as an array. Let Twig resolve it first
            if (is_array($template)) {
                $loaded = $env->resolveTemplate($template);

                if ($loaded) {
                    $template = $loaded->getSourceContext()->getName();
                }
            }

            $templatePath = Comments::$plugin->getComments()->getComponentTemplatePath($template);
            $view->setTemplatesPath($templatePath);
        }

        $template = twig_include($env, $context, $template, $variables, $withContext, $ignoreMissing, $sandboxed);

        $view->setTemplatesPath($oldTemplatePath);

        return $template;
    }

    public function commentsSiteInclude(Environment $env, $context, $template, $variables = [], $withContext = true, $ignoreMissing = false, $sandboxed = false): string
    {
        $view = $context['view'];

        $oldTemplatesPath = $view->getTemplatesPath();
        $view->setTemplatesPath(Craft::$app->path->getSiteTemplatesPath());

        $result = twig_include($env, $context, $template, $variables, $withContext, $ignoreMissing, $sandboxed);

        $view->setTemplatesPath($oldTemplatesPath);

        return $result;
    }
}
