#!/usr/bin/env node
/**
 * Packs the reviewed builds into tarballs for publishing, without changing versions, changelogs or the
 * lockfile, then checks each tarball with publint and Are the Types Wrong. The publish job only ever
 * publishes these tarballs. Copied from Formie's `pack-prepared.mjs`, with the two checks added.
 */
import { execFileSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { basename, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { assertPackedManifest, assertReviewedCheckout, releasePlan } from './package-release.mjs';

/**
 * Checks a tarball the way npm and TypeScript users will meet it. Both tools read the tarball as it is.
 * A failure throws, unless `problem` is given to collect it, as a rehearsal does.
 */
export function lintTarball(root, tarball, run = execFileSync, problem = null) {
    const bin = (name) => join(root, 'node_modules/.bin', name);
    const check = (tool, args) => {
        try {
            run(bin(tool), args, { cwd: root, stdio: 'inherit' });
        } catch (error) {
            if (!problem) throw error;
            problem(`${tool} found problems in ${basename(tarball)}. Its report is above.`);
        }
    };

    // publint: the exports, files and fields a bundler or Node relies on, with warnings as failures.
    check('publint', ['run', tarball, '--strict']);

    // The packages are ES modules only, so a CommonJS `require()` isn't expected to resolve. Are the
    // Types Wrong can't resolve a stylesheet export, such as `./css/comments.css`, so those are skipped.
    const { exports = {} } = JSON.parse(run('tar', ['-xOf', tarball, 'package/package.json'], { encoding: 'utf8' }));
    const stylesheets = typeof exports === 'object' ? Object.keys(exports).filter((subpath) => subpath.endsWith('.css')) : [];

    check('attw', [tarball, '--profile', 'esm-only', '--format', 'ascii', ...(stylesheets.length ? ['--exclude-entrypoints', ...stylesheets] : [])]);
}

/**
 * Packs every package. A rehearsal (`{ checkout: false, rehearse: true }`) packs a working copy with
 * changes in it, and adds lint failures to the plan's blockers instead of stopping.
 */
export function packPrepared(plan, { run = execFileSync, checkout = true, rehearse = false } = {}) {
    if (checkout) assertReviewedCheckout(plan, run);

    const staging = mkdtempSync(join(tmpdir(), 'comments-package-release-'));

    try {
        mkdirSync(plan.tarballDir, { recursive: true });

        for (const pkg of plan.packages) {
            // npm's own publishable file set, packed as it is, with only the manifest replaced by the
            // reviewed one.
            const original = JSON.parse(run('npm', ['pack', join(plan.root, pkg.dir), '--ignore-scripts', '--json', '--pack-destination', staging], { cwd: plan.root, encoding: 'utf8' }))[0];
            const unpacked = mkdtempSync(join(staging, 'package-'));

            run('tar', ['-xf', join(staging, original.filename), '-C', unpacked]);
            writeFileSync(join(unpacked, 'package/package.json'), `${JSON.stringify(pkg.packed, null, 2)}\n`);
            run('npm', ['pack', join(unpacked, 'package'), '--ignore-scripts', '--pack-destination', plan.tarballDir], { cwd: staging, stdio: 'inherit' });
            assertPackedManifest(pkg, run);
            lintTarball(plan.root, pkg.tarball, run, rehearse ? (problem) => plan.blockers.push(problem) : null);
        }

        if (checkout) assertReviewedCheckout(plan, run);
    } finally {
        rmSync(staging, { recursive: true, force: true });
    }

    console.log(`Packed ${plan.version} for npm's ${plan.channel} channel. Nothing was published.`);
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    packPrepared(releasePlan(fileURLToPath(new URL('../', import.meta.url)), process.env));
}
