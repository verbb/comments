import { startComments } from '@verbb/comments-browser';

/**
 * The plugin's startup script: it reads its own `<script>` tag's nonce, if the page has one, and
 * starts every thread on the page. A site that bundles @verbb/comments-browser itself turns off
 * Load Bundled JavaScript and calls startComments() from its own code instead.
 */
function startupScript(): HTMLScriptElement | null {
    const url = new URL(import.meta.url, document.baseURI).href;
    const scripts = Array.from(document.querySelectorAll<HTMLScriptElement>('script[src]'));

    return scripts.find((script) => new URL(script.src, document.baseURI).href === url) ?? null;
}

startComments({ nonce: startupScript()?.nonce || undefined });
