// The two Node built-ins the class list test uses, declared here rather than adding @types/node.
declare module 'node:fs' {
    export function readFileSync(path: URL | string, encoding: 'utf8'): string;
    export function writeFileSync(path: URL | string, data: string): void;
}

declare const process: { env: Record<string, string | undefined> };
