// The browser half of the checks' app: hydrates the server's HTML, or renders afresh under
// StrictMode, as the page's settings say.
import { createElement as e, StrictMode } from 'react';
import { createRoot, hydrateRoot } from 'react-dom/client';
import { App } from './app.js';

const settings = globalThis.__settings;
const container = document.getElementById('root');
const app = e(App, { endpoint: settings.endpoint });

globalThis.__checks = [];
globalThis.__root = settings.hydrate
    ? hydrateRoot(container, app, { onRecoverableError: (error) => globalThis.__checks.push(['recoverable', String(error)]) })
    : createRoot(container);

if (!settings.hydrate) globalThis.__root.render(settings.strict ? e(StrictMode, null, app) : app);

// A site's own script calling the plugin's Comments.mount() over the page before the adapter's
// renderer has arrived, which must leave the adapter's thread to it.
if (settings.mountEarly) globalThis.Comments?.mount();
