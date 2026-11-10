import { editorElementOf, destroyForm, initForm, isUploading, prepareForm, setPosting, wantsEditor } from './editor-bridge';
import {
  clearEditorDraft,
  destroyEditors,
  enhanceEditor,
  handleEditorEvent,
  hasVerbbEditorDraft,
  plainBox,
  refreshEditor,
  refuseSubmit,
  setEditorPosting,
  type EditorLabels,
} from './editor-frame';
import { frontendApiUrl } from './api-url';
import { mountProtections, destroyProtections, prepareProtection, protectionSubmitted } from './protection';
import { applyFragments, isDirtyForm } from './fragments';
import { envelopeMessage, isEnvelopeError } from './envelope';
import { dispatchCommentsEvent } from './events';
import { requestEnvelope } from './request';
import type { CommentsController, CommentsEditorMode, CommentsEventDetailMap, CommentsEventName, CommentsFormErrors } from './types/public';

type InstanceOptions = {
  apiBase: string;
};

function dispatch<K extends CommentsEventName>(root: HTMLElement, name: K, detail: CommentsEventDetailMap[K]): void {
  dispatchCommentsEvent(root, name, detail, { controller: controllers.get(root) });
}

/** A form value as the events report it: text, or nothing. */
function formValue(body: FormData, name: string): string | null {
  const value = body.get(name);

  return typeof value === 'string' ? value : null;
}

const controllers = new WeakMap<HTMLElement, CommentsController>();
// Shared by every thread on the page, so Editor loads once however many there are.
const editorLoads = new Map<string, Promise<void>>();
// How long an opening reply or edit waits for an Editor bundle the page loads itself. The plain box
// stays usable meanwhile, and Editor still takes over if it turns up later.
const EDITOR_WAIT_MS = 8000;

function apiUrl(base: string, path: string, params: Record<string, string | number | undefined> = {}): string {
  const url = frontendApiUrl(base, path);
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== '') {
      url.searchParams.set(key, String(value));
    }
  }
  return url.toString();
}

function commentIdFromElement(el: Element): string | null {
  const host = el.closest('[data-comments-comment-id]');
  if (host instanceof HTMLElement && host.dataset.commentsCommentId) {
    return host.dataset.commentsCommentId;
  }

  const form = el.closest('form');
  const input = form?.querySelector<HTMLInputElement>('input[name="commentId"]');
  return input?.value ?? null;
}

function reportDialog(root: HTMLElement): HTMLDialogElement | null {
  const dialog = root.querySelector('[data-comments-report-dialog]');
  return dialog instanceof HTMLDialogElement ? dialog : null;
}

function deepLinkId(): string | null {
  const hash = window.location.hash;
  if (!hash.startsWith('#comment-')) {
    return null;
  }

  const id = hash.slice('#comment-'.length);
  return /^\d+$/.test(id) ? id : null;
}

function focusComment(root: HTMLElement, uid: string | null | undefined): boolean {
  const article = uid
    ? root.querySelector<HTMLElement>(`[data-comments-comment][data-comments-comment-uid="${uid}"] > article`)
    : null;

  article?.focus();
  return Boolean(article);
}

/**
 * The first thing to fill in: a guest's empty name or address, then what they're writing. Protect's
 * trap field is left alone, and so is anything hidden, such as the plain box behind Editor.
 */
function focusFirstField(scope: ParentNode | null | undefined): void {
  if (!scope) {
    return;
  }

  const fields = Array.from(scope.querySelectorAll<HTMLElement>(
    '[contenteditable="true"], input:not([type="hidden"]), textarea, select',
  )).filter((field) => field.getClientRects().length > 0 && !field.closest('[data-comments-protection], .protect-honeypot'));
  const emptyRequired = fields.find((field) => field instanceof HTMLInputElement && field.required && !field.value);
  // Focusing Editor's element itself puts the caret in its text, which may not be its first focusable part.
  const form = scope.querySelector<HTMLFormElement>('form[data-comments-editor-mounted]')
    ?? (scope instanceof HTMLFormElement && scope.dataset.commentsEditorMounted ? scope : null);
  const editorElement = form ? editorElementOf(form) : null;

  (emptyRequired ?? editorElement ?? fields[0])?.focus();
}

/**
 * Enough to find the same control again after the server has redrawn it.
 */
function controlSelector(el: Element | null): string | null {
  const control = el?.closest<HTMLElement>('[data-comments-action]');
  if (!control) {
    return null;
  }

  const { commentsAction, commentsCommentUid, commentsRootUid, commentsInteractionType, commentsModerationAction } = control.dataset;
  const attributes: Array<[string, string | undefined]> = [
    ['data-comments-action', commentsAction],
    ['data-comments-comment-uid', commentsCommentUid],
    ['data-comments-root-uid', commentsRootUid],
    ['data-comments-interaction-type', commentsInteractionType],
    ['data-comments-moderation-action', commentsModerationAction],
  ];

  return attributes
    .filter(([, value]) => value)
    .map(([name, value]) => `[${name}="${value}"]`)
    .join('');
}

function setBusy(el: Element | null | undefined, busy: boolean): void {
  if (!el) {
    return;
  }

  // A menu item closes with its menu, so the menu's own button shows the wait instead.
  const targets = [el, el.closest('.comments__menu')?.querySelector('.comments__menu-toggle')];

  targets.forEach((target) => {
    if (!target) {
      return;
    }

    if (busy) {
      target.setAttribute('aria-busy', 'true');
    } else {
      target.removeAttribute('aria-busy');
    }
  });
}

