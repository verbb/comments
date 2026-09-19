import { defineScreenshotScenario } from '@verbb/craft-screenshots/api';

import { seedCommentsFixture } from '../../support/fixtures';

let settingsRoute = '/admin/comments/settings';

export default defineScreenshotScenario({
    id: 'comments-feature-tour-settings',
    output: 'feature-tour/comments-settings.png',
    route: () => settingsRoute,
    viewport: { width: 1280, height: 900, deviceScaleFactor: 2 },
    async setup(context) {
        settingsRoute = (await seedCommentsFixture(context)).settingsRoute;
    },
    waitFor: [
        { type: 'loadState', state: 'networkidle' },
        { type: 'text', text: 'Allow Guest Comments' },
        { type: 'text', text: 'Maximum Reply Depth' },
    ],
    preSteps: [
        {
            type: 'evaluate',
            expression: `
                (() => {
                    document.activeElement?.blur();
                    window.scrollTo(0, 0);
                    document.querySelector('#main-container')?.scrollTo(0, 0);
                    document.querySelector('#content-container')?.scrollTo(0, 0);
                })();
            `,
        },
        { type: 'wait', waitFor: { type: 'timeout', ms: 300 } },
    ],
    target: {
        type: 'clip',
        x: 225,
        y: 44,
        width: 1055,
        height: 856,
    },
    caption: 'The Comments settings area with controls for guests, moderation and reply limits.',
    intent: 'Show the breadth of the real Craft 5 settings UI, including its current settings sidebar.',
});
