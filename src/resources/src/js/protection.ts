import { frontendApiUrl } from './api-url';
import { mountProtection, parseManifest, type ProtectionController } from '@verbb/protect';
import '@verbb/protect/styles.css';

const controllers = new Map<HTMLFormElement, ProtectionController>();
const consumed = new WeakSet<HTMLFormElement>();

export function mountProtections(scope: ParentNode): void {
    for (const [form, controller] of controllers) {
        if (!form.isConnected) { controller.destroy(); controllers.delete(form); }
    }
    scope.querySelectorAll<HTMLElement>('[data-comments-protection]').forEach((holder) => {
        const form = holder.closest('form');
        if (!form || controllers.has(form)) return;
        try {
            const manifest = parseManifest(JSON.parse(holder.dataset.commentsProtection ?? 'null'));
            controllers.set(form, mountProtection(form, manifest));
        } catch { form.dispatchEvent(new CustomEvent('protect:error', { bubbles: true })); }
    });
}

export function destroyProtections(scope: ParentNode): void {
    for (const [form, controller] of controllers) {
        if (scope === form || scope.contains(form)) { controller.destroy(); controllers.delete(form); }
    }
}

async function refreshProtection(form: HTMLFormElement, apiBase: string): Promise<void> {
    const data = new FormData(form);
    const url = frontendApiUrl(apiBase, 'protection');
    url.searchParams.set('ownerId', String(data.get('elementId') ?? data.get('ownerId') ?? ''));
    url.searchParams.set('siteId', String(data.get('siteId') ?? ''));
    url.searchParams.set('commentId', String(data.get('commentId') ?? ''));
    url.searchParams.set('channel', 'browser');
    const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(15000) });
    if (!response.ok) throw new Error('Could not refresh verification. Please try again.');
    const dataResult = await response.json();
    const manifest = parseManifest(dataResult.manifest);
    if (!form.isConnected) throw new Error('The comment form was removed.');
    controllers.get(form)?.destroy();
    // All old evidence is replaced together, including randomized honeypot names.
    form.querySelectorAll<HTMLInputElement>('input[name^="__protect["]').forEach((el) => el.remove());
    form.querySelectorAll('.protect-honeypot').forEach((el) => el.remove());
    const holder = form.querySelector<HTMLElement>('[data-comments-protection]');
    if (holder) holder.dataset.commentsProtection = JSON.stringify(manifest);
    controllers.set(form, mountProtection(form, manifest));
    consumed.delete(form);
}

export async function prepareProtection(form: HTMLFormElement, apiBase: string): Promise<void> {
    const holder = form.querySelector<HTMLElement>('[data-comments-protection]');
    if (!holder) throw new Error('Refresh the page before posting your comment.');
    const existing = controllers.get(form);
    if (consumed.has(form) || !existing || existing.expiresIn() === 0) await refreshProtection(form, apiBase);
    const controller = controllers.get(form);
    if (!controller) throw new Error('Comment verification is unavailable.');
    await controller.prepare();
}

export function protectionSubmitted(form: HTMLFormElement): void { consumed.add(form); }
