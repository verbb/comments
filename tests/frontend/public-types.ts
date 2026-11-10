// Compiled against the built runtime the way a site's own bundle would import it, so the shipped
// `comments-frontend.d.ts` is checked from the outside: the import resolves to it, the global and the
// events are typed, and a wrong detail field fails the build.
import Comments, { Comments as Named, type CommentsController, type CommentsEventMap } from '../../src/resources/dist/js/comments-frontend.js';

const runtime: typeof window.Comments = Comments;
const controllers: CommentsController[] = Named.mount(document.body);

document.addEventListener('comments:submit-before', (event) => {
    const { form, mode, commentUid, formData, controller } = event.detail;
    const text = String(formData.get('fields[comment]') ?? '');

    if (mode !== 'edit' && text.length < 20) {
        event.preventDefault();
        controller?.showFormErrors(form, { fieldErrors: { 'fields[comment]': ['Please write at least 20 characters.'] } });
    }

    void commentUid?.toUpperCase();
});

document.addEventListener('comments:destroyed', (event) => {
    runtime.get(event.detail.root)?.closeEditor();
});

window.addEventListener('comments:comment-created', (event) => {
    const resource: string | null = event.detail.resource;
    void resource;
});

const created: CommentsEventMap['comments:comment-created'] | null = null;
void created;
void controllers;

// @ts-expect-error `sort` is on comments:sorted, not comments:mounted.
document.addEventListener('comments:mounted', (event) => event.detail.sort);
