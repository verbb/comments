/**
 * The ten controls `themeConfig` reaches. Design systems standardise exactly these, so a site can
 * put its own `btn btn-primary` on Comments' buttons rather than restyling ours.
 */
export const CONTROL_NAMES = [
    'primaryButton',
    'secondaryButton',
    'quietButton',
    'input',
    'textarea',
    'select',
    'checkbox',
    'radio',
    'link',
    'avatar',
];
/**
 * Each control's styling classes, which `resetClass` drops. The theme draws a control's look with
 * these alone (and a control's own look only where one of them is present), so a design system's
 * classes don't have to fight it once they're gone.
 */
export const CONTROL_STYLE_CLASSES = {
    primaryButton: ['comments__button', 'comments__button--primary'],
    secondaryButton: ['comments__button', 'comments__button--secondary'],
    quietButton: ['comments__button', 'comments__button--quiet'],
    input: ['comments__input'],
    textarea: ['comments__textarea'],
    select: ['comments__select'],
    checkbox: ['comments__checkbox'],
    radio: ['comments__radio'],
    link: ['comments__link'],
    avatar: ['comments__avatar'],
};
/**
 * The attributes `themeConfig` may set: classes, a title, an accessible name, and a site's own
 * `data-*` (never `data-comments-*`, which behaviour depends on). Everything else, such as event
 * handlers, `style`, `form*` or `srcset`, is refused with a warning, because the script manages it
 * or because it could run script or change what a control does.
 */
const ALLOWED = /^(class|title|aria-label|data-(?!comments-)[a-z0-9]+(-[a-z0-9]+)*)$/;
const list = (value) => (typeof value === 'string' ? value.split(/\s+/) : [...(value ?? [])]).filter(Boolean);
export function isControlName(name) {
    return CONTROL_NAMES.includes(name);
}
/**
 * Merges `themeConfig` from each source in order, later ones winning per control and per
 * attribute, and warns once about an unknown control or a protected attribute.
 */
export function mergeThemeConfig(sources, warn = defaultWarn) {
    const merged = {};
    for (const source of sources) {
        if (!source || typeof source !== 'object')
            continue;
        for (const [name, value] of Object.entries(source)) {
            if (!isControlName(name)) {
                warn(`Comments: themeConfig has no control called "${name}". It covers ${CONTROL_NAMES.join(', ')}.`);
                continue;
            }
            if (!value || typeof value !== 'object' || Array.isArray(value)) {
                warn(`Comments: themeConfig's ${name} must be an object, such as { attributes: { class: 'btn' } }.`);
                continue;
            }
            const config = value;
            const current = merged[name] ?? {};
            const attributes = { ...current.attributes };
            const given = config.attributes ?? {};
            if (typeof given !== 'object' || Array.isArray(given)) {
                warn(`Comments: themeConfig's ${name}.attributes must be an object.`);
                continue;
            }
            for (const [attribute, attributeValue] of Object.entries(given)) {
                if (!ALLOWED.test(attribute)) {
                    warn(`Comments: themeConfig can't set "${attribute}" on ${name}. It sets class, title, aria-label and data-* attributes.`);
                    continue;
                }
                const plain = (item) => typeof item === 'string' || typeof item === 'number' || typeof item === 'boolean' || item === null;
                if (!plain(attributeValue) && !(Array.isArray(attributeValue) && attributeValue.every((item) => typeof item === 'string'))) {
                    warn(`Comments: themeConfig's ${name} "${attribute}" must be text, a number, true or false, or a list of classes.`);
                    continue;
                }
                attributes[attribute] = attributeValue;
            }
            merged[name] = {
                resetClass: config.resetClass ?? current.resetClass,
                attributes,
            };
        }
    }
    return merged;
}
const warned = new Set();
function defaultWarn(message) {
    if (!warned.has(message) && typeof console !== 'undefined') {
        warned.add(message);
        console.warn(message);
    }
}
/**
 * The attributes for one control: its identifying classes, its styling classes unless
 * `resetClass` is on, the site's classes and attributes, then the ones the script manages, which
 * always win. `data-comments-control` names the control for the base CSS and for tests.
 */
export function controlAttributes(config, name, spec = {}) {
    const settings = config[name] ?? {};
    const classes = [
        ...list(spec.identify),
        ...(settings.resetClass ? [] : [...CONTROL_STYLE_CLASSES[name], ...list(spec.style)]),
    ];
    const result = {};
    for (const [attribute, value] of Object.entries(settings.attributes ?? {})) {
        if (attribute === 'class') {
            classes.push(...list(Array.isArray(value) ? value.map(String) : typeof value === 'string' ? value : undefined));
        }
        else if (value === true) {
            result[attribute] = true;
        }
        else if (value !== false && value !== null && value !== undefined) {
            result[attribute] = Array.isArray(value) ? value.join(' ') : String(value);
        }
    }
    for (const [attribute, value] of Object.entries(spec.attributes ?? {})) {
        if (value === true)
            result[attribute] = true;
        else if (value === false || value === null || value === undefined)
            delete result[attribute];
        else
            result[attribute] = String(value);
    }
    const unique = [...new Set(classes)];
    // The class first and the control's name last, so the markup reads as a reference would show it.
    return { ...(unique.length ? { class: unique.join(' ') } : {}), ...result, 'data-comments-control': name };
}
