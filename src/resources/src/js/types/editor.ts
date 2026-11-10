import type { Editor } from '@verbb/editor';

/** The parts of Editor's `<verbb-editor>` element that Comments uses. */
export interface VerbbEditorElement extends HTMLElement {
  readonly editor: Editor | null;
  readonly value: string;
  requestSubmit(): void;
  showPostError(message?: string): void;
  clearPostError(): void;
  clearDraft(): void;
}

declare global {
  interface Window {
    // Editor's own bundle. It's a module, so anything earlier awaits `verbb-editor` being defined.
    VerbbEditor?: {
      Editor: typeof Editor;
    };
  }
}
