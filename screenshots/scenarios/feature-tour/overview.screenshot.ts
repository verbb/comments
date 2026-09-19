import { defineScreenshotScenario } from '@verbb/craft-screenshots/api';

import { seedCommentsFixture } from '../../support/fixtures';

let commentsRoute = '/admin/comments';

export default defineScreenshotScenario({
    id: 'comments-feature-tour-overview',
    output: 'feature-tour/comments-overview.png',
    route: () => commentsRoute,
    viewport: { width: 1440, height: 900, deviceScaleFactor: 2 },
    async setup(context) {
        commentsRoute = (await seedCommentsFixture(context)).commentsRoute;
    },
    waitFor: [
        { type: 'loadState', state: 'networkidle' },
        { type: 'selector', selector: '#elements', state: 'visible', timeout: 30000 },
        { type: 'text', text: 'Maya Chen' },
        { type: 'text', text: 'Community gardens worth visiting' },
    ],
    steps: [
        {
            type: 'evaluate',
            expression: `
                (() => {
                    document.activeElement?.blur();
                    window.scrollTo(0, 0);

                    const style = document.createElement('style');
                    style.textContent = \`
                        #elements .tablepane,
                        #elements table { width: 914px !important; max-width: 914px !important; }
                        #elements table { table-layout: fixed !important; }
                        #elements table tr > *:nth-child(3),
                        #elements table tr > *:nth-child(n + 6) { display: none !important; }
                        #elements table tr > *:nth-child(1) { width: 50px !important; }
                        #elements table tr > *:nth-child(2) { width: 525px !important; }
                        #elements table tr > *:nth-child(4) { width: 123px !important; }
                        #elements table tr > *:nth-child(5) { width: 216px !important; white-space: normal !important; }
                        #elements table tr > *:nth-child(5) a { white-space: normal !important; }
                        #elements tbody td,
                        #elements tbody th { padding-top: 10px !important; padding-bottom: 10px !important; }
                    \`;
                    document.head.append(style);
                })();
            `,
        },
        { type: 'wait', waitFor: { type: 'timeout', ms: 250 } },
    ],
    target: {
        type: 'anchoredClip',
        selector: '#elements .tablepane',
        x: 0,
        y: 0,
        width: 914,
        height: 655,
    },
    caption: 'Comments awaiting review alongside approved conversations and threaded replies.',
    intent: 'Show realistic moderation content in the real Craft 5 Comments element index.',
});
