export function generateSchema(): Record<string, unknown> & { $defs: Record<string, unknown> };
export function schemaText(schema?: Record<string, unknown>): string;
export function currentSchemaText(): string;
