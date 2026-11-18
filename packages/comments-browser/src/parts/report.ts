import { html, nothing, type TemplateResult } from 'lit-html';
import { attributes } from '../directives.js';
import type { ThreadView } from '../view.js';

/** Characters of the reported comment quoted in the dialog, so it's clear which one this is. */
const QUOTE_LENGTH = 200;

/**
 * The report dialog. It's one `<dialog>` for the thread, opened with `showModal()` for whichever
 * comment's Report… was chosen, and its form can't be overridden, because it carries the reasons
 * the server accepts.
 */
export function reportDialog(view: ThreadView): TemplateResult {
    const { labels, state, instanceId } = view;
    const target = view.ui.reportUid ? state.comments[view.ui.reportUid] : undefined;
    const text = (target?.content?.text ?? '').replace(/\s+/g, ' ').trim();
    const quote = text.length > QUOTE_LENGTH ? `${text.slice(0, QUOTE_LENGTH)}…` : text;
    const reasons = state.conversation?.reportReasons ?? [];

    return html`<dialog class="comments__report-dialog" data-comments-report-dialog aria-labelledby=${`${instanceId}-report-title`}><form class="comments__report-form" data-comments-form="report" novalidate>${view.ui.reportError ? html`<p class="comments__form-error" tabindex="-1">${view.ui.reportError}</p>` : nothing}<div class="comments__report-header"><h3 class="comments__report-title" id=${`${instanceId}-report-title`}>${labels.reportTitle}</h3><button type="button" class="comments__report-close" data-comments-action="cancel-report" aria-label=${labels.close}>×</button></div><blockquote class="comments__report-quote" ?hidden=${!target}>${target?.author?.name ? html`<strong>${target.author.name}</strong>: ` : nothing}${quote}</blockquote><fieldset class="comments__report-fields"><legend class="comments__label">${labels.reportReason}</legend>${reasons.map((reason, index) => html`<label class="comments__report-reason" for=${`${instanceId}-report-reason-${index + 1}`}><input ${attributes(view.control('radio', { attributes: { id: `${instanceId}-report-reason-${index + 1}`, type: 'radio', name: 'reason', value: reason.value, required: true } }))}><span><span class="comments__report-reason-label">${reason.label}</span>${reason.description ? html`<span class="comments__report-reason-description">${reason.description}</span>` : nothing}</span></label>`)}</fieldset><label class="comments__field" for=${`${instanceId}-report-details`}><span class="comments__label">${labels.reportDetails}</span><span class="comments__field-instructions">${labels.reportDetailsHint}</span><textarea ${attributes(view.control('textarea', { attributes: { id: `${instanceId}-report-details`, name: 'details', rows: 3, maxlength: 2000 } }))}></textarea></label><div class="comments__report-actions"><button ${attributes(view.control('secondaryButton', { attributes: { type: 'button', 'data-comments-action': 'cancel-report' } }))}>${labels.cancel}</button><button ${attributes(view.control('primaryButton', { identify: 'comments__submit', attributes: { type: 'submit', 'aria-busy': view.ui.busy.has('report') ? 'true' : null } }))}>${labels.sendReport}</button></div></form></dialog>`;
}
