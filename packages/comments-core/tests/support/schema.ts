type Schema = {
    $ref?: string;
    const?: unknown;
    enum?: unknown[];
    anyOf?: Schema[];
    type?: string | string[];
    properties?: Record<string, Schema>;
    required?: string[];
    additionalProperties?: Schema | boolean;
    items?: Schema;
};

type Root = { $defs: Record<string, Schema> };

/**
 * Checks a value against one definition in the generated schema. It covers only the keywords the
 * generator writes. Strict by default: an object with listed properties may have no others, so a
 * misspelt or unplanned field in a fixture (or a server response) is caught, although the
 * published schema itself leaves objects open.
 */
export function validate(root: Root, definition: string, value: unknown, strict = true): string[] {
    const errors: string[] = [];
    check({ $ref: `#/$defs/${definition}` }, value, definition, errors);
    return errors;

    function check(schema: Schema, current: unknown, path: string, out: string[]): void {
        if (schema.$ref) {
            const name = schema.$ref.replace('#/$defs/', '');
            const target = root.$defs[name];

            if (!target) {
                out.push(`${path}: unknown definition ${name}`);
                return;
            }

            check(target, current, path, out);
            return;
        }

        if ('const' in schema && current !== schema.const) {
            out.push(`${path}: expected ${JSON.stringify(schema.const)}, got ${JSON.stringify(current)}`);
        }

        if (schema.enum && !schema.enum.includes(current as never)) {
            out.push(`${path}: ${JSON.stringify(current)} isn't one of ${JSON.stringify(schema.enum)}`);
        }

        if (schema.anyOf) {
            const branches = schema.anyOf.map((branch) => {
                const branchErrors: string[] = [];
                check(branch, current, path, branchErrors);
                return branchErrors;
            });

            if (!branches.some((branchErrors) => branchErrors.length === 0)) {
                out.push(`${path}: matches no option (${branches.map((branchErrors) => branchErrors[0]).join('; ')})`);
            }
        }

        if (schema.type) {
            const types = Array.isArray(schema.type) ? schema.type : [schema.type];

            if (!types.includes(typeOf(current))) {
                out.push(`${path}: expected ${types.join(' or ')}, got ${typeOf(current)}`);
                return;
            }
        }

        if (typeOf(current) === 'object' && (schema.properties || schema.additionalProperties !== undefined || schema.type === 'object')) {
            const object = current as Record<string, unknown>;

            for (const name of schema.required ?? []) {
                if (!(name in object)) out.push(`${path}.${name}: missing`);
            }

            for (const [name, item] of Object.entries(object)) {
                const property = schema.properties?.[name];

                if (property) {
                    check(property, item, `${path}.${name}`, out);
                } else if (typeof schema.additionalProperties === 'object') {
                    check(schema.additionalProperties, item, `${path}.${name}`, out);
                } else if (schema.additionalProperties === false || (strict && schema.properties)) {
                    out.push(`${path}.${name}: not in the schema`);
                }
            }
        }

        if (Array.isArray(current) && schema.items) {
            current.forEach((item, index) => check(schema.items!, item, `${path}[${index}]`, out));
        }
    }
}

function typeOf(value: unknown): string {
    if (value === null) return 'null';
    if (Array.isArray(value)) return 'array';
    return typeof value;
}
