import type { EnhanceAppContext, Theme } from 'vitepress';
import { createVerbbDocsTheme } from '@verbb/vitepress-theme';

// Plugin docs have no home page (verbb.io doesn't use index.md), so the preview opens on Installation & Setup.
const home = '/get-started/installation-setup';

const theme: Theme = createVerbbDocsTheme({
    enhanceApp(context) {
        // VitePress passes the full context, though the shared theme's option type only lists `app`.
        const { router } = context as EnhanceAppContext;

        if (typeof window !== 'undefined' && window.location.pathname === '/') {
            router.go(home);
        }

        router.onBeforeRouteChange = (to) => {
            if (to === '/' || to === '/index.html') {
                router.go(home);

                return false;
            }
        };
    },
});

export default theme;
