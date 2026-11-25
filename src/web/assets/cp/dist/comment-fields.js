import { a as e, i as t, l as n, n as r, o as i, r as a, s as o, t as s } from "./chunks/directive.js";
import { t as c } from "./chunks/repeat.js";
// packages/comments-browser/dist/css/comments.css?inline
var l = "[data-comments-region=live],[data-comments-root] .comments__visually-hidden{clip-path:inset(50%);white-space:nowrap;border:0;block-size:1px;inline-size:1px;margin:-1px;padding:0;position:absolute;overflow:hidden}[data-comments-root] .protect-honeypot{clip-path:inset(50%)!important;white-space:nowrap!important;width:1px!important;height:1px!important;position:absolute!important;overflow:hidden!important}:where([data-comments-root]){display:block}[data-comments-root] verbb-comments-host{display:contents}[data-comments-root] [hidden]{display:none!important}[data-comments-root]:not([data-comments-phase=ready]){box-sizing:border-box;min-block-size:var(--comments-reserve,10rem);align-content:start}[data-comments-root].comments--reserve-1:not([data-comments-phase=ready]){min-block-size:var(--comments-reserve,16rem)}[data-comments-root].comments--reserve-2:not([data-comments-phase=ready]){min-block-size:var(--comments-reserve,24rem)}[data-comments-root].comments--reserve-3:not([data-comments-phase=ready]){min-block-size:var(--comments-reserve,32rem)}[data-comments-root].comments--reserve-4:not([data-comments-phase=ready]){min-block-size:var(--comments-reserve,40rem)}[data-comments-root] [data-comments-placeholder][aria-hidden=true]{visibility:hidden}[data-comments-root] :where(button,summary,a)[aria-busy=true]:not([data-editor-root] *){cursor:progress;-webkit-text-fill-color:transparent;position:relative}[data-comments-root] :where(button,summary,a)[aria-busy=true]:not([data-editor-root] *)>*{visibility:hidden}[data-comments-root] :where(button,summary,a)[aria-busy=true]:not([data-editor-root] *):after{content:\"\";box-sizing:border-box;border:1.5px solid;-webkit-text-fill-color:currentColor;border-inline-end-color:#0000;border-radius:50%;block-size:1em;inline-size:1em;margin:auto;animation:.7s linear infinite comments-spin;position:absolute;inset:0}[data-comments-root] ol[aria-busy=true]{opacity:.55;transition:opacity .15s}@keyframes comments-spin{to{transform:rotate(360deg)}}[data-comments-root] [data-comments-control][aria-disabled=true]{cursor:not-allowed}[data-comments-root] svg[aria-hidden=true]{flex:none}[data-comments-collapsed]>:not(.comments__frame-box,verbb-editor),[data-comments-collapsed]>.comments__frame-box>:not(textarea,label){display:none!important}[data-comments-collapsed]>.comments__frame-box>textarea{resize:none}form[data-comments-editor-mounted] :is(.comments__frame-head,.comments__frame-box,.comments__frame-foot){display:none!important}[data-comments-root] verbb-editor{display:block}[data-comments-root] form:not([data-comments-editor-mounted]) verbb-editor{display:none!important}[data-comments-root] verbb-editor:not(:defined){display:none!important}[data-comments-root] details.comments__menu{position:relative}[data-comments-root] .comments__menu>summary{list-style:none}[data-comments-root] .comments__menu>summary::-webkit-details-marker{display:none}[data-comments-root] .comments__menu-items{z-index:10;display:grid;position:absolute;inset-inline-start:0}[data-comments-theme=none] .comments__menu-items{color:canvastext;background:canvas;border:1px solid graytext;min-inline-size:12em;padding:.25em}[data-comments-theme=none] .comments__author-meta{flex-wrap:wrap;align-items:baseline;gap:.25em .5em;display:flex}[data-comments-report-dialog]{box-sizing:border-box;inline-size:min(28rem,100vw - 2rem)}[data-comments-report-dialog]::backdrop{background:#00000073}[data-comments-region=toast]{z-index:20;pointer-events:none;justify-content:center;display:flex;position:sticky;inset-block-end:1rem}[data-comments-region=toast]>:empty{display:none!important}[data-comments-region=toast]>*{pointer-events:auto}@media (prefers-reduced-motion:reduce){[data-comments-root] :where(button,summary,a)[aria-busy=true]:after{animation-duration:1.6s}[data-comments-root] *{scroll-behavior:auto!important;transition:none!important;animation:none!important}}.comments__gif-area:empty{display:none}.comments__gif-preview{flex-wrap:wrap;align-items:flex-start;margin:0;display:flex}.comments__gif-preview-image{block-size:auto;max-block-size:12rem;inline-size:auto;max-inline-size:min(100%,12rem);display:block}.comments__gif-results{overscroll-behavior:contain;grid-template-columns:repeat(auto-fill,minmax(6.5rem,1fr));max-block-size:16rem;display:grid;overflow-y:auto}.comments__gif-result{cursor:pointer;background:0 0;border:0;inline-size:100%;padding:0;display:block}.comments__gif-result-image{object-fit:cover;block-size:6rem;inline-size:100%;display:block}.comments__field-thumb,.comments__upload-thumb{object-fit:cover;flex:none;max-block-size:4rem;max-inline-size:4rem;display:block}.comments__upload-thumb{border-radius:.375rem;block-size:3.25rem;inline-size:3.25rem}@layer comments-tokens{[data-comments-root]{--comments-font-family:inherit;--comments-font-size:1rem;--comments-line-height:1.5;--comments-color-text:#1f2328;--comments-color-muted:#5a6068;--comments-color-link:#0b57d0;--comments-color-border:#d6d9dd;--comments-color-control-border:#8b9198;--comments-color-surface:#fff;--comments-color-surface-muted:#f5f6f7;--comments-color-accent:#0b57d0;--comments-color-on-accent:#fff;--comments-color-danger:#b3261e;--comments-color-success:#146c2e;--comments-color-warning:#a85d00;--comments-color-focus:#0b57d0;--comments-color-scrim:#00000073;--comments-color-toast:#1f2328;--comments-color-on-toast:#fff;--comments-color-toast-success:#7dd99b;--comments-color-toast-danger:#ff9a93;--comments-color-held-surface:#fff8eb;--comments-color-held-border:#f2d39b;--comments-color-held-text:#8a5300;--comments-color-reported-surface:#fff1f0;--comments-color-reported-border:#f5c2bd;--comments-color-reported-text:#a8322a;--comments-color-like:#c4314b;--comments-color-avatar-0:#3b6ea5;--comments-color-avatar-1:#8a5a2b;--comments-color-avatar-2:#2f7d4f;--comments-color-avatar-3:#8a3f7d;--comments-color-avatar-4:#b0532a;--comments-color-avatar-5:#237a7a;--comments-color-avatar-6:#5a4fa3;--comments-color-avatar-7:#6b7a2a;--comments-color-on-avatar:#fff;--comments-space-1:.25rem;--comments-space-2:.5rem;--comments-space-3:.75rem;--comments-space-4:1rem;--comments-space-5:1.5rem;--comments-radius:.5rem;--comments-avatar-size:2.25rem;--comments-gif-max-size:15rem;--comments-thread-indent:.875rem;--comments-control-size:2.25rem}[data-comments-root][data-comments-theme=dark]{--comments-color-text:#eceef0;--comments-color-muted:#b4b9c0;--comments-color-link:#8ab4f8;--comments-color-border:#43484f;--comments-color-control-border:#70767e;--comments-color-surface:#1f2226;--comments-color-surface-muted:#2a2e33;--comments-color-panel:#17191c;--comments-color-accent:#8ab4f8;--comments-color-on-accent:#10223f;--comments-color-danger:#f2b8b5;--comments-color-success:#7dd99b;--comments-color-focus:#8ab4f8;--comments-color-toast:#eceef0;--comments-color-on-toast:#17191c;--comments-color-toast-success:#146c2e;--comments-color-toast-danger:#b3261e;--comments-color-held-surface:#33291a;--comments-color-held-border:#6b5428;--comments-color-held-text:#f3c77a;--comments-color-reported-surface:#3a2422;--comments-color-reported-border:#74403b;--comments-color-reported-text:#f2a8a1;--comments-color-like:#ff8a9e}@media (prefers-color-scheme:dark){[data-comments-root][data-comments-theme=auto]{--comments-color-text:#eceef0;--comments-color-muted:#b4b9c0;--comments-color-link:#8ab4f8;--comments-color-border:#43484f;--comments-color-control-border:#70767e;--comments-color-surface:#1f2226;--comments-color-surface-muted:#2a2e33;--comments-color-panel:#17191c;--comments-color-accent:#8ab4f8;--comments-color-on-accent:#10223f;--comments-color-danger:#f2b8b5;--comments-color-success:#7dd99b;--comments-color-focus:#8ab4f8;--comments-color-toast:#eceef0;--comments-color-on-toast:#17191c;--comments-color-toast-success:#146c2e;--comments-color-toast-danger:#b3261e;--comments-color-held-surface:#33291a;--comments-color-held-border:#6b5428;--comments-color-held-text:#f3c77a;--comments-color-reported-surface:#3a2422;--comments-color-reported-border:#74403b;--comments-color-reported-text:#f2a8a1;--comments-color-like:#ff8a9e}}@media (pointer:coarse){[data-comments-root]{--comments-control-size:2.75rem}}@media (max-width:40rem){[data-comments-root]{--comments-thread-indent:.75rem}}}[data-comments-root]{font-family:var(--comments-font-family);font-size:var(--comments-font-size);line-height:var(--comments-line-height);color:var(--comments-color-text);--lightningcss-light:initial;--lightningcss-dark: ;color-scheme:light}[data-comments-root][data-comments-theme=dark]{padding:var(--comments-space-4);border-radius:var(--comments-radius);background:var(--comments-color-panel);--lightningcss-light: ;--lightningcss-dark:initial;color-scheme:dark}@media (prefers-color-scheme:dark){[data-comments-root][data-comments-theme=auto]{padding:var(--comments-space-4);border-radius:var(--comments-radius);background:var(--comments-color-panel);--lightningcss-light: ;--lightningcss-dark:initial;color-scheme:dark}}.comments{gap:var(--comments-space-4);grid-template-columns:minmax(0,1fr);display:grid}.comments :where(a:not([data-editor-root] *)){color:var(--comments-color-link)}.comments :where(input,select,textarea,button):where(:not([data-editor-root] *)){font:inherit}.comments :where(button:not([data-editor-root] *)){color:inherit;cursor:pointer}.comments :where(a,button,input,select,textarea,summary,article):focus-visible{outline:2px solid var(--comments-color-focus);outline-offset:2px}.comments article:focus:not(:focus-visible),.comments__heading:focus:not(:focus-visible){outline:none}.comments__header{align-items:flex-end;gap:var(--comments-space-3);flex-wrap:wrap;display:flex}.comments__header-main{align-items:baseline;gap:var(--comments-space-2) var(--comments-space-3);flex-wrap:wrap;margin-inline-end:auto;display:flex}.comments__heading{margin:0;font-size:1.25em;line-height:1.3}.comments__count{color:var(--comments-color-muted);margin:0}.comments__follow-form{align-items:flex-end;gap:var(--comments-space-2);flex-wrap:wrap;max-inline-size:100%;margin:0;display:flex}.comments__list,.comments__replies-list{gap:var(--comments-space-4);grid-template-columns:minmax(0,1fr);margin:0;padding:0;list-style:none;display:grid}.comments__comment{padding:var(--comments-space-4);border:1px solid var(--comments-color-border);border-radius:var(--comments-radius);background:var(--comments-color-surface)}.comments__replies .comments__comment{background:0 0;border:0;border-radius:0;padding:0}.comments__replies{gap:var(--comments-space-4);border-inline-start:2px solid color-mix(in srgb, var(--comments-color-border) 65%, transparent);grid-template-columns:minmax(0,1fr);margin-block-start:.875rem;padding-inline-start:var(--comments-thread-indent);display:grid}.comments__comment[data-comments-visual-depth=\"4\"]>article>.comments__replies{border-inline-start:0;grid-column:1/-1;padding-inline-start:0}.comments__comment>article{grid-template-columns:var(--comments-avatar-size) minmax(0, 1fr);column-gap:var(--comments-space-3);border-radius:var(--comments-radius);align-items:start;display:grid}.comments__comment>article>*{grid-column:2}[data-comments-avatars=off] .comments__comment>article{grid-template-columns:0 minmax(0,1fr);column-gap:0}[data-comments-avatars=off] .comments__editor-row{grid-template-columns:minmax(0,1fr)}[data-comments-avatars=off] .comments__editor-row>.comments__field-error{grid-column:1}.comments__comment>article>.comments__placeholder{grid-column:1/-1}.comments__comment>article:target,.comments__comment--highlight>article{outline:2px solid var(--comments-color-accent);outline-offset:4px}.comments__author{display:contents}.comments__author>[data-comments-control=avatar]{grid-area:1/1/span 2}.comments__avatar{inline-size:var(--comments-avatar-size);block-size:var(--comments-avatar-size);object-fit:cover;border-radius:50%;flex:none}.comments__author-meta{align-items:baseline;gap:var(--comments-space-1) var(--comments-space-2);flex-wrap:wrap;grid-area:1/2;min-inline-size:0;margin-block-end:.125rem;font-size:.875em;display:flex}.comments__author-name{overflow-wrap:anywhere;font-weight:650}.comments__date,.comments__edited{color:var(--comments-color-muted);font-size:.875em}.comments__permalink{color:inherit;text-decoration:none}.comments__permalink:hover .comments__date,.comments__permalink:focus-visible .comments__date{color:var(--comments-color-link);text-decoration:underline}.comments__author-score{color:var(--comments-color-muted);font-variant-numeric:tabular-nums;font-size:.8em;font-weight:650}.comments__author-score--positive{color:var(--comments-color-success)}.comments__author-score--negative{color:var(--comments-color-danger)}.comments__badge{background:color-mix(in srgb, var(--comments-color-accent) 12%, transparent);color:var(--comments-color-accent);border-radius:.25rem;padding:.0625rem .375rem;font-size:.8em;font-weight:700;line-height:1.5}.comments__state-badge{margin:0 0 var(--comments-space-2);border:1px dashed var(--comments-color-muted);border-radius:var(--comments-radius);padding:.1rem .45rem;font-size:.8125em;display:inline-block}.comments__content{overflow-wrap:anywhere;font-size:.9375em;line-height:1.6}.comments__content-plain{margin:0}.comments__content-muted,.comments__placeholder{color:var(--comments-color-muted);margin:0;font-style:italic}.comments__folded{align-items:center;gap:var(--comments-space-1) var(--comments-space-2);flex-wrap:wrap;font-size:.9375em;display:flex}.comments__folded-label{color:var(--comments-color-muted);margin:0;font-style:italic}.comments__content-rich :where(p,ul,ol,blockquote,pre){margin-block:var(--comments-space-2)}.comments__content-rich :where(:first-child){margin-block-start:0}.comments__content-rich :where(:last-child){margin-block-end:0}.comments__content-rich :where(img,video){block-size:auto;max-inline-size:100%}.comments__content-rich :where(pre){overflow-x:auto}.comments__gif{max-inline-size:min(100%, var(--comments-gif-max-size));max-block-size:var(--comments-gif-max-size);border-radius:var(--comments-space-1);margin-block-start:var(--comments-space-2);display:block}.comments__gif:not(:where(.comments__gif--portrait)){block-size:auto}.comments__gif--portrait{object-fit:contain;inline-size:auto}.comments__button{justify-content:center;align-items:center;gap:var(--comments-space-1);min-block-size:var(--comments-control-size);padding:var(--comments-space-1) var(--comments-space-3);border:1px solid var(--comments-color-border);border-radius:var(--comments-radius);color:inherit;font:inherit;cursor:pointer;background:0 0;font-size:.875em;text-decoration:none;display:inline-flex}.comments__button:hover{background:var(--comments-color-surface-muted)}.comments__button--primary{border-color:var(--comments-color-accent);background:var(--comments-color-accent);color:var(--comments-color-on-accent);font-weight:600}.comments__button--primary:hover{background:var(--comments-color-accent);filter:brightness(.92)}.comments__button--secondary{background:0 0}.comments__button--quiet{min-block-size:1.75rem;padding:var(--comments-space-1) var(--comments-space-2);color:var(--comments-color-muted);border-color:#0000;border-radius:.375rem;gap:.3125rem;font-size:.8125rem;font-weight:600}.comments__button--quiet:hover{background:var(--comments-color-surface-muted);color:var(--comments-color-text)}@media (pointer:coarse){.comments__button--quiet{min-block-size:var(--comments-control-size)}}.comments__moderation-actions>.comments__button,.comments__moderator-bar-actions>.comments__button,.comments__join-actions>.comments__button,.comments__join-guest>.comments__button,.comments__report-actions>.comments__button--secondary{border-radius:999px}.comments__actions{align-items:center;gap:var(--comments-space-1);flex-wrap:wrap;margin-block-start:var(--comments-space-1);margin-inline-start:calc(var(--comments-space-2) * -1);display:flex}.comments__interactions{align-items:center;gap:var(--comments-space-1);flex-wrap:wrap;display:flex}.comments__follow[aria-pressed=true]:where(.comments__button){background:var(--comments-color-surface-muted);border-color:currentColor;font-weight:600}.comments__vote-count,.comments__score{font-variant-numeric:tabular-nums}.comments__score{min-inline-size:1.25em;color:var(--comments-color-muted);text-align:center;font-size:.8125rem;font-weight:600}.comments__moderation-actions{gap:var(--comments-space-2);flex-wrap:wrap;margin-inline-start:auto;display:flex}.comments__toggle-replies{justify-self:start}.comments__toggle-replies:where(.comments__button){min-block-size:0;color:var(--comments-color-muted);background:0 0;border:0;border-radius:0;padding:0;font-size:.8125em;font-weight:600}.comments__toggle-replies:where(.comments__button):hover{color:var(--comments-color-text);background:0 0;text-decoration:underline}.comments__load-more,.comments__show-comments,.comments__load-error{margin-block-start:var(--comments-space-1)}.comments__editor{margin:0;display:block}.comments__editor-row{grid-template-columns:var(--comments-avatar-size) minmax(0, 1fr);gap:var(--comments-space-3);align-items:start;display:grid}.comments__editor-row>.comments__avatar{margin-block-start:.2rem}.comments__avatar--guest:where(.comments__avatar){box-sizing:border-box;border:1.5px solid var(--comments-color-border);color:var(--comments-color-muted);justify-content:center;align-items:center;display:inline-flex}.comments__avatar--guest:where(.comments__avatar) .comments__icon{block-size:60%;inline-size:60%}.comments__frame{border:1px solid var(--comments-color-control-border);border-radius:var(--comments-radius);background:var(--comments-color-surface);flex-direction:column;min-inline-size:0;font-size:.9375rem;line-height:1.55;transition:border-color .15s,box-shadow .15s;display:flex;position:relative;container:comments-frame/inline-size}.comments__frame:focus-within{border-color:color-mix(in srgb, var(--comments-color-accent) 70%, var(--comments-color-control-border));box-shadow:0 0 0 3px color-mix(in srgb, var(--comments-color-accent) 22%, transparent)}.comments__frame--fields:not([data-comments-collapsed]),.comments__frame--fields:not([data-comments-collapsed]):focus-within,.comments verbb-editor .verbb-editor.has-fields:not(.is-collapsed){border-color:var(--comments-color-border);box-shadow:none}.comments__frame--fields:not([data-comments-collapsed])>.comments__frame-box{transition:border-color .15s,box-shadow .15s}.comments__frame--fields:not([data-comments-collapsed])>.comments__frame-box:focus-within,.comments :is(.comments__input,.comments__select,.comments__textarea:not(.comments__textarea-fallback)):focus-visible{border-color:color-mix(in srgb, var(--comments-color-accent) 70%, var(--comments-color-control-border));box-shadow:0 0 0 3px color-mix(in srgb, var(--comments-color-accent) 22%, transparent);outline:none}.comments__frame:has(>.comments__frame-box>[aria-invalid=true]){border-color:var(--comments-color-danger)}.comments__frame :focus-visible{outline-offset:1px}.comments__frame-icon{fill:none;stroke:currentColor;stroke-width:1.9px;stroke-linecap:round;stroke-linejoin:round;flex:none;block-size:18px;inline-size:18px}.comments__frame-head{min-inline-size:0;color:var(--comments-color-muted);flex-wrap:wrap;align-items:center;gap:8px;padding:9px 12px 0;font-size:.8125rem;display:flex}.comments__frame-head>.comments__guest-fields{flex:1 0 100%}.comments__frame-head>.comments__posting-as{flex:1 0 auto}.comments__frame-reply{background:color-mix(in srgb, var(--comments-color-accent) 10%, var(--comments-color-surface));block-size:26px;min-inline-size:0;max-inline-size:100%;color:var(--comments-color-text);border-radius:13px;align-items:center;gap:6px;margin:0;padding:0 4px 0 9px;font-size:.78125rem;display:inline-flex}.comments__frame-reply>.comments__frame-icon{block-size:14px;inline-size:14px}.comments__frame-reply-text{text-overflow:ellipsis;white-space:nowrap;min-inline-size:0;overflow:hidden}.comments__frame-reply b,.comments__posting-as b{font-weight:600}.comments__frame-reply-cancel{block-size:20px;min-block-size:0;inline-size:20px;color:color-mix(in srgb, var(--comments-color-text) 72%, transparent);cursor:pointer;background:0 0;border:0;border-radius:50%;flex:none;place-items:center;padding:0;display:grid}.comments__frame-reply-cancel:hover{color:var(--comments-color-text)}.comments__frame-reply-cancel .comments__frame-icon{block-size:14px;inline-size:14px}.comments__frame .comments__textarea-fallback:where(.comments__textarea){box-sizing:border-box;block-size:calc(3.75em + 16px);min-block-size:calc(3.75em + 16px);max-block-size:436px;font-size:inherit;line-height:inherit;overflow-wrap:anywhere;resize:vertical;background:0 0;border:0;border-radius:0;padding:10px 14px 6px;display:block}.comments__frame .comments__textarea-fallback:where(.comments__textarea)::placeholder{color:color-mix(in srgb, var(--comments-color-muted) 85%, transparent);opacity:1}.comments__frame .comments__textarea-fallback:where(.comments__textarea):focus-visible{outline:none}.comments__frame .comments__textarea-fallback:where(.comments__textarea){resize:none}.comments__frame-notice{background:color-mix(in srgb, var(--comments-color-accent) 10%, var(--comments-color-surface));border-radius:7px;align-items:center;gap:8px;margin:0 10px 8px;padding:7px 10px;font-size:.8125rem;display:flex}.comments__frame-notice[hidden]{display:none}.comments__frame-notice>.comments__frame-icon{block-size:16px;inline-size:16px}.comments__frame-notice-text{flex:1;min-inline-size:0}.comments__frame-link{min-block-size:0;color:var(--comments-color-accent);font:inherit;cursor:pointer;background:0 0;border:0;padding:0 2px;font-weight:600;text-decoration:underline;display:inline}.comments__frame>.comments__editor-fields{padding:0 12px 8px}.comments__frame>.comments__editor-fields--above{padding:10px 12px 0}.comments__frame--fields:not([data-comments-collapsed])>.comments__frame-box{border:1px solid var(--comments-color-control-border);border-radius:calc(var(--comments-radius) - 2px);margin:10px 12px}.comments__frame--fields:not([data-comments-collapsed])>:is(.comments__frame-head,.comments__editor-fields--above)+.comments__frame-box{margin-block-start:8px}.comments__frame--fields:not([data-comments-collapsed])>.comments__frame-box~.comments__editor-fields{padding-block-start:2px}.comments__frame-foot{flex-wrap:wrap;justify-content:flex-end;align-items:center;gap:6px;min-inline-size:0;padding:6px 8px 8px;display:flex}.comments__frame-meta{min-inline-size:0;color:var(--comments-color-muted);flex:none;align-items:center;gap:8px;margin-inline-end:auto;padding-inline-start:6px;font-size:.8125rem;display:flex}.comments__frame-status{white-space:nowrap;text-overflow:ellipsis;align-items:center;gap:5px;transition:opacity .3s;display:inline-flex;overflow:hidden}.comments__frame-status .comments__frame-icon{block-size:14px;inline-size:14px}.comments__frame-status--bad{color:var(--comments-color-danger);font-weight:600}.comments__frame-status--faded{opacity:.55}.comments__frame-count{font-variant-numeric:tabular-nums;flex:none;align-items:center;gap:4px;font-size:.75rem;font-weight:600;display:inline-flex}.comments__frame-count svg{block-size:20px;inline-size:20px;transform:rotate(-90deg)}.comments__frame-count circle{fill:none;stroke-width:2.6px}.comments__frame-count .comments__ring-bg{stroke:color-mix(in srgb, var(--comments-color-text) 14%, transparent)}.comments__frame-count .comments__ring-fg{stroke:var(--comments-color-accent);stroke-linecap:round}.comments__frame-count--warn{color:var(--comments-color-warning)}.comments__frame-count--warn .comments__ring-fg{stroke:var(--comments-color-warning)}.comments__frame-count--bad{color:var(--comments-color-danger)}.comments__frame-count--bad .comments__ring-fg{stroke:var(--comments-color-danger)}.comments__frame-foot .comments__submit:where(.comments__button),.comments__cancel:where(.comments__button),.comments__gif-button:where(.comments__button){border-radius:calc(var(--comments-radius) * .7);white-space:nowrap;cursor:pointer;border:0;justify-content:center;align-items:center;gap:8px;block-size:34px;min-block-size:0;padding:0 14px;font-size:.875rem;font-weight:600;line-height:normal;text-decoration:none;display:inline-flex}.comments__cancel:where(.comments__button),.comments__gif-button:where(.comments__button){color:var(--comments-color-text);background:0 0}.comments__gif-button:where(.comments__button){letter-spacing:.04em;padding:0 10px;font-size:.75rem}.comments__gif-button:where(.comments__button)[aria-expanded=true]{background:var(--comments-color-surface-muted)}.comments__frame-foot .comments__submit:where(.comments__button):hover{background:color-mix(in srgb, var(--comments-color-accent) 88%, #000);filter:none}.comments__cancel:where(.comments__button):hover,.comments__gif-button:where(.comments__button):hover{background:var(--comments-color-surface-muted)}.comments__gif-area{gap:8px;padding:0 12px 8px;display:grid}.comments__gif-preview{gap:8px}.comments__gif-preview-image{border-radius:calc(var(--comments-radius) * .5)}.comments__gif-picker{border:1px solid var(--comments-color-control-border);border-radius:calc(var(--comments-radius) - 2px);background:var(--comments-color-surface);gap:8px;padding:8px;display:grid}.comments__gif-results{gap:6px}.comments__gif-result{border-radius:calc(var(--comments-radius) * .5);background:var(--comments-color-surface-muted);overflow:hidden}.comments__gif-result:hover .comments__gif-result-image{opacity:.85}.comments__gif-status{color:var(--comments-color-muted);margin:0;font-size:.8125rem}.comments__gif-status:empty{display:none}.comments__gif-attribution{color:var(--comments-color-text);text-align:end;margin:0;font-size:.75rem;font-weight:700}.comments__frame-foot .comments__submit:where(.comments__button)[aria-disabled=true]{opacity:.45;cursor:not-allowed}.comments__frame-foot .comments__submit:where(.comments__button)[aria-disabled=true]:hover{background:var(--comments-color-accent)}.comments__submit-keys{letter-spacing:.06em;opacity:.7;align-items:center;font-family:inherit;font-size:12px;font-weight:500;line-height:1;display:inline-flex}.comments__submit-icon{display:none}.comments__submit .comments__frame-icon{stroke-width:2.2px;block-size:18px;inline-size:18px}@container comments-frame (width<=520px){.comments__submit-keys,.comments__frame-status-text{display:none}.comments__frame-meta{gap:4px;padding-inline-start:2px}}@container comments-frame (width<=400px){.comments__frame-foot .comments__submit:where(.comments__button){border-radius:50%;inline-size:36px;padding:0}.comments__submit-label{display:none}.comments__submit-icon{display:block}}.comments__frame[data-comments-collapsed]{border-radius:999px}.comments__frame[data-comments-collapsed] .comments__textarea-fallback:where(.comments__textarea){block-size:auto;min-block-size:0;padding-block:9px;padding-inline:16px}form[data-comments-editor-mounted] .comments__frame{box-shadow:none;background:0 0;border:0;border-radius:0}.comments__editor-row>.comments__field-error{grid-column:2}.comments__contextual-editor:not(:empty){margin-block-start:var(--comments-space-3)}.comments__guest-fields{gap:var(--comments-space-2) var(--comments-space-3);min-inline-size:0;color:var(--comments-color-text);border:0;flex:1 0 100%;grid-template-columns:repeat(2,minmax(0,1fr));align-items:start;margin:0;padding:0;font-size:.875rem;display:grid}.comments__guest-fields>.comments__field--checkbox{grid-column:1/-1}@container (width<=30rem){.comments__guest-fields{grid-template-columns:minmax(0,1fr)}}.comments__guest-fields .comments__input,.comments__editor-fields :is(.comments__input,.comments__select),.comments__editor-fields .comments__textarea{border-color:var(--comments-color-control-border);background:var(--comments-color-surface)}.comments__field-hint{color:var(--comments-color-muted);margin-inline-start:var(--comments-space-1);font-weight:400}.comments__editor-fields{gap:var(--comments-space-3);color:var(--comments-color-text);grid-template-columns:repeat(4,minmax(0,1fr));align-items:start;font-size:.875rem;display:grid}.comments__field-cell{grid-column:span 4;min-inline-size:0}.comments__field-cell[data-comments-width=\"25\"]{grid-column:span 1}.comments__field-cell[data-comments-width=\"50\"]{grid-column:span 2}.comments__field-cell[data-comments-width=\"75\"]{grid-column:span 3}@container (width<=30rem){.comments__field-cell{grid-column:span 4}}.comments__report-fields,.comments__field-custom:where(fieldset){gap:var(--comments-space-3);border:0;min-inline-size:0;margin:0;padding:0;display:grid}.comments__report-fields legend,.comments__field-custom legend{padding:0;font-weight:600}.comments__field{gap:var(--comments-space-1);grid-template-columns:minmax(0,1fr);display:grid}.comments__field-label,.comments__label{font-size:.875em;font-weight:600;line-height:1.35}.comments__field-instructions{color:var(--comments-color-muted);margin:0;font-size:.875em;line-height:1.4}.comments__field-label+.comments__field-instructions{margin-block-start:calc(var(--comments-space-1) * -.5)}.comments__field--checkbox{align-items:flex-start;gap:var(--comments-space-2);font-size:.875em;display:flex}.comments__field--checkbox input{flex:none;margin:.2em 0 0}.comments__field-custom:where(fieldset)>legend{margin-block-end:var(--comments-space-1)}.comments__field-custom:where(fieldset){gap:var(--comments-space-2)}.comments__switch:where(.comments__checkbox){appearance:none;background:var(--comments-color-control-border);cursor:pointer;border:1px solid #0000;border-radius:999px;flex:none;block-size:1.25rem;inline-size:2.25rem;margin:0;transition:background-color .15s;position:relative}.comments__switch:where(.comments__checkbox):before{content:\"\";background:var(--comments-color-surface);border-radius:50%;block-size:calc(1.25rem - 4px);inline-size:calc(1.25rem - 4px);transition:inset-inline-start .15s;position:absolute;inset-block-start:1px;inset-inline-start:1px;box-shadow:0 1px 2px #00000040}.comments__switch:where(.comments__checkbox):checked{background:var(--comments-color-accent)}.comments__switch:where(.comments__checkbox):checked:before{inset-inline-start:calc(100% - 1.25rem + 3px)}.comments__switch:where(.comments__checkbox)[aria-invalid=true]{border-color:var(--comments-color-danger)}@media (forced-colors:active){.comments__switch:where(.comments__checkbox){background:buttonface;border-color:buttontext}.comments__switch:where(.comments__checkbox):before{background:buttontext}.comments__switch:where(.comments__checkbox):checked{background:highlight}.comments__switch:where(.comments__checkbox):checked:before{background:highlighttext}}@media (prefers-reduced-motion:reduce){.comments__switch:where(.comments__checkbox),.comments__switch:where(.comments__checkbox):before{transition:none}}.comments__input,.comments__textarea,.comments__select{box-sizing:border-box;inline-size:100%;max-inline-size:100%;min-block-size:var(--comments-control-size);padding:var(--comments-space-2) var(--comments-space-3);border:1px solid var(--comments-color-control-border);border-radius:var(--comments-radius);background:var(--comments-color-surface);color:inherit;font:inherit;transition:border-color .15s,box-shadow .15s}.comments__select:not([multiple]){appearance:none;background-image:linear-gradient(45deg,#0000 calc(50% - .75px),currentColor calc(50% - .75px) calc(50% + .75px),#0000 calc(50% + .75px)),linear-gradient(-45deg,#0000 calc(50% - .75px),currentColor calc(50% - .75px) calc(50% + .75px),#0000 calc(50% + .75px));background-position:calc(100% - var(--comments-space-3) - 5px) 50%, calc(100% - var(--comments-space-3)) 50%;background-repeat:no-repeat;background-size:5px 5px;padding-inline-end:calc(var(--comments-space-3) + 18px)}.comments__select:not([multiple]):is(:lang(ae),:lang(ar),:lang(arc),:lang(bcc),:lang(bqi),:lang(ckb),:lang(dv),:lang(fa),:lang(glk),:lang(he),:lang(ku),:lang(mzn),:lang(nqo),:lang(pnb),:lang(ps),:lang(sd),:lang(ug),:lang(ur),:lang(yi)){background-position:var(--comments-space-3) 50%, calc(var(--comments-space-3) + 5px) 50%}.comments__field-required{color:var(--comments-color-danger)}.comments__textarea{resize:vertical;min-block-size:6rem}.comments__report-form .comments__textarea{min-block-size:var(--comments-control-size)}.comments [aria-invalid=true]{border-color:var(--comments-color-danger)}.comments__field-error,.comments__form-error{color:var(--comments-color-danger);margin:0;font-size:.875em}.comments__report-actions{gap:var(--comments-space-2);flex-wrap:wrap;margin-block-start:var(--comments-space-4);display:flex}.comments__report-dialog{padding:var(--comments-space-4);border:1px solid var(--comments-color-border);border-radius:var(--comments-radius);background:var(--comments-color-surface);color:var(--comments-color-text)}.comments__report-dialog::backdrop{background:var(--comments-color-scrim,#00000073)}.comments__empty p,.comments__guest-notice,.comments__closed-notice,.comments__loading{color:var(--comments-color-muted);margin:0}.comments__empty p+p{margin-block-start:var(--comments-space-1)}[data-comments-region=toast]{inset-block-end:var(--comments-space-4)}.comments__toast{align-items:center;gap:var(--comments-space-2);max-inline-size:min(100%,32rem);padding:var(--comments-space-2) var(--comments-space-3);background:var(--comments-color-toast);color:var(--comments-color-on-toast);border-radius:.5rem;font-size:.875rem;font-weight:500;line-height:1.4;animation:.18s ease-out comments-toast-in;display:inline-flex;box-shadow:0 8px 24px -8px #00000073}.comments__toast[data-comments-state]:before{content:\"\";block-size:1rem;inline-size:1rem;-webkit-mask:var(--_comments-toast-icon) center / contain no-repeat;-webkit-mask:var(--_comments-toast-icon) center / contain no-repeat;mask:var(--_comments-toast-icon) center / contain no-repeat;background:currentColor;flex:none}.comments__toast[data-comments-state=success]{--_comments-toast-icon:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M20 6 9 17l-5-5'/%3E%3C/svg%3E\")}.comments__toast[data-comments-state=success]:before{color:var(--comments-color-toast-success)}.comments__toast[data-comments-state=error]{--_comments-toast-icon:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='10'/%3E%3Cpath d='M12 8v4M12 16h.01'/%3E%3C/svg%3E\")}.comments__toast[data-comments-state=error]:before{color:var(--comments-color-toast-danger)}.comments__toast button{min-block-size:0;color:inherit;font-size:inherit;text-underline-offset:2px;background:0 0;border:0;margin-inline-start:var(--comments-space-1);padding:0;font-weight:600;text-decoration:underline}.comments__toast button:hover{opacity:.85;background:0 0}@keyframes comments-toast-in{0%{opacity:0;transform:translateY(6px)}}@media (max-width:40rem){.comments__moderation-actions{margin-inline-start:0}}.comments__icon{fill:none;stroke:currentColor;stroke-width:1.8px;stroke-linecap:round;stroke-linejoin:round;flex:none;block-size:1.1em;inline-size:1.1em}.comments__icon--more circle,.comments__vote[aria-pressed=true] .comments__icon--heart{fill:currentColor}.comments__vote[aria-pressed=true] :is(.comments__icon--up,.comments__icon--down){stroke-width:2.4px}.comments__heading .comments__count{margin-inline-start:var(--comments-space-1);font-size:.7em;font-weight:400}.comments__sort{background:var(--comments-color-surface-muted);border-radius:999px;padding:2px;display:inline-flex}.comments__sort-option:where(.comments__button){min-block-size:0;padding:var(--comments-space-1) var(--comments-space-3);color:color-mix(in srgb, var(--comments-color-text) 60%, var(--comments-color-muted));font-size:.875em;font-weight:400;line-height:inherit;background:0 0;border:0;border-radius:999px;display:inline}.comments__sort-option:where(.comments__button):hover{color:var(--comments-color-text);background:0 0}.comments__sort-option:where(.comments__button)[aria-pressed=true]{background:var(--comments-color-surface);color:var(--comments-color-text);font-weight:600}.comments__actions .comments__icon{block-size:.9375rem;inline-size:.9375rem}.comments .comments__vote-total{min-block-size:1.75rem;padding:var(--comments-space-1) var(--comments-space-2);color:var(--comments-color-muted);border:1px solid #0000;align-items:center;gap:.3125rem;font-size:.8125rem;font-weight:600;display:inline-flex}.comments [data-comments-interaction-mode=updown]{gap:0}.comments__vote[aria-pressed=true]{color:var(--comments-color-like)}[data-comments-interaction-mode=updown] .comments__vote--up[aria-pressed=true]{color:var(--comments-color-accent)}.comments__follow:where(.comments__button){gap:var(--comments-space-2)}.comments__menu-items{min-inline-size:12rem;padding:var(--comments-space-1);border:1px solid var(--comments-color-border);border-radius:var(--comments-radius);background:var(--comments-color-surface);margin-block-start:var(--comments-space-1);box-shadow:0 8px 24px #0000001f}.comments__menu-item{justify-content:flex-start;align-items:center;gap:var(--comments-space-1);box-sizing:border-box;inline-size:100%;min-block-size:var(--comments-control-size);padding:var(--comments-space-1) var(--comments-space-3);border-radius:calc(var(--comments-radius) - var(--comments-space-1));text-align:start;white-space:nowrap;background:0 0;border:1px solid #0000;font-size:.875em;display:inline-flex}.comments__menu-item:hover{background:var(--comments-color-surface-muted)}.comments .comments__menu-item:focus-visible{outline-offset:-2px}.comments__menu-group{border-block-start:1px solid var(--comments-color-border);margin-block-start:var(--comments-space-1);padding-block-start:var(--comments-space-1);display:grid}.comments__menu-group:first-child{border-block-start:0;margin-block-start:0;padding-block-start:0}.comments__menu-heading{padding:var(--comments-space-1) var(--comments-space-3);color:var(--comments-color-muted);letter-spacing:.04em;text-transform:uppercase;font-size:.75em;font-weight:600}.comments__menu-item--danger{color:var(--comments-color-danger)}.comments__avatar--initials:where(.comments__avatar){color:var(--comments-color-on-avatar);background:var(--comments-color-avatar-0);justify-content:center;align-items:center;font-size:.8125rem;font-weight:700;display:inline-flex}.comments__avatar--initials:where(.comments__avatar)[data-tone=\"1\"]{background:var(--comments-color-avatar-1)}.comments__avatar--initials:where(.comments__avatar)[data-tone=\"2\"]{background:var(--comments-color-avatar-2)}.comments__avatar--initials:where(.comments__avatar)[data-tone=\"3\"]{background:var(--comments-color-avatar-3)}.comments__avatar--initials:where(.comments__avatar)[data-tone=\"4\"]{background:var(--comments-color-avatar-4)}.comments__avatar--initials:where(.comments__avatar)[data-tone=\"5\"]{background:var(--comments-color-avatar-5)}.comments__avatar--initials:where(.comments__avatar)[data-tone=\"6\"]{background:var(--comments-color-avatar-6)}.comments__avatar--initials:where(.comments__avatar)[data-tone=\"7\"]{background:var(--comments-color-avatar-7)}.comments__avatar--small{block-size:1.5rem;inline-size:1.5rem;font-size:.625em}.comments__comment--held>article,.comments__comment--pending>article,.comments__comment--reported>article{padding:var(--comments-space-3);border:1px solid var(--comments-color-held-border);border-radius:var(--comments-radius);background:var(--comments-color-held-surface)}.comments__comment--reported>article{border-color:var(--comments-color-reported-border);background:var(--comments-color-reported-surface)}.comments__review{align-items:center;gap:var(--comments-space-2);border-block-start:1px solid var(--comments-color-held-border);flex-wrap:wrap;margin-block-start:var(--comments-space-3);padding-block-start:var(--comments-space-2);display:flex}.comments__review-label{align-items:center;gap:var(--comments-space-1);color:var(--comments-color-held-text);margin:0;font-size:.875em;display:inline-flex}.comments__comment--reported>article>.comments__review{border-block-start-color:var(--comments-color-reported-border)}.comments__comment--reported>article>.comments__review .comments__review-label{color:var(--comments-color-reported-text)}.comments :is(.comments__moderator-bar,.comments__comment--held>article,.comments__comment--pending>article,.comments__comment--reported>article){--_comments-tint-border:var(--comments-color-held-border)}.comments .comments__comment--reported>article{--_comments-tint-border:var(--comments-color-reported-border)}.comments :is(.comments__moderator-bar,.comments__comment--held>article,.comments__comment--reported>article) .comments__button--secondary{border-color:var(--_comments-tint-border)}.comments :is(.comments__moderator-bar,.comments__comment--held>article,.comments__comment--reported>article) .comments__button--secondary:hover{background:color-mix(in srgb, var(--_comments-tint-border) 35%, transparent)}.comments__moderator-bar{justify-content:space-between;align-items:center;gap:var(--comments-space-2);inline-size:100%;padding:var(--comments-space-2) var(--comments-space-3);border:1px solid var(--comments-color-held-border);border-radius:var(--comments-radius);background:var(--comments-color-held-surface);color:var(--comments-color-held-text);flex-wrap:wrap;display:flex}.comments__moderator-bar p{margin:0}.comments__moderator-bar-actions{align-items:center;gap:var(--comments-space-2);flex-wrap:wrap;display:flex}.comments__join{padding:var(--comments-space-4);border:1px solid var(--comments-color-border);border-radius:var(--comments-radius);background:var(--comments-color-surface)}.comments__join-title{margin:0;font-size:1em}.comments__join-text{margin:var(--comments-space-1) 0 var(--comments-space-3);color:var(--comments-color-muted)}.comments__join-actions{align-items:flex-start;gap:var(--comments-space-2);flex-wrap:wrap;display:flex}.comments__join-guest{flex:0 auto}.comments__join-guest[open]{flex-basis:100%}.comments__join-guest>summary{list-style:none}.comments__join-guest>summary:where(.comments__button){padding-inline:var(--comments-space-4)}.comments__join-guest>summary::-webkit-details-marker{display:none}.comments__join:has(.comments__join-guest[open]){background:0 0;border:0;padding:0}.comments__join-guest[open]>summary{display:none!important}.comments__join:has(.comments__join-guest[open])>:is(.comments__join-title,.comments__join-text){display:none!important}.comments__join-actions:has(.comments__join-guest[open])>:not(.comments__join-guest[open]){display:none!important}.comments__join-actions>.comments__button{min-block-size:var(--comments-control-size);padding:var(--comments-space-1) var(--comments-space-4);align-items:center;text-decoration:none;display:inline-flex}.comments__join-actions:has(.comments__join-guest[open]){display:block}.comments__posting-as{align-items:center;gap:var(--comments-space-2);color:var(--comments-color-muted);flex-wrap:wrap;margin:0;font-size:.8125rem;display:flex}.comments__posting-as b{color:var(--comments-color-text)}.comments__report-header{justify-content:space-between;align-items:center;gap:var(--comments-space-2);margin-block-end:var(--comments-space-3);display:flex}.comments__report-title{margin:0;font-size:1.05em}.comments__report-close{min-block-size:var(--comments-control-size);padding:var(--comments-space-1) var(--comments-space-3);border-radius:var(--comments-radius);background:0 0;border:1px solid #0000;justify-content:center;align-items:center;font-size:1.25em;display:inline-flex}.comments__report-close:hover{background:var(--comments-color-surface-muted)}.comments__report-quote{margin:0 0 var(--comments-space-3);padding:var(--comments-space-2) var(--comments-space-3);border-inline-start:3px solid var(--comments-color-border);background:var(--comments-color-surface-muted);font-size:.875em}.comments__report-reason{align-items:flex-start;gap:var(--comments-space-2);padding:var(--comments-space-2) var(--comments-space-3);border:1px solid var(--comments-color-border);border-radius:var(--comments-radius);cursor:pointer;margin-block-end:var(--comments-space-2);display:flex}.comments__report-reason:has(input:checked){border-color:var(--comments-color-accent)}.comments__report-reason input{margin-block-start:.25em}.comments__report-reason-label{font-weight:600;display:block}.comments__report-reason-description{color:var(--comments-color-muted);font-size:.875em;display:block}.comments__comment{background:0 0;border:0;border-radius:0;padding:0}.comments__list{gap:1.25rem}.comments verbb-editor{--verbb-editor-accent:var(--comments-color-accent);--verbb-editor-accent-fg:var(--comments-color-on-accent);--verbb-editor-bg:var(--comments-color-surface);--verbb-editor-fg:var(--comments-color-text);--verbb-editor-muted:var(--comments-color-muted);--verbb-editor-border:var(--comments-color-control-border);--verbb-editor-hover:var(--comments-color-surface-muted);--verbb-editor-danger:var(--comments-color-danger);--verbb-editor-radius:var(--comments-radius);--verbb-editor-font-family:inherit}.comments verbb-editor .verbb-editor.is-collapsed{border-radius:999px}.comments verbb-editor .verbb-editor.is-collapsed .verbb-editor__body{padding-inline:16px}.comments verbb-editor .verbb-editor__head .comments__posting-as,.comments verbb-editor .verbb-editor__head .comments__guest-fields{flex:1 0 100%}.comments verbb-editor .verbb-editor__head .comments__posting-as{flex-basis:auto}.comments__meta-item{color:var(--comments-color-muted);font-size:.875em}.comments__meta-item:before{content:\"· \"}.comments__fields{gap:var(--comments-space-1);margin:var(--comments-space-2) 0 0;font-size:.875em;display:grid}.comments__field-value{gap:var(--comments-space-2);flex-wrap:wrap;display:flex}.comments__field-name{color:var(--comments-color-muted);font-weight:600}.comments__field-text{margin:0}.comments__field-link,.comments__field-file{color:var(--comments-color-link);overflow-wrap:anywhere}.comments__field-files{align-items:center;gap:var(--comments-space-2);flex-wrap:wrap;display:flex}span.comments__field-file{color:inherit}.comments__field-thumb{border-radius:var(--comments-radius);block-size:4rem;inline-size:4rem;box-shadow:inset 0 0 0 1px var(--comments-color-border);display:block}.comments__field--files{justify-items:start}.comments__field--files>:is(.comments__uploads,.comments__upload-row){margin-block-start:var(--comments-space-1)}.comments__upload-row{align-items:center;gap:var(--comments-space-1) var(--comments-space-3);flex-wrap:wrap;display:flex}.comments__upload-limit{color:var(--comments-color-muted);font-size:.8125rem}.comments__uploads{gap:var(--comments-space-2);flex-wrap:wrap;margin:0;padding:0;list-style:none;display:flex}.comments__upload{border-radius:var(--comments-radius);background:var(--comments-color-surface-muted);block-size:4rem;min-inline-size:0;max-inline-size:15rem;box-shadow:inset 0 0 0 1px var(--comments-color-border);align-items:center;gap:.5625rem;padding-block:0;padding-inline:.375rem 2rem;display:flex;position:relative}.comments__upload--failed{box-shadow:inset 0 0 0 1px var(--comments-color-danger)}.comments__upload--uploading{opacity:.75}.comments__upload-icon{background:var(--comments-color-surface);block-size:2.5rem;inline-size:2.125rem;box-shadow:0 0 0 1px var(--comments-color-border);color:var(--comments-color-accent);border-radius:.3125rem;flex:none;place-items:center;display:grid}.comments__upload-text{gap:.1875rem;min-inline-size:0;font-size:.8125rem;display:grid}.comments__upload-name{text-overflow:ellipsis;white-space:nowrap;font-weight:600;overflow:hidden}.comments__upload-meta{color:var(--comments-color-muted)}.comments__upload--failed .comments__upload-meta{color:var(--comments-color-danger)}.comments__upload-remove,.comments__upload-retry{background:var(--comments-color-toast);block-size:1.375rem;inline-size:1.375rem;color:var(--comments-color-on-toast);cursor:pointer;border:0;border-radius:50%;place-items:center;padding:0;display:grid;position:absolute;inset-block-start:.25rem;inset-inline-end:.25rem}.comments__upload-retry{inset-block:auto .25rem}.comments__upload-remove .comments__icon,.comments__upload-retry .comments__icon{stroke-width:2.4px;block-size:.75rem;inline-size:.75rem}@media (pointer:coarse){.comments__upload-remove:after,.comments__upload-retry:after{content:\"\";block-size:2.75rem;inline-size:2.75rem;position:absolute}}.comments__upload-add{align-items:center;gap:var(--comments-space-1);display:inline-flex}.comments__loading[aria-busy=true]{animation:1.6s ease-in-out infinite comments-pulse}@keyframes comments-pulse{50%{opacity:.55}}[dir=rtl] :is(.comments__icon--reply,.comments__frame-reply>.comments__frame-icon){transform:scaleX(-1)}.comments__load-error>.comments__loading{margin-block-end:var(--comments-space-2)}", u = "verbb-comments", d, f = /* @__PURE__ */ new Set();
function p(e) {
	try {
		let t = new URL(e, globalThis.location?.href);
		return (t.protocol === "https:" || t.protocol === "http:") && f.has(t.origin);
	} catch {
		return !1;
	}
}
function m() {
	let e = globalThis;
	if (!e.trustedTypes) return null;
	if (d === void 0) try {
		d = e.trustedTypes.createPolicy(u, {
			createHTML: (e) => e,
			createScriptURL(e) {
				if (!p(e)) throw TypeError("Comments only loads Editor from the data API’s origin or its own.");
				return e;
			}
		});
	} catch {
		d = null;
	}
	return d;
}
function h(e) {
	let t = m();
	return t ? t.createHTML(e) : e;
}
// packages/comments-browser/src/directives.ts
var g = /* @__PURE__ */ new Map(), _ = /* @__PURE__ */ new Set(), v = class extends r {
	constructor(e) {
		if (super(e), this.applied = /* @__PURE__ */ new Map(), e.type !== a.ELEMENT) throw Error("Comments: attributes() only works on an element.");
	}
	render(e) {
		return t;
	}
	update(e, [t]) {
		let n = e.element, r = /* @__PURE__ */ new Map();
		for (let [e, n] of Object.entries(t)) (_.has(e) || /^[a-zA-Z_:][-a-zA-Z0-9_:.]*$/.test(e) && _.add(e)) && r.set(e, n === !0 ? "" : n);
		for (let e of this.applied.keys()) r.has(e) || n.removeAttribute(e);
		for (let [e, t] of r) (this.applied.get(e) !== t || n.getAttribute(e) !== t) && n.setAttribute(e, t);
		return this.applied = r, i;
	}
}, y = s(v);
g.set(v, { attributes: ([e]) => e });
var b = class extends r {
	constructor(e) {
		if (super(e), e.type !== a.ELEMENT) throw Error("Comments: richHtml() only works on an element.");
	}
	render(e) {
		return t;
	}
	update(e, [t]) {
		return t !== this.last && (this.last = t, e.element.innerHTML = h(t)), i;
	}
}, x = s(b);
g.set(b, { inner: ([e]) => String(e ?? "") });
var S = class extends r {
	constructor(e) {
		if (super(e), this.element = null, this.tag = "", e.type !== a.CHILD) throw Error("Comments: a custom element part goes where content goes.");
	}
	render(e, t) {
		return e;
	}
	update(e, [t, n]) {
		return this.element && this.tag === t || (this.tag = t, this.element = document.createElement(t)), Object.assign(this.element, n), this.element;
	}
};
s(S), g.set(S, { child: ([e]) => {
	let n = String(e);
	return /^[a-z][a-z0-9._-]*-[a-z0-9._-]*$/.test(n) ? {
		_$litType$: 1,
		strings: [`<${n}></${n}>`],
		values: []
	} : t;
} });
var C = c([], () => "")._$litDirective$;
g.set(C, { child: (e) => {
	let [t, n, r] = e, i = r ?? n;
	return Array.from(t, (e, t) => i(e, t));
} });
// packages/comments-browser/src/labels.ts
var w = {
	comments: "Comments",
	showComments: "Show comments",
	showCommentsCount: "Show comments ({count})",
	loading: "Loading comments…",
	guest: "Guest",
	error: "Something went wrong. Please try again.",
	tryAgain: "Try again",
	moderation: "Moderation",
	onThisPage: "on this page",
	hold: "Hold",
	removedNote: "Removed · only moderators see this",
	chooseOption: "Choose…",
	formFailed: "The comment form couldn’t load. Please try again.",
	gif: "GIF",
	gifAdd: "Add a GIF",
	gifSearch: "Search GIPHY",
	gifResults: "GIFs",
	gifRemove: "Remove GIF",
	gifEmpty: "No GIFs found.",
	gifPoweredBy: "Powered by GIPHY",
	gifUnavailable: "GIFs can’t be loaded right now.",
	foldedReports: "Comment marked as inappropriate",
	foldedDownvotes: "Comment hidden due to low rating",
	showFolded: "Show comment",
	authorScore: "Total votes across this user’s comments"
};
function ee(...e) {
	let t = { ...w };
	for (let n of e) for (let [e, r] of Object.entries(n ?? {})) typeof r == "string" && r !== "" && (t[e] = r);
	return t;
}
function T(e, t) {
	return e.replace(/\{(\w+)\}/g, (e, n) => n in t ? String(t[n]) : e);
}
// packages/comments-core/dist/store.js
function E(e, t) {
	return (e.replies[t]?.uids ?? []).flatMap((t) => e.comments[t] ?? []);
}
// packages/comments-browser/src/view.ts
var te = () => ({
	collapsed: /* @__PURE__ */ new Set(),
	fullDates: /* @__PURE__ */ new Set(),
	hideHeld: !1,
	highlight: null,
	toast: null,
	live: "",
	reportUid: null,
	busy: /* @__PURE__ */ new Set(),
	pendingVotes: /* @__PURE__ */ new Map(),
	openForm: null,
	reportError: null,
	joinOpen: !1,
	menus: /* @__PURE__ */ new Set(),
	unfolded: /* @__PURE__ */ new Set()
});
function D(e) {
	let t = e.permalink ? e.permalink.slice(e.permalink.indexOf("#") + 1) : "";
	return /^comment-[\w-]+$/.test(t) ? t : `comment-${e.uid}`;
}
var O = "ugc nofollow noopener";
function ne(e) {
	if (typeof e != "string" || e.trim() === "") return null;
	let t = e.trim(), n = typeof location > "u" ? null : location.href;
	try {
		if (t.startsWith("/")) {
			if (!n) return /^\/(?![/\\])[^\s\\]*$/.test(t) ? t : null;
			let e = new URL(t, n);
			return e.origin === new URL(n).origin ? e.href : null;
		}
		let e = new URL(t);
		return e.protocol === "http:" || e.protocol === "https:" ? e.href : null;
	} catch {
		return null;
	}
}
function re(e) {
	return Array.isArray(e.value) ? e.value.filter((e) => !!e && typeof e == "object") : [];
}
function ie(e) {
	let n = ne(e.url), r = String(e.filename ?? "");
	return n && e.kind === "image" ? o`<a class="comments__field-file comments__field-file--image" href=${n} rel=${O}><img class="comments__field-thumb" src=${n} alt=${r} loading="lazy" width=${e.width ?? t} height=${e.height ?? t}></a>` : n ? o`<a class="comments__field-file" href=${n} rel=${O}>${r}</a>` : o`<span class="comments__field-file">${r}</span>`;
}
function ae(e, t = 0) {
	let n = t && e.text.length > t ? `${e.text.slice(0, t)}…` : e.text;
	if (e.type === "url") {
		let t = ne(e.value);
		return t ? o`<a class="comments__field-link" href=${t} rel=${O}>${n}</a>` : n;
	}
	if (e.type === "file" && !t) {
		let t = re(e);
		return t.length ? o`<span class="comments__field-files">${t.map(ie)}</span>` : n;
	}
	return n;
}
function oe(e) {
	return !!e.text || e.type === "file" && re(e).length > 0;
}
function k(e, t, n, r = 0) {
	return e.field("display", n.handle, {
		field: n,
		comment: t,
		value: n.value,
		text: n.text
	}, () => ae(n, r));
}
function se(e, t) {
	return t.fields.filter((e) => e.placement === "inline" && oe(e)).map((n) => o`<span class=${`comments__meta-item comments__field-value--${n.handle}`}>${k(e, t, n, 40)}</span>`);
}
function ce(e, n, r) {
	let i = n.fields.filter((e) => e.placement === "details" && oe(e));
	return i.length ? e.part("fields", {
		...r,
		fields: i
	}, () => o`<dl class="comments__fields">${i.map((t) => o`<div class=${`comments__field-value comments__field-value--${t.handle}`}><dt class="comments__field-name">${t.label}</dt><dd class="comments__field-text">${k(e, n, t)}</dd></div>`)}</dl>`) : t;
}
// packages/comments-browser/src/parts/icons.ts
var le = {
	heart: n`<path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7z"/>`,
	up: n`<path d="M12 19V5M5 12l7-7 7 7"/>`,
	down: n`<path d="M12 5v14M19 12l-7 7-7-7"/>`,
	reply: n`<path d="m9 17-5-5 5-5"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/>`,
	more: n`<circle cx="5" cy="12" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="19" cy="12" r="1.3"/>`,
	bell: n`<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>`,
	user: n`<circle cx="12" cy="8" r="4.5"/><path d="M20 21a8 8 0 0 0-16 0"/>`,
	clock: n`<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>`,
	spam: n`<path d="M7.9 2h8.2L22 7.9v8.2L16.1 22H7.9L2 16.1V7.9z"/><path d="M12 8v4M12 16h.01"/>`,
	flag: n`<path d="M4 22V4c3-2 6-1 8 0s5 2 8 0v10c-3 2-6 1-8 0s-5-2-8 0"/>`,
	removed: n`<path d="M10.7 5.1A10 10 0 0 1 12 5c7 0 10 7 10 7a13 13 0 0 1-1.7 2.7M6.6 6.6A13.5 13.5 0 0 0 2 12s3 7 10 7a9.7 9.7 0 0 0 5.4-1.6M2 2l20 20M9.9 9.9a3 3 0 0 0 4.2 4.2"/>`,
	close: n`<path d="M18 6 6 18M6 6l12 12"/>`,
	check: n`<path d="M20 6 9 17l-5-5"/>`,
	alert: n`<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>`,
	info: n`<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>`,
	file: n`<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>`,
	attach: n`<path d="m21 11-8.6 8.6a5 5 0 0 1-7-7l8.6-8.6a3.3 3.3 0 0 1 4.7 4.7l-8.6 8.6a1.7 1.7 0 0 1-2.4-2.4L15.6 7"/>`,
	retry: n`<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>`
};
function A(e) {
	return o`<svg class=${`comments__icon comments__icon--${e}`} width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${le[e]}</svg>`;
}
function j(e, t = "") {
	return o`<svg class=${`comments__frame-icon${t ? ` ${t}` : ""}`} width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${le[e]}</svg>`;
}
var ue = {
	approve: "approve",
	keep: "keep",
	remove: "remove",
	restore: "restore",
	spam: "spam",
	"not-spam": "notSpam",
	hold: "hold"
}, de = /* @__PURE__ */ new Set([
	"held",
	"spam",
	"pending",
	"removed",
	"reported"
]);
function M(e, t) {
	return e.state.permissions[t] ?? null;
}
function fe(e, t) {
	return t.moderation ?? M(e, t.uid)?.moderation ?? null;
}
var pe = /* @__PURE__ */ new Set([
	"held",
	"spam",
	"removed"
]);
function me(e, n) {
	if (e.ui.hideHeld && pe.has(n.state)) return t;
	let r = e.budget;
	if (r && !r.drawn.has(n.uid)) {
		if (r.left <= 0) return r.more = !0, t;
		r.left--, r.drawn.add(n.uid);
	} else if (r?.adding && N(e, n, r.drawn)) return i;
	return e.part("comment", F(e, n), () => he(e, n), {
		around: (t) => R(e, n, o`${t}${z(e, n)}`),
		inside: () => I(e, n)
	});
}
function N(e, t, n) {
	return E(e.state, t.uid).every((t) => e.ui.hideHeld && pe.has(t.state) || n.has(t.uid) && N(e, t, n));
}
var P = /* @__PURE__ */ new WeakMap();
function F(e, t) {
	let n = P.get(e);
	n || (n = /* @__PURE__ */ new Map(), P.set(e, n));
	let r = n.get(t.uid);
	return r && r.comment === t || (r = {
		comment: t,
		permissions: M(e, t.uid),
		viewer: e.state.viewer,
		conversation: e.state.conversation,
		actions: e.actions(t.uid),
		form: () => B(e, t),
		replies: () => q(e, t)
	}, n.set(t.uid, r)), r;
}
function he(e, t) {
	return R(e, t, t.state === "placeholder" ? I(e, t) : o`${I(e, t)}${z(e, t)}`);
}
function I(e, t) {
	return t.state === "placeholder" ? e.part("placeholder", F(e, t), () => o`<p class="comments__placeholder">${e.labels.unavailable}</p>`) : L(e, t) ? o`${H(e, t)}${ge(e, t)}${W(e, t)}${K(e, t)}` : o`${H(e, t)}${e.part("content", F(e, t), () => be(e, t))}${xe(e, t)}${W(e, t)}${K(e, t)}`;
}
function L(e, t) {
	return !!t.folded && !e.ui.unfolded.has(t.uid);
}
function ge(e, n) {
	let { labels: r } = e, i = n.folded === "downvotes" ? r.foldedDownvotes : r.foldedReports;
	return o`<div class="comments__folded" data-comments-folded=${n.folded ?? t}><p class="comments__folded-label">${i}</p><button ${y(e.control("quietButton", {
		identify: "comments__folded-show",
		attributes: {
			type: "button",
			"data-comments-action": "unfold",
			"data-comments-comment-uid": n.uid
		}
	}))}>${r.showFolded}</button></div>`;
}
function R(e, n, r) {
	let { labels: i } = e, a = n.state === "placeholder", s = n.state, c = [
		"comments__comment",
		s === "held" || s === "spam" || s === "removed" ? "comments__comment--held" : "",
		s === "pending" ? "comments__comment--pending" : "",
		s === "reported" || s === "approved" && M(e, n.uid)?.moderation?.openReportCount ? "comments__comment--reported" : "",
		e.ui.highlight === n.uid ? "comments__comment--highlight" : ""
	].filter(Boolean).join(" "), l = n.author?.name ?? i.guest;
	return o`<li class=${c} data-comments-comment data-comments-comment-uid=${n.uid} data-comments-depth=${n.depth} data-comments-visual-depth=${Math.min(n.depth, 4)} data-comments-state=${s}><article id=${D(n)} tabindex="-1" aria-label=${a ? t : T(i.commentBy, { name: l })}>${r}${q(e, n)}</article></li>`;
}
function z(e, t) {
	return o`<div class="comments__contextual-editor" data-comments-region="contextual-editor" data-comments-comment-uid=${t.uid}>${B(e, t)}</div>`;
}
function B(e, n) {
	let r = e.ui.openForm;
	return r && r.uid === n.uid ? e.form(`${r.mode}:${n.uid}`) : t;
}
function V(e, t, n = 32) {
	return t?.avatarUrl ? o`<img ${y(e.control("avatar", { attributes: {
		src: t.avatarUrl,
		alt: "",
		width: n,
		height: n,
		loading: "lazy"
	} }))}>` : o`<span ${y(e.control("avatar", {
		style: "comments__avatar--initials",
		attributes: {
			"data-tone": t?.avatarTone ?? 0,
			"aria-hidden": "true"
		}
	}))}>${t?.initials ?? ""}</span>`;
}
function H(e, n) {
	let r = F(e, n), i = n.author?.badges ?? [];
	return o`<div class="comments__author">${e.state.conversation?.showAvatars === !1 ? t : e.part("avatar", r, () => V(e, n.author))}<div class="comments__author-meta">${e.part("authorName", r, () => n.author?.name ? o`<span class="comments__author-name">${n.author.name}</span>` : t)}${_e(e, n)}${i.map((t) => e.part("badge", {
		...r,
		badge: t
	}, () => U(t)))}${e.part("meta", r, () => ve(e, n))}</div></div>`;
}
function _e(e, n) {
	let r = n.author?.isGuest ? null : n.author?.score;
	return typeof r == "number" ? o`<span class=${`comments__author-score${r > 0 ? " comments__author-score--positive" : r < 0 ? " comments__author-score--negative" : ""}`} title=${e.labels.authorScore}><span class="comments__visually-hidden">${e.labels.authorScore}: </span>${r > 0 ? `+${r}` : String(r)}</span>` : t;
}
function U(e) {
	return o`<span class=${`comments__badge comments__badge--${e.type.replace(/[^\w-]/g, "")}`}>${e.label}</span>`;
}
function ve(e, n) {
	let r = n.display, i = e.ui.fullDates.has(n.uid), a = L(e, n) ? t : se(e, n);
	return o`${r && n.createdAt ? o`<a ${y(e.control("link", {
		identify: "comments__permalink",
		attributes: {
			href: n.permalink ?? `#${D(n)}`,
			"data-comments-action": "toggle-date",
			"data-comments-comment-uid": n.uid
		}
	}))}><time class="comments__date" datetime=${n.createdAt} title=${r.fullDate}>${i ? r.fullDate : r.timeAgo || r.fullDate}</time></a>` : t}${a}${n.editedAt ? o`<span class="comments__edited" title=${n.editedAt}>${e.labels.edited}</span>` : t}`;
}
function ye(e, t) {
	return e && t && t > e ? "comments__gif comments__gif--portrait" : "comments__gif";
}
function be(e, n) {
	let r = n.content;
	if (!r) return o`<div class="comments__content" dir="auto"></div>`;
	let i = r.text.split("\n");
	return o`<div class="comments__content" dir="auto">${r.format === "rich" && r.html ? o`<div class="comments__content-rich" ${x(r.html)}></div>` : r.text ? o`<p class="comments__content-plain">${i.map((e, t) => t ? o`<br>${e}` : e)}</p>` : t}${r.gifUrl ? o`<img class=${ye(r.gifWidth, r.gifHeight)} src=${r.gifUrl} alt=${e.labels.gif} loading="lazy" width=${r.gifWidth ?? t} height=${r.gifHeight ?? t}>` : t}</div>`;
}
function xe(e, t) {
	return ce(e, t, F(e, t));
}
function W(e, n) {
	return n.state !== "approved" && n.state !== "reported" ? t : e.part("actions", F(e, n), () => Se(e, n));
}
function Se(e, n) {
	let { labels: r } = e, i = M(e, n.uid), a = e.state.conversation?.interactionMode ?? "like", s = a === "none" ? null : n.interaction, c = e.ui.pendingVotes.has(n.uid) ? e.ui.pendingVotes.get(n.uid) : void 0, l = c === void 0 ? i?.vote ?? null : c, u = i?.vote ?? null, d = (e) => c === void 0 ? 0 : (l === e) - +(u === e), f = (s?.upvotes ?? 0) + d("upvote"), p = (s?.downvotes ?? 0) + d("downvote"), m = a === "updown", h = fe(e, n), g = n.state === "approved" && !h?.openReportCount ? h?.actions ?? [] : [], _ = !!n.permalink, v = !(!i?.canReport || i.reported), b = _ || i?.canEdit || i?.canDelete || v || g.length > 0, x = D(n);
	if (!s && !i?.canReply && !b) return t;
	let S = s ? o`<div class="comments__interactions" data-comments-interaction-mode=${a}>${i?.canVote ? o`<button ${y(e.control("quietButton", {
		identify: "comments__vote comments__vote--up",
		attributes: {
			type: "button",
			"data-comments-action": "vote",
			"data-comments-vote": "upvote",
			"data-comments-comment-uid": n.uid,
			"aria-pressed": l === "upvote" ? "true" : "false"
		}
	}))}>${A(m ? "up" : "heart")}<span class=${m || f > 0 ? "comments__visually-hidden" : "comments__vote-label"}>${m ? r.upvote : r.like}</span>${!m && f > 0 ? o`<span class="comments__vote-count">${f}</span>` : t}</button>` : !m && f > 0 ? o`<span class="comments__vote-total">${A("heart")}<span class="comments__visually-hidden">${r.likes}: </span><span class="comments__vote-count">${f}</span></span>` : t}${m ? o`<span class="comments__score"><span class="comments__visually-hidden">${r.score}: </span>${f - p}</span>` : t}${m && i?.canDownvote ? o`<button ${y(e.control("quietButton", {
		identify: "comments__vote comments__vote--down",
		attributes: {
			type: "button",
			"data-comments-action": "vote",
			"data-comments-vote": "downvote",
			"data-comments-comment-uid": n.uid,
			"aria-pressed": l === "downvote" ? "true" : "false"
		}
	}))}>${A("down")}<span class="comments__visually-hidden">${r.downvote}</span></button>` : t}</div>` : t, C = i?.canReply ? o`<button ${y(e.control("quietButton", {
		identify: "comments__action",
		attributes: {
			type: "button",
			"data-comments-action": "reply",
			"data-comments-comment-uid": n.uid,
			"aria-busy": e.ui.busy.has(`reply:${n.uid}`) ? "true" : null
		}
	}))}>${A("reply")}${r.reply}</button>` : t, w = (e, r, i = {}, a = !1) => o`<button type="button" class=${`comments__menu-item${a ? " comments__menu-item--danger" : ""}`} data-comments-action=${e} data-comments-comment-uid=${n.uid} data-comments-moderation-action=${i.moderation ?? t}>${r}</button>`, ee = e.mode === "string" || e.ui.menus.has(n.uid), T = b ? o`<details class="comments__menu"><summary ${y(e.control("quietButton", {
		identify: "comments__action comments__menu-toggle",
		attributes: {
			"data-comments-comment-uid": n.uid,
			"aria-busy": e.ui.busy.has(`menu:${n.uid}`) ? "true" : null
		}
	}))}>${A("more")}<span class="comments__visually-hidden">${r.moreActions}</span></summary>${ee ? o`<div class="comments__menu-items">${_ ? w("copy-link", r.copyLink) : t}${i?.canEdit ? w("edit", r.edit) : t}${v ? w("report", r.report) : t}${i?.canDelete ? w("delete", r.delete, {}, !0) : t}${g.length ? o`<div class="comments__menu-group" role="group" aria-labelledby=${`${x}-moderator-actions`}><span class="comments__menu-heading" id=${`${x}-moderator-actions`}>${r.moderator}</span>${g.map((t) => w("moderate", G(e, t, !0), { moderation: t }, !0))}</div>` : t}</div>` : t}</details>` : t;
	return o`<div class="comments__actions">${S}${C}${T}</div>`;
}
function G(e, t, n = !1) {
	let { labels: r } = e;
	return n && t === "spam" ? r.markSpam : r[ue[t] ?? t] ?? t;
}
function K(e, n) {
	let r = fe(e, n), i = n.state === "reported" || !!(n.state === "approved" && r?.openReportCount);
	return !de.has(n.state) && !i ? t : e.part("review", {
		...F(e, n),
		moderation: r
	}, () => Ce(e, n, r, i));
}
function Ce(e, n, r, i) {
	let { labels: a } = e, s = i ? "flag" : n.state === "spam" ? "spam" : n.state === "removed" ? "removed" : "clock", c = r?.reviewLabel ?? (n.state === "removed" ? a.removedNote : n.state === "pending" ? a.awaitingApproval : ""), l = r?.actions ?? [];
	return o`<div class="comments__review" data-comments-state-badge=${n.state}><p class="comments__review-label">${A(s)}${c}</p>${l.length ? o`<div class="comments__moderation-actions" role="group" aria-label=${a.moderation}>${l.map((t, r) => o`<button ${y(e.control(r === 0 ? "primaryButton" : "secondaryButton", { attributes: {
		type: "button",
		"data-comments-action": "moderate",
		"data-comments-moderation-action": t,
		"data-comments-comment-uid": n.uid,
		"aria-busy": e.ui.busy.has(`moderate:${n.uid}:${t}`) ? "true" : null
	} }))}>${G(e, t)}</button>`)}</div>` : t}</div>`;
}
function q(e, n) {
	let r = e.state.replies[n.uid], i = E(e.state, n.uid), a = r?.count ?? n.replies.count, s = !!(r ? r.next : n.replies.next) && a > i.filter((e) => e.state === "approved" || e.state === "reported").length;
	if (!i.length && !s) return t;
	let l = `${D(n)}-replies`, u = e.ui.collapsed.has(n.uid), d = Math.max(0, a - i.filter((e) => e.state === "approved" || e.state === "reported").length);
	return o`<div class="comments__replies" data-comments-replies data-comments-root-uid=${n.uid}>${i.length ? o`<button ${y(e.control("quietButton", {
		identify: "comments__toggle-replies",
		attributes: {
			type: "button",
			"data-comments-action": "toggle-replies",
			"data-comments-comment-uid": n.uid,
			"aria-expanded": u ? "false" : "true",
			"aria-controls": l
		}
	}))}>${u ? e.labels.showReplies : e.labels.hideReplies}</button><ol class="comments__replies-list" id=${l} role="list" ?hidden=${u}>${c(i, (e) => e.uid, (t) => me(e, t))}</ol>` : t}${s ? o`<div class="comments__load-more" ?hidden=${u}><button ${y(e.control("secondaryButton", {
		identify: "comments__load-more-button",
		attributes: {
			type: "button",
			"data-comments-action": "load-replies",
			"data-comments-comment-uid": n.uid,
			"aria-controls": i.length ? l : null,
			"aria-busy": e.ui.busy.has(`replies:${n.uid}`) || r?.loading ? "true" : null
		}
	}))}>${e.labels.loadMoreReplies}${d ? ` (${d})` : ""}</button></div>` : t}</div>`;
}
// packages/comments-browser/src/parts/form.ts
function we(e) {
	return `comments__editor comments__editor--${e}`;
}
var J = (e, t) => `${e.idPrefix}-${t.replace(/[^\w-]/g, "-")}-error`, Te = /* @__PURE__ */ new Set();
function Y(e, n) {
	let r = e.errors[n];
	return r ? o`<p class="comments__field-error" id=${J(e, n)}>${r}</p>` : t;
}
function X(e, t) {
	return e.errors[t] ? {
		"aria-invalid": "true",
		"aria-describedby": J(e, t)
	} : {
		"aria-invalid": null,
		"aria-describedby": null
	};
}
function Ee(e) {
	let { limit: t, length: n, labels: r } = e;
	if (!(t > 0) || n < t * .8) return o`<span class="comments__frame-count"></span>`;
	let i = t - n, a = 2 * Math.PI * 8, s = Math.min(n / t, 1), c = i < 0 ? " comments__frame-count--bad" : i <= t * .1 ? " comments__frame-count--warn" : "";
	return o`<span class=${`comments__frame-count${c}`} title=${T(r.characterCount, {
		count: n.toLocaleString(),
		limit: t.toLocaleString()
	})}><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><circle class="comments__ring-bg" cx="10" cy="10" r=${8}></circle><circle class="comments__ring-fg" cx="10" cy="10" r=${8} stroke-dasharray=${a} stroke-dashoffset=${a * (1 - s)}></circle></svg><span>${i}</span><span class="comments__visually-hidden">${i < 0 ? T(r.tooLong, { count: -i }) : T(r.charactersLeft, { count: i })}</span></span>`;
}
function De(e) {
	let t = e.limit > 0 ? e.length - e.limit : 0;
	return t > 0 ? o`<span class="comments__frame-status comments__frame-status--bad">${j("alert")}<span class="comments__frame-status-text">${T(e.labels.tooLong, { count: t })}</span></span>` : e.draftState ? o`<span class=${`comments__frame-status${e.draftState === "faded" ? " comments__frame-status--faded" : ""}`}>${j("check")}<span class="comments__frame-status-text">${e.labels.draftSaved}</span></span>` : o`<span class="comments__frame-status"></span>`;
}
function Oe(e) {
	let { labels: n, viewer: r } = e, i = r && !r.isGuest ? r : null, a = `${e.idPrefix}-comment-body`, s = `${e.idPrefix}-comment-label`, c = !!(e.replyTo || i || e.guest.show), l = e.fields.some((e) => Z(e) === "above"), u = e.fields.some((e) => Z(e) === "below");
	return o`${e.errors.form ? o`<p class="comments__form-error" tabindex="-1">${e.errors.form}</p>` : t}<div class="comments__editor-row">${e.avatars === !1 ? t : i ? ke(e, i) : o`<span ${y(e.control("avatar", {
		style: "comments__avatar--guest",
		attributes: { "aria-hidden": "true" }
	}))}>${A("user")}</span>`}<div class=${`comments__frame${e.fields.length ? " comments__frame--fields" : ""}`} ?data-comments-collapsed=${e.collapsed}>${c ? o`<div class="comments__frame-head">${e.replyTo ? Ae(e) : t}${i ? e.slots.postingAs : t}${e.guest.show ? e.slots.guestFields : t}</div>` : t}${l ? e.slots.fieldsAbove : t}<div class="comments__frame-box"><label class="comments__visually-hidden" id=${s} for=${a}>${n.yourComment}</label><textarea ${y(e.control("textarea", {
		identify: "comments__textarea-fallback",
		attributes: {
			id: a,
			name: "content",
			rows: 1,
			placeholder: e.placeholder,
			required: !0,
			"data-comments-body": !0,
			...X(e, "content")
		}
	}))}></textarea>${e.notice ? o`<div class="comments__frame-notice" role="status">${j("info")}<span class="comments__frame-notice-text">${e.notice.message}</span>${e.notice.action ? o`<button type="button" class="comments__frame-link" data-comments-action="discard-draft">${e.notice.action}</button>` : t}</div>` : o`<div class="comments__frame-notice" role="status" hidden></div>`}</div>${e.slots.editor}${e.gifs ? e.slots.gifArea : t}${u ? e.slots.formFields : t}<div class="comments__frame-foot"><span class="comments__frame-meta">${De(e)}${Ee(e)}</span>${e.gifs ? e.slots.gifButton : t}${e.slots.cancel}<button ${y(e.control("primaryButton", {
		identify: "comments__submit",
		attributes: {
			type: "submit",
			"aria-label": e.submitLabel,
			"aria-disabled": e.posting || e.blocker ? "true" : "false",
			"aria-description": !e.posting && e.blocker ? e.blocker : null,
			"data-comments-posting": e.posting ? !0 : null
		}
	}))}><span class="comments__submit-label">${e.posting ? n.posting : e.submitLabel}</span><kbd class="comments__submit-keys">${e.shortcut}</kbd>${j("up", "comments__submit-icon")}</button></div></div>${Y(e, "content")}</div>`;
}
function ke(e, t) {
	return V(e, t, 36);
}
function Ae(e) {
	let [t, ...n] = e.labels.replyingTo.split("{name}");
	return o`<p class="comments__frame-reply">${j("reply")}<span class="comments__frame-reply-text">${t}<b>${e.replyTo}</b>${n.join("{name}")}</span><button type="button" class="comments__frame-reply-cancel" data-comments-action="cancel-editor" aria-label=${e.labels.cancelReply}>${j("close")}</button></p>`;
}
function je(e) {
	let n = e.viewer;
	return !n || n.isGuest ? t : e.part("postingAs", { viewer: n }, () => {
		let [t, ...r] = e.labels.postingAs.split("{name}");
		return o`<span>${t}<b>${n.name}</b>${r.join("{name}")}</span>${n.badges.map(U)}`;
	});
}
function Me(e) {
	let { labels: n, guest: r } = e, i = (t) => `${e.idPrefix}-guest-${t}`, a = r.required ? o` <span class="comments__field-required" aria-hidden="true">*</span>` : t;
	return o`<legend class="comments__visually-hidden">${n.yourDetails}</legend><label class="comments__field" for=${i("name")}><span class="comments__field-label">${n.yourName}${a}</span><input ${y(e.control("input", { attributes: {
		id: i("name"),
		name: "guest[name]",
		type: "text",
		autocomplete: "name",
		value: r.name,
		required: r.required,
		...X(e, "guest.name")
	} }))}>${Y(e, "guest.name")}</label><label class="comments__field" for=${i("email")}><span class="comments__field-label">${n.yourEmail}${a}<span class="comments__field-hint">${n.notShownPublicly}</span></span><input ${y(e.control("input", { attributes: {
		id: i("email"),
		name: "guest[email]",
		type: "email",
		autocomplete: "email",
		value: r.email,
		required: r.required,
		...X(e, "guest.email")
	} }))}>${Y(e, "guest.email")}</label>${r.offerReplyEmails ? o`<label class="comments__field--checkbox" for=${i("notify")}><input ${y(e.control("checkbox", { attributes: {
		id: i("notify"),
		name: "guest[notifyReplies]",
		type: "checkbox",
		value: "1"
	} }))}><span>${n.replyEmails}</span></label>` : t}`;
}
function Z(e) {
	return e.formPosition === "above" ? "above" : "below";
}
function Ne(e, t = "below") {
	let n = e.fields.filter((e) => Z(e) === t);
	return e.part("formFields", {
		fields: n,
		position: t
	}, () => n.map((t) => o`<div class="comments__field-cell" data-comments-width=${t.width ?? 100}>${Pe(e, t)}</div>`));
}
function Pe(e, n) {
	let r = `${e.idPrefix}-${n.handle}`, i = `fields[${n.handle}]`, a = `fields.${n.handle}`, s = n.instructions ? `${r}-instructions` : null, c = [s, e.errors[a] ? J(e, a) : null].filter(Boolean).join(" ") || null, l = n.required ? o` <span class="comments__field-required" aria-hidden="true">*</span>` : t, u = n.instructions ? o`<p class="comments__field-instructions" id=${s}>${n.instructions}</p>` : t, d = `${r}-label`, f = {
		field: n,
		value: e.values[n.handle] ?? null,
		setValue: (t) => e.setValue(n.handle, t),
		error: e.errors[a] ?? null,
		id: r,
		name: i,
		labelId: d,
		describedBy: c,
		mode: e.mode,
		...n.type === "file" ? {
			files: e.fieldFiles?.(n.handle) ?? [],
			upload: (t) => e.upload?.(n.handle, t) ?? Promise.reject(/* @__PURE__ */ Error("Comments: uploads aren’t available here."))
		} : {}
	};
	return e.overridden(n.handle) ? o`<div class="comments__field comments__field-custom" data-comments-field=${n.handle} data-comments-field-override><span class="comments__field-label" id=${d}>${n.label}${l}</span>${u}${e.field("input", n.handle, f, () => Fe(e, n, r, c, l, u))}${Y(e, a)}</div>` : Fe(e, n, r, c, l, u);
}
function Fe(e, n, r, i, a, s) {
	let c = `fields[${n.handle}]`, l = `fields.${n.handle}`, u = `${r}-label`;
	if (n.type === "file") {
		let c = {
			inputId: r,
			labelId: u,
			describedBy: i,
			star: a,
			instructions: s
		};
		return e.fileTray ? e.fileTray(e, n, c) : o`<div class="comments__field comments__field-custom comments__field--files" data-comments-field-pending=${n.handle}><span class="comments__field-label" id=${u}>${n.label}${a}</span>${s}${e.trayFailed ? o`<p class="comments__field-error" role="alert">${e.labels.uploadsUnavailable ?? "Files can’t be added right now."}</p><button ${y(e.control("secondaryButton", {
			identify: "comments__upload-add",
			attributes: {
				type: "button",
				"data-comments-upload-action": "reload",
				"data-comments-upload-field": n.handle
			}
		}))}>${e.labels.tryAgain}</button>` : t}</div>`;
	}
	let d = {
		"aria-describedby": i,
		"aria-invalid": e.errors[l] ? "true" : null,
		"data-comments-field": n.handle
	}, f = Object.fromEntries(Object.entries(n.attributes ?? {}).filter(([e]) => /^(min|max|step|maxlength|minlength|pattern|placeholder)$/.test(e))), p = {
		"data-1p-ignore": !0,
		"data-lpignore": "true",
		"data-bwignore": !0,
		"data-form-type": "other"
	};
	if (n.type === "checkboxes" || n.type === "radio") {
		let u = n.type === "radio" ? "radio" : "checkbox";
		return o`<fieldset class="comments__field comments__field-custom" aria-describedby=${i ?? t}><legend class="comments__field-label">${n.label}${a}</legend>${s}${n.options.map((t, i) => o`<label class="comments__field--checkbox" for=${`${r}-${i + 1}`}><input ${y(e.control(u, { attributes: {
			id: `${r}-${i + 1}`,
			type: u,
			name: u === "checkbox" ? `${c}[]` : c,
			value: t.value,
			required: u === "radio" && n.required,
			"aria-invalid": i === 0 && e.errors[l] ? "true" : null,
			"aria-describedby": i === 0 && e.errors[l] ? J(e, l) : null,
			"data-comments-field": n.handle
		} }))}><span>${t.label}</span></label>`)}${Y(e, l)}</fieldset>`;
	}
	if (n.type === "toggle") return o`<div class="comments__field comments__field-custom"><label class="comments__field-label" for=${r}>${n.label}</label>${s}<input ${y(e.control("checkbox", {
		identify: "comments__switch",
		attributes: {
			id: r,
			type: "checkbox",
			role: "switch",
			name: c,
			value: "1",
			...d
		}
	}))}>${Y(e, l)}</div>`;
	if (n.type === "custom") return Te.has(n.handle) || (Te.add(n.handle), console.warn(`Comments: the "${n.handle}" field needs an input override to be drawn: Comments.configure({ fields: { ${n.handle}: { input } } }).`)), t;
	let m;
	return m = n.type === "select" ? o`<select ${y(e.control("select", { attributes: {
		id: r,
		name: n.multiple ? `${c}[]` : c,
		multiple: n.multiple,
		required: n.required,
		...d
	} }))}>${n.multiple ? t : o`<option value="">${e.labels.chooseOption}</option>`}${n.options.map((e) => o`<option value=${e.value}>${e.label}</option>`)}</select>` : n.type === "textarea" ? o`<textarea ${y(e.control("textarea", { attributes: {
		id: r,
		name: c,
		required: n.required,
		...f,
		...d,
		...p
	} }))}></textarea>` : o`<input ${y(e.control("input", { attributes: {
		id: r,
		name: c,
		type: n.type,
		required: n.required,
		...f,
		...d,
		...p
	} }))}>`, o`<div class="comments__field comments__field-custom"><label class="comments__field-label" for=${r}>${n.label}${a}</label>${s}${m}${Y(e, l)}</div>`;
}
// packages/comments-browser/src/parts/report.ts
var Ie = 200;
function Le(e) {
	let { labels: n, state: r, instanceId: i } = e, a = e.ui.reportUid ? r.comments[e.ui.reportUid] : void 0, s = (a?.content?.text ?? "").replace(/\s+/g, " ").trim(), c = s.length > Ie ? `${s.slice(0, Ie)}…` : s, l = r.conversation?.reportReasons ?? [];
	return o`<dialog class="comments__report-dialog" data-comments-report-dialog aria-labelledby=${`${i}-report-title`}><form class="comments__report-form" data-comments-form="report" novalidate>${e.ui.reportError ? o`<p class="comments__form-error" tabindex="-1">${e.ui.reportError}</p>` : t}<div class="comments__report-header"><h3 class="comments__report-title" id=${`${i}-report-title`}>${n.reportTitle}</h3><button type="button" class="comments__report-close" data-comments-action="cancel-report" aria-label=${n.close}>×</button></div><blockquote class="comments__report-quote" ?hidden=${!a}>${a?.author?.name ? o`<strong>${a.author.name}</strong>: ` : t}${c}</blockquote>${l.length ? o`<fieldset class="comments__report-fields"><legend class="comments__label">${n.reportReason}</legend>${l.map((n, r) => o`<label class="comments__report-reason" for=${`${i}-report-reason-${r + 1}`}><input ${y(e.control("radio", { attributes: {
		id: `${i}-report-reason-${r + 1}`,
		type: "radio",
		name: "reason",
		value: n.value,
		required: !0
	} }))}><span><span class="comments__report-reason-label">${n.label}</span>${n.description ? o`<span class="comments__report-reason-description">${n.description}</span>` : t}</span></label>`)}</fieldset>` : t}<label class="comments__field" for=${`${i}-report-details`}><span class="comments__label">${n.reportDetails}</span><span class="comments__field-instructions">${n.reportDetailsHint}</span><textarea ${y(e.control("textarea", { attributes: {
		id: `${i}-report-details`,
		name: "details",
		rows: 3,
		maxlength: 2e3
	} }))}></textarea></label><div class="comments__report-actions"><button ${y(e.control("secondaryButton", { attributes: {
		type: "button",
		"data-comments-action": "cancel-report"
	} }))}>${n.cancel}</button><button ${y(e.control("primaryButton", {
		identify: "comments__submit",
		attributes: {
			type: "submit",
			"aria-busy": e.ui.busy.has("report") ? "true" : null
		}
	}))}>${n.sendReport}</button></div></form></dialog>`;
}
// packages/comments-browser/src/parts/tray.ts
var Re = {
	addFiles: "Add files",
	addFile: "Add a file",
	removeFile: "Remove {name}",
	retryUpload: "Try {name} again",
	uploading: "Uploading…",
	uploaded: "{name} uploaded.",
	uploadFailed: "{name} didn’t upload.",
	fileRemoved: "{name} removed.",
	uploadLimits: "Up to {count} files, {size} each.",
	uploadLimit: "Up to {size}.",
	fileTypeNotAllowed: "{name} can’t be added here. Choose {types}.",
	fileTooLarge: "{name} is larger than {size}.",
	tooManyFiles: "You can add up to {count} files here."
};
function Q(e, t) {
	return e.labels[t] ?? Re[t] ?? t;
}
function ze(e) {
	return e >= 1048576 ? `${Number((e / 1048576).toFixed(1))} MB` : `${Math.max(1, Math.round(e / 1024))} KB`;
}
function Be(e, n, r) {
	let i = `fields.${n.handle}`, a = e.uploads[n.handle] ?? [], s = n.maxFiles ?? null, c = s !== null && a.filter((e) => e.state !== "failed").length >= s, l = n.multiple && s !== 1, u = `${r.inputId}-limits`, d = n.maxSize ? ze(n.maxSize) : null, f = d ? l && s ? T(Q(e, "uploadLimits"), {
		count: s,
		size: d
	}) : T(Q(e, "uploadLimit"), { size: d }) : "", p = [r.describedBy, f ? u : null].filter(Boolean).join(" ") || null;
	return o`<div class="comments__field comments__field-custom comments__field--files" role="group" aria-labelledby=${r.labelId} aria-describedby=${p ?? t} data-comments-field=${n.handle}><span class="comments__field-label" id=${r.labelId}>${n.label}${r.star}</span>${r.instructions}${a.length ? o`<ul class="comments__uploads" role="list">${a.map((t) => Ve(e, n, t))}</ul>` : t}<input class="comments__visually-hidden" id=${r.inputId} type="file" tabindex="-1" aria-hidden="true" ?multiple=${l} accept=${n.accept?.join(",") || t} data-comments-upload-input=${n.handle}><div class="comments__upload-row"><button ${y(e.control("secondaryButton", {
		identify: "comments__upload-add",
		attributes: {
			type: "button",
			"data-comments-upload-action": "add",
			"data-comments-upload-field": n.handle,
			"aria-describedby": p,
			"aria-invalid": e.errors[i] ? "true" : null,
			hidden: c
		}
	}))}>${A("attach")}${Q(e, l ? "addFiles" : "addFile")}</button>${f ? o`<span class="comments__upload-limit" id=${u}>${f}</span>` : t}</div>${Y(e, i)}</div>`;
}
function Ve(e, n, r) {
	let i = r.kind === "image" && r.preview, a = r.state === "uploading" ? Q(e, "uploading") : r.state === "failed" ? r.error ?? Q(e, "uploadFailed") : r.size ? ze(r.size) : "", s = {
		"data-comments-upload-field": n.handle,
		"data-comments-upload": r.key
	};
	return o`<li class=${`comments__upload comments__upload--${i ? "image" : "file"} comments__upload--${r.state}`} aria-busy=${r.state === "uploading" ? "true" : t}>${i ? o`<img class="comments__upload-thumb" src=${r.preview} alt="">` : o`<span class="comments__upload-icon">${A("file")}</span>`}<span class="comments__upload-text"><span class="comments__upload-name">${r.filename}</span><span class="comments__upload-meta">${a}</span></span>${r.state === "failed" ? o`<button type="button" class="comments__upload-retry" data-comments-upload-action="retry" ${y(s)} aria-label=${T(Q(e, "retryUpload"), { name: r.filename })}>${A("retry")}</button>` : t}<button type="button" class="comments__upload-remove" data-comments-upload-action="remove" ${y(s)} aria-label=${T(Q(e, "removeFile"), { name: r.filename })}>${A("close")}</button></li>`;
}
// packages/comments-browser/src/parts/gif.ts
function He(e) {
	return e.control("secondaryButton", {
		identify: "comments__gif-button verbb-editor__button",
		attributes: {
			type: "button",
			"aria-label": e.labels.gifAdd,
			"aria-expanded": e.gifOpen ? "true" : "false",
			"aria-controls": `${e.idPrefix}-gif-picker`,
			"data-comments-gif-action": "toggle",
			"data-comments-slot": "actions"
		}
	});
}
function Ue(e) {
	return o`<span aria-hidden="true">${e.labels.gif}</span>`;
}
function We(e) {
	let n = e.gif;
	return o`${n ? o`<figure class="comments__gif-preview"><img class="comments__gif-preview-image" src=${n.url} alt=${e.labels.gif} width=${n.width ?? t} height=${n.height ?? t}><button ${y(e.control("quietButton", {
		identify: "comments__gif-remove",
		attributes: {
			type: "button",
			"data-comments-gif-action": "remove"
		}
	}))}>${e.labels.gifRemove}</button></figure>` : t}${e.gifOpen ? e.gifPanel ?? o`<div class="comments__gif-picker" id=${`${e.idPrefix}-gif-picker`} aria-busy=${e.gifFailed ? "false" : "true"}><p class="comments__gif-status" role="status">${e.gifFailed ? e.labels.gifUnavailable : t}</p><p class="comments__gif-attribution">${e.labels.gifPoweredBy}</p></div>` : t}`;
}
// packages/comments-browser/src/theme-config.ts
var Ge = [
	"primaryButton",
	"secondaryButton",
	"quietButton",
	"input",
	"textarea",
	"select",
	"checkbox",
	"radio",
	"link",
	"avatar"
], Ke = {
	primaryButton: ["comments__button", "comments__button--primary"],
	secondaryButton: ["comments__button", "comments__button--secondary"],
	quietButton: ["comments__button", "comments__button--quiet"],
	input: ["comments__input"],
	textarea: ["comments__textarea"],
	select: ["comments__select"],
	checkbox: ["comments__checkbox"],
	radio: ["comments__radio"],
	link: ["comments__link"],
	avatar: ["comments__avatar"]
}, qe = /^(class|title|aria-label|data-(?!comments-)[a-z0-9]+(-[a-z0-9]+)*)$/, $ = (e) => (typeof e == "string" ? e.split(/\s+/) : [...e ?? []]).filter(Boolean);
function Je(e) {
	return Ge.includes(e);
}
function Ye(e, t = Ze) {
	let n = {};
	for (let r of e) if (r && typeof r == "object") for (let [e, i] of Object.entries(r)) {
		if (!Je(e)) {
			t(`Comments: themeConfig has no control called "${e}". It covers ${Ge.join(", ")}.`);
			continue;
		}
		if (!i || typeof i != "object" || Array.isArray(i)) {
			t(`Comments: themeConfig's ${e} must be an object, such as { attributes: { class: 'btn' } }.`);
			continue;
		}
		let r = i, a = n[e] ?? {}, o = { ...a.attributes }, s = r.attributes ?? {};
		if (typeof s != "object" || Array.isArray(s)) t(`Comments: themeConfig's ${e}.attributes must be an object.`);
		else {
			for (let [n, r] of Object.entries(s)) qe.test(n) ? !((e) => typeof e == "string" || typeof e == "number" || typeof e == "boolean" || e === null)(r) && !(Array.isArray(r) && r.every((e) => typeof e == "string")) ? t(`Comments: themeConfig's ${e} "${n}" must be text, a number, true or false, or a list of classes.`) : o[n] = r : t(`Comments: themeConfig can't set "${n}" on ${e}. It sets class, title, aria-label and data-* attributes.`);
			n[e] = {
				resetClass: r.resetClass ?? a.resetClass,
				attributes: o
			};
		}
	}
	return n;
}
var Xe = /* @__PURE__ */ new Set();
function Ze(e) {
	!Xe.has(e) && typeof console < "u" && (Xe.add(e), console.warn(e));
}
function Qe(e, t, n = {}) {
	let r = e[t] ?? {}, i = [...$(n.identify), ...r.resetClass ? [] : [...Ke[t], ...$(n.style)]], a = {};
	for (let [e, t] of Object.entries(r.attributes ?? {})) e === "class" ? i.push(...$(Array.isArray(t) ? t.map(String) : typeof t == "string" ? t : void 0)) : t === !0 ? a[e] = !0 : t !== !1 && t != null && (a[e] = Array.isArray(t) ? t.join(" ") : String(t));
	for (let [e, t] of Object.entries(n.attributes ?? {})) t === !0 ? a[e] = !0 : t === !1 || t == null ? delete a[e] : a[e] = String(t);
	let o = [...new Set(i)];
	return {
		...o.length ? { class: o.join(" ") } : {},
		...a,
		"data-comments-control": t
	};
}
// packages/comments-browser/src/string-view.ts
function $e(e) {
	let { state: n } = e, r = ee(n.labels, e.labels), i = Ye([e.themeConfig]), a = e.instanceId ?? `comments-${n.conversation?.ownerId ?? 0}-${n.conversation?.siteId ?? 1}`, s = {
		...te(),
		...e.ui
	}, c = (e, t) => Qe(i, e, t), l = (e, n, i, a, s) => {
		if (!e) return a();
		let l = {
			labels: r,
			actions: {},
			...i,
			controlAttributes: c,
			html: o,
			defaultRender: a
		};
		if (typeof e == "string") return /^[a-z][a-z0-9._-]*-[a-z0-9._-]*$/.test(e) ? {
			_$litType$: 1,
			strings: [`<${e}></${e}>`],
			values: []
		} : t;
		if (typeof e == "object") {
			let e = {
				_$litType$: 1,
				strings: ["<verbb-comments-host></verbb-comments-host>"],
				values: []
			};
			return s ? s.around(e) : e;
		}
		try {
			return e(l);
		} catch (e) {
			return console.error(`Comments: the ${n} override failed, so the default was drawn.`, e), a();
		}
	}, u = {
		mode: "string",
		instanceId: a,
		phase: e.phase ?? (n.status === "ready" ? "ready" : "waiting"),
		count: e.count ?? n.conversation?.commentCount ?? 0,
		state: n,
		ui: s,
		labels: r,
		control: c,
		part: (t, n, r, i) => l(e.parts?.[t], `"${t}" part`, n, r, i),
		field: (t, n, r, i) => l(e.fields?.[n]?.[t], `"${n}" field's ${t}`, r, i),
		fieldInputs: new Set(Object.keys(e.fields ?? {}).filter((t) => e.fields?.[t]?.input)),
		reportDialog: Le,
		form: (e) => {
			let [t, n] = e.split(":"), r = et(u, t, n ?? null);
			return o`<form class=${we(t)} data-comments-form=${t} ?data-comments-collapsible=${r.collapsible} novalidate>${Oe(r)}</form>`;
		},
		actions: () => ({}),
		formFields: (e) => (e === "reply" ? n.viewerEditor?.fields.reply : n.viewerEditor?.fields.comment) ?? []
	};
	return u;
}
function et(e, t, n) {
	let { state: r, labels: i } = e, a = r.viewer, s = r.viewerEditor?.guest, c = (n ? r.comments[n]?.author?.name : null) ?? i.guest, l = !(!a || a.isGuest), u = {
		mode: t,
		idPrefix: `${e.instanceId}-${t}-${n ?? "new"}`,
		labels: i,
		control: e.control,
		part: (t, n, r) => e.part(t, n, r),
		field: (t, n, r, i) => e.field(t, n, r, i),
		overridden: (t) => !!e.fieldInputs?.has(t),
		viewer: a,
		guest: {
			show: !!(a?.isGuest && s?.show !== !1 && t !== "edit"),
			required: s?.required ?? !0,
			name: s?.name ?? null,
			email: s?.email ?? null,
			offerReplyEmails: s?.offerReplyEmails ?? !1
		},
		fields: e.formFields(t),
		values: {},
		setValue: () => void 0,
		uploads: {},
		fileTray: Be,
		avatars: r.conversation?.showAvatars !== !1,
		collapsible: t === "create" && l,
		collapsed: t === "create" && l,
		replyTo: t === "reply" ? c : null,
		placeholder: t === "edit" ? i.editPlaceholder : t === "reply" ? T(i.replyTo, { name: c.split(/\s+/)[0] ?? c }) : i.editorPlaceholder,
		submitLabel: t === "edit" ? i.save : t === "reply" ? i.reply : i.post,
		limit: r.editor?.maxLength ?? 0,
		length: 0,
		draftState: null,
		notice: null,
		posting: !1,
		blocker: i.writeSomethingFirst,
		errors: {},
		editorMounted: !1,
		shortcut: "⌘↵",
		gifs: !!r.viewerEditor?.gifs,
		gif: null,
		gifOpen: !1,
		slots: {
			postingAs: "",
			guestFields: "",
			fieldsAbove: "",
			formFields: "",
			cancel: "",
			editor: "",
			gifButton: "",
			gifArea: ""
		}
	};
	return u.slots = {
		postingAs: o`<p class="comments__posting-as" data-comments-slot="header">${je(u)}</p>`,
		guestFields: o`<fieldset class="comments__guest-fields" data-comments-slot="header">${Me(u)}</fieldset>`,
		fieldsAbove: o`<div class="comments__editor-fields comments__editor-fields--above" data-comments-slot="above">${Ne(u, "above")}</div>`,
		formFields: o`<div class="comments__editor-fields" data-comments-slot="footer">${Ne(u, "below")}</div>`,
		cancel: o`<button ${tt(u)}>${i.cancel}</button>`,
		editor: "",
		gifButton: o`<button ${y(He(u))}>${Ue(u)}</button>`,
		gifArea: o`<div class="comments__gif-area" data-comments-slot="footer">${We(u)}</div>`
	}, u;
}
function tt(e) {
	return y(e.control("secondaryButton", {
		identify: "comments__cancel verbb-editor__button",
		attributes: {
			type: "button",
			"data-comments-action": "cancel-editor",
			"data-comments-slot": "actions"
		}
	}));
}
// src/web/assets/cp/src/comment-fields.ts
var nt = "Has anyone styled the thread with Tailwind? The web starter does it with the stable class names.";
function rt(e, t) {
	let n = e.options.map((e) => e.label);
	switch (e.type) {
		case "number": return {
			value: 4,
			text: "4"
		};
		case "textarea": return {
			value: "A longer answer, a sentence or two about how it went.",
			text: "A longer answer, a sentence or two about how it went."
		};
		case "email": return {
			value: "alex@example.com",
			text: "alex@example.com"
		};
		case "url": return {
			value: "https://example.com/",
			text: "example.com"
		};
		case "date": return {
			value: "2026-11-11",
			text: new Date(2026, 10, 11).toLocaleDateString(void 0, { dateStyle: "medium" })
		};
		case "select": return e.multiple ? {
			value: [],
			text: n.slice(0, 2).join(", ")
		} : {
			value: "",
			text: n[0] ?? "An option"
		};
		case "radio": return {
			value: "",
			text: n[0] ?? "An option"
		};
		case "checkboxes": return {
			value: [],
			text: n.slice(0, 2).join(", ") || "Two options"
		};
		case "toggle": return {
			value: !0,
			text: "Yes"
		};
		case "file": return {
			value: [{
				url: t,
				filename: "photo.jpg",
				kind: "image",
				width: 64,
				height: 48
			}],
			text: "photo.jpg"
		};
		default: return {
			value: "Wellington",
			text: "Wellington"
		};
	}
}
function it(e, t, n) {
	let r = {
		isGuest: !1,
		hasSession: !0,
		name: "Alex Morgan",
		initials: "AM",
		avatarUrl: null,
		avatarTone: 5,
		badges: [],
		canComment: !0,
		canVote: !0,
		canReport: !0,
		isModerator: !1,
		blocked: !1,
		loginUrl: null,
		follow: {
			available: !1,
			following: !1,
			needsEmail: !1
		}
	};
	return {
		status: "ready",
		error: null,
		sort: "newest",
		threadVersion: null,
		conversation: null,
		editor: null,
		labels: n,
		locale: null,
		comments: t ? { [t.uid]: t } : {},
		roots: t ? [t.uid] : [],
		next: null,
		loadingMore: !1,
		replies: {},
		viewer: r,
		viewerEditor: {
			draftKey: "preview",
			guest: {
				show: !1,
				required: !1,
				name: null,
				email: null,
				offerReplyEmails: !1
			},
			fields: {
				comment: e,
				reply: e
			},
			protection: null,
			richEditor: null
		},
		permissions: t ? { [t.uid]: {
			canReply: !0,
			canEdit: !1,
			canDelete: !1,
			canVote: !0,
			canDownvote: !0,
			canReport: !0,
			vote: null,
			reported: !1,
			isAuthor: !1,
			moderation: null,
			custom: {}
		} } : {},
		moderation: null,
		busy: {},
		unavailable: []
	};
}
function at(e) {
	let t = e.attachShadow({ mode: "open" }), n = new CSSStyleSheet();
	return n.replaceSync(`${l}\n:host { display: block; }\n.preview { pointer-events: none; }`), t.adoptedStyleSheets = [n], t;
}
function ot(t) {
	let n = JSON.parse(t.querySelector("[data-comments-field-rows]")?.textContent ?? "[]"), r = JSON.parse(t.querySelector("[data-comments-field-labels]")?.textContent ?? "{}"), i = t.dataset.photoUrl ?? "", a = t.querySelector("[data-comments-form-preview]"), s = t.querySelector("[data-comments-card-preview]");
	if (!a || !s) return;
	let c = at(a), l = at(s), u = t.closest("form")?.querySelector("input[name=\"settings[fieldLayout]\"]"), d = () => {
		let e = [], t = {}, n = /* @__PURE__ */ new Set(), r = !1;
		try {
			let i = JSON.parse(u?.value ?? "{}");
			for (let a of (i.tabs ?? []).flatMap((e) => e.elements ?? [])) a.type === "verbb\\comments\\fieldlayoutelements\\CommentsField" ? r = !0 : a.uid && (e.push(a.uid), t[a.uid] = a.width ?? 100, r || n.add(a.uid));
		} catch {}
		return r || n.clear(), {
			order: e,
			width: t,
			above: n
		};
	}, f = (e) => t.querySelector(`[data-comments-field-ask="${e}"]`)?.classList.contains("on") ?? !1, p = (e) => t.querySelector(`[data-comments-field-show="${e}"]`)?.value ?? "none", m = () => {
		let a = d(), s = (e) => a.order.includes(e) ? a.order.indexOf(e) : 2 ** 53 - 1, u = n.filter((e) => e.description && f(e.uid) && (a.order.length === 0 || a.order.includes(e.uid))).sort((e, t) => s(e.uid) - s(t.uid)).map((e) => ({
			...e.description,
			...a.order.length ? {
				width: [
					25,
					50,
					75
				].includes(a.width[e.uid] ?? 100) ? a.width[e.uid] : 100,
				formPosition: a.above.has(e.uid) ? "above" : "below"
			} : {}
		})), m = new Map(u.map((e) => [e, n.find((t) => t.description?.handle === e.handle).uid])), h = et($e({
			state: it(u, null, r),
			instanceId: "preview"
		}), "create", null);
		h.collapsed = !1, e(o`<section class="comments preview" data-comments-root data-comments-theme="light"><form class=${we("create")} novalidate>${Oe(h)}</form></section>`, c);
		let g = [];
		for (let e of u) {
			let t = p(m.get(e));
			(t === "inline" || t === "details") && g.push({
				handle: e.handle,
				label: e.label,
				type: e.type,
				placement: t,
				...rt(e, i)
			});
		}
		let _ = {
			uid: "preview",
			parentUid: null,
			depth: 0,
			state: "approved",
			content: {
				format: "plain",
				html: null,
				text: nt,
				gifUrl: null
			},
			author: {
				name: "Alex Morgan",
				initials: "AM",
				avatarUrl: null,
				avatarTone: 5,
				isGuest: !1,
				badges: []
			},
			createdAt: (/* @__PURE__ */ new Date(Date.now() - 2592e5)).toISOString(),
			editedAt: null,
			display: {
				timeAgo: "3 days ago",
				fullDate: ""
			},
			permalink: "#",
			fields: g,
			interaction: {
				upvotes: 2,
				downvotes: 0
			},
			custom: {},
			replies: {
				count: 0,
				items: [],
				next: null
			}
		}, v = $e({
			state: it(u, _, r),
			instanceId: "preview"
		});
		e(o`<section class="comments preview" data-comments-root data-comments-theme="light"><ol class="comments__list">${me(v, _)}</ol></section>`, l);
		let y = [];
		for (let e of n) {
			let n = t.querySelector(`[data-comments-shown-row="${e.uid}"]`), r = f(e.uid);
			n && (n.hidden = !r), r && e.description && p(e.uid) === "none" && y.push(e.label), n?.querySelector("[data-comments-email-warning]")?.toggleAttribute("hidden", p(e.uid) === "none");
		}
		let b = t.querySelector("[data-comments-card-hidden]");
		b && (b.textContent = y.length ? Craft.t("comments", "Not shown: {fields}.", { fields: y.join(", ") }) : ""), t.querySelector("[data-comments-shown-empty]")?.toggleAttribute("hidden", n.some((e) => e.description && f(e.uid)));
	};
	window.jQuery?.(t).on("change", m), t.addEventListener("change", m);
	let h = t.closest("form")?.querySelector(".layoutdesigner"), g = 0;
	h && new MutationObserver(() => {
		cancelAnimationFrame(g), g = requestAnimationFrame(m);
	}).observe(h, {
		subtree: !0,
		childList: !0,
		attributes: !0
	}), m();
}
document.querySelectorAll("[data-comments-field-settings]").forEach(ot);

//# sourceMappingURL=comment-fields.js.map