function clearFormErrors(form: HTMLFormElement): void {
  form.querySelectorAll('.comments__field-error, .comments__form-error').forEach((el) => el.remove());
  form.querySelectorAll('[aria-invalid]').forEach((el) => {
    el.removeAttribute('aria-invalid');

    const describedBy = (el.getAttribute('aria-describedby') ?? '').split(' ').filter((id) => !id.endsWith('-error')).join(' ');
    if (describedBy) {
      el.setAttribute('aria-describedby', describedBy);
    } else {
      el.removeAttribute('aria-describedby');
    }
  });
}

/**
 * Say what's wrong next to each field, and take the author to the first of them. `message` stands in
 * for a field's own message if it has none, and goes at the top of the form if no field can be found.
 * The same markup serves the server's errors and a site's own, through `controller.showFormErrors()`.
 */
function showFormErrors(form: HTMLFormElement, message: string, fieldErrors: Record<string, string[]> = {}): void {
  clearFormErrors(form);

  let first: HTMLElement | null = null;
  const prefix = form.dataset.commentsOperationId ?? 'comment';

  Object.entries(fieldErrors).forEach(([name, messages], index) => {
    const input = form.querySelector<HTMLElement>(`[name="${name}"]:not([type="hidden"]), [name="${name}[]"]:not([type="hidden"])`);
    const editor = name === 'fields[comment]' || name === 'fields[editorContent]' ? form.querySelector<HTMLElement>('[data-editor-root]') : null;
    const anchor = form.dataset.commentsEditorMounted && editor ? editor : input;

    if (!anchor) {
      return;
    }

    // What someone's actually typing in is inside the editor, and that's what reports the error.
    const target = anchor === editor ? editor.querySelector<HTMLElement>('[contenteditable]') ?? anchor : anchor;

    const id = `${prefix}-${index}-error`;
    const error = document.createElement('p');
    error.className = 'comments__field-error';
    error.id = id;
    error.textContent = messages[0] ?? message;
    anchor.after(error);
    target.setAttribute('aria-invalid', 'true');
    target.setAttribute('aria-describedby', [target.getAttribute('aria-describedby'), id].filter(Boolean).join(' '));
    first ??= anchor;
  });

  if (first) {
    focusFirstField((first as HTMLElement).parentElement ?? form);
    return;
  }

  if (!message) {
    return;
  }

  const error = document.createElement('p');
  error.className = 'comments__form-error';
  error.textContent = message;
  error.tabIndex = -1;
  form.prepend(error);
  error.focus();
}

function highlightComment(root: HTMLElement, id: string): boolean {
  const target = root.querySelector<HTMLElement>(`[data-comments-comment-id="${id}"]`);
  if (!target) {
    return false;
  }

  root.querySelectorAll('.comments__comment--highlight').forEach((el) => {
    el.classList.remove('comments__comment--highlight');
  });

  target.classList.add('comments__comment--highlight');
  target.scrollIntoView({
    behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
    block: 'center',
  });
  target.querySelector<HTMLElement>(':scope > article')?.focus({ preventScroll: true });

  window.setTimeout(() => {
    target.classList.remove('comments__comment--highlight');
  }, 4000);

  return true;
}

