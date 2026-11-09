import path from 'node:path';
import { defineConfig } from 'vite';

// Control panel screens that use Plugin Kit. Built to fixed names, so the asset bundles stay static.
const cpRoot = path.resolve(import.meta.dirname, 'src/web/assets/cp');

export default defineConfig({
  root: path.resolve(cpRoot, 'src'),
  build: {
    outDir: path.resolve(cpRoot, 'dist'),
    emptyOutDir: true,
    sourcemap: true,
    target: 'es2022',
    cssCodeSplit: true,
    lib: {
      entry: {
        'plugin-kit': path.resolve(cpRoot, 'src/plugin-kit.ts'),
        commenters: path.resolve(cpRoot, 'src/commenters.ts'),
        'comment-edit': path.resolve(cpRoot, 'src/comment-edit.ts'),
      },
      formats: ['es'],
      fileName: (_format, name) => `${name}.js`,
    },
    rollupOptions: {
      output: {
        assetFileNames: '[name].[ext]',
        chunkFileNames: 'chunks/[name].js',
      },
    },
  },
});
