/**
 * The plugin's own editor, which is the plain box in the form with what Editor would add around it.
 * Post waits for some text, ⌘↵ or Ctrl+Enter posts, there's a character limit and an unsent draft is kept
 * in the browser. Plain authoring never needs more, and rich authoring writes here until Editor arrives.
 */

export type EditorLabels = {
  writeFirst: string;
  tooLong: string;
  charactersLeft: string;
  charactersOf: string;
  draftSaved: string;
  draftRestored: string;
  discard: string;
  posting: string;
};

type EditorState = {
  form: HTMLFormElement;
  box: HTMLTextAreaElement;
  frame: HTMLElement;
  submit: HTMLButtonElement | null;
  label: HTMLElement | null;
  status: HTMLElement;
  count: HTMLElement;
  notice: HTMLElement;
  labels: EditorLabels;
  announce: (message: string) => void;
  draftState: 'saved' | 'faded' | null;
  draftTimer?: number;
  fadeTimer?: number;
  collapseTimer?: number;
};

const DEFAULT_LABELS: EditorLabels = {
  writeFirst: 'Write something first.',
  tooLong: 'Too long by {count} characters',
  charactersLeft: '{count} characters left',
  charactersOf: '{count} of {limit} characters',
  draftSaved: 'Draft saved',
  draftRestored: 'Restored your draft from {time}.',
  discard: 'Discard',
  posting: 'Posting…',
};

const DRAFT_PREFIX = 'comments-draft:';
const DRAFT_VERSION = 1;
// The same paths Editor draws, so nothing changes when it takes over.
const ICONS = {
  check: '<path d="M20 6 9 17l-5-5"/>',
  alert: '<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>',
  info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
};

const states = new WeakMap<HTMLFormElement, EditorState>();

function format(template: string, values: Record<string, string | number>): string {
  return template.replace(/\{(\w+)\}/g, (match, key: string) => (key in values ? String(values[key]) : match));
}

function icon(name: keyof typeof ICONS): string {
  // Sized and drawn by its attributes too, so a site's own CSS starts from a sensible icon.
  return `<svg class="comments__frame-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONS[name]}</svg>`;
}

function element(tag: string, className: string, text?: string): HTMLElement {
  const el = document.createElement(tag);
  el.className = className;

  if (text !== undefined) {
    el.textContent = text;
  }

  return el;
}

function isMac(): boolean {
  const platform = (navigator as Navigator & { userAgentData?: { platform?: string } }).userAgentData?.platform ?? navigator.platform ?? '';
  return /mac|iphone|ipad|ipod/i.test(platform);
}

function relativeTime(timestamp: number): string {
  // At least a minute, so a draft from a moment ago doesn't read as "from now".
  const minutes = Math.min(-1, Math.round((timestamp - Date.now()) / 60000));
  let formatter: Intl.RelativeTimeFormat;

  // Some sites set a `lang` that isn't a valid language tag, such as `en_US`.
  try {
    formatter = new Intl.RelativeTimeFormat(document.documentElement.lang || undefined, { numeric: 'auto' });
  } catch {
    formatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
  }

  if (Math.abs(minutes) < 60) return formatter.format(minutes, 'minute');
  if (Math.abs(minutes) < 60 * 24) return formatter.format(Math.round(minutes / 60), 'hour');

  return formatter.format(Math.round(minutes / (60 * 24)), 'day');
}

// Storage can be full or blocked, and the editor still works without drafts.
function readDraft(key: string): { savedAt: number; text: string } | null {
  try {
    const draft: unknown = JSON.parse(localStorage.getItem(DRAFT_PREFIX + key) ?? 'null');

    if (!draft || typeof draft !== 'object') return null;

    const { version, savedAt, text } = draft as Record<string, unknown>;

    return version === DRAFT_VERSION && typeof savedAt === 'number' && typeof text === 'string' ? { savedAt, text } : null;
  } catch {
    return null;
  }
}

