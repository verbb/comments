import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

import type { ScreenshotSetupContext } from '@verbb/craft-screenshots/types';

type CommentsFixture = {
    commentsRoute: string;
    settingsRoute: string;
    frontendRoute: string;
    commentCount: number;
};

const supportDir = dirname(fileURLToPath(import.meta.url));
const seedScript = readFileSync(join(supportDir, 'seed', 'seed-comments.php'), 'utf8');

/** Seed realistic entries, guest conversations and the default front-end form example. */
export async function seedCommentsFixture(context: ScreenshotSetupContext): Promise<CommentsFixture> {
    const output = await context.runCraftScript(seedScript, { label: 'seed-comments' });
    const fixture = JSON.parse(output.trim()) as CommentsFixture;

    if (!fixture.commentsRoute || !fixture.settingsRoute || !fixture.frontendRoute || fixture.commentCount < 9) {
        throw new Error(`Invalid Comments fixture payload: ${output}`);
    }

    return fixture;
}
