import { destroyForm, initForm, isUploading, prepareForm, setPosting } from './editor-bridge';
import { frontendApiUrl } from './api-url';
import { mountProtections, destroyProtections, prepareProtection, protectionSubmitted } from './protection';
import { applyFragments, isDirtyForm } from './fragments';
import { envelopeMessage, isEnvelopeError } from './envelope';
import { requestEnvelope } from './request';
import { CONTRACT_VERSION } from './types/envelope';

export type CommentsController = {
  root: HTMLElement;
  conversationUid: string;
  refresh: (options?: { reason?: string; sort?: string }) => void;
  openComposer: (options: { mode: string; commentUid?: string }) => void;
  closeComposer: (options?: { reason?: string }) => void;
  destroy: () => void;
};


type InstanceOptions = {
  apiBase: string;
};

function dispatch(root: HTMLElement, name: string, detail: Record<string, unknown>): void {
  root.dispatchEvent(new CustomEvent(name, {
    bubbles: true,
    composed: true,
    detail: {
      contractVersion: CONTRACT_VERSION,
      root,
      instanceId: root.dataset.commentsInstance ?? '',
      conversationUid: root.dataset.commentsConversationUid ?? '',
      controller: controllers.get(root),
      source: 'api',
      ...detail,
    },
  }));
}

const controllers = new WeakMap<HTMLElement, CommentsController>();
const editorLoads = new Map<string, Promise<void>>();

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

