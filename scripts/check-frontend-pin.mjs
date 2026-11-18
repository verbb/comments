import { readFileSync, realpathSync } from 'node:fs';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const frontendManifest = new URL('../src/web/assets/frontend/package.json', import.meta.url);
const frontend = JSON.parse(readFileSync(frontendManifest, 'utf8'));
const requireFromFrontend = createRequire(frontendManifest);

// A nested published copy can shadow the current workspace even when its pin has been corrected.
// Refuse that build before stale code reaches Composer.
for (const name of ['browser', 'core']) {
    const packageName = `@verbb/comments-${name}`;
    const workspaceManifest = new URL(`../packages/comments-${name}/package.json`, import.meta.url);
    const resolvedManifest = requireFromFrontend.resolve(`${packageName}/package.json`);
    const workspace = JSON.parse(readFileSync(workspaceManifest, 'utf8'));

    if (realpathSync(resolvedManifest) !== realpathSync(fileURLToPath(workspaceManifest))) {
        throw new Error(`${packageName} resolves outside the current workspace. Align the frontend dependency and run npm ci before building.`);
    }

    if (name === 'browser' && frontend.dependencies[packageName] !== workspace.version) {
        throw new Error(`${packageName} must match the current workspace version (${workspace.version}).`);
    }
}
