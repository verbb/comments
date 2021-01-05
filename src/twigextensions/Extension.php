<?php
namespace verbb\comments\twigextensions;

use verbb\comments\Comments;

use Craft;
use craft\web\View;

use Twig_Extension;
use Twig_SimpleFunction;
use Twig_SimpleFilter;
use Twig_Environment;

class Extension extends Twig_Extension
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
            new Twig_SimpleFunction('commentsInclude', [$this, 'commentsInclude'], ['needs_environment' => true, 'needs_context' => true, 'is_safe' => ['all']]),
            new Twig_SimpleFunction('commentsSiteInclude', [$this, 'commentsSiteInclude'], ['needs_environment' => true, 'needs_context' => true, 'is_safe' => ['all']]),
        ];
    }

    public function commentsInclude(Twig_Environment $env, $context, $template, $variables = [], $withContext = true, $ignoreMissing = false, $sandboxed = false)
    {
        $view = $context['view'];

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
        
        return twig_include($env, $context, $template, $variables, $withContext, $ignoreMissing, $sandboxed);
    }

    public function commentsSiteInclude(Twig_Environment $env, $context, $template, $variables = [], $withContext = true, $ignoreMissing = false, $sandboxed = false)
    {
        $view = $context['view'];

        $oldTemplatesPath = $view->getTemplatesPath();
        $view->setTemplatesPath(Craft::$app->path->getSiteTemplatesPath());

        $result = twig_include($env, $context, $template, $variables, $withContext, $ignoreMissing, $sandboxed);

        $view->setTemplatesPath($oldTemplatesPath);

        return $result;
    }
}
