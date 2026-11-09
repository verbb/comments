import type { Editor, EditorTheme } from '@verbb/editor';

import './types/editor';
import type { VerbbEditorComposer } from './types/editor';

type EditorBridgeSettings = {
  theme?: EditorTheme | 'auto' | string;
};

const listening = new WeakSet<HTMLElement>();

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
 * Editor's composer, `<verbb-editor>`, once its element is defined. Its options come from the server,
 * which knows what this viewer can do here.
 */
function findComposer(form: ParentNode): VerbbEditorComposer | null {
  const element = form.querySelector<HTMLElement>('verbb-editor[data-editor-root]');

  return element && customElements.get('verbb-editor') ? element as VerbbEditorComposer : null;
}

function editorOf(form: ParentNode): Editor | null {
  return findComposer(form)?.editor ?? null;
}

export function initForm(form: HTMLFormElement, settings: EditorBridgeSettings): VerbbEditorComposer | null {
  const composer = findComposer(form);

  if (!composer) {
    return null;
  }

  composer.setAttribute('theme', resolveTheme(settings.theme));

  // Keep the form's values current as they change, because a form can be read before any submit
  // handler of ours gets to run.
  if (!listening.has(composer)) {
    composer.addEventListener('editor-update', () => prepareForm(form));
    listening.add(composer);
  }

  prepareForm(form);

  return composer;
}

export function prepareForm(form: HTMLFormElement): void {
  const editor = editorOf(form);
  // Inside a slideout these names are namespaced.
  const formatInput = form.querySelector<HTMLInputElement>('input[name="contentFormat"], input[name$="[contentFormat]"]');
  const editorContentInput = form.querySelector<HTMLInputElement>('input[name$="[editorContent]"]');
  const plainInput = form.querySelector<HTMLTextAreaElement>('textarea[name$="[comment]"]');

  if (!editor || !editorContentInput) {
    if (formatInput) {
      formatInput.value = 'plain';
    }

    return;
  }

  editorContentInput.value = JSON.stringify(editor.getJSON());

  if (formatInput) {
    formatInput.value = 'editor';
  }

  // Keep plain fallback in sync for no-JS diagnostics and legacy consumers.
  if (plainInput) {
    plainInput.value = editor.getText();
  }
}

export function isUploading(form: ParentNode): boolean {
  return Boolean(editorOf(form)?.isUploading());
}

/**
 * Tell the composer a post is under way, or that it ended, so it can show "Posting…" or keep the
 * text for another try.
 */
export function setPosting(form: ParentNode, posting: boolean, failure?: string): void {
  const composer = findComposer(form);

  if (!composer) {
    return;
  }

  composer.toggleAttribute('busy', posting);

  if (failure !== undefined) {
    composer.showPostError(failure || undefined);
  } else if (posting) {
    composer.clearPostError();
  }
}

/**
 * The composer cleans up after itself when it leaves the page, so this only forgets our listener.
 */
export function destroyForm(form: ParentNode | null | undefined): void {
  const composer = form ? form.querySelector<HTMLElement>('verbb-editor[data-editor-root]') : null;

  if (composer) {
    listening.delete(composer);
  }
}
