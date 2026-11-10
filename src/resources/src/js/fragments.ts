import { destroyForm } from './editor-bridge';
import type { FrontendFragment } from './types/envelope';

function region(root: HTMLElement, name: string): HTMLElement | null {
  return root.querySelector<HTMLElement>(`[data-comments-region="${name}"]`);
}

function contextualEditor(root: HTMLElement, commentUid: string): HTMLElement | null {
  return root.querySelector<HTMLElement>(
    `[data-comments-region="contextual-editor"][data-comments-comment-uid="${commentUid}"]`,
  );
}

/**
 * Whether a form holds anything its author would lose, compared with what it started with.
 */
export function isDirtyForm(form: HTMLFormElement | null | undefined): boolean {
  if (!form) {
    return false;
  }

  const textarea = form.querySelector<HTMLTextAreaElement>('textarea[name$="[comment]"]');
  if (textarea && textarea.value !== textarea.defaultValue) {
    return true;
  }

  // Editor draws its own toolbar and status text, so only what was written counts.
  const editorElement = form.querySelector<HTMLElement & { editor?: { getText(): string } | null }>('[data-editor-root]');
  const text = editorElement?.editor?.getText() ?? '';
  return Boolean(text.trim()) && text !== editorElement?.dataset.commentsInitialText;
}

function parseFragmentHtml(html: string): Document {
  return new DOMParser().parseFromString(html, 'text/html');
}

function rootsLoadMore(scope: ParentNode): Element | null {
  return scope.querySelector('[data-comments-action="load-roots"]')?.closest('.comments__load-more') ?? null;
}

function appendRootList(root: HTMLElement, html: string): void {
  const listRegion = region(root, 'list');
  if (!listRegion) {
    return;
  }

  const doc = parseFragmentHtml(html);
  const existingList = listRegion.querySelector('.comments__list');
  const incomingList = doc.querySelector('.comments__list');

  if (existingList && incomingList) {
    incomingList.querySelectorAll<HTMLElement>(':scope > li').forEach((item) => {
      // A linked thread can already be showing, and votes can move a comment between pages.
      const uid = item.dataset.commentsCommentUid;

      if (!uid || !existingList.querySelector(`:scope > [data-comments-comment-uid="${uid}"]`)) {
        existingList.appendChild(document.importNode(item, true));
      }
    });
  }

  // Comments have their own for loading more replies, which come first in the list.
  const loadMore = rootsLoadMore(listRegion);
  const incomingLoadMore = rootsLoadMore(doc);

  if (loadMore) {
    if (incomingLoadMore) {
      loadMore.replaceWith(document.importNode(incomingLoadMore, true));
    } else {
      loadMore.remove();
    }
  } else if (incomingLoadMore) {
    listRegion.appendChild(document.importNode(incomingLoadMore, true));
  }
}

/**
 * The sort the thread is showing. The header's links mark the active one, and a sort change
 * redraws the header, so they're current where the root's attribute is only the first render's.
 * A theme that still uses a select for sorting gets its value instead.
 */
function currentSort(root: HTMLElement): string | undefined {
  const control = root.querySelector<HTMLElement>(
    '[data-comments-action="sort"][aria-current="true"], select[data-comments-action="sort"]',
  );

  if (control instanceof HTMLSelectElement) {
    return control.value;
  }

  return control?.dataset.commentsSort ?? root.dataset.commentsSort;
}

function insertComment(root: HTMLElement, resourceUid: string, html: string): void {
  const existing = root.querySelector(`[data-comments-comment][data-comments-comment-uid="${resourceUid}"]`);
  if (existing) {
    existing.outerHTML = html;
    return;
  }

  const doc = parseFragmentHtml(html);
  const item = doc.querySelector(`[data-comments-comment-uid="${resourceUid}"]`) ?? doc.body.firstElementChild;

  if (!item) {
    return;
  }

  const imported = document.importNode(item, true);
  let list = root.querySelector('.comments__list');

  // The first comment on a discussion takes the place of its empty message.
  if (!list) {
    const listRegion = region(root, 'list');
    if (!listRegion) {
      return;
    }

    list = document.createElement('ol');
    list.className = 'comments__list';
    list.setAttribute('role', 'list');
    list.id = `${root.dataset.commentsInstance ?? 'comments'}-list`;
    listRegion.replaceChildren(list);
  }

  // A new comment belongs where the current sort puts it, and only oldest-first puts it last.
  if (currentSort(root) === 'oldest') {
    list.appendChild(imported);
  } else {
    list.prepend(imported);
  }
}

function insertThread(root: HTMLElement, resourceUid: string, html: string): void {
  const list = root.querySelector('.comments__list');
  const item = parseFragmentHtml(html).querySelector(`[data-comments-comment-uid="${resourceUid}"]`);

  if (!list || !item) {
    return;
  }

  list.querySelector(`:scope > [data-comments-comment-uid="${resourceUid}"]`)?.remove();
  list.prepend(document.importNode(item, true));
}

function patchEditor(root: HTMLElement, resourceUid: string, html: string): void {
  const contextual = contextualEditor(root, resourceUid);
  if (contextual) {
    destroyForm(contextual);
    contextual.innerHTML = html;
    return;
  }

  // Someone part way through writing a comment keeps what they've written.
  if (resourceUid === root.dataset.commentsConversationUid && !isDirtyForm(region(root, 'editor')?.querySelector('form'))) {
    patchRegion(root, 'editor', html);
  }
}

export function applyFragments(root: HTMLElement, fragments: FrontendFragment[]): void {
  for (const fragment of fragments) {
    applyFragment(root, fragment);
  }
}

export function applyFragment(root: HTMLElement, fragment: FrontendFragment): void {
  switch (fragment.type) {
    case 'conversationHeader': {
      // The fragment is the whole header, not what goes inside it.
      const header = region(root, 'header');
      if (header) {
        header.outerHTML = fragment.html;
      }
      break;
    }
    case 'rootList':
      if (fragment.mode === 'append') {
        appendRootList(root, fragment.html);
      } else {
        patchRegion(root, 'list', fragment.html);
      }
      break;
    case 'editor':
    case 'viewerControls':
      patchEditor(root, fragment.resourceUid, fragment.html);
      break;
    case 'reportDialog':
      patchRegion(root, 'report-dialog', fragment.html);
      break;
    case 'comment':
      if (fragment.mode === 'remove') {
        root.querySelector(`[data-comments-comment][data-comments-comment-uid="${fragment.resourceUid}"]`)?.remove();
        break;
      }
      if (fragment.mode === 'insert') {
        insertComment(root, fragment.resourceUid, fragment.html);
        break;
      }
      {
        const existing = root.querySelector(`[data-comments-comment][data-comments-comment-uid="${fragment.resourceUid}"]`);
        if (existing) {
          existing.outerHTML = fragment.html;
        }
      }
      break;
    case 'thread':
      insertThread(root, fragment.resourceUid, fragment.html);
      break;
    case 'replies': {
      const host = root.querySelector(`[data-comments-replies][data-comments-root-uid="${fragment.resourceUid}"]`);
      if (host) {
        host.outerHTML = fragment.html;
      }
      break;
    }
    default:
      break;
  }
}

function patchRegion(root: HTMLElement, regionName: string, html: string): void {
  const target = region(root, regionName);
  if (!target) {
    return;
  }

  if (regionName === 'editor') {
    destroyForm(target);
  }

  target.innerHTML = html;
}
