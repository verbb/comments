import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        // The parts are DOM-free and render to strings, so their tests run in plain Node. What needs a
        // page (loading, focus, the editor) is covered by the Playwright checks in tests/browser.
        environment: 'node',
        include: ['tests/**/*.test.ts'],
    },
});
