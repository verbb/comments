import { readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Writes a copy of each stylesheet wrapped in `@layer comments`, which the CSS Layer setting
 * (`useCssLayers`) loads instead, so a site that orders its own cascade layers, such as Tailwind's, can
 * put Comments below its utilities. The tokens keep their own `comments-tokens` layer inside it.
 */
const css = join(dirname(fileURLToPath(import.meta.url)), 'dist/css');

for (const file of readdirSync(css).filter((name) => name.endsWith('.css') && !name.endsWith('.layered.css'))) {
    const source = readFileSync(join(css, file), 'utf8');

    // A layer can't wrap `@charset` or `@import`, and the build leaves neither.
    if (/@(charset|import)\b/.test(source)) {
        throw new Error(`${file} starts with @charset or @import, which can't go inside @layer.`);
    }

    writeFileSync(join(css, file.replace(/\.css$/, '.layered.css')), `@layer comments{${source.trim()}}\n`);
}

// Vite writes an empty script for each stylesheet entry.
for (const name of ['comments-styles.js', 'comments-base.js']) {
    rmSync(join(css, name), { force: true });
}
