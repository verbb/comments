import '@verbb/plugin-kit-web/components/button.js';
import '@verbb/plugin-kit-web/components/dialog.js';
import '@verbb/plugin-kit-web/components/radio-group.js';
import '@verbb/plugin-kit-web/components/status-label.js';
import '@verbb/plugin-kit-web/components/textarea.js';
import '@verbb/plugin-kit-web/components/data-table/pk-data-table-toolbar.js';
import { PkDataTable, defaultDataTableQuery, persistDataTableView, type DataTableRow } from '@verbb/plugin-kit-web/components/data-table.js';
import './commenters.css';

interface CraftGlobal {
    t(category: string, message: string, params?: Record<string, string | number>): string;
    sendActionRequest(method: string, action: string, options?: { data?: unknown }): Promise<{ data: { message?: string } }>;
    cp: { displaySuccess(message: string): void; displayError(message?: string): void };
}

interface ReviewSetting {
    value: string;
    label: string;
    instructions: string;
}

declare global {
    interface Window {
        Craft: CraftGlobal;
    }
}

const t = (message: string, params: Record<string, string | number> = {}): string => window.Craft?.t('comments', message, params) ?? message;

const settingStatus: Record<string, string> = { trusted: 'teal', normal: 'disabled', held: 'orange', blocked: 'red' };

// Cells render inside the table's shadow root, where page styles don't reach, so the little layout
// they need is inline. Colours still come from Craft's variables, which do inherit.
const styles = {
    cell: 'display:flex;align-items:center;gap:10px;min-width:0',
    // The same avatar as the Comments index (`.comments-avatar` in comments-cp.css), whose colours are custom properties.
    avatar: 'display:inline-flex;flex:none;align-items:center;justify-content:center;box-sizing:border-box;width:32px;height:32px;border-radius:50%;font-size:12px;font-weight:600;line-height:1;color:var(--white);object-fit:cover',
    guestAvatar: 'color:var(--medium-text-color);background-color:var(--white);box-shadow:inset 0 0 0 1px var(--gray-300)',
    name: 'display:flex;align-items:center;gap:6px;min-width:0',
    guestPill: 'flex:none;padding:0 6px;border-radius:999px;font-size:11px;font-weight:500;line-height:18px;color:var(--medium-text-color);background-color:var(--gray-100)',
    secondary: 'color:var(--light-text-color);font-size:12px',
    // Long names, addresses and titles truncate (with the full text on hover where it's not a link),
    // so the table fits its card on a laptop screen instead of pushing its last column out.
    truncate: 'max-width:190px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap',
    attention: 'font-weight:600;color:var(--amber-700)',
};

function element<K extends keyof HTMLElementTagNameMap>(tag: K, attributes: Record<string, string> = {}, text?: string): HTMLElementTagNameMap[K] {
    const node = document.createElement(tag);
    Object.entries(attributes).forEach(([name, value]) => node.setAttribute(name, value));
    if (text !== undefined) node.textContent = text;
    return node;
}

// A photo when they have one, otherwise initials on the colour picked from their name; guests are outlined.
function avatar(row: DataTableRow): HTMLElement {
    if (typeof row.photo === 'string' && row.photo !== '') {
        return element('img', { src: row.photo, alt: '', style: styles.avatar });
    }

    const tone = row.guest ? styles.guestAvatar : `background-color:var(--comments-color-avatar-${Number(row.tone) || 0})`;

    return element('span', { style: `${styles.avatar};${tone}`, 'aria-hidden': 'true' }, String(row.initials ?? ''));
}

function relativeDate(value: unknown): string {
    if (typeof value !== 'string' || value === '') return '—';
    const date = new Date(value);
    const minutes = Math.round((Date.now() - date.getTime()) / 60000);
    if (minutes < 60) return t('{count} min', { count: Math.max(1, minutes) });
    if (minutes < 1440) return t('{count} h', { count: Math.round(minutes / 60) });
    if (minutes < 10080) return t('{count} d', { count: Math.round(minutes / 1440) });
    return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
}

