import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        // The element's lifecycle needs custom elements and a document. What needs a real browser
        // (scrolling into view, Editor, other engines) is in the browser package's Playwright checks.
        environment: 'happy-dom',
        include: ['tests/**/*.test.ts'],
    },
});