function focusFirstField(scope: ParentNode | null | undefined): void {
  scope?.querySelector<HTMLElement>(
    '[contenteditable="true"], input:not([type="hidden"]), textarea:not([hidden]), select',
  )?.focus();
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

  if (busy) {
    el.setAttribute('aria-busy', 'true');
  } else {
    el.removeAttribute('aria-busy');
  }
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
 * Say what's wrong next to each field, and take the author to the first of them.
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
  let composerOpener: string | null = null;
  let feedbackTimer: number | undefined;

  const controller: CommentsController = {
    root,
    conversationUid: root.dataset.commentsConversationUid ?? '',
    refresh(opts) {
      void refreshConversation(opts?.reason ?? 'refresh', opts?.sort);
    },
    openComposer(opts) {
      void openComposer(opts.mode, opts.commentUid);
    },
    closeComposer(opts) {
      closeComposer(opts?.reason ?? 'user-cancel');
    },
    destroy() {
      abort.abort();
      window.clearTimeout(feedbackTimer);
      delete root.dataset.commentsJs;
      destroyProtections(root);
      root.removeEventListener('click', onClick);
      root.removeEventListener('change', onChange);
      root.removeEventListener('submit', onSubmit);
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

  async function refreshConversation(reason: string, sort?: string): Promise<void> {
    if (!ownerId) {
      return;
    }

    if (sort) {
      currentSort = sort;
    }

    dispatch(root, 'comments:request-start', { operationId: null, reason });

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
        apiUrl(options.apiBase, 'replies', { commentUid, ...(limit ? { limit } : {}) }),
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

  async function openComposer(mode: string, commentUid?: string, control?: HTMLElement): Promise<void> {
    const openForm = root.querySelector<HTMLFormElement>('[data-comments-form="reply"], [data-comments-form="edit"]');

    // There's one reply or edit form at a time, and opening another can't quietly lose what's in this one.
    if (isDirtyForm(openForm) && !window.confirm(root.dataset.commentsDiscardMessage ?? 'Discard your changes?')) {
      return;
    }

    closeComposer('replaced', false);
    composerOpener = control ? controlSelector(control) : null;
    setBusy(control, true);
    dispatch(root, 'comments:composer-opened', { mode, commentUid: commentUid ?? null });
    dispatch(root, 'comments:request-start', { operationId: null, reason: 'composer-open' });

    try {
      const envelope = await requestEnvelope(
        apiUrl(options.apiBase, 'composer', {
          ownerId,
          siteId,
          mode,
          commentUid,
        }),
        root,
        { signal: abort.signal },
      );

      await applyEnvelopeResponse(envelope, 'composer-open');
      focusFirstField(root.querySelector(`[data-comments-region="contextual-composer"][data-comments-comment-uid="${commentUid}"]`));
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Could not open composer.';
      showFeedback(root, message, true);
      dispatch(root, 'comments:request-error', { reason: 'composer-open', message });
    } finally {
      setBusy(control, false);
    }
  }

  function closeComposer(reason: string, restoreFocus = true): void {
    const activeForm = root.querySelector<HTMLFormElement>('[data-comments-form="reply"], [data-comments-form="edit"]');
    const host = activeForm?.closest('[data-comments-region="contextual-composer"]');

    if (host instanceof HTMLElement) {
      destroyForm(host);
      destroyProtections(host);
      host.innerHTML = '';

      if (restoreFocus && composerOpener) {
        root.querySelector<HTMLElement>(composerOpener)?.focus();
      }

      dispatch(root, 'comments:composer-closed', { reason });
    }
  }

  /**
   * A page that waited to load its comments hasn't downloaded rich authoring yet either.
   */
  async function loadEditor(): Promise<void> {
    const { commentsEditorModule: module, commentsEditorCss: css } = root.dataset;

    if (module) {
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
          script.onerror = () => reject(new Error('Rich authoring could not be loaded.'));
          document.head.append(script);
        });
        editorLoads.set(module, loading);
      }

      await loading;
    }

    await customElements.whenDefined('verbb-editor');
  }

  function mountEditors(scope: ParentNode): void {
    if (!scope.querySelector('[data-editor-root]')) {
      return;
    }

    if (!window.VerbbEditor) {
      // The plain box underneath stays usable if it doesn't arrive.
      loadEditor().then(() => mountEditors(scope)).catch(() => undefined);

      return;
    }

    scope.querySelectorAll<HTMLFormElement>('[data-comments-form]').forEach((form) => {
      const composer = initForm(form, { theme: root.dataset.commentsTheme });

      if (composer) {
        form.dataset.commentsEditorMounted = '1';

        // What the author started with, so leaving with nothing new to lose doesn't ask first.
        if (composer.dataset.commentsInitialText === undefined) {
          composer.dataset.commentsInitialText = composer.editor?.getText() ?? '';
        }
      }
    });
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

    const before = new CustomEvent('comments:action-before', {
      bubbles: true,
      composed: true,
      cancelable: true,
      detail: {
        contractVersion: CONTRACT_VERSION,
        controller,
        root,
        instanceId: root.dataset.commentsInstance ?? '',
        conversationUid: controller.conversationUid,
        action,
        source: 'user',
      },
    });

    if (!root.dispatchEvent(before)) {
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
      void openComposer(action, actionEl.dataset.commentsCommentUid, actionEl);
      return;
    }

    if (action === 'cancel-composer') {
      event.preventDefault();
      closeComposer('user-cancel');
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
      void refreshConversation('sort', sort).then(() => {
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

  // An open menu closes when the reader clicks elsewhere or presses Escape.
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

    event.preventDefault();

    if (form.matches('[data-comments-form="create"], [data-comments-form="reply"], [data-comments-form="edit"]')) {
      if (form.dataset.commentsSubmitting === '1') return;

      // What's still uploading isn't in the document yet, so posting now would lose it.
      if (isUploading(form)) {
        showFormErrors(form, root.dataset.commentsUploadingMessage ?? '');
        return;
      }

      form.dataset.commentsSubmitting = '1';
      prepareForm(form);

      const operationId = form.dataset.commentsOperationId ?? null;
      const formMode = form.dataset.commentsForm ?? 'create';
      const submit = form.querySelector('[type="submit"]');
      const parentUid = form.closest<HTMLElement>('[data-comments-comment]')?.dataset.commentsCommentUid;
      dispatch(root, 'comments:request-start', { operationId });
      setBusy(submit, true);
      setPosting(form, true);
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
          form.reset();
          destroyForm(form);
        }
        if (await applyEnvelopeResponse(envelope, formMode, form)) {
          // To what they just wrote, or back to where they were if it isn't public yet.
          if (!focusComment(root, envelope.resource) && !focusComment(root, parentUid)) {
            focusFirstField(root.querySelector('[data-comments-region="composer"]'));
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
        // The composer keeps the text and offers to try again.
        setPosting(form, false, message);
        announce(message);
        dispatch(root, 'comments:request-error', { message });
      } finally {
        delete form.dataset.commentsSubmitting;
        setBusy(submit, false);
        setPosting(form, false);
      }

      return;
    }

    if (form.matches('[data-comments-form="report"]')) {
      void postMutation('report', new FormData(form), 'report', form.querySelector('[type="submit"]'), form).then((ok) => {
        if (ok) {
          closeReportDialog();
          dispatch(root, 'comments:report-submitted', { commentId: new FormData(form).get('commentId') });
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
            commentId: body.get('commentId'),
            type: body.get('type'),
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
            commentId: body.get('commentId'),
            moderationAction: body.get('moderationAction'),
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
  root.addEventListener('change', onChange);
  root.addEventListener('submit', onSubmit);

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
