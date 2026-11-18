#!/usr/bin/env node
/**
 * Rehearses a package release on this machine: builds the packages, works out the release as the
 * workflow would, packs each package with the manifest it would be published with, checks every
 * tarball with publint and Are the Types Wrong, and lists anything that would stop a real release.
 * With `--starters`, it also installs the tarballs into the starters and builds every one of them.
 * Nothing is versioned, committed, tagged or published.
 *
 *   node scripts/rehearse-release.mjs
 *   node scripts/rehearse-release.mjs --starters=../../starters/comments-starters
 *   node scripts/rehearse-release.mjs --channel=next --no-build
 *
 * The packages' own version is rehearsed. Prepare a new one with prepare-release.mjs first to rehearse
 * that instead.
 */
import { execFileSync } from 'node:child_process';
import { copyFileSync, existsSync, mkdtempSync, readdirSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { basename, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { packPrepared } from './pack-prepared.mjs';
import { isStable, loadConfig, releasePlan } from './package-release.mjs';

const root = fileURLToPath(new URL('../', import.meta.url));
const option = (name) => process.argv.find((argument) => argument.startsWith(`--${name}=`))?.slice(name.length + 3);
const run = (command, args, options = {}) => execFileSync(command, args, { stdio: 'inherit', ...options });

const known = /^--(channel|out|starters)=.+$|^--no-build$/;
const unknown = process.argv.slice(2).filter((argument) => !known.test(argument));

if (unknown.length) {
    throw new Error(`Unexpected arguments: ${unknown.join(' ')}. Options take a value with =, such as --starters=../../starters/comments-starters.`);
}

const config = loadConfig(root);
const { version } = JSON.parse(readFileSync(join(root, config.packages[0], 'package.json'), 'utf8'));
const channel = option('channel') ?? (isStable(version) ? 'latest' : 'next');
const tarballDir = resolve(option('out') ?? mkdtempSync(join(tmpdir(), 'comments-release-rehearsal-')));

const generated = channel === 'next' && isStable(version);
const startersRoot = option('starters') && resolve(root, option('starters'));

// Checked before building, so a rehearsal that can't install into the starters stops straight away.
if (startersRoot) {
    if (!existsSync(join(startersRoot, 'scripts/install-apps.mjs'))) {
        throw new Error(`${startersRoot} isn't the starters repository.`);
    }

    // The starters name each tarball by its version, so they only install the version they expect.
    const expected = `verbb-comments-core-${version}.tgz`;
    const apps = readdirSync(startersRoot, { withFileTypes: true })
        .filter((entry) => entry.isDirectory() && existsSync(join(startersRoot, entry.name, 'package.json')))
        .filter((entry) => readFileSync(join(startersRoot, entry.name, 'package.json'), 'utf8').includes(expected));

    if (generated || !apps.length) {
        throw new Error(`The starters don't install ${generated ? 'a generated next version' : expected}. Update their package.json files to the packages' version first.`);
    }
}

if (!process.argv.includes('--no-build')) {
    run('npm', ['run', 'build:packages'], { cwd: root });
}

const plan = releasePlan(root, {
    RELEASE_CHANNEL: channel,
    RELEASE_VERSION: generated ? '' : version,
    RELEASE_SHA: execFileSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' }).trim(),
    PACKAGE_TARBALL_DIR: tarballDir,
    GITHUB_RUN_NUMBER: '1',
}, { rehearse: true });

// A rehearsal runs on a working copy with changes in it, so it doesn't insist on a clean, reviewed one.
packPrepared(plan, { checkout: false, rehearse: true });

if (startersRoot) {
    const packages = join(startersRoot, 'packages');

    // The starters' own pack script supplies Editor and Protect. These replace only the Comments
    // packages, with the tarballs a release would publish.
    run('node', ['scripts/pack-packages.mjs'], { cwd: startersRoot });

    for (const pkg of plan.packages) {
        copyFileSync(pkg.tarball, join(packages, basename(pkg.tarball)));
    }

    run('npm', ['run', 'install:apps'], { cwd: startersRoot });
    run('npm', ['run', 'build'], { cwd: startersRoot });
    console.log(`Installed the rehearsed tarballs into every starter and built them. Run npm run smoke in ${startersRoot} with the dev servers running to try them.`);
}

console.log(`\nRehearsed ${plan.version} for npm's ${plan.channel} channel. The tarballs are in ${tarballDir}.`);

if (plan.blockers.length) {
    console.log(`\nA real release would stop here:\n${plan.blockers.map((blocker) => `- ${blocker}`).join('\n')}`);
    process.exitCode = 2;
} else {
    console.log('Nothing would stop a real release.');
}
