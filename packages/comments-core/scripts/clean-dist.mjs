import { rmSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

// Old modules would otherwise stay in the tarball after a source file is renamed or removed.
rmSync(fileURLToPath(new URL('../dist', import.meta.url)), { recursive: true, force: true });
