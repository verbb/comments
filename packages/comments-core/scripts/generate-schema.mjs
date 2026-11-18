import { readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

/**
 * Generates `schema/data.json` (JSON Schema 2020-12) from the types exported by `src/types.ts`, so
 * the published schema can't drift from the types. Each exported type becomes an entry in
 * `$defs`, and descriptions come from the TSDoc comments. Run with `--write` to update the file;
 * the package tests fail when it's out of date.
 *
 * Objects are left open (no `additionalProperties: false`), because clients must ignore fields
 * they don't know and the format grows by adding fields. The tests validate strictly instead.
 */
const require = createRequire(import.meta.url);
const ts = require('typescript');
const packageDir = fileURLToPath(new URL('..', import.meta.url));
const schemaPath = fileURLToPath(new URL('../schema/data.json', import.meta.url));

export function generateSchema() {
    const entry = `${packageDir}src/types.ts`;
    const program = ts.createProgram([entry], {
        strict: true,
        target: ts.ScriptTarget.ES2022,
        module: ts.ModuleKind.ESNext,
        moduleResolution: ts.ModuleResolutionKind.Bundler,
        skipLibCheck: true,
        noEmit: true,
    });
    const checker = program.getTypeChecker();
    const source = program.getSourceFile(entry);
    const moduleSymbol = checker.getSymbolAtLocation(source);
    const exported = checker.getExportsOfModule(moduleSymbol)
        .filter((symbol) => symbol.flags & ts.SymbolFlags.TypeAlias)
        .sort((a, b) => a.name.localeCompare(b.name));
    const names = new Set(exported.map((symbol) => symbol.name));
    const defs = {};

    for (const symbol of exported) {
        const declaration = symbol.declarations[0];
        const schema = convert(checker.getTypeAtLocation(declaration.name), symbol.name);
        const description = docs(symbol);

        defs[symbol.name] = description ? { description, ...schema } : schema;
    }

    return {
        $schema: 'https://json-schema.org/draft/2020-12/schema',
        $id: 'https://docs.verbb.io/comments/schema/data.json',
        title: 'Comments data API',
        description: 'The JSON sent and accepted by the Comments data API (comments/api/*) for API version 1, generated from @verbb/comments-core’s types.',
        $defs: defs,
    };

    function docs(symbol) {
        return ts.displayPartsToString(symbol.getDocumentationComment(checker)).replace(/\s+/g, ' ').trim();
    }

    /** `self` is the alias being defined, which is expanded rather than referenced. */
    function convert(type, self) {
        const alias = type.aliasSymbol?.name;

        if (alias && alias !== self && names.has(alias) && !type.aliasTypeArguments) {
            return { $ref: `#/$defs/${alias}` };
        }

        if (type.flags & ts.TypeFlags.Unknown || type.flags & ts.TypeFlags.Any) return {};
        if (type.flags & ts.TypeFlags.Null) return { type: 'null' };
        if (type.flags & ts.TypeFlags.StringLiteral) return { const: type.value };
        if (type.flags & ts.TypeFlags.NumberLiteral) return { const: type.value };
        if (type.flags & ts.TypeFlags.BooleanLiteral) return { const: type.intrinsicName === 'true' };
        if (type.flags & ts.TypeFlags.String) return { type: 'string' };
        if (type.flags & ts.TypeFlags.Number) return { type: 'number' };
        if (type.flags & ts.TypeFlags.Boolean) return { type: 'boolean' };

        if (type.isUnion()) {
            // An optional property's type includes `undefined`, which JSON can't carry.
            const members = type.types.filter((member) => !(member.flags & ts.TypeFlags.Undefined));

            return members.length === 1 ? convert(members[0]) : union(members);
        }

        if (checker.isArrayType(type)) {
            return { type: 'array', items: convert(checker.getTypeArguments(type)[0]) };
        }

        return object(type);
    }

    function union(members) {
        const literals = members.filter((member) => member.flags & (ts.TypeFlags.StringLiteral | ts.TypeFlags.NumberLiteral | ts.TypeFlags.Null));

        // `boolean` arrives as `true | false`.
        if (members.length === 2 && members.every((member) => member.flags & ts.TypeFlags.BooleanLiteral)) {
            return { type: 'boolean' };
        }

        if (literals.length === members.length) {
            return { enum: members.map((member) => (member.flags & ts.TypeFlags.Null ? null : member.value)) };
        }

        const booleans = members.filter((member) => member.flags & ts.TypeFlags.BooleanLiteral);
        const rest = members.filter((member) => !(member.flags & ts.TypeFlags.BooleanLiteral));
        const options = rest.map((member) => convert(member));

        if (booleans.length === 2) options.push({ type: 'boolean' });

        // `T | null` is common enough to write the short way.
        if (options.length === 2 && options.some((option) => option.type === 'null')) {
            const other = options.find((option) => option.type !== 'null');

            if (typeof other.type === 'string' && Object.keys(other).length === 1) {
                return { type: [other.type, 'null'] };
            }
        }

        return { anyOf: options };
    }

    function object(type) {
        const schema = { type: 'object' };
        const properties = {};
        const required = [];

        for (const property of checker.getPropertiesOfType(type)) {
            const declaration = property.valueDeclaration ?? property.declarations?.[0];
            const propertyType = checker.getTypeOfSymbolAtLocation(property, declaration);
            const description = docs(property);
            const converted = convert(propertyType);

            properties[property.name] = description ? { description, ...converted } : converted;

            if (!(property.flags & ts.SymbolFlags.Optional)) {
                required.push(property.name);
            }
        }

        if (Object.keys(properties).length) {
            schema.properties = properties;
        }

        if (required.length) {
            schema.required = required;
        }

        const index = checker.getIndexInfoOfType(type, ts.IndexKind.String);

        if (index) {
            schema.additionalProperties = convert(index.type);
        }

        return schema;
    }
}

export function schemaText(schema = generateSchema()) {
    return `${JSON.stringify(schema, null, 4)}\n`;
}

export function currentSchemaText() {
    return readFileSync(schemaPath, 'utf8');
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
    const text = schemaText();

    if (process.argv.includes('--write')) {
        writeFileSync(schemaPath, text);
        console.log(`Wrote ${schemaPath}.`);
    } else if (text !== currentSchemaText()) {
        console.error('schema/data.json is out of date. Run `npm run schema -w @verbb/comments-core`.');
        process.exit(1);
    } else {
        console.log('schema/data.json is current.');
    }
}