function mountCommenters(root: HTMLElement): void {
    const settings: ReviewSetting[] = JSON.parse(root.dataset.settings ?? '[]');
    const table = new PkDataTable();
    // The `pk-data-table-toolbar` in the page header renders this table's search and View menu.
    table.id = 'comments-commenters-table';
    table.label = root.dataset.label ?? t('Commenters');
    table.rowKey = 'id';
    table.mode = 'remote';
    table.pagination = 'offset';
    table.searchable = true;
    table.showFilters = false;
    table.selectable = true;
    table.layout = 'index';
    table.rowLabel = 'name';
    table.defaultSort = [{ id: 'lastComment', direction: 'desc' }];
    table.query = { ...defaultDataTableQuery(), pageSize: Number(root.dataset.pageSize ?? 50), sort: table.defaultSort };
    table.columns = [
        {
            id: 'name', label: t('Commenter'), type: 'custom', sortable: true, hideable: false, width: '15rem',
            renderCell: ({ row }) => {
                // The whole block is one link, so the avatar and email open the commenter too, with a single tab stop.
                // A floor too, so on a narrow screen the table scrolls rather than squeezing names to an initial.
                // Hovering underlines only the name: the link's own underline would run under the email and the Guest
                // pill too, and inline styles can't target :hover, so the pointer events set it.
                const cell = element('a', { href: String(row.url), style: `${styles.cell};min-width:200px;text-decoration:none` });
                cell.append(avatar(row));
                const text = element('div', { style: 'display:grid;gap:1px;min-width:0;line-height:1.35' });
                const name = element('div', { style: styles.name });
                // The table sets its cell colours only on selected rows, where the text turns white on grey.
                const label = element('span', { style: `font-weight:600;color:var(--pk-data-table-cell-color, var(--link-color));${styles.truncate}` }, String(row.name));
                cell.addEventListener('pointerenter', () => { label.style.textDecoration = 'underline'; });
                cell.addEventListener('pointerleave', () => { label.style.textDecoration = ''; });
                name.append(label);
                if (row.guest) name.append(element('span', { style: styles.guestPill }, t('Guest')));
                text.append(name);
                // A truncated address is still read in full, as it's all in the link's text.
                if (row.email) text.append(element('div', { style: `color:var(--pk-data-table-cell-muted-color, var(--light-text-color));font-size:13px;${styles.truncate}` }, String(row.email)));
                cell.append(text);
                return cell;
            },
        },
        { id: 'type', label: t('Type'), type: 'text', wrap: false },
        { id: 'comments', label: t('Comments'), type: 'number', sortable: true, align: 'end' },
        {
            id: 'waiting', label: t('Waiting'), type: 'custom', sortable: true, align: 'end',
            renderCell: ({ row }) => element('span', Number(row.waiting) > 0 ? { style: styles.attention } : {}, String(row.waiting ?? 0)),
        },
        { id: 'removed', label: t('Removed'), type: 'number', sortable: true, align: 'end' },
        {
            id: 'reviewSetting', label: t('Review setting'), type: 'custom', wrap: false,
            renderCell: ({ row }) => {
                const label = element('pk-status-label' as keyof HTMLElementTagNameMap, { status: settingStatus[String(row.reviewSetting)] ?? 'disabled' }, String(row.reviewSettingLabel ?? ''));
                return label;
            },
        },
        {
            id: 'lastComment', label: t('Last comment'), type: 'custom', sortable: true,
            renderCell: ({ row }) => {
                const cell = element('div', { style: 'min-width:0;white-space:nowrap' });
                cell.append(element('div', {}, relativeDate(row.lastComment)));
                if (row.lastOn) cell.append(element('div', { style: `${styles.secondary};${styles.truncate}`, title: String(row.lastOn) }, String(row.lastOn)));
                return cell;
            },
        },
        {
            id: 'firstSeen', label: t('First seen'), type: 'custom', sortable: true, wrap: false,
            renderCell: ({ row }) => element('span', {}, typeof row.firstSeen === 'string' ? new Date(row.firstSeen).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '—'),
        },
    ];
    // Type repeats the Guest pill beside the name, and First seen is background, so they start hidden
    // to leave room for the rest. Either comes back from the View menu, which remembers the choice.
    table.defaultView = { hiddenColumns: ['type', 'firstSeen'], columnOrder: [] };
    table.view = structuredClone(table.defaultView);

    table.dataSource = async (query, { signal }) => {
        const url = new URL(root.dataset.endpoint as string, window.location.href);
        url.searchParams.set('source', root.dataset.source ?? 'all');
        url.searchParams.set('page', String(query.pageIndex + 1));
        if (query.search) url.searchParams.set('search', query.search);
        if (query.sort[0]) {
            url.searchParams.set('sort', query.sort[0].id);
            url.searchParams.set('direction', query.sort[0].direction);
        }
        const response = await fetch(url, { signal, credentials: 'same-origin', headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error(t('Couldn’t load commenters. Reload the page and try again.'));
        const data = await response.json() as { rows: DataTableRow[]; total: number };
        return { rows: data.rows, total: data.total };
    };

    // Bulk: change the review setting of the selected commenters, in a dialog with an optional note.
    const change = element('pk-button' as keyof HTMLElementTagNameMap, { slot: 'selection-actions', size: 'small' }, t('Change review setting…'));
    const dialog = element('pk-dialog' as keyof HTMLElementTagNameMap, { label: t('Change review setting') }) as HTMLElement & { open: boolean };
    const group = element('pk-radio-group' as keyof HTMLElementTagNameMap, { label: t('Review setting') }) as HTMLElement & { value: string };
    settings.forEach((setting) => {
        const radio = element('pk-radio' as keyof HTMLElementTagNameMap, { value: setting.value });
        radio.append(element('strong', {}, setting.label), element('span', { style: `display:block;${styles.secondary}` }, setting.instructions));
        group.append(radio);
    });
    const note = element('pk-textarea' as keyof HTMLElementTagNameMap, { label: t('Note'), instructions: t('Optional. Recorded in each commenter’s history.'), rows: '2' }) as HTMLElement & { value: string };
    const apply = element('pk-button' as keyof HTMLElementTagNameMap, { slot: 'footer', variant: 'primary' }, t('Apply'));
    const cancel = element('pk-button' as keyof HTMLElementTagNameMap, { slot: 'footer' }, t('Cancel'));
    dialog.append(group, note, cancel, apply);
    document.body.append(dialog);

    change.addEventListener('click', () => {
        group.value = '';
        note.value = '';
        dialog.open = true;
    });
    cancel.addEventListener('click', () => { dialog.open = false; });
    apply.addEventListener('click', async () => {
        if (!group.value) return;
        if (group.value === 'blocked' && !window.confirm(root.dataset.blockConfirm ?? '')) return;
        try {
            const response = await window.Craft.sendActionRequest('POST', root.dataset.setAction as string, {
                data: { identityIds: table.selectedKeys, reviewSetting: group.value, note: note.value },
            });
            dialog.open = false;
            window.Craft.cp.displaySuccess(response.data.message ?? '');
            window.location.reload();
        } catch (error) {
            window.Craft.cp.displayError((error as { response?: { data?: { message?: string } } }).response?.data?.message);
        }
    });
    table.append(change);

    // Keeps the table's sticky header below Craft's, which holds the toolbar.
    const header = document.querySelector<HTMLElement>('#header');
    if (header) {
        new ResizeObserver(() => table.style.setProperty('--pk-data-table-header-offset', `${header.offsetHeight}px`)).observe(header);
    }

    persistDataTableView(table, { key: 'comments:commenters', userId: root.dataset.userId ?? '' });
    root.replaceChildren(table);
}

// Craft's jQuery, only to hand elements to its tab manager.
type JQueryLike = object;

declare global {
    interface Window {
        jQuery?: (element: HTMLElement) => JQueryLike;
    }
}

// Each review setting on a commenter's page saves when it's clicked, and Blocked asks first.
function initReviewSetting(form: HTMLFormElement): void {
    form.addEventListener('submit', (event) => {
        const chosen = event.submitter as HTMLElement | null;
        if (chosen?.dataset.commentsConfirm && !window.confirm(chosen.dataset.commentsConfirm)) {
            event.preventDefault();
        }
    });
}

interface CraftTabs {
    on(event: 'selectTab', handler: (event: { $tab: { attr(name: string): string | undefined } }) => void): void;
    selectTab(tab: JQueryLike): void;
}

// A commenter's Comments and Activity tabs, with Craft's tab manager, showing the panel each tab links to as
// Craft does for its own content tabs, then letting the element index re-measure itself.
function initCommenterTabs(container: HTMLElement): void {
    const $ = window.jQuery;
    const Tabs = (window.Craft as unknown as { Tabs?: new (element: JQueryLike) => CraftTabs }).Tabs;

    if (!$ || !Tabs) return;

    const panels = [...container.querySelectorAll<HTMLAnchorElement>('[role="tab"]')]
        .map((tab) => document.getElementById(tab.dataset.id ?? ''))
        .filter((panel): panel is HTMLElement => panel !== null);

    const tabs = new Tabs($(container));
    tabs.on('selectTab', ({ $tab }) => {
        const id = ($tab.attr('href') ?? '').replace(/^#/, '');
        panels.forEach((panel) => panel.classList.toggle('hidden', panel.id !== id));
        window.dispatchEvent(new Event('resize'));
    });

    // A link to #commenter-activity opens on that tab, as Craft's own tabs do. Craft takes the hash off the URL
    // as the page loads and keeps it, without the #, in LOCATION_HASH.
    const hash = (window as unknown as { LOCATION_HASH?: string }).LOCATION_HASH || window.location.hash.slice(1);
    const linked = [...container.querySelectorAll<HTMLElement>('[role="tab"]')].find((tab) => hash !== '' && tab.getAttribute('href') === `#${hash}`);
    if (linked) tabs.selectTab($(linked));
}

// A note on a commenter's Activity tab saves straight away, and the panel comes back redrawn with it.
document.addEventListener('click', async (event) => {
    const button = (event.target as HTMLElement).closest<HTMLButtonElement>('[data-comments-commenter-note]');
    const panel = button?.closest<HTMLElement>('[data-comments-commenter-activity]');
    const input = panel?.querySelector<HTMLTextAreaElement>('[data-comments-note-input]');

    if (!button || !panel || !input) return;

    event.preventDefault();

    if (!input.value.trim()) {
        input.focus();
        return;
    }

    button.classList.add('loading');

    try {
        const response = await window.Craft.sendActionRequest('POST', 'comments/commenters/add-note', {
            data: { identityId: panel.dataset.identityId, note: input.value },
        }) as { data: { message?: string; html?: string } };
        panel.outerHTML = response.data.html ?? panel.outerHTML;
        window.Craft.cp.displaySuccess(response.data.message ?? '');
    } catch (error) {
        button.classList.remove('loading');
        window.Craft.cp.displayError((error as { response?: { data?: { message?: string } } }).response?.data?.message);
    }
});

document.querySelectorAll<HTMLElement>('[data-comments-commenters]').forEach(mountCommenters);
document.querySelectorAll<HTMLElement>('[data-comments-commenter-tabs]').forEach(initCommenterTabs);
document.querySelectorAll<HTMLFormElement>('[data-comments-review-form]').forEach(initReviewSetting);
