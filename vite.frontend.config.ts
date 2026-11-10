import { readFileSync } from 'node:fs';
import path from 'node:path';
import { defineConfig, type Plugin } from 'vite';

const frontendRoot = path.resolve(__dirname, 'src/resources/src/js');
const distRoot = path.resolve(__dirname, 'src/resources/dist');

/**
 * The runtime's public types, written by hand and imported by the runtime itself, so they can't drift
 * from it. They ship beside the built script under its name, which is where TypeScript looks for a
 * declaration when a site imports `comments-frontend.js` into its own bundle.
 */
function publicTypes(): Plugin {
  const source = path.resolve(frontendRoot, 'types/public.d.ts');

  return {
    name: 'comments-public-types',
    generateBundle() {
      this.emitFile({
        type: 'asset',
        fileName: 'comments-frontend.d.ts',
        source: readFileSync(source, 'utf8'),
      });
    },
  };
}

export default defineConfig({
  root: frontendRoot,
  plugins: [publicTypes()],
  build: {
    outDir: path.resolve(distRoot, 'js'),
    emptyOutDir: false,
    sourcemap: true,
    target: 'es2020',
    lib: {
      entry: path.resolve(frontendRoot, 'comments-frontend.ts'),
      formats: ['es'],
      fileName: 'comments-frontend',
    },
    rollupOptions: {
      output: {
        assetFileNames: 'comments-frontend.[ext]',
      },
    },
  },
});
