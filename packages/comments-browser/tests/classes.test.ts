import { readFileSync, writeFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { classMatches, HOST_ATTRIBUTES, INTERNAL_CLASSES, MOUNT_POINT_ATTRIBUTES, PUBLIC_ATTRIBUTES, STABLE_CLASSES } from '../src/render';
import { classesIn, scenarios } from './support/scenarios';

const reference = new URL('../markup/classes.json', import.meta.url);

type Reference = { stable: Record<string, string>; internal: Record<string, string>; attributes: { public: Record<string, string>; internal: Record<string, string> } };

/** Where each list entry is first seen, as `<tag>` in a scenario, for the markup reference. */
async function generate(): Promise<Reference> {
    const drawn = await scenarios();
    const seen = new Map<string, string>();

    for (const [scenario, html] of Object.entries(drawn)) {
        for (const match of html.matchAll(/<([a-z][\w-]*)[^>]*\sclass="([^"]*)"/g)) {
            for (const name of match[2]!.split(/\s+/).filter(Boolean)) {
                const entry = classMatches(name, STABLE_CLASSES) ?? classMatches(name, INTERNAL_CLASSES) ?? name;
                if (!seen.has(entry)) seen.set(entry, `<${match[1]}> in ${scenario}`);
            }
        }
    }

    const pick = (list: readonly string[]) => Object.fromEntries([...list].sort().map((name) => [name, seen.get(name) ?? 'not drawn']));

    // Each `data-comments-*` attribute, public or internal. The mount point's own are drawn by
    // `craft.comments.render()` and the adapters, not the renderer's templates.
    const attributes = new Map<string, string>();

    for (const [scenario, html] of Object.entries(drawn)) {
        for (const match of html.matchAll(/<([a-z][\w-]*)([^>]*)>/g)) {
            for (const [, name] of match[2]!.matchAll(/\s(data-comments-[a-z-]+)(?==|\s|$)/g)) {
                if (!attributes.has(name!)) attributes.set(name!, `<${match[1]}> in ${scenario}`);
            }
        }
    }

    for (const name of MOUNT_POINT_ATTRIBUTES) if (!attributes.has(name)) attributes.set(name, 'on the mount point');
    for (const name of HOST_ATTRIBUTES) if (!attributes.has(name)) attributes.set(name, 'on a component override’s host');

    const publicAttributes = Object.fromEntries([...PUBLIC_ATTRIBUTES].sort().map((name) => [name, attributes.get(name) ?? 'not drawn']));
    const internalAttributes = Object.fromEntries([...attributes.keys()].filter((name) => !(PUBLIC_ATTRIBUTES as readonly string[]).includes(name)).sort().map((name) => [name, attributes.get(name)!]));

    return { stable: pick(STABLE_CLASSES), internal: pick(INTERNAL_CLASSES), attributes: { public: publicAttributes, internal: internalAttributes } };
}

describe('the stable class list', () => {
    it('classifies every class the renderer draws as stable or internal', async () => {
        const drawn = new Set<string>();
        Object.values(await scenarios()).forEach((html) => classesIn(html).forEach((name) => drawn.add(name)));
        const unlisted = [...drawn].filter((name) => !classMatches(name, STABLE_CLASSES) && !classMatches(name, INTERNAL_CLASSES));

        expect(unlisted).toEqual([]);
    });

    it('still draws every class markup/classes.json promises is stable', async () => {
        const committed = JSON.parse(readFileSync(reference, 'utf8')) as Reference;
        const generated = await generate();

        if (process.env.UPDATE_CLASSES) {
            // A stable class can be added here, but removing one is a major release: it has to be
            // taken out of markup/classes.json by hand, with an upgrade note.
            const stable = { ...generated.stable };
            for (const name of Object.keys(committed.stable)) stable[name] ??= 'not drawn';
            writeFileSync(reference, `${JSON.stringify({ ...generated, stable }, null, 4)}\n`);
            return;
        }

        const missing = Object.keys(committed.stable).filter((name) => generated.stable[name] === undefined || generated.stable[name] === 'not drawn');

        expect(missing).toEqual([]);
        expect(generated).toEqual(committed);
    });
});
