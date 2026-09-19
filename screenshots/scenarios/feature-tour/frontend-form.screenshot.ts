import { defineScreenshotScenario } from '@verbb/craft-screenshots/api';

import { seedCommentsFixture } from '../../support/fixtures';

let frontendRoute = '/stories/community-gardens-worth-visiting';

export default defineScreenshotScenario({
    id: 'comments-feature-tour-form',
    output: 'feature-tour/comments-form.png',
    route: () => frontendRoute,
    viewport: { width: 1100, height: 1100, deviceScaleFactor: 2 },
    async setup(context) {
        frontendRoute = (await seedCommentsFixture(context)).frontendRoute;
    },
    waitFor: [
        { type: 'loadState', state: 'networkidle' },
        { type: 'selector', selector: '.cc-f-wrap', state: 'visible', timeout: 30000 },
        { type: 'selector', selector: 'textarea[name="fields[comment]"]', state: 'visible' },
        { type: 'text', text: 'Maya Chen' },
        { type: 'text', text: 'Oliver Grant' },
    ],
    target: { type: 'selector', selector: 'main', padding: 6 },
    caption: 'A real threaded conversation above the default Comments form.',
    intent: 'Show approved comments, a threaded reply and the plugin’s genuine bundled front-end form.',
});
