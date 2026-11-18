import { html, nothing } from 'lit-html';
import { attributes } from '../directives.js';
import { format } from '../labels.js';
import { fieldError } from './form.js';
import { icon } from './icons.js';
/** The words the tray needs, used until the data's `labels` carry them. */
export const UPLOAD_LABELS = {
    addFiles: 'Add files',
    addFile: 'Add a file',
    removeFile: 'Remove {name}',
    retryUpload: 'Try {name} again',
    uploading: 'Uploading…',
    uploaded: '{name} uploaded.',
    uploadFailed: '{name} didn’t upload.',
    fileRemoved: '{name} removed.',
    uploadLimits: 'Up to {count} files, {size} each.',
    uploadLimit: 'Up to {size}.',
    fileTypeNotAllowed: '{name} can’t be added here. Choose {types}.',
    fileTooLarge: '{name} is larger than {size}.',
    tooManyFiles: 'You can add up to {count} files here.',
};
/** A tray word from the data's labels, or the renderer's own. */
export function uploadLabel(view, key) {
    return view.labels[key] ?? UPLOAD_LABELS[key] ?? key;
}
/** A file size as people read it: KB below a megabyte, MB with one decimal above. */
export function formatSize(bytes) {
    return bytes >= 1048576 ? `${Number((bytes / 1048576).toFixed(1))} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}
/**
 * An Assets field: its files in a tray like Editor's uploads, each with remove, and retry when it
 * failed, then **Add files**, which opens the browser's file picker. The limits are said under it.
 */
export function fileTray(view, field, parts) {
    const key = `fields.${field.handle}`;
    const items = view.uploads[field.handle] ?? [];
    const max = field.maxFiles ?? null;
    const full = max !== null && items.filter((item) => item.state !== 'failed').length >= max;
    const multiple = field.multiple && max !== 1;
    const hintId = `${parts.inputId}-limits`;
    const size = field.maxSize ? formatSize(field.maxSize) : null;
    const hint = size ? (multiple && max ? format(uploadLabel(view, 'uploadLimits'), { count: max, size }) : format(uploadLabel(view, 'uploadLimit'), { size })) : '';
    const describedBy = [parts.describedBy, hint ? hintId : null].filter(Boolean).join(' ') || null;
    return html `<div class="comments__field comments__field-custom comments__field--files" role="group" aria-labelledby=${parts.labelId} aria-describedby=${describedBy ?? nothing} data-comments-field=${field.handle}><span class="comments__field-label" id=${parts.labelId}>${field.label}${parts.star}</span>${parts.instructions}${items.length
        ? html `<ul class="comments__uploads" role="list">${items.map((item) => uploadItem(view, field, item))}</ul>`
        : nothing}<input class="comments__visually-hidden" id=${parts.inputId} type="file" tabindex="-1" aria-hidden="true" ?multiple=${multiple} accept=${field.accept?.join(',') || nothing} data-comments-upload-input=${field.handle}><div class="comments__upload-row"><button ${attributes(view.control('secondaryButton', {
        identify: 'comments__upload-add',
        attributes: { type: 'button', 'data-comments-upload-action': 'add', 'data-comments-upload-field': field.handle, 'aria-describedby': describedBy, 'aria-invalid': view.errors[key] ? 'true' : null, hidden: full },
    }))}>${icon('attach')}${uploadLabel(view, multiple ? 'addFiles' : 'addFile')}</button>${hint ? html `<span class="comments__upload-limit" id=${hintId}>${hint}</span>` : nothing}</div>${fieldError(view, key)}</div>`;
}
function uploadItem(view, field, item) {
    const image = item.kind === 'image' && item.preview;
    const meta = item.state === 'uploading' ? uploadLabel(view, 'uploading') : item.state === 'failed' ? item.error ?? uploadLabel(view, 'uploadFailed') : item.size ? formatSize(item.size) : '';
    const target = { 'data-comments-upload-field': field.handle, 'data-comments-upload': item.key };
    return html `<li class=${`comments__upload comments__upload--${image ? 'image' : 'file'} comments__upload--${item.state}`} aria-busy=${item.state === 'uploading' ? 'true' : nothing}>${image
        ? html `<img class="comments__upload-thumb" src=${item.preview} alt="">`
        : html `<span class="comments__upload-icon">${icon('file')}</span>`}<span class="comments__upload-text"><span class="comments__upload-name">${item.filename}</span><span class="comments__upload-meta">${meta}</span></span>${item.state === 'failed'
        ? html `<button type="button" class="comments__upload-retry" data-comments-upload-action="retry" ${attributes(target)} aria-label=${format(uploadLabel(view, 'retryUpload'), { name: item.filename })}>${icon('retry')}</button>`
        : nothing}<button type="button" class="comments__upload-remove" data-comments-upload-action="remove" ${attributes(target)} aria-label=${format(uploadLabel(view, 'removeFile'), { name: item.filename })}>${icon('close')}</button></li>`;
}