function writeDraft(key: string, text: string | null): void {
  try {
    if (text === null) {
      localStorage.removeItem(DRAFT_PREFIX + key);
    } else {
      localStorage.setItem(DRAFT_PREFIX + key, JSON.stringify({ version: DRAFT_VERSION, savedAt: Date.now(), text }));
    }
  } catch {
    // See readDraft().
  }
}

/** Whether Editor kept a draft for this form, which only Editor itself can bring back. */
export function hasVerbbEditorDraft(form: HTMLFormElement): boolean {
  const key = form.dataset.commentsDraftKey;

  try {
    return Boolean(key && localStorage.getItem(`verbb-editor-draft:${key}`));
  } catch {
    return false;
  }
}

/** The plain box a form's comment is written in. */
export function plainBox(form: ParentNode): HTMLTextAreaElement | null {
  return form.querySelector<HTMLTextAreaElement>('textarea[name$="[comment]"]');
}

/**
 * A rich form that will hand over to Editor keeps no draft here, because Editor keeps its own once it's
 * running and the two would only disagree.
 */
function keepsDraft(state: EditorState): string | null {
  return state.form.querySelector('template[data-comments-editor]') ? null : state.form.dataset.commentsDraftKey ?? null;
}

function limitOf(state: EditorState): number {
  return Number(state.form.dataset.commentsLimit) || 0;
}

/** Why Post can't go yet, or nothing if it can. */
export function editorBlocker(form: HTMLFormElement): string {
  const state = states.get(form);

  if (!state || form.dataset.commentsEditorMounted) {
    return '';
  }

  const text = state.box.value;
  const limit = limitOf(state);

  if (!text.trim()) return state.labels.writeFirst;
  if (limit > 0 && text.length > limit) return format(state.labels.tooLong, { count: text.length - limit });

  return '';
}

/** Says why Post isn't going, the way Editor does. */
export function refuseSubmit(form: HTMLFormElement): boolean {
  const reason = editorBlocker(form);
  const state = states.get(form);

  if (!reason || !state) {
    return false;
  }

  state.announce(reason);
  state.submit?.animate?.([{ transform: 'translateX(0)' }, { transform: 'translateX(-3px)' }, { transform: 'translateX(3px)' }, { transform: 'translateX(0)' }], { duration: 220 });
  state.box.focus();

  return true;
}

/** A ring appears at 80% of the limit and counts down the last stretch, then how far over. */
function renderCount(state: EditorState): void {
  const { count, labels } = state;
  const limit = limitOf(state);
  const length = state.box.value.length;

  count.replaceChildren();
  count.className = 'comments__frame-count';
  count.removeAttribute('title');

  if (!(limit > 0) || length < limit * 0.8) return;

  const left = limit - length;
  const radius = 8;
  const circumference = 2 * Math.PI * radius;
  const fraction = Math.min(length / limit, 1);

  count.classList.toggle('is-bad', left < 0);
  count.classList.toggle('is-warn', left >= 0 && left <= limit * 0.1);
  count.title = format(labels.charactersOf, { count: length.toLocaleString(), limit: limit.toLocaleString() });
  count.innerHTML = `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><circle class="comments__ring-bg" cx="10" cy="10" r="${radius}"/><circle class="comments__ring-fg" cx="10" cy="10" r="${radius}" stroke-dasharray="${circumference}" stroke-dashoffset="${circumference * (1 - fraction)}"/></svg>`;
  count.append(element('span', '', String(left)), element('span', 'comments__visually-hidden', left < 0 ? format(labels.tooLong, { count: -left }) : format(labels.charactersLeft, { count: left })));
}

