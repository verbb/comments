import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        // Components and hooks run against happy-dom; the server tests run in plain Node.
        environment: 'happy-dom',
        include: ['tests/**/*.test.{ts,tsx}'],
    },
});
