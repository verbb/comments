import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        // Core has no DOM, so its tests run in plain Node.
        environment: 'node',
        include: ['tests/**/*.test.ts'],
    },
});
