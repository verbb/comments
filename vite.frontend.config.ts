import path from 'node:path';
import { defineConfig } from 'vite';

const frontendRoot = path.resolve(__dirname, 'src/resources/src/js');
const distRoot = path.resolve(__dirname, 'src/resources/dist');

export default defineConfig({
  root: frontendRoot,
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
