#!/usr/bin/env node
/**
 * Publishes the validated tarballs from the reviewed checkout in GitHub Actions, with npm's trusted
 * publishing. Every tarball is checked before the first one is published, so a release never goes out
 * half done, and a version npm already has is skipped, so a rerun finishes what a failed run started.
 * Copied from Formie's `publish-prepared.mjs`.
 */
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { assertPackedManifest, assertReviewedCheckout, isStable, releasePlan } from './package-release.mjs';

const REGISTRY = 'https://registry.npmjs.org/';

export function publishPrepared(plan, run = execFileSync) {
    assertReviewedCheckout(plan, run);

    for (const pkg of plan.packages) {
        assertPackedManifest(pkg, run);
    }

    const output = (command, args) => run(command, args, { cwd: plan.root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
    /** The version and commit npm has for this release of a package, or null when it has none. */
    const published = (name) => {
        try {
            const answer = output('npm', ['view', `${name}@${plan.version}`, 'version', 'gitHead', '--json', '--registry', REGISTRY, '--prefer-online']);

            return answer ? JSON.parse(answer) : null;
        } catch (error) {
            if (/E404|404 Not Found/.test(`${error.stderr ?? ''}\n${error.stdout ?? ''}`)) return null;

            throw new Error(`Couldn't check ${name}@${plan.version} on npm, so nothing more was published.`, { cause: error });
        }
    };

    for (const { manifest: { name }, tarball } of plan.packages) {
        const existing = published(name);

        if (existing && existing.version !== plan.version) {
            throw new Error(`npm answered ${name} with ${existing.version}, not ${plan.version}.`);
        }

        if (existing) {
            // A rerun only finishes a release from the same commit, so the packages never pin siblings
            // built from another.
            if (existing.gitHead !== plan.sha) {
                throw new Error(`npm already has ${name}@${plan.version} from ${existing.gitHead ?? 'an unknown commit'}, not ${plan.sha}. Release a new version instead.`);
            }

            console.log(`Already on npm: ${name}@${plan.version}`);
            continue;
        }

        console.log(`Publishing ${name}@${plan.version} from ${plan.sha}…`);
        run('npm', ['publish', tarball, '--tag', plan.channel, '--access', 'public', '--ignore-scripts', '--registry', REGISTRY], { stdio: 'inherit' });
        console.log(`npm accepted ${name}@${plan.version}. Its scan can delay installing it for a few minutes.`);
    }

    // npm points `latest` at a package's first version whatever tag it was published with, so the first
    // beta also becomes latest until 4.0.0 replaces it. Say so, rather than leave it to be found.
    for (const { manifest: { name } } of plan.packages) {
        try {
            const latest = output('npm', ['view', name, 'dist-tags.latest', '--registry', REGISTRY, '--prefer-online']);

            if (latest && !isStable(latest)) {
                console.warn(`::warning::${name}'s latest is the prerelease ${latest}. It moves when a stable version is published to latest.`);
            }
        } catch {
            console.warn(`::warning::Couldn't read ${name}'s dist-tags after publishing.`);
        }
    }

    console.log(`npm has all ${plan.packages.length} Comments packages at ${plan.version} on ${plan.channel}.`);
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    publishPrepared(releasePlan(fileURLToPath(new URL('../', import.meta.url)), process.env));
}
