import { COMMENTS_EVENT_NAMES } from '@verbb/comments-core';
/** Every event name with its prop name. */
export const EVENT_PROPS = COMMENTS_EVENT_NAMES.map((name) => [
    name,
    `on${name.slice('comments:'.length).split('-').map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join('')}`,
]);
