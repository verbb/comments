import { describe, expect, it } from 'vitest';
import { createStringView, renderToString, threadTemplate } from '../src/render';
import { threadState } from './support/thread';

/**
 * A site can list no report reasons. Its report dialog then asks only for details, so a report can
 * still be sent.
 */
describe('the report dialog', () => {
    it('lists the site’s reasons as required choices', async () => {
        const html = renderToString(threadTemplate(createStringView({ state: await threadState(), ui: { reportUid: 'c1' } })));

        expect(html).toContain('class="comments__report-fields"');
        expect(html).toContain('name="reason" value="spam" required');
    });

    it('leaves the reasons out when the site lists none', async () => {
        const state = await threadState({ conversation: { reportReasons: [] } });
        const html = renderToString(threadTemplate(createStringView({ state, ui: { reportUid: 'c1' } })));

        expect(html).toContain('data-comments-form="report"');
        expect(html).not.toContain('class="comments__report-fields"');
        expect(html).not.toContain('name="reason"');
    });
});
