import type { Editor, EditorTheme } from '@verbb/editor';

import './types/editor';
import type { VerbbEditorElement } from './types/editor';
import { plainBox } from './editor-frame';

type EditorBridgeSettings = {
  theme?: EditorTheme | 'auto' | string;
};

const listening = new WeakSet<HTMLElement>();
const SLOTS = ['header', 'actions', 'footer'];

function resolveTheme(theme: EditorBridgeSettings['theme']): EditorTheme {
  if (theme === 'dark' || theme === 'none') {
    return theme;
  }

  if (theme === 'auto' && typeof window !== 'undefined' && window.matchMedia) {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  return 'light';
}

/**
 * Editor's `<verbb-editor>`, once its element is defined and it's in the form. Its options come
 * from the server, which knows what this viewer can do here.
 */
function findEditorElement(form: ParentNode): VerbbEditorElement | null {
  const element = form.querySelector<HTMLElement>('verbb-editor[data-editor-root]');

  return element && customElements.get('verbb-editor') ? element as VerbbEditorElement : null;
}

function editorOf(form: ParentNode): Editor | null {
  return findEditorElement(form)?.editor ?? null;
}

/**
 * Whether this form writes in Editor once it's wanted. A rich form keeps `<verbb-editor>` in a
 * `<template>` until then; a template override from before that may have it in the form already.
 */
export function wantsEditor(form: HTMLFormElement): boolean {
  return Boolean(form.querySelector('template[data-comments-editor], verbb-editor[data-editor-root]'));
}

/**
 * Swap the plain editor for Editor's, which must already be defined. Whatever's marked
 * `data-comments-slot` (who's posting, a guest's details, public fields, Cancel) moves into Editor's
 * frame, along with anything typed so far, and the caret if the plain box had it.
 */
export function initForm(form: HTMLFormElement, settings: EditorBridgeSettings): VerbbEditorElement | null {
  const plain = plainBox(form);
  let element = form.querySelector<HTMLElement>('verbb-editor[data-editor-root]');

  if (!element && customElements.get('verbb-editor')) {
    const template = form.querySelector<HTMLTemplateElement>('template[data-comments-editor]');
    const copy = template?.content.querySelector('verbb-editor')?.cloneNode(true);

    if (copy instanceof HTMLElement) {
      element = copy;
      template?.after(element);
    }
  }

  if (!element || !customElements.get('verbb-editor')) {
    return null;
  }

  const editorElement = element as VerbbEditorElement;
  const hadFocus = plain !== null && document.activeElement === plain;

  editorElement.setAttribute('theme', resolveTheme(settings.theme));

  form.querySelectorAll<HTMLElement>('[data-comments-slot]').forEach((part) => {
    const slot = part.dataset.commentsSlot ?? '';

    // Editor moves slotted children into its frame as they arrive.
    if (SLOTS.includes(slot) && !editorElement.contains(part)) {
      part.setAttribute('slot', slot);
      editorElement.append(part);
    }
  });

  if (plain && !listening.has(editorElement)) {
    if (plain.value !== plain.defaultValue && editorElement.editor) {
      editorElement.editor.setContent(plain.value);
    }

    if (hadFocus) {
      editorElement.focus();
    }

    // Kept for its value, which Editor fills in, but nobody types in it now.
    plain.required = false;
  }

  // Keep the form's values current as they change, because a form can be read before any submit
  // handler of ours gets to run.
  if (!listening.has(editorElement)) {
    editorElement.addEventListener('editor-update', () => prepareForm(form));
    listening.add(editorElement);
  }

  prepareForm(form);

  return editorElement;
}

/**
 * Put what's in the editor where the server reads it. Plain authoring posts the text as
 * `fields[comment]`; rich authoring posts Editor's document too, with the text alongside it.
 */
export function prepareForm(form: HTMLFormElement): void {
  const editor = editorOf(form);
  // Inside a slideout these names are namespaced.
  const formatInput = form.querySelector<HTMLInputElement>('input[name="contentFormat"], input[name$="[contentFormat]"]');
  const editorContentInput = form.querySelector<HTMLInputElement>('input[name$="[editorContent]"]');
  const plainInput = plainBox(form);

  if (!editor) {
    if (formatInput) {
      formatInput.value = 'plain';
    }

    return;
  }

  if (plainInput) {
    plainInput.value = editor.getText();
  }

  if (!editorContentInput) {
    return;
  }

  editorContentInput.value = JSON.stringify(editor.getJSON());

  if (formatInput) {
    formatInput.value = 'editor';
  }
}

export function isUploading(form: ParentNode): boolean {
  return Boolean(editorOf(form)?.isUploading());
}

/**
 * Tell Editor a post is under way, or that it ended, so it can show "Posting…" or keep the
 * text for another try.
 */
export function setPosting(form: ParentNode, posting: boolean, failure?: string): void {
  const editorElement = findEditorElement(form);

  if (!editorElement) {
    return;
  }

  editorElement.toggleAttribute('busy', posting);

  if (failure !== undefined) {
    editorElement.showPostError(failure || undefined);
  } else if (posting) {
    editorElement.clearPostError();
  }
}

/**
 * Editor cleans up after itself when it leaves the page, so this only forgets our listener.
 */
export function destroyForm(form: ParentNode | null | undefined): void {
  const editorElement = form ? form.querySelector<HTMLElement>('verbb-editor[data-editor-root]') : null;

  if (editorElement) {
    listening.delete(editorElement);
  }
}

export function editorElementOf(form: ParentNode): VerbbEditorElement | null {
  return findEditorElement(form);
}
