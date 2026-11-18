// The browser half of the checks' app: hydrates the server's HTML, or mounts afresh, as the page's
// settings say.
import { createApp, createSSRApp } from 'vue';
import { CommentsPlugin } from '@verbb/comments-vue';
import { App } from './app.js';

const settings = globalThis.__settings;

globalThis.__checks = [];
const app = (settings.hydrate ? createSSRApp : createApp)(App).use(CommentsPlugin, { endpoint: settings.endpoint }).provide('tone', 'warm');
app.config.warnHandler = (message) => globalThis.__checks.push(['vue-warn', message]);
app.mount('#root');
globalThis.__root = app;

// A site's own script calling the plugin's Comments.mount() over the page before the adapter's
// renderer has arrived, which must leave the adapter's thread to it.
if (settings.mountEarly) globalThis.Comments?.mount();
