// The Node built-ins the server helpers' test uses, declared here rather than adding @types/node.
declare module 'node:child_process' {
    export function execFileSync(file: string, args: string[], options: { encoding: 'utf8' }): string;
}

declare const process: { execPath: string; env: Record<string, string | undefined> };