/** Everything that depends on the text: the counter, the status, Post and the box's height. */
function render(state: EditorState): void {
  const { box, status, labels, submit } = state;
  const limit = limitOf(state);
  const over = limit > 0 ? box.value.length - limit : 0;

  renderCount(state);
  status.className = 'comments__frame-status';
  status.replaceChildren();

  if (over > 0) {
    status.classList.add('is-bad');
    status.innerHTML = icon('alert');
    status.append(element('span', 'comments__frame-status-text', format(labels.tooLong, { count: over })));
  } else if (state.draftState) {
    status.classList.toggle('is-faded', state.draftState === 'faded');
    status.innerHTML = icon('check');
    status.append(element('span', 'comments__frame-status-text', labels.draftSaved));
  }

  if (submit && !submit.hasAttribute('aria-busy')) {
    const reason = editorBlocker(state.form);
    submit.setAttribute('aria-disabled', String(Boolean(reason)));

    if (reason) {
      submit.setAttribute('aria-description', reason);
    } else {
      submit.removeAttribute('aria-description');
    }
  }

  // The box grows with what's written, as Editor's does, up to the same height.
  box.style.height = '';
  if (box.value && box.scrollHeight > box.clientHeight) {
    box.style.height = `${Math.min(box.scrollHeight + box.offsetHeight - box.clientHeight, 436)}px`;
  }
}

/**
 * A member's new comment is a single line until they start. Leaving the frame waits a moment, so a
 * press on Cancel or Post lands before the frame shrinks out from under it.
 */
function renderCollapsed(state: EditorState): void {
  if (!state.form.hasAttribute('data-comments-collapsible')) return;

  const collapsed = !state.frame.contains(document.activeElement) && !state.box.value;
  state.frame.toggleAttribute('data-comments-collapsed', collapsed);
}

function showNotice(state: EditorState, message: string, action?: { label: string; run: () => void }): void {
  const { notice } = state;

  notice.hidden = false;
  notice.innerHTML = icon('info');
  notice.append(element('span', 'comments__frame-notice-text', message));

  if (action) {
    const button = element('button', 'comments__frame-link', action.label) as HTMLButtonElement;
    button.type = 'button';
    button.addEventListener('click', action.run);
    notice.append(button);
  }
}

function clearNotice(state: EditorState): void {
  state.notice.hidden = true;
  state.notice.replaceChildren();
}

/** Saves the text in this browser shortly after each change, and forgets it once it's back to where it started. */
function scheduleDraft(state: EditorState): void {
  const key = keepsDraft(state);

  if (!key) return;

  window.clearTimeout(state.draftTimer);
  state.draftTimer = window.setTimeout(() => {
    const { box } = state;

    if (box.value === box.defaultValue || !box.value.trim()) {
      writeDraft(key, null);

      if (state.draftState) {
        state.draftState = null;
        render(state);
      }

      return;
    }

    if (readDraft(key)?.text === box.value) return;

    writeDraft(key, box.value);
    state.draftState = 'saved';
    render(state);
    window.clearTimeout(state.fadeTimer);
    state.fadeTimer = window.setTimeout(() => {
      state.draftState = 'faded';
      render(state);
    }, 2600);
  }, 800);
}

/** Brings back an unfinished draft, with a way to throw it away. */
function restoreDraft(state: EditorState): void {
  const key = keepsDraft(state);
  const draft = key ? readDraft(key) : null;

  if (!key || !draft || draft.text === state.box.value) return;

  state.box.value = draft.text;
  showNotice(state, format(state.labels.draftRestored, { time: relativeTime(draft.savedAt) }), {
    label: state.labels.discard,
    run: () => {
      clearEditorDraft(state.form);
      state.box.value = state.box.defaultValue;
      render(state);
      state.box.focus();
    },
  });
}

function onInput(state: EditorState): void {
  clearNotice(state);
  render(state);
  renderCollapsed(state);
  scheduleDraft(state);
}

/**
 * Turns a form's plain box into the editor. Safe to call again on the same form, and on forms drawn
 * by an overridden template that leaves parts out, where anything missing is simply not added.
 */
