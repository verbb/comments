import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        // Components and hooks run against happy-dom; the server helpers' test runs in plain Node.
        environment: 'happy-dom',
        include: ['tests/**/*.test.{ts,tsx}'],
    },
});
