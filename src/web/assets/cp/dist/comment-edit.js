import { A as e, C as t, D as n, E as r, M as i, S as a, T as o, a as s, i as c, n as l, o as u, p as d, r as f, t as p, w as m, x as h } from "./chunks/pk-copy-button-Dfji6-PV.js";
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/pk-alert-CgKRyPJu.js
var g = class extends Event {
	constructor() {
		super("pk-dismiss", {
			bubbles: !0,
			cancelable: !0,
			composed: !0
		});
	}
}, _ = i`
    @layer pk-component {
        :host {
            display: block;
            width: 100%;
            color: var(--pk-alert-color);
            font-family: var(--pk-font-family);
            font-size: var(--_pk-alert-font-size);
            line-height: var(--pk-line-height);
            --pk-alert-accent: var(--pk-color-sky-600);
            --pk-alert-background: var(--pk-color-sky-50);
            --pk-alert-border: var(--pk-color-sky-300);
            --pk-alert-color: var(--pk-color-sky-800);
            --_pk-alert-font-size: var(--pk-font-size-sm);
            --_pk-alert-padding: 0.625rem 0.75rem;
            --_pk-alert-radius: var(--pk-radius-md);
            --_pk-alert-gap: 0.5rem;
            --_pk-alert-icon-size: 1.125rem;
            --_pk-alert-title-size: var(--pk-font-size-base);
            --_pk-alert-dismiss-size: 1.5rem;
            --_pk-alert-dismiss-padding: 0.375rem;
            --_pk-alert-dismiss-margin: -0.125rem -0.1875rem -0.125rem 0;
            --_pk-alert-section-margin-top: 0.625rem;
            --_pk-alert-details-padding-top: 0.5rem;
            --_pk-alert-details-content-margin-top: 0.625rem;
            --_pk-alert-details-pre-padding: 0.625rem;
            --_pk-alert-details-pre-radius: var(--pk-radius-md);
            --_pk-alert-details-pre-font-size: 0.6875rem;
            --_pk-alert-copy-button-size: var(--pk-btn-height-xs);
            --_pk-alert-copy-icon-size: var(--pk-btn-icon-size-xs);
            --_pk-alert-copy-inset: 0.375rem;
            --_pk-alert-copy-radius: var(--pk-radius-md);
            --_pk-alert-copy-status-size: 0.6875rem;
            --_pk-alert-action-height: var(--pk-btn-height-sm);
            --_pk-alert-action-font: var(--pk-btn-font-sm);
            --_pk-alert-action-padding-inline: var(--pk-btn-padding-inline-sm);
            --_pk-alert-action-icon-size: var(--pk-btn-icon-size-sm);
            --_pk-alert-action-icon-gap: var(--pk-btn-icon-gap-sm);
            --_pk-alert-action-caret-size: var(--pk-btn-caret-size-sm);
            --_pk-alert-action-radius: var(--pk-btn-radius-sm);
        }

        :host([hidden]) {
            display: none;
        }

        :host([size='sm']) {
            --_pk-alert-font-size: var(--pk-btn-font-xs);
            --_pk-alert-padding: 0.5rem 0.625rem;
            --_pk-alert-radius: var(--pk-radius-sm);
            --_pk-alert-gap: 0.4375rem;
            --_pk-alert-icon-size: 1rem;
            --_pk-alert-title-size: var(--pk-font-size-sm);
            --_pk-alert-dismiss-size: 1.375rem;
            --_pk-alert-dismiss-padding: 0.3125rem;
            --_pk-alert-dismiss-margin: -0.125rem -0.125rem -0.125rem 0;
            --_pk-alert-section-margin-top: 0.5rem;
            --_pk-alert-details-padding-top: 0.375rem;
            --_pk-alert-details-content-margin-top: 0.5rem;
            --_pk-alert-details-pre-padding: 0.5rem;
            --_pk-alert-details-pre-radius: var(--pk-radius-sm);
            --_pk-alert-details-pre-font-size: 0.6875rem;
            --_pk-alert-copy-button-size: var(--pk-btn-height-xxs);
            --_pk-alert-copy-icon-size: var(--pk-btn-icon-size-xxs);
            --_pk-alert-copy-inset: 0.3125rem;
            --_pk-alert-copy-radius: var(--pk-radius-sm);
            --_pk-alert-copy-status-size: 0.6875rem;
            --_pk-alert-action-height: var(--pk-btn-height-xs);
            --_pk-alert-action-font: var(--pk-btn-font-xs);
            --_pk-alert-action-padding-inline: var(--pk-btn-padding-inline-xs);
            --_pk-alert-action-icon-size: var(--pk-btn-icon-size-xs);
            --_pk-alert-action-icon-gap: var(--pk-btn-icon-gap-xs);
            --_pk-alert-action-caret-size: var(--pk-btn-caret-size-xs);
            --_pk-alert-action-radius: var(--pk-btn-radius-xs);
        }

        :host([size='lg']) {
            --_pk-alert-font-size: var(--pk-font-size-base);
            --_pk-alert-padding: 0.875rem 1rem;
            --_pk-alert-radius: var(--pk-radius-lg);
            --_pk-alert-gap: 0.75rem;
            --_pk-alert-icon-size: 1.375rem;
            --_pk-alert-title-size: 0.9375rem;
            --_pk-alert-dismiss-size: 1.75rem;
            --_pk-alert-dismiss-padding: 0.4375rem;
            --_pk-alert-dismiss-margin: -0.1875rem -0.25rem -0.1875rem 0;
            --_pk-alert-section-margin-top: 0.75rem;
            --_pk-alert-details-padding-top: 0.625rem;
            --_pk-alert-details-content-margin-top: 0.75rem;
            --_pk-alert-details-pre-padding: 0.75rem;
            --_pk-alert-details-pre-radius: var(--pk-radius-lg);
            --_pk-alert-details-pre-font-size: 0.75rem;
            --_pk-alert-copy-button-size: var(--pk-btn-height-sm);
            --_pk-alert-copy-icon-size: var(--pk-btn-icon-size-sm);
            --_pk-alert-copy-inset: 0.5rem;
            --_pk-alert-copy-radius: var(--pk-radius-lg);
            --_pk-alert-copy-status-size: 0.75rem;
            --_pk-alert-action-height: 2.125rem;
            --_pk-alert-action-font: var(--pk-font-size-base);
            --_pk-alert-action-padding-inline: 9px;
            --_pk-alert-action-icon-size: 14px;
            --_pk-alert-action-icon-gap: 6px;
            --_pk-alert-action-caret-size: 12px;
            --_pk-alert-action-radius: var(--pk-radius-lg);
        }

        .alert {
            box-sizing: border-box;
            width: 100%;
            padding: var(--pk-alert-padding, var(--_pk-alert-padding));
            border: 1px solid var(--pk-alert-border);
            border-inline-start: 4px solid var(--pk-alert-accent);
            border-radius: var(--pk-alert-radius, var(--_pk-alert-radius));
            background: var(--pk-alert-background);
        }

        :host([variant='success']) {
            --pk-alert-accent: var(--pk-color-teal-600);
            --pk-alert-background: var(--pk-color-teal-50);
            --pk-alert-border: var(--pk-color-teal-400);
            --pk-alert-color: var(--pk-color-teal-800);
        }

        :host([variant='neutral']) {
            --pk-alert-accent: var(--pk-color-gray-600);
            --pk-alert-background: var(--pk-color-gray-50);
            --pk-alert-border: var(--pk-color-gray-300);
            --pk-alert-color: var(--pk-color-gray-800);
        }

        :host([variant='warning']) {
            --pk-alert-accent: var(--pk-color-amber-600);
            --pk-alert-background: var(--pk-color-amber-50);
            --pk-alert-border: var(--pk-color-amber-300);
            --pk-alert-color: var(--pk-color-amber-800);
        }

        :host([variant='error']) {
            --pk-alert-accent: var(--pk-color-red-600);
            --pk-alert-background: var(--pk-color-red-50);
            --pk-alert-border: var(--pk-color-red-300);
            --pk-alert-color: var(--pk-color-red-800);
        }

        :host([appearance='filled-outlined']) .alert {
            border-inline-start-width: 1px;
            border-inline-start-color: var(--pk-alert-border);
        }

        :host([appearance='filled']) .alert {
            border-color: transparent;
            border-inline-start-width: 1px;
        }

        :host([appearance='outlined']) .alert {
            border-color: var(--pk-alert-accent);
            border-inline-start-width: 1px;
            background: transparent;
        }

        :host([appearance='plain']) .alert {
            border-color: transparent;
            border-inline-start-width: 1px;
            background: transparent;
        }

        /* For tinted surfaces, such as a control panel page: a raised white card with a whisper of the variant
           colour, so the alert keeps its edge where a pale tint would blend in. Only the edge, icon and title
           carry colour. */
        :host([appearance='raised']) {
            --pk-alert-title-color: var(--pk-alert-color);
            --pk-alert-body-color: var(--pk-color-gray-700);
        }

        /* The outline is a real border, not a ring shadow, so the accent edge replaces it on the leading side
           instead of sitting inside it. */
        :host([appearance='raised']) .alert {
            border-color: color-mix(in oklab, var(--pk-color-gray-400) 45%, transparent);
            border-inline-start-color: var(--pk-alert-accent);
            background: color-mix(in srgb, var(--pk-alert-accent) 5%, var(--pk-color-white, #fff));
            background-clip: padding-box;
            box-shadow: var(
                --pk-alert-raised-shadow,
                0 2px 8px -2px color-mix(in oklab, var(--pk-color-gray-900) 12%, transparent)
            );
        }


        .notice {
            display: flex;
            align-items: flex-start;
            gap: var(--_pk-alert-gap);
            min-width: 0;
        }

        .icon {
            display: inline-flex;
            flex: 0 0 auto;
            align-items: center;
            justify-content: center;
            width: var(--_pk-alert-icon-size);
            height: var(--_pk-alert-icon-size);
            margin-top: 0.0625rem;
            color: var(--pk-alert-accent);
        }

        .icon svg,
        .dismiss svg {
            display: block;
            width: 100%;
            height: 100%;
            fill: currentColor;
        }

        .content {
            min-width: 0;
            flex: 1 1 auto;
        }

        .title {
            display: block;
            margin: 0 0 0.125rem;
            color: var(--pk-alert-title-color, var(--pk-alert-accent));
            font-size: var(--pk-alert-title-size, var(--_pk-alert-title-size));
            font-weight: 700;
            line-height: 1.4;
        }

        .body {
            color: var(
                --pk-alert-body-color,
                var(--pk-alert-title-color, var(--pk-alert-accent))
            );
        }

        .body ::slotted(*) {
            /* Host document styles outrank normal shadow rules for slotted elements. */
            margin-block: 0 !important;
        }

        .dismiss {
            display: inline-flex;
            flex: 0 0 auto;
            align-items: center;
            justify-content: center;
            width: var(--_pk-alert-dismiss-size);
            height: var(--_pk-alert-dismiss-size);
            margin: var(--_pk-alert-dismiss-margin);
            padding: var(--_pk-alert-dismiss-padding);
            border: 0;
            border-radius: var(--pk-radius-md);
            background: transparent;
            color: var(--pk-alert-accent);
            cursor: pointer;
        }

        .dismiss:hover {
            background: color-mix(
                in srgb,
                var(--pk-alert-accent) 10%,
                transparent
            );
        }

        .dismiss:focus-visible,
        .copy:focus-visible,
        summary:focus-visible {
            outline: none;
            box-shadow: var(--pk-shadow-focus);
        }

        .actions,
        .details {
            margin-inline-start: calc(var(--_pk-alert-icon-size) + var(--_pk-alert-gap));
        }

        :host([hide-icon]) .actions,
        :host([hide-icon]) .details {
            margin-inline-start: 0;
        }

        .actions {
            margin-top: var(--_pk-alert-section-margin-top);
        }

        .actions slot::slotted(pk-button) {
            --pk-btn-height-default: var(--_pk-alert-action-height);
            --pk-btn-font-default: var(--_pk-alert-action-font);
            --pk-btn-padding-inline-default: var(--_pk-alert-action-padding-inline);
            --pk-btn-icon-size-default: var(--_pk-alert-action-icon-size);
            --pk-btn-icon-gap-default: var(--_pk-alert-action-icon-gap);
            --pk-btn-caret-size-default: var(--_pk-alert-action-caret-size);
            --pk-btn-radius-default: var(--_pk-alert-action-radius);
        }

        .details {
            margin-top: var(--_pk-alert-section-margin-top);
            padding-top: var(--_pk-alert-details-padding-top);
            border-top: 1px solid var(--pk-alert-border);
            color: var(--pk-color-gray-800);
        }

        summary {
            width: fit-content;
            border-radius: var(--pk-radius-sm);
            font-weight: 600;
            cursor: pointer;
        }

        .details-content {
            position: relative;
            margin-top: var(--_pk-alert-details-content-margin-top);
        }

        .details-scroll {
            min-width: 0;
        }

        .details-content ::slotted(pre) {
            box-sizing: border-box;
            max-height: var(--pk-alert-details-max-height, 16rem);
            margin: 0;
            padding: var(--_pk-alert-details-pre-padding);
            overflow: auto;
            border: 1px solid var(--pk-color-gray-200);
            border-radius: var(--_pk-alert-details-pre-radius);
            background: var(--pk-color-white);
            color: var(--pk-color-gray-800);
            /* Keep host pre resets from changing diagnostic typography. */
            font:
                var(--_pk-alert-details-pre-font-size)/1.5 ui-monospace,
                SFMono-Regular,
                Consolas,
                'Liberation Mono',
                monospace !important;
            overflow-wrap: anywhere;
            user-select: text;
            white-space: pre-wrap;
        }

        :host([copyable]) .details-content ::slotted(pre) {
            max-height: none;
            padding-inline-end: calc(
                var(--_pk-alert-details-pre-padding) + var(--_pk-alert-copy-button-size) +
                    var(--_pk-alert-copy-inset)
            );
            overflow: visible;
            border: 0;
            border-radius: 0;
            background: transparent;
        }

        :host([copyable]) .details-content {
            overflow: hidden;
            border: 1px solid var(--pk-color-gray-200);
            border-radius: var(--_pk-alert-details-pre-radius);
            background: var(--pk-color-white);
        }

        :host([copyable]) .details-scroll {
            position: relative;
            max-height: var(--pk-alert-details-max-height, 16rem);
            overflow: auto;
        }

        .copy-overlay {
            position: sticky;
            z-index: 1;
            top: 0;
            display: flex;
            justify-content: flex-end;
            height: 0;
            pointer-events: none;
        }

        .copy {
            flex: none;
            margin-block-start: var(--_pk-alert-copy-inset);
            margin-inline-end: var(--_pk-alert-copy-inset);
            pointer-events: auto;
            --pk-btn-height-default: var(--_pk-alert-copy-button-size);
            --pk-btn-icon-size-default: var(--_pk-alert-copy-icon-size);
            --pk-btn-radius-default: var(--_pk-alert-copy-radius);
            --pk-copy-button-background: var(--pk-color-white);
            --pk-copy-button-border-color: transparent;
            --pk-copy-button-hover-border-color: transparent;
            --pk-copy-button-color: var(--pk-color-gray-300);
            --pk-copy-button-hover-color: var(--pk-color-gray-500);
            --pk-copy-button-hover-background: color-mix(
                in srgb,
                var(--pk-color-gray-300) 6%,
                var(--pk-color-white)
            );
        }

        .copy-status {
            display: block;
            margin-top: 0.375rem;
            color: var(--pk-color-red-700);
            font-size: var(--_pk-alert-copy-status-size);
        }

        .copy-status:not([data-error]) {
            position: absolute;
            width: 1px;
            height: 1px;
            margin: -1px;
            padding: 0;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        @media (max-width: 480px) {
            .actions,
            .details {
                margin-inline-start: 0;
            }
        }
    }
`, v = 3e3, y = {
	neutral: s.circleInfo,
	info: s.circleInfo,
	success: s.circleCheck,
	warning: s.triangleExclamation,
	error: s.circleExclamation
}, b = class extends m {
	constructor(...e) {
		super(...e), this.hasSlotController = new c(this, "title", "icon", "actions", "details"), this.variant = "info", this.size = "default", this.appearance = "accent", this.heading = "", this.hideIcon = !1, this.dismissible = !1, this.announce = "off", this.detailsLabel = "Details", this.detailsOpen = !1, this.copyable = !1, this.copyLabel = "Copy details", this.copiedLabel = "Details copied.", this.copyErrorLabel = "Copy failed. Select the details and copy them manually.", this.dismissLabel = "Dismiss", this.copyStatus = "", this.copyFailed = !1;
	}
	static {
		this.styles = _;
	}
	disconnectedCallback() {
		window.clearTimeout(this.copyStatusResetTimer), super.disconnectedCallback();
	}
	hasTitle() {
		return !!this.heading || this.hasSlotController.test("title");
	}
	hasDetails() {
		return this.hasSlotController.test("details");
	}
	get detailsValue() {
		return (this.detailsSlot?.assignedNodes({ flatten: !0 }) ?? []).map((e) => e.textContent ?? "").join("").trim();
	}
	resetCopyStatusLater() {
		window.clearTimeout(this.copyStatusResetTimer), this.copyStatusResetTimer = window.setTimeout(() => {
			this.copyStatus = "", this.copyFailed = !1;
		}, v);
	}
	showCopySuccess() {
		this.copyFailed = !1, this.copyStatus = this.copiedLabel, this.resetCopyStatusLater();
	}
	showCopyError() {
		this.copyFailed = !0, this.copyStatus = this.copyErrorLabel, this.resetCopyStatusLater();
	}
	handleCopySuccess() {
		this.showCopySuccess();
	}
	handleCopyError() {
		this.showCopyError(), this.selectDetails();
	}
	selectDetails() {
		let e = this.detailsSlot?.assignedNodes({ flatten: !0 }) ?? [], t = e[0], n = e[e.length - 1];
		if (!t || !n) return;
		this.detailsOpen = !0;
		let r = document.createRange();
		r.setStartBefore(t), r.setEndAfter(n);
		let i = window.getSelection();
		i?.removeAllRanges(), i?.addRange(r);
	}
	async copyDetails() {
		let e = this.detailsValue;
		if (!e) this.showCopyError(), this.dispatchEvent(new p());
		else if (this.copyButton) this.copyButton.value = e, await this.copyButton.copy();
		else try {
			await f(e), this.showCopySuccess();
			let t = new l(e);
			this.dispatchEvent(t);
		} catch {
			this.showCopyError(), this.selectDetails(), this.dispatchEvent(new p());
		}
	}
	dismiss() {
		this.dispatchEvent(new g()) && (this.hidden = !0);
	}
	show() {
		this.hidden = !1;
	}
	handleDetailsToggle(e) {
		this.detailsOpen = e.currentTarget.open;
	}
	renderIcon() {
		if (this.hideIcon) return n;
		let t = this.hasSlotController.test("icon") ? e`<slot name="icon"></slot>` : d(u(y[this.variant] ?? y.info));
		return e`<span part="icon" class="icon" aria-hidden="true">${t}</span>`;
	}
	render() {
		let t = this.announce === "assertive" ? "alert" : this.announce === "polite" ? "status" : n, r = this.announce === "off" ? n : this.announce, i = this.hasSlotController.test("actions"), a = this.hasDetails();
		return e`
            <div
                part="base"
                class="alert"
                role=${t}
                aria-live=${r}
                aria-atomic=${this.announce === "off" ? n : "true"}
            >
                <div part="notice" class="notice">
                    ${this.renderIcon()}

                    <div part="content" class="content">
                        ${this.hasTitle() ? e`
                                <strong part="title" class="title">
                                    ${this.hasSlotController.test("title") ? e`<slot name="title"></slot>` : this.heading}
                                </strong>
                            ` : n}

                        <div part="body" class="body"><slot></slot></div>
                    </div>

                    ${this.dismissible ? e`
                            <button
                                part="dismiss-button"
                                class="dismiss"
                                type="button"
                                aria-label=${this.dismissLabel}
                                @click=${this.dismiss}
                            >
                                ${d(u(s.xmark))}
                            </button>
                        ` : n}
                </div>

                ${i ? e`<div part="actions" class="actions"><slot name="actions"></slot></div>` : n}

                ${a ? e`
                        <details
                            part="details"
                            class="details"
                            ?open=${this.detailsOpen}
                            @toggle=${this.handleDetailsToggle}
                        >
                            <summary part="details-summary">${this.detailsLabel}</summary>
                            <div part="details-content" class="details-content">
                                <div class="details-scroll">
                                    ${this.copyable ? e`
                                            <div class="copy-overlay">
                                                <pk-copy-button
                                                    part="copy-button"
                                                    class="copy"
                                                    variant="transparent"
                                                    aria-label=${this.copyLabel}
                                                    .copiedLabel=${this.copiedLabel}
                                                    .value=${this.detailsValue}
                                                    @pk-copy=${this.handleCopySuccess}
                                                    @pk-copy-error=${this.handleCopyError}
                                                ></pk-copy-button>
                                            </div>
                                        ` : n}
                                    <slot name="details"></slot>
                                </div>
                            </div>

                            ${this.copyable ? e`
                                    <span
                                        part="copy-status"
                                        class="copy-status"
                                        data-error=${this.copyFailed ? "" : n}
                                        role="status"
                                        aria-live="polite"
                                    >${this.copyStatus}</span>
                                ` : n}
                        </details>
                    ` : n}
            </div>
        `;
	}
};
o([t({ reflect: !0 })], b.prototype, "variant", void 0), o([t({ reflect: !0 })], b.prototype, "size", void 0), o([t({ reflect: !0 })], b.prototype, "appearance", void 0), o([t()], b.prototype, "heading", void 0), o([t({
	type: Boolean,
	attribute: "hide-icon",
	reflect: !0
})], b.prototype, "hideIcon", void 0), o([t({
	type: Boolean,
	reflect: !0
})], b.prototype, "dismissible", void 0), o([t({ reflect: !0 })], b.prototype, "announce", void 0), o([t({ attribute: "details-label" })], b.prototype, "detailsLabel", void 0), o([t({
	type: Boolean,
	attribute: "details-open",
	reflect: !0
})], b.prototype, "detailsOpen", void 0), o([t({
	type: Boolean,
	reflect: !0
})], b.prototype, "copyable", void 0), o([t({ attribute: "copy-label" })], b.prototype, "copyLabel", void 0), o([t({ attribute: "copied-label" })], b.prototype, "copiedLabel", void 0), o([t({ attribute: "copy-error-label" })], b.prototype, "copyErrorLabel", void 0), o([t({ attribute: "dismiss-label" })], b.prototype, "dismissLabel", void 0), o([h("slot[name=\"details\"]")], b.prototype, "detailsSlot", void 0), o([h("pk-copy-button.copy")], b.prototype, "copyButton", void 0), o([a()], b.prototype, "copyStatus", void 0), o([a()], b.prototype, "copyFailed", void 0), b = o([r("pk-alert")], b);

//# sourceMappingURL=comment-edit.js.map