export function enhanceEditor(form: HTMLFormElement, labels: Partial<EditorLabels>, announce: (message: string) => void): void {
  const box = plainBox(form);
  const frame = box?.closest<HTMLElement>('.comments__frame') ?? null;

  if (states.has(form) || !box || !frame) {
    return;
  }

  // The stylesheet sizes the box from here on. Its `rows` are for visitors without JavaScript, and an
  // empty box would otherwise measure as tall as they make it.
  box.rows = 1;

  const submit = form.querySelector<HTMLButtonElement>('.comments__submit');
  const foot = form.querySelector<HTMLElement>('.comments__frame-foot');
  const meta = element('span', 'comments__frame-meta');
  const status = element('span', 'comments__frame-status');
  const count = element('span', 'comments__frame-count');
  const notice = element('div', 'comments__frame-notice');

  meta.append(status, count);
  foot?.prepend(meta);
  notice.setAttribute('role', 'status');
  notice.hidden = true;
  box.after(notice);

  if (submit && !submit.querySelector('kbd')) {
    const keys = element('kbd', 'comments__submit-keys', isMac() ? '⌘↵' : 'Ctrl+Enter');
    submit.querySelector('.comments__submit-label')?.after(keys);
  }

  const state: EditorState = {
    form,
    box,
    frame,
    submit,
    label: submit?.querySelector<HTMLElement>('.comments__submit-label') ?? null,
    status,
    count,
    notice,
    labels: { ...DEFAULT_LABELS, ...labels },
    announce,
    draftState: null,
  };

  states.set(form, state);
  form.dataset.commentsEditorFrame = '1';
  restoreDraft(state);
  render(state);
  renderCollapsed(state);
}

/** Handles the editor's own events, delegated from the thread's root. */
export function handleEditorEvent(event: Event): void {
  const target = event.target;

  if (!(target instanceof Element)) return;

  const form = target.closest<HTMLFormElement>('form[data-comments-editor-frame]');
  const state = form ? states.get(form) : undefined;

  if (!form || !state || form.dataset.commentsEditorMounted) return;

  switch (event.type) {
    case 'input':
      if (target === state.box) onInput(state);
      break;
    case 'keydown': {
      const key = event as KeyboardEvent;

      if (target === state.box && key.key === 'Enter' && (key.metaKey || key.ctrlKey)) {
        event.preventDefault();

        if (!refuseSubmit(form)) {
          form.requestSubmit(state.submit ?? undefined);
        }
      }
      break;
    }
    case 'click':
      // Post looks unavailable until there's something to post, and says why when it's pressed.
      if (state.submit && target.closest('.comments__submit') === state.submit && refuseSubmit(form)) {
        event.preventDefault();
      }
      break;
    case 'focusin':
      window.clearTimeout(state.collapseTimer);
      renderCollapsed(state);
      break;
    case 'focusout':
      window.clearTimeout(state.collapseTimer);
      state.collapseTimer = window.setTimeout(() => renderCollapsed(state), 140);
      break;
  }
}

/** Shows "Posting…" on the button while a post is under way. */
export function setEditorPosting(form: HTMLFormElement, posting: boolean): void {
  const state = states.get(form);

  if (!state?.submit || !state.label) return;

  const original = state.submit.getAttribute('aria-label') ?? state.label.textContent ?? '';
  state.label.textContent = posting ? state.labels.posting : original;
  state.submit.toggleAttribute('data-comments-posting', posting);

  if (posting) {
    state.submit.setAttribute('aria-disabled', 'true');
  } else {
    render(state);
  }
}

/** Draws the editor again after the form was reset or its text changed from outside. */
export function refreshEditor(form: HTMLFormElement): void {
  const state = states.get(form);

  if (!state) return;

  clearNotice(state);
  render(state);
  renderCollapsed(state);
}

export function clearEditorDraft(form: HTMLFormElement): void {
  const state = states.get(form);
  const key = state ? keepsDraft(state) : null;

  if (!state) return;

  window.clearTimeout(state.draftTimer);
  window.clearTimeout(state.fadeTimer);
  state.draftState = null;

  if (key) {
    writeDraft(key, null);
  }

  clearNotice(state);
  render(state);
}

/** Stops the timers of every editor in `scope`, which is leaving the page. */
export function destroyEditors(scope: ParentNode): void {
  const forms = scope instanceof HTMLFormElement ? [scope] : Array.from(scope.querySelectorAll<HTMLFormElement>('form[data-comments-editor-frame]'));

  forms.forEach((form) => {
    const state = states.get(form);

    if (state) {
      window.clearTimeout(state.draftTimer);
      window.clearTimeout(state.fadeTimer);
      window.clearTimeout(state.collapseTimer);
    }
  });
}