export function bindInstance(root: HTMLElement, options: InstanceOptions): CommentsController {
  const abort = new AbortController();
  const ownerId = root.dataset.commentsOwnerId ?? '';
  const siteId = root.dataset.commentsSiteId ?? '';
  const requestedThreads = new Set<string>();
  // Starts as what the page was rendered with, so what loads later keeps the same order and size.
  let currentSort: string | undefined = root.dataset.commentsSort;
  const rootLimit = root.dataset.commentsLimit;
  let editorOpener: string | null = null;
  let feedbackTimer: number | undefined;

  const controller: CommentsController = {
    root,
    conversationUid: root.dataset.commentsConversationUid ?? '',
    refresh(opts) {
      void refreshConversation(opts?.reason ?? 'refresh', opts?.sort);
    },
    openEditor(opts) {
      void openEditor(opts.mode, opts.commentUid);
    },
    closeEditor(opts) {
      closeEditor(opts?.reason ?? 'user-cancel');
    },
    showFormErrors(form, errors: CommentsFormErrors) {
      const fieldErrors = errors.fieldErrors ?? {};
      const message = errors.message ?? '';

      showFormErrors(form, message, fieldErrors);
      // The field's own description is read when focus lands there, and the live region covers the rest.
      announce(Object.values(fieldErrors)[0]?.[0] ?? message);
    },
    clearFormErrors(form) {
      clearFormErrors(form);
    },
    destroy() {
      abort.abort();
      window.clearTimeout(feedbackTimer);
      delete root.dataset.commentsJs;
      destroyProtections(root);
      root.removeEventListener('click', onClick);
      root.removeEventListener('change', onChange);
      root.removeEventListener('submit', onSubmit);
      root.removeEventListener('editor-reply-cancel', onReplyCancel);
      destroyEditors(root);
      dispatch(root, 'comments:destroyed', {});
    },
  };

  async function hydratePrivateViewerControls(): Promise<void> {
    if (root.dataset.commentsSharedCache !== '1' || !ownerId) {
      return;
    }

    delete root.dataset.commentsSharedCache;
    delete root.dataset.commentsLazy;

    dispatch(root, 'comments:request-start', { operationId: null, reason: 'viewer-hydrate' });

    try {
      const envelope = await requestEnvelope(
        apiUrl(options.apiBase, 'viewer-controls', { ownerId, siteId, sort: currentSort, limit: rootLimit }),
        root,
        { signal: abort.signal },
      );

      if (await applyEnvelopeResponse(envelope, 'viewer-hydrate')) {
        dispatch(root, 'comments:viewer-hydrated', {});
      }
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Could not load viewer controls.';
      showFeedback(root, message, true, true);
      dispatch(root, 'comments:request-error', { reason: 'viewer-hydrate', message });
    }
  }

  /**
   * A linked comment may be pages back or below a collapsed reply, so its thread is fetched if it isn't here.
   */
  async function revealDeepLink(): Promise<void> {
    const id = deepLinkId();

    if (!id || highlightComment(root, id) || requestedThreads.has(id) || !ownerId) {
      return;
    }

    requestedThreads.add(id);

    try {
      const envelope = await requestEnvelope(
        apiUrl(options.apiBase, 'thread', { ownerId, siteId, commentId: id }),
        root,
        { signal: abort.signal },
      );

      if (await applyEnvelopeResponse(envelope, 'deep-link')) {
        highlightComment(root, id);
      }
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Could not load the linked comment.';
      dispatch(root, 'comments:request-error', { reason: 'deep-link', message });
    }
  }

  function openReportDialog(actionEl: HTMLElement): void {
    const dialog = reportDialog(root);
    const form = dialog?.querySelector('[data-comments-form="report"]');
    const commentId = commentIdFromElement(actionEl);

    if (!(dialog instanceof HTMLDialogElement) || !(form instanceof HTMLFormElement) || !commentId) {
      return;
    }

    form.reset();
    clearFormErrors(form);
    const commentInput = form.querySelector<HTMLInputElement>('input[name="commentId"]');
    if (commentInput) {
      commentInput.value = commentId;
    }

    // Quote the comment, so it's clear which one is being reported.
    const quote = dialog.querySelector<HTMLElement>('[data-comments-report-quote]');
    const article = actionEl.closest('[data-comments-comment]')?.querySelector(':scope > article');
    if (quote && article) {
      const author = article.querySelector('.comments__author-name')?.textContent?.trim() ?? '';
      const text = (article.querySelector(':scope > .comments__content')?.textContent ?? '').replace(/\s+/g, ' ').trim();
      quote.replaceChildren();
      if (author) {
        const name = document.createElement('strong');
        name.textContent = author;
        quote.append(name, document.createTextNode(': '));
      }
      quote.append(document.createTextNode(text.length > 200 ? `${text.slice(0, 200)}…` : text));
      quote.hidden = false;
    }

    actionEl.closest('details')?.removeAttribute('open');
    dialog.showModal();
  }

  function closeReportDialog(): void {
    const dialog = reportDialog(root);
    dialog?.close();
  }

  async function applyEnvelopeResponse(
    envelope: Awaited<ReturnType<typeof requestEnvelope>>,
    reason: string,
    form?: HTMLFormElement,
  ): Promise<boolean> {
    if (isEnvelopeError(envelope)) {
      const message = envelopeMessage(envelope);
      const error = envelope.errors?.[0];

      if (form) {
        showFormErrors(form, message, error?.fieldErrors);
        announce(message);
      } else {
        showFeedback(root, message, true);
      }

      dispatch(root, 'comments:request-error', {
        reason,
        message,
        category: error?.category,
        code: error?.code,
        fieldErrors: error?.fieldErrors,
        recoverable: error?.recoverable,
      });
      return false;
    }

    if (envelope.fragments) {
      // Redrawing what someone's using mustn't leave their keyboard back at the top of the page.
      const focused = document.activeElement;
      const selector = root.contains(focused) ? controlSelector(focused) : null;
      const commentUid = focused?.closest<HTMLElement>('[data-comments-comment]')?.dataset.commentsCommentUid;

      destroyProtections(root);
      applyFragments(root, envelope.fragments);
      mountEditors(root);
      mountProtections(root);

      if (selector !== null && (!document.activeElement || document.activeElement === document.body)) {
        const again = selector ? root.querySelector<HTMLElement>(selector) : null;

        if (again) {
          again.focus();
        } else {
          focusComment(root, commentUid);
        }
      }
    }

    if (envelope.announcement) {
      showFeedback(root, envelope.announcement, false);
    }

    dispatch(root, 'comments:request-end', { reason });
    return true;
  }

  async function refreshConversation(reason: string, sort?: string, control?: Element | null): Promise<void> {
    if (!ownerId) {
      return;
    }

    if (sort) {
      currentSort = sort;
    }

    dispatch(root, 'comments:request-start', { operationId: null, reason });

    // The list is replaced when the response arrives, so only the control needs clearing.
    const list = root.querySelector('.comments__list');
    setBusy(control, true);
    setBusy(list, true);

    try {
      const envelope = await requestEnvelope(
        apiUrl(options.apiBase, 'conversation', {
          ownerId,
          siteId,
          sort: currentSort,
          limit: rootLimit,
        }),
        root,
        { signal: abort.signal },
      );

      await applyEnvelopeResponse(envelope, reason);
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Refresh failed.';
      showFeedback(root, message, true, true);
      dispatch(root, 'comments:request-error', { reason, message });
    } finally {
      setBusy(control, false);
      setBusy(list, false);
    }
  }

  async function loadRoots(cursor: string, control: HTMLElement): Promise<void> {
    dispatch(root, 'comments:request-start', { operationId: null, reason: 'load-roots' });
    setBusy(control, true);
    const shown = root.querySelectorAll('.comments__list > li').length;

    try {
      const envelope = await requestEnvelope(
        apiUrl(options.apiBase, 'roots', { ownerId, siteId, cursor, sort: currentSort, limit: rootLimit }),
        root,
        { signal: abort.signal },
      );

      await applyEnvelopeResponse(envelope, 'load-roots');
      root.querySelectorAll<HTMLElement>('.comments__list > li > article')[shown]?.focus();
      dispatch(root, 'comments:roots-loaded', {});
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Load more failed.';
      showFeedback(root, message, true);
      dispatch(root, 'comments:request-error', { reason: 'load-roots', message });
      setBusy(control, false);
    }
  }

  async function loadReplies(commentUid: string, limit: string | undefined, control: HTMLElement): Promise<void> {
    dispatch(root, 'comments:request-start', { operationId: null, reason: 'load-replies' });
    setBusy(control, true);
    const hostSelector = `[data-comments-replies][data-comments-root-uid="${commentUid}"]`;
    const shown = root.querySelectorAll(`${hostSelector} > ol > li`).length;

    try {
      const envelope = await requestEnvelope(
        apiUrl(options.apiBase, 'replies', { commentUid, siteId, ...(limit ? { limit } : {}) }),
        root,
        { signal: abort.signal },
      );

      await applyEnvelopeResponse(envelope, 'load-replies');
      root.querySelectorAll<HTMLElement>(`${hostSelector} > ol > li > article`)[shown]?.focus();
      dispatch(root, 'comments:replies-loaded', { commentUid });
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Load replies failed.';
      showFeedback(root, message, true);
      dispatch(root, 'comments:request-error', { reason: 'load-replies', message });
      setBusy(control, false);
    }
  }

  async function postMutation(path: string, body: FormData, reason: string, control?: Element | null, form?: HTMLFormElement): Promise<boolean> {
    dispatch(root, 'comments:request-start', { operationId: null, reason });
    setBusy(control, true);

    // The site the thread belongs to, so what comes back is drawn for it.
    if (siteId && !body.has('siteId')) {
      body.set('siteId', siteId);
    }

    try {
      const envelope = await requestEnvelope(apiUrl(options.apiBase, path), root, {
        method: 'POST',
        body,
        signal: abort.signal,
      });

      return await applyEnvelopeResponse(envelope, reason, form);
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Request failed.';
      showFeedback(root, message, true);
      dispatch(root, 'comments:request-error', { reason, message });
      return false;
    } finally {
      setBusy(control, false);
    }
  }

  async function openEditor(mode: string, commentUid?: string, control?: HTMLElement): Promise<void> {
    const openForm = root.querySelector<HTMLFormElement>('[data-comments-form="reply"], [data-comments-form="edit"]');

    // There's one reply or edit form at a time, and opening another can't quietly lose what's in this one.
    if (isDirtyForm(openForm) && !window.confirm(root.dataset.commentsDiscardMessage ?? 'Discard your changes?')) {
      return;
    }

    closeEditor('replaced', false);
    editorOpener = control ? controlSelector(control) : null;
    // Opening a reply or an edit is the moment to fetch Editor, alongside the form.
    prefetchEditor();
    setBusy(control, true);
    dispatch(root, 'comments:request-start', { operationId: null, reason: 'editor-open' });

    try {
      const envelope = await requestEnvelope(
        apiUrl(options.apiBase, 'editor', {
          ownerId,
          siteId,
          mode,
          commentUid,
        }),
        root,
        { signal: abort.signal },
      );

      if (!await applyEnvelopeResponse(envelope, 'editor-open')) {
        return;
      }

      const host = mode === 'create'
        ? root.querySelector<HTMLElement>('[data-comments-region="editor"]')
        : root.querySelector<HTMLElement>(`[data-comments-region="contextual-editor"][data-comments-comment-uid="${commentUid}"]`);
      const form = host?.querySelector<HTMLFormElement>('form[data-comments-form]');

      if (!host || !form) {
        return;
      }

      // Opened means ready: the form is in the page, with Editor running if it writes in one,
      // which keeps an edited comment's formatting. A page that neither has Editor nor says where to load
      // it from gets the plain box straight away. The detail stays plain data, so it can be logged;
      // there's only ever one reply or edit form to find.
      mountEditors(host);

      if (wantsEditor(form) && (customElements.get('verbb-editor') || editorModule(form))) {
        await upgradeForm(form);
      } else if (wantsEditor(form)) {
        void upgradeForm(form);
      }

      focusFirstField(form);
      dispatch(root, 'comments:editor-opened', { mode, commentUid: commentUid ?? null });
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Could not open the editor.';
      showFeedback(root, message, true);
      dispatch(root, 'comments:request-error', { reason: 'editor-open', message });
    } finally {
      setBusy(control, false);
    }
  }

  function closeEditor(reason: string, restoreFocus = true): void {
    const activeForm = root.querySelector<HTMLFormElement>('[data-comments-form="reply"], [data-comments-form="edit"]');
    const host = activeForm?.closest('[data-comments-region="contextual-editor"]');

    if (host instanceof HTMLElement) {
      destroyForm(host);
      destroyEditors(host);
      destroyProtections(host);
      host.innerHTML = '';

      if (restoreFocus && editorOpener) {
        root.querySelector<HTMLElement>(editorOpener)?.focus();
      }

      dispatch(root, 'comments:editor-closed', { reason });
    }
  }

  /**
   * Cancel on the editor at the top clears it. A member's goes back to one line, and a guest's closes
   * back to the Join card.
   */
  function cancelNewComment(form: HTMLFormElement): void {
    if (isDirtyForm(form) && !window.confirm(root.dataset.commentsDiscardMessage ?? 'Discard your changes?')) {
      return;
    }

    form.reset();
    clearEditorDraft(form);
    refreshEditor(form);
    clearFormErrors(form);

    const guest = form.closest<HTMLDetailsElement>('details.comments__join-guest');

    if (guest) {
      guest.open = false;
      guest.querySelector<HTMLElement>(':scope > summary')?.focus();
    } else {
      root.querySelector<HTMLElement>('.comments__heading')?.focus();
    }
  }

  /** Where this form, or failing that the thread, says Editor's bundle is. */
  function editorModule(form?: HTMLFormElement | null): string | undefined {
    return form?.dataset.commentsEditorModule || root.dataset.commentsEditorModule || undefined;
  }

  /**
   * Editor's bundle, which the page either loads itself or leaves to us to load once someone's about to
   * write in a rich editor. Without it, people carry on in the plain box.
   */
  async function loadEditor(form?: HTMLFormElement | null): Promise<void> {
    if (customElements.get('verbb-editor')) {
      return;
    }

    const module = editorModule(form);
    const css = form?.dataset.commentsEditorCss || root.dataset.commentsEditorCss;

    if (!module) {
      await Promise.race([
        customElements.whenDefined('verbb-editor'),
        new Promise((_, reject) => window.setTimeout(() => reject(new Error('Editor did not load.')), EDITOR_WAIT_MS)),
      ]);

      return;
    }

    if (css && !document.querySelector(`link[href="${css}"]`)) {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = css;
      document.head.append(link);
    }

    let loading = editorLoads.get(module);

    if (!loading) {
      loading = new Promise<void>((resolve, reject) => {
        const script = document.createElement('script');
        script.src = module;
        script.type = 'module';
        script.onload = () => resolve();
        script.onerror = () => {
          // Another try can load it, if the network comes back.
          editorLoads.delete(module);
          script.remove();
          reject(new Error('Editor could not be loaded.'));
        };
        document.head.append(script);
      });
      editorLoads.set(module, loading);
    }

    await loading;
    await customElements.whenDefined('verbb-editor');
  }

  /**
   * Starts fetching Editor ahead of need, when a rich thread's visitor heads for an editor, Reply or
   * Edit. Nothing changes on the page until they actually start.
   */
  function prefetchEditor(form?: HTMLFormElement | null): void {
    if (!customElements.get('verbb-editor') && editorModule(form)) {
      loadEditor(form).catch(() => undefined);
    }
  }

  /**
   * Swap a rich form's plain box for Editor, loading Editor first if it isn't here yet.
   * Whatever was typed meanwhile comes along.
   */
  async function upgradeForm(form: HTMLFormElement): Promise<void> {
    if (form.dataset.commentsEditorMounted || !wantsEditor(form)) {
      return;
    }

    try {
      await loadEditor(form);
    } catch {
      // The plain box stays usable if it doesn't arrive.
      return;
    }

    if (abort.signal.aborted || !form.isConnected || form.dataset.commentsEditorMounted || form.dataset.commentsSubmitting) {
      return;
    }

    const editorElement = initForm(form, { theme: root.dataset.commentsTheme });

    if (editorElement) {
      form.dataset.commentsEditorMounted = '1';
      // Editor collapses itself from here on.
      form.querySelector('.comments__frame')?.removeAttribute('data-comments-collapsed');

      // What the author started with, so leaving with nothing new to lose doesn't ask first.
      if (editorElement.dataset.commentsInitialText === undefined) {
        editorElement.dataset.commentsInitialText = editorElement.editor?.getText() ?? '';
      }
    }
  }

  function editorLabels(): Partial<EditorLabels> {
    try {
      const labels: unknown = JSON.parse(root.dataset.commentsEditorLabels ?? '{}');
      return labels && typeof labels === 'object' ? labels as Partial<EditorLabels> : {};
    } catch {
      return {};
    }
  }

  /**
   * Every form starts in the plugin's own editor. Editor only takes over a rich form here when it
   * already has a reason to: a draft only Editor can bring back, or an override that puts
   * `<verbb-editor>` straight into the form.
   */
  function mountEditors(scope: ParentNode): void {
    scope.querySelectorAll<HTMLFormElement>('form[data-comments-form]').forEach((form) => {
      if (!['create', 'reply', 'edit'].includes(form.dataset.commentsForm ?? '')) {
        return;
      }

      enhanceEditor(form, editorLabels(), announce);

      if (form.querySelector('verbb-editor[data-editor-root]') || (wantsEditor(form) && hasVerbbEditorDraft(form))) {
        void upgradeForm(form);
      }
    });
  }

  /** Someone heading for, or starting in, a rich editor, Reply or Edit. */
  function onIntent(event: Event): void {
    const target = event.target;

    if (!(target instanceof Element)) {
      return;
    }

    const form = target.closest<HTMLFormElement>('form[data-comments-editor-frame]');

    if (form && wantsEditor(form) && !form.dataset.commentsEditorMounted && target.closest('.comments__frame')) {
      // Only the text itself swaps, because moving a guest's details into Editor's frame would take
      // the keyboard out of whichever field they're filling in.
      if (event.type === 'focusin' && target === plainBox(form)) {
        void upgradeForm(form);
      } else {
        prefetchEditor(form);
      }

      return;
    }

    if (event.type !== 'focusin' && target.closest('[data-comments-action="reply"], [data-comments-action="edit"], .comments__join-guest > summary')) {
      prefetchEditor();
    }
  }

  function announce(message: string): void {
    const live = root.querySelector('[data-comments-region="live"]');
    if (live) {
      live.textContent = message;
    }
  }

  function showFeedback(host: HTMLElement, message: string, isError = false, offerRetry = false): void {
    const feedback = host.querySelector('[data-comments-region="feedback"] .comments__feedback');
    if (!(feedback instanceof HTMLElement)) {
      return;
    }

    window.clearTimeout(feedbackTimer);
    feedback.textContent = message;
    feedback.dataset.commentsState = isError ? 'error' : 'success';
    announce(message);

    if (offerRetry) {
      const retry = document.createElement('button');
      retry.type = 'button';
      retry.dataset.commentsAction = 'retry';
      retry.textContent = root.dataset.commentsRetryLabel ?? 'Try again';
      feedback.append(retry);
    }

    // Something that went wrong stays until the next thing happens.
    if (!isError) {
      feedbackTimer = window.setTimeout(() => {
        feedback.textContent = '';
        delete feedback.dataset.commentsState;
      }, 6000);
    }
  }

  function onClick(event: Event): void {
    const target = event.target;
    if (!(target instanceof Element)) {
      return;
    }

    const actionEl = target.closest('[data-comments-action]');
    if (!(actionEl instanceof HTMLElement)) {
      return;
    }

    const action = actionEl.dataset.commentsAction;
    if (!action) {
      return;
    }

    if (!dispatchCommentsEvent(root, 'comments:action-before', { action }, { controller, source: 'user', cancelable: true })) {
      return;
    }

    // Choosing anything in a comment's menu closes it.
    actionEl.closest('.comments__menu')?.removeAttribute('open');

    if (action === 'refresh' || action === 'retry') {
      event.preventDefault();
      void refreshConversation(action);
      return;
    }

    if (action === 'load-roots') {
      event.preventDefault();
      const cursor = actionEl.dataset.commentsNext;
      if (cursor) {
        void loadRoots(cursor, actionEl);
      }
      return;
    }

    if (action === 'load-replies') {
      event.preventDefault();
      const commentUid = actionEl.dataset.commentsRootUid ?? actionEl.dataset.commentsCommentUid;
      if (commentUid) {
        void loadReplies(commentUid, actionEl.dataset.commentsReplyLimit, actionEl);
      }
      return;
    }

    if (action === 'reply' || action === 'edit') {
      event.preventDefault();
      void openEditor(action, actionEl.dataset.commentsCommentUid, actionEl);
      return;
    }

    if (action === 'cancel-editor') {
      event.preventDefault();
      const form = actionEl.closest<HTMLFormElement>('form[data-comments-form]');

      if (form?.dataset.commentsForm === 'create') {
        cancelNewComment(form);
      } else {
        closeEditor('user-cancel');
      }
      return;
    }

    if (action === 'toggle-replies') {
      event.preventDefault();
      const list = document.getElementById(actionEl.getAttribute('aria-controls') ?? '');
      if (list) {
        const expanded = actionEl.getAttribute('aria-expanded') === 'true';
        list.hidden = expanded;
        // Loading more replies belongs to the list, so it hides with it and comes back on Show.
        actionEl
          .closest('[data-comments-replies]')
          ?.querySelectorAll<HTMLElement>(':scope > .comments__load-more')
          .forEach((loadMore) => {
            loadMore.hidden = expanded;
          });
        actionEl.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        actionEl.textContent = (expanded ? actionEl.dataset.commentsLabelShow : actionEl.dataset.commentsLabelHide) ?? '';
      }
      return;
    }

    if (action === 'remove' && root.dataset.commentsEnhanced === '1') {
      event.preventDefault();

      if (actionEl.dataset.commentsConfirm && !window.confirm(actionEl.dataset.commentsConfirm)) {
        return;
      }

      const commentId = commentIdFromElement(actionEl);
      const commentUid = actionEl.dataset.commentsCommentUid;
      if (!commentId) {
        return;
      }

      // Removing it takes focus with it, so the keyboard goes to whatever's now in its place.
      const item = actionEl.closest<HTMLElement>('[data-comments-comment]');
      const parentComment = item?.parentElement?.closest<HTMLElement>('[data-comments-comment]');
      const neighbour = (item?.nextElementSibling ?? item?.previousElementSibling ?? parentComment) as HTMLElement | null;

      const body = new FormData();
      body.set('commentId', commentId);
      void postMutation('remove', body, 'remove', actionEl).then((ok) => {
        if (ok) {
          if (!focusComment(root, neighbour?.dataset.commentsCommentUid)) {
            root.querySelector<HTMLElement>('.comments__heading')?.focus();
          }

          dispatch(root, 'comments:comment-removed', { commentUid: commentUid ?? null });
        }
      });
      return;
    }

    if (action === 'report' && root.dataset.commentsEnhanced === '1') {
      event.preventDefault();
      openReportDialog(actionEl);
      return;
    }

    if (action === 'cancel-report') {
      event.preventDefault();
      closeReportDialog();
      return;
    }

    if (action === 'sort' && root.dataset.commentsEnhanced === '1') {
      event.preventDefault();
      const sort = actionEl.dataset.commentsSort ?? '';
      void refreshConversation('sort', sort, actionEl).then(() => {
        dispatch(root, 'comments:sorted', { sort });
      });
      return;
    }

    // Tapping the time switches between "3 days ago" and the full date, which works on touch screens.
    if (action === 'toggle-date') {
      const time = actionEl.querySelector('time');
      const full = actionEl.dataset.commentsFullDate;
      if (time && full) {
        event.preventDefault();
        const relative = time.dataset.commentsRelative ?? time.textContent ?? '';
        time.dataset.commentsRelative = relative;
        time.textContent = time.textContent === full ? relative : full;
      }
      return;
    }

    if (action === 'copy-link') {
      event.preventDefault();
      const link = new URL(actionEl.dataset.commentsLink ?? '', window.location.href).toString();
      actionEl.closest('details')?.removeAttribute('open');
      void navigator.clipboard?.writeText(link).then(() => {
        showFeedback(root, root.dataset.commentsCopiedMessage ?? 'Link copied.');
      });
      return;
    }

    if (action === 'toggle-held') {
      event.preventDefault();
      const hiding = actionEl.getAttribute('aria-pressed') !== 'true';
      root.classList.toggle('comments--hide-held', hiding);
      actionEl.setAttribute('aria-pressed', hiding ? 'true' : 'false');
      actionEl.textContent = (hiding ? actionEl.dataset.commentsLabelShow : actionEl.dataset.commentsLabelHide) ?? '';
      return;
    }
  }

  /**
   * The × on Editor's "Replying to" chip. A reply here only exists in place under its comment, so
   * cancelling it closes the form, as Cancel does.
   */
  function onReplyCancel(event: Event): void {
    if (event.target instanceof Element && event.target.closest('[data-comments-form="reply"]')) {
      event.preventDefault();
      closeEditor('user-cancel');
    }
  }

  // An open menu closes when the reader clicks elsewhere or presses Escape.
  function visibleMenuItems(menu: Element): HTMLElement[] {
    return Array.from(menu.querySelectorAll<HTMLElement>('.comments__menu-item'))
      .filter((item) => item.getClientRects().length > 0);
  }

  // The ··· menu works like a menu button: Enter, Space or an arrow opens it on an item, the arrows,
  // Home and End move through it, Escape closes it and returns to the button, and Tab leaves it closed.
  function onMenuKeydown(event: KeyboardEvent): void {
    const target = event.target instanceof HTMLElement ? event.target : null;
    const menu = target?.closest<HTMLDetailsElement>('.comments__menu');
    const toggle = menu?.querySelector<HTMLElement>('.comments__menu-toggle');

    if (!target || !menu || !toggle) {
      return;
    }

    if (target === toggle) {
      if (!['Enter', ' ', 'ArrowDown', 'ArrowUp'].includes(event.key)) {
        return;
      }

      event.preventDefault();

      if (menu.open && (event.key === 'Enter' || event.key === ' ')) {
        menu.open = false;

        return;
      }

      closeMenus(menu);
      menu.open = true;
      const items = visibleMenuItems(menu);
      (event.key === 'ArrowUp' ? items[items.length - 1] : items[0])?.focus();

      return;
    }

    const items = visibleMenuItems(menu);
    const index = items.indexOf(target);

    if (index === -1) {
      return;
    }

    const next: Record<string, HTMLElement | undefined> = {
      ArrowDown: items[(index + 1) % items.length],
      ArrowUp: items[(index - 1 + items.length) % items.length],
      Home: items[0],
      End: items[items.length - 1],
    };

    if (next[event.key]) {
      event.preventDefault();
      next[event.key]?.focus();
    } else if (event.key === 'Escape') {
      event.preventDefault();
      menu.open = false;
      toggle.focus();
    } else if (event.key === 'Tab') {
      menu.open = false;
    }
  }

  function closeMenus(except?: Element | null): void {
    root.querySelectorAll('.comments__menu[open]').forEach((menu) => {
      if (menu !== except) {
        menu.removeAttribute('open');
      }
    });
  }

  function onChange(event: Event): void {
    const target = event.target;
    if (!(target instanceof HTMLSelectElement)) {
      return;
    }

    if (target.dataset.commentsAction !== 'sort' || root.dataset.commentsEnhanced !== '1') {
      return;
    }

    event.preventDefault();
    void refreshConversation('sort', target.value).then(() => {
      dispatch(root, 'comments:sorted', { sort: target.value });
    });
  }

  async function onSubmit(event: Event): Promise<void> {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    if (!form.matches('[data-comments-form], .comments__vote-form, .comments__moderate-form')) {
      return;
    }

    if (!root.dataset.commentsEnhanced || root.dataset.commentsEnhanced !== '1') {
      return;
    }

    // A listener that ran first, on the form itself or while capturing, has already stopped this.
    if (event.defaultPrevented) {
      return;
    }

    event.preventDefault();

    if (form.matches('[data-comments-form="create"], [data-comments-form="reply"], [data-comments-form="edit"]')) {
      if (form.dataset.commentsSubmitting === '1') return;

      // Nothing to post yet, or too much of it.
      if (refuseSubmit(form)) {
        return;
      }

      // What's still uploading isn't in the document yet, so posting now would lose it.
      if (isUploading(form)) {
        showFormErrors(form, root.dataset.commentsUploadingMessage ?? '');
        return;
      }

      // Editor's content goes into the form's inputs first, so a site's checks read what will be posted.
      prepareForm(form);

      const operationId = form.dataset.commentsOperationId ?? null;
      const formMode = (form.dataset.commentsForm ?? 'create') as CommentsEditorMode;
      const submit = form.querySelector('[type="submit"]');
      // For a reply this is the comment being answered, and for an edit the comment itself.
      const parentUid = form.closest<HTMLElement>('[data-comments-comment]')?.dataset.commentsCommentUid;

      // The site's last word before anything is sent, such as its own validation. PHP still checks
      // everything when the post arrives, so this can only refuse a post, never allow one.
      const allowed = dispatchCommentsEvent(root, 'comments:submit-before', {
        form,
        mode: formMode,
        commentUid: parentUid ?? null,
        formData: new FormData(form),
      }, { controller, source: 'user', cancelable: true });

      if (!allowed) {
        return;
      }

      form.dataset.commentsSubmitting = '1';
      dispatch(root, 'comments:request-start', { operationId });
      setBusy(submit, true);
      // One for Editor's element, if it's running, and one for the plugin's own frame.
      setPosting(form, true);
      setEditorPosting(form, true);
      clearFormErrors(form);

      try {
        await prepareProtection(form, options.apiBase);
        protectionSubmitted(form);

        const body = new FormData(form);
        if (currentSort) {
          body.set('sort', currentSort);
        }

        const envelope = await requestEnvelope(apiUrl(options.apiBase, 'create'), root, {
          method: form.method || 'POST',
          body,
          signal: abort.signal,
        });

        if (!isEnvelopeError(envelope)) {
          clearEditorDraft(form);
          form.reset();
          destroyForm(form);
        }
        if (await applyEnvelopeResponse(envelope, formMode, form)) {
          // To what they just wrote, or back to where they were if it isn't public yet.
          if (!focusComment(root, envelope.resource) && !focusComment(root, parentUid)) {
            focusFirstField(root.querySelector('[data-comments-region="editor"]'));
          }

          if (formMode === 'edit') {
            dispatch(root, 'comments:comment-updated', {
              operationId: envelope.operationId ?? null,
              resource: envelope.resource ?? null,
            });
          } else {
            dispatch(root, 'comments:comment-created', {
              operationId: envelope.operationId ?? null,
              resource: envelope.resource ?? null,
            });
          }
        }
      } catch (error) {
        const message = error instanceof Error ? error.message : 'Submit failed.';
        showFormErrors(form, message);
        // The editor keeps the text and offers to try again.
        setPosting(form, false, message);
        announce(message);
        dispatch(root, 'comments:request-error', { message });
      } finally {
        delete form.dataset.commentsSubmitting;
        setBusy(submit, false);
        setPosting(form, false);
        setEditorPosting(form, false);
      }

      return;
    }

    if (form.matches('[data-comments-form="report"]')) {
      void postMutation('report', new FormData(form), 'report', form.querySelector('[type="submit"]'), form).then((ok) => {
        if (ok) {
          closeReportDialog();
          dispatch(root, 'comments:report-submitted', { commentId: formValue(new FormData(form), 'commentId') });
        }
      });
      return;
    }

    if (form.matches('[data-comments-form="subscribe"]')) {
      const body = new FormData(form);
      const subscribed = form.querySelector<HTMLButtonElement>('[data-comments-action="subscribe"]')
        ?.getAttribute('aria-pressed') !== 'true';
      body.set('subscribed', subscribed ? '1' : '0');
      if (currentSort) {
        body.set('sort', currentSort);
      }
      void postMutation('subscription', body, 'subscribe', form.querySelector('[data-comments-action="subscribe"]')).then((ok) => {
        if (ok) {
          dispatch(root, 'comments:subscription-changed', { subscribed });
        }
      });
      return;
    }

    if (form.matches('.comments__vote-form')) {
      const body = new FormData(form);
      void postMutation('interaction', body, 'interact', form.querySelector('button')).then((ok) => {
        if (ok) {
          dispatch(root, 'comments:interaction-changed', {
            commentId: formValue(body, 'commentId'),
            type: formValue(body, 'type'),
          });
        }
      });
      return;
    }

    if (form.matches('.comments__moderate-form')) {
      const body = new FormData(form);
      void postMutation('moderate', body, 'moderate', form.querySelector('button')).then((ok) => {
        if (ok) {
          dispatch(root, 'comments:comment-moderated', {
            commentId: formValue(body, 'commentId'),
            moderationAction: formValue(body, 'moderationAction'),
          });
        }
      });
    }
  }

  controllers.set(root, controller);
  document.addEventListener('click', (event) => {
    closeMenus(event.target instanceof Element ? event.target.closest('.comments__menu') : null);
  }, { signal: abort.signal });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeMenus();
    }
  }, { signal: abort.signal });
  root.addEventListener('click', onClick);
  root.addEventListener('keydown', onMenuKeydown, { signal: abort.signal });
  root.addEventListener('change', onChange);
  root.addEventListener('submit', onSubmit);
  root.addEventListener('editor-reply-cancel', onReplyCancel);

  // The editor's own keys and states, and the moments a rich one fetches or swaps in Editor.
  for (const type of ['input', 'keydown', 'click', 'focusin', 'focusout']) {
    root.addEventListener(type, handleEditorEvent, { signal: abort.signal });
  }

  for (const type of ['focusin', 'pointerdown', 'pointerover']) {
    root.addEventListener(type, onIntent, { signal: abort.signal });
  }

  const start = (): Promise<void> => hydratePrivateViewerControls().then(revealDeepLink);

  const onHashChange = (): void => {
    void start();
  };
  window.addEventListener('hashchange', onHashChange, { signal: abort.signal });

  mountEditors(root);
  mountProtections(root);
  root.dataset.commentsJs = '1';

  // Comments that wait to be reached still load straight away for a link to one of them.
  if (root.dataset.commentsLazy === '1' && !deepLinkId() && typeof IntersectionObserver !== 'undefined') {
    const observer = new IntersectionObserver((entries) => {
      if (entries.some((entry) => entry.isIntersecting)) {
        observer.disconnect();
        void start();
      }
    }, { rootMargin: '600px 0px' });

    observer.observe(root);
    abort.signal.addEventListener('abort', () => observer.disconnect());
  } else {
    void start();
  }

  return controller;
}
