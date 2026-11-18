import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

/**
 * Copies the two stylesheets into `dist/css` and writes `comments.css`, both in one file, which is
 * what themes Light, Dark and Auto load. Concatenated rather than `@import`ed, so the file works in
 * a `<link>` and in any bundler without resolving a second request.
 */
const source = new URL('../src/css/', import.meta.url);
const target = fileURLToPath(new URL('../dist/css/', import.meta.url));
const base = readFileSync(new URL('comments-base.css', source), 'utf8');
const theme = readFileSync(new URL('comments-theme.css', source), 'utf8');

mkdirSync(target, { recursive: true });
writeFileSync(`${target}comments-base.css`, base);
writeFileSync(`${target}comments-theme.css`, theme);
writeFileSync(`${target}comments.css`, `${base.trimEnd()}\n\n${theme}`);
