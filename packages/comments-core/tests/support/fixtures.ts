/**
 * The JSON fixtures in `tests/fixtures`, one file per response, with `manifest.json` naming the
 * schema definition each one must match and the request that produces it. To test against a real
 * server, replace the files with that server's answers under the same names; the schema test
 * checks every file against its definition, and the other tests read them only through here.
 */
const files = import.meta.glob<unknown>('../fixtures/*.json', { eager: true, import: 'default' });

export type FixtureEntry = { schema: string; request: string };

export const fixtureManifest = files['../fixtures/manifest.json'] as Record<string, FixtureEntry>;

export function fixtureNames(): string[] {
    return Object.keys(files).map((path) => path.replace('../fixtures/', '')).filter((name) => name !== 'manifest.json').sort();
}

/** A fresh copy of a fixture, so a test can change it freely. */
// Typed loosely on purpose: tests reshape fixtures freely, and the schema test checks them.
export function fixture(name: string): any {
    const value = files[`../fixtures/${name}`];

    if (value === undefined) {
        throw new Error(`There's no fixture called ${name}.`);
    }

    return structuredClone(value);
}
