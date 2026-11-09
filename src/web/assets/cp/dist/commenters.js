import { A as e, C as t, D as n, E as r, M as i, O as a, S as o, T as s, _ as c, a as l, b as u, c as d, d as f, f as p, g as m, h, i as g, j as _, k as v, l as y, m as b, n as x, o as S, p as C, r as ee, s as w, t as te, u as ne, v as re, w as T, x as E, y as ie } from "./chunks/pk-copy-button-Dfji6-PV.js";
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/dismissible-stack-CwhtwAyo.js
var D = [];
function ae(e) {
	D.push(e);
}
function oe(e) {
	for (let t = D.length - 1; t >= 0; --t) if (D[t] === e) {
		D.splice(t, 1);
		break;
	}
}
function se(e) {
	return D.length > 0 && D[D.length - 1] === e;
}
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/overlay-lifecycle-C582MGBJ.js
var ce = class extends Event {
	constructor() {
		super("pk-show", {
			bubbles: !0,
			cancelable: !1,
			composed: !0
		});
	}
}, le = class extends Event {
	constructor() {
		super("pk-after-show", {
			bubbles: !0,
			cancelable: !1,
			composed: !0
		});
	}
}, ue = class extends Event {
	constructor(e = "unknown") {
		super("pk-hide", {
			bubbles: !0,
			cancelable: !0,
			composed: !0
		}), this.detail = { source: e };
	}
}, de = class extends Event {
	constructor() {
		super("pk-after-hide", {
			bubbles: !0,
			cancelable: !1,
			composed: !0
		});
	}
};
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/scroll-lock-DE1pElMQ.js
function fe(e, t) {
	return {
		top: Math.round(e.getBoundingClientRect().top - t.getBoundingClientRect().top),
		left: Math.round(e.getBoundingClientRect().left - t.getBoundingClientRect().left)
	};
}
var pe = /* @__PURE__ */ new Set(), me = null, he = /* @__PURE__ */ new Set([
	" ",
	"ArrowUp",
	"ArrowDown",
	"ArrowLeft",
	"ArrowRight",
	"PageUp",
	"PageDown",
	"Home",
	"End"
]);
function ge(e) {
	for (let t of e.composedPath()) if (t instanceof HTMLElement && pe.has(t)) return !0;
	return !1;
}
function _e(e) {
	if (!(e instanceof HTMLElement)) return !1;
	if (e.isContentEditable) return !0;
	let t = e.tagName;
	return t === "INPUT" || t === "TEXTAREA" || t === "SELECT";
}
function ve() {
	let e = document.body, t = e.style.overflow;
	e.style.setProperty("overflow", "hidden", "important");
	let n = (e) => {
		ge(e) || e.preventDefault();
	}, r = (e) => {
		ge(e) || e.preventDefault();
	}, i = (e) => {
		he.has(e.key) && (ge(e) || _e(e.target) || e.preventDefault());
	};
	return window.addEventListener("wheel", n, {
		passive: !1,
		capture: !0
	}), window.addEventListener("touchmove", r, {
		passive: !1,
		capture: !0
	}), window.addEventListener("keydown", i, { capture: !0 }), () => {
		window.removeEventListener("wheel", n, { capture: !0 }), window.removeEventListener("touchmove", r, { capture: !0 }), window.removeEventListener("keydown", i, { capture: !0 }), e.style.removeProperty("overflow"), t && (e.style.overflow = t);
	};
}
function ye(e) {
	pe.add(e), pe.size === 1 && (document.documentElement.classList.add("pk-scroll-lock"), document.documentElement.style.setProperty("--pk-scroll-lock-size", "0px"), me = ve());
}
function be(e) {
	pe.delete(e), pe.size === 0 && (me?.(), me = null, document.documentElement.classList.remove("pk-scroll-lock"), document.documentElement.style.removeProperty("--pk-scroll-lock-size"), document.documentElement.style.removeProperty("--pk-scroll-lock-gutter"));
}
function xe(e, t, n = "vertical", r = "smooth") {
	let i = fe(e, t), a = i.top + t.scrollTop, o = i.left + t.scrollLeft, s = t.scrollLeft, c = t.scrollLeft + t.offsetWidth, l = t.scrollTop, u = t.scrollTop + t.offsetHeight;
	(n === "horizontal" || n === "both") && (o < s ? t.scrollTo({
		left: o,
		behavior: r
	}) : o + e.clientWidth > c && t.scrollTo({
		left: o - t.offsetWidth + e.clientWidth,
		behavior: r
	})), (n === "vertical" || n === "both") && (a < l ? t.scrollTo({
		top: a,
		behavior: r
	}) : a + e.clientHeight > u && t.scrollTo({
		top: a - t.offsetHeight + e.clientHeight,
		behavior: r
	}));
}
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/animate-with-class-CjTZI0NU.js
function Se(e, t, n = 500) {
	return new Promise((r) => {
		let i = new AbortController(), { signal: a } = i;
		if (e.classList.contains(t)) {
			r();
			return;
		}
		e.classList.add(t);
		let o = !1, s = () => {
			o || (o = !0, e.classList.remove(t), window.clearTimeout(c), r(), i.abort());
		};
		e.addEventListener("animationend", s, {
			once: !0,
			signal: a
		}), e.addEventListener("animationcancel", s, {
			once: !0,
			signal: a
		});
		let c = window.setTimeout(s, n);
		requestAnimationFrame(() => {
			!o && e.getAnimations().length === 0 && s();
		});
	});
}
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/pk-dialog-CosE5pFj.js
var Ce = ".modal-shade, .modal", we = (e) => {
	let t = getComputedStyle(e);
	return t.display !== "none" && t.visibility !== "hidden" && Number.parseFloat(t.opacity || "1") > 0;
}, Te = (e) => Array.from(e.querySelectorAll(Ce)).filter((e) => e instanceof HTMLElement && !e.closest("pk-dialog") && we(e));
function Ee(e, t = {}) {
	let n = t.getDocument?.() ?? document, r = t.root ?? n.body, i = new Set(t.ignoreExisting ? Te(n) : []), a = () => Te(n).some((e) => !i.has(e)), o = a(), s = () => {
		let t = a();
		t !== o && (o = t, e(t));
	}, c = new MutationObserver(() => {
		s();
	});
	c.observe(r, {
		childList: !0,
		subtree: !0,
		attributes: !0,
		attributeFilter: [
			"class",
			"style",
			"hidden"
		]
	});
	let l = window.setInterval(s, 250);
	return { disconnect: () => {
		c.disconnect(), window.clearInterval(l);
	} };
}
var De = i`
    @layer pk-component {
        :host {
            /* Not display:contents — that flattens the trigger slot into flex parents
               (e.g. playground cards) and stretches pk-button full width, same class of
               bug as the dropdown host. Dialog host is display none/block, not contents. */
            display: inline-block;
            width: fit-content;
            max-width: 100%;
            align-self: flex-start;
            flex: none;
            vertical-align: middle;
        }

        /*
         * Controlled dialogs (no slot="trigger") — panel is top-layer / fixed while
         * yielding. An inline-block host still sizes to the open <dialog> box in some
         * engines and expands parents (Formie nested field cards grow a blank gap).
         *
         * Zero box ≠ gone from the tree: Tailwind space-y-* uses :not(:last-child) on
         * DOM siblings, so an in-tree host still steals last-child and margins the
         * previous sibling. Prefer flex/grid gap-* (skips out-of-flow children), or
         * mount overlays outside the spaced stack. True light-DOM portal would also fix it.
         */
        :host(:not([data-has-trigger])) {
            position: absolute;
            width: 0;
            height: 0;
            max-width: none;
            margin: 0;
            padding: 0;
            overflow: visible;
            vertical-align: unset;
        }

        .dialog {
            display: flex;
            flex-direction: column;
            width: min(100%, var(--pk-dialog-width, var(--pk-dialog-max-width, 32rem)));
            min-width: var(--pk-dialog-min-width, 0);
            /* Keep UA :modal inset (0) — that + margin:auto centers the panel. Do not
             * unset inset; it breaks centering (field edit landed top-left). */
            height: var(--pk-dialog-height, fit-content);
            min-height: var(--pk-dialog-min-height, 0);
            max-height: var(--pk-dialog-max-height, calc(100vh - 2rem));
            margin: auto;
            padding: 0;
            /* v1 DialogContent: no CSS border — edge is the 1px ring inside --pk-shadow-modal. */
            border: 0;
            border-radius: var(--pk-radius-lg);
            background: var(--pk-color-white);
            box-shadow: var(--pk-shadow-modal);
            color: var(--pk-color-gray-900);
            overflow: hidden;
            opacity: 1;
            transform: scale(1);
        }

        .dialog:focus,
        .dialog:focus-visible {
            outline: none;
        }

        .dialog:not([open]) {
            display: none;
        }

        .dialog--wide {
            --pk-dialog-max-width: 42rem;
        }

        /* motion only via animateWithClass — never auto-animate on [open] alone. */
        .dialog.show {
            animation: pk-dialog-in 0.15s ease;
        }

        .dialog.hide {
            animation: pk-dialog-out 0.15s ease forwards;
        }

        .dialog.pulse {
            animation: pk-dialog-pulse 0.25s ease;
        }

        @keyframes pk-dialog-in {
            from {
                opacity: 0;
                transform: scale(0.95);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes pk-dialog-out {
            from {
                opacity: 1;
                transform: scale(1);
            }

            to {
                opacity: 0;
                transform: scale(0.95);
            }
        }

        @keyframes pk-dialog-pulse {
            0%, 100% {
                transform: scale(1);
            }

            50% {
                transform: scale(0.98);
            }
        }

        .dialog.show::backdrop {
            animation: pk-dialog-backdrop-in 0.15s ease;
        }

        .dialog.hide::backdrop {
            animation: pk-dialog-backdrop-in 0.15s ease reverse;
        }

        .dialog::backdrop {
            background: hsl(from var(--pk-color-gray-900) h s l / 0.2);
            opacity: 1;
        }

        @keyframes pk-dialog-backdrop-in {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .header {
            position: relative;
            display: flex;
            flex-shrink: 0;
            flex-direction: column;
            gap: 0.2rem;
            padding: 1rem;
            border-bottom: 1px solid var(--pk-color-gray-150);
            border-radius: var(--pk-radius-lg) var(--pk-radius-lg) 0 0;
            background: #f3f7fb;
            text-align: left;
        }

        .title {
            margin: 0;
            padding-inline-end: 2rem;
            font-size: 0.9375rem;
            font-weight: 600;
            line-height: 1.2;
            color: var(--pk-color-gray-900);
        }

        .description {
            margin: 0;
            padding-inline-end: 2rem;
            font-size: 0.75rem;
            font-weight: 400;
            line-height: 1.4;
            color: var(--pk-color-gray-500);
        }

        .close {
            --pk-dialog-close-focus-padding: 0.25rem;
            position: absolute;
            top: calc(1rem - var(--pk-dialog-close-focus-padding));
            right: calc(1rem - var(--pk-dialog-close-focus-padding));
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: calc(1.125rem + 2 * var(--pk-dialog-close-focus-padding));
            height: calc(1.125rem + 2 * var(--pk-dialog-close-focus-padding));
            margin: 0;
            padding: var(--pk-dialog-close-focus-padding);
            border: 0;
            border-radius: var(--pk-radius-sm);
            background: transparent;
            color: var(--pk-color-gray-600);
            cursor: pointer;
            line-height: 0;
            opacity: 0.7;
            transition: opacity 0.12s ease;
            box-sizing: border-box;
        }

        .close-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 0;
        }

        .close-icon svg {
            display: block;
            width: 1.125rem;
            height: 1.125rem;
        }

        .close:hover {
            opacity: 1;
            background: transparent;
        }

        .close:focus-visible {
            opacity: 1;
            box-shadow: 0 0 0 2px var(--pk-color-gray-600);
        }

        .body {
            flex: 1 1 auto;
            min-height: 0;
            overflow: auto;
            padding: 0;
            /* v1 DialogContent inherited CP text defaults (14px / gray-700) — do not
             * downshift body copy to sm/gray-600 or slotted content reads smaller than v1. */
            font-size: var(--pk-font-size-base);
            line-height: 1.5;
            color: var(--pk-color-gray-700);
        }

        .body--padded {
            padding: 1rem;
        }

        .footer {
            display: flex;
            flex-shrink: 0;
            flex-direction: row;
            justify-content: flex-end;
            gap: 0.5rem;
            padding: 0.625rem 1rem;
            border-top: 1px solid var(--pk-color-gray-150);
            border-radius: 0 0 var(--pk-radius-lg) var(--pk-radius-lg);
            background: #e4edf6;
        }
    }
`, Oe = S(l.xmark), O = class extends T {
	constructor(...e) {
		super(...e), this.open = !1, this.label = "", this.description = "", this.disablePointerDismissal = !1, this.withoutHeader = !1, this.withoutBodyPadding = !1, this.disableScrollLock = !1, this.size = "default", this.triggerElement = null, this.previouslyFocused = null, this.yieldingToHostModal = !1, this.hostModalObserver = null, this.yieldBox = null, this.handleDocumentKeyDown = (e) => {
			this.yieldingToHostModal || e.key === "Escape" && this.open && se(this) && (e.preventDefault(), e.stopPropagation(), this.requestClose("escape"));
		}, this.onHostModalPresenceChange = (e) => {
			e ? this.yieldToHostModal() : this.restoreFromHostModal();
		}, this.showing = !1, this.isClosing = !1, this.transitionId = 0, this.handleDialogCancel = (e) => {
			e.preventDefault(), !this.dialogElement.classList.contains("hide") && se(this) && this.requestClose("escape");
		}, this.handleDialogClick = (e) => {
			e.composedPath().some((e) => e instanceof Element && e.matches("[data-dialog=\"close\"], [data-dialog-close]")) && (e.stopPropagation(), this.requestClose("close-button"));
		}, this.handleDialogPointerDown = async (e) => {
			e.target === this.dialogElement && se(this) && (this.disablePointerDismissal ? await Se(this.dialogElement, "pulse") : this.requestClose("pointer-dismiss"));
		}, this.onTriggerClick = (e) => {
			e.preventDefault(), this.open = !0;
		}, this.onFooterSlotChange = () => {
			this.requestUpdate();
		};
	}
	static {
		this.styles = De;
	}
	hasCustomHeaderSlot() {
		return this.querySelector(":scope > [slot=\"header\"]") !== null;
	}
	applyYieldPosition(e) {
		let t = this.dialogElement;
		t.style.position = "fixed", t.style.top = `${e.top}px`, t.style.left = `${e.left}px`, t.style.width = `${e.width}px`, t.style.height = `${e.height}px`, t.style.margin = "0", t.style.maxHeight = "none", t.style.zIndex = "99", t.toggleAttribute("data-yielding", !0);
	}
	clearYieldPosition() {
		let e = this.dialogElement;
		e && (e.style.position = "", e.style.top = "", e.style.left = "", e.style.width = "", e.style.height = "", e.style.margin = "", e.style.maxHeight = "", e.style.zIndex = "", e.removeAttribute("data-yielding"), this.yieldBox = null);
	}
	yieldToHostModal() {
		if (this.yieldingToHostModal || !this.open || !this.dialogElement?.open) return;
		let e = this.dialogElement.getBoundingClientRect();
		this.yieldBox = {
			top: e.top,
			left: e.left,
			width: e.width,
			height: e.height
		}, this.yieldingToHostModal = !0;
		try {
			this.dialogElement.close(), this.dialogElement.show(), this.applyYieldPosition(this.yieldBox);
		} catch {
			this.yieldingToHostModal = !1, this.clearYieldPosition();
		}
	}
	restoreFromHostModal() {
		if (this.yieldingToHostModal && (this.yieldingToHostModal = !1, this.clearYieldPosition(), this.open && this.dialogElement)) try {
			this.dialogElement.open && this.dialogElement.close(), this.dialogElement.showModal();
		} catch {}
	}
	firstUpdated() {
		this.open && this.show();
	}
	disconnectedCallback() {
		this.transitionId++, this.showing = !1, this.isClosing = !1, this.disableScrollLock || be(this), this.removeOpenListeners(), super.disconnectedCallback();
	}
	updated(e) {
		super.updated(e), e.has("open") && this.hasUpdated && this.handleOpenChange();
	}
	handleOpenChange() {
		this.open && (!this.dialogElement.open || this.isClosing) ? this.show() : !this.open && this.dialogElement.open && !this.isClosing && this.requestClose("api");
	}
	async show(e = "api") {
		if (this.showing || this.dialogElement?.open && !this.isClosing) return;
		let t = ++this.transitionId;
		this.isClosing = !1, this.showing = !0, this.dialogElement.classList.remove("hide");
		try {
			let e = new ce();
			if (!this.dispatchEvent(e)) {
				this.open = !1;
				return;
			}
			if (this.addOpenListeners(), this.dialogElement.open || (this.previouslyFocused = document.activeElement), this.open = !0, this.dialogElement.open || this.dialogElement.showModal(), this.disableScrollLock || ye(this), requestAnimationFrame(() => {
				if (t !== this.transitionId || !this.isConnected) return;
				let e = this.querySelector("[autofocus]");
				e ? (e.shadowRoot?.querySelector("input, textarea, select, button") ?? e).focus({ preventScroll: !0 }) : this.dialogElement.focus({ preventScroll: !0 });
			}), await Se(this.dialogElement, "show"), t !== this.transitionId || !this.isConnected) return;
			this.dispatchEvent(new CustomEvent("pk-open-change", {
				detail: { open: !0 },
				bubbles: !0,
				composed: !0
			})), this.dispatchEvent(new le());
		} finally {
			t === this.transitionId && (this.showing = !1);
		}
	}
	async hide(e = "unknown") {
		await this.requestClose(e);
	}
	closeDialog() {
		this.requestClose("close-button");
	}
	async requestClose(e = "unknown") {
		if (this.isClosing || !this.dialogElement?.open) return;
		let t = new ue(typeof e == "string" ? e : "close-button");
		if (!this.dispatchEvent(t)) {
			this.open = !0, await Se(this.dialogElement, "pulse");
			return;
		}
		let n = ++this.transitionId;
		if (this.isClosing = !0, this.showing = !1, this.open = !1, this.dialogElement.classList.remove("show"), this.removeOpenListeners(), await Se(this.dialogElement, "hide"), n !== this.transitionId || !this.isConnected) return;
		this.isClosing = !1, this.dialogElement.close(), this.disableScrollLock || be(this);
		let r = this.previouslyFocused;
		this.previouslyFocused = null, r?.isConnected && window.setTimeout(() => {
			n === this.transitionId && !this.open && r.focus({ preventScroll: !0 });
		}, 0), this.dispatchEvent(new de()), this.dispatchEvent(new CustomEvent("pk-open-change", {
			detail: { open: !1 },
			bubbles: !0,
			composed: !0
		}));
	}
	forceOverlayReset() {
		if (this.transitionId++, this.showing = !1, this.isClosing = !1, this.open = !1, this.yieldingToHostModal = !1, this.clearYieldPosition(), this.removeOpenListeners(), this.dialogElement?.open) try {
			this.dialogElement.close();
		} catch {}
		this.dialogElement?.classList.remove("hide", "show", "pulse"), this.disableScrollLock || be(this);
	}
	addOpenListeners() {
		document.addEventListener("keydown", this.handleDocumentKeyDown), ae(this), this.hostModalObserver?.disconnect(), this.hostModalObserver = Ee(this.onHostModalPresenceChange, { ignoreExisting: !0 });
	}
	removeOpenListeners() {
		document.removeEventListener("keydown", this.handleDocumentKeyDown), oe(this), this.hostModalObserver?.disconnect(), this.hostModalObserver = null, this.yieldingToHostModal = !1, this.clearYieldPosition();
	}
	syncHasTriggerAttribute() {
		this.toggleAttribute("data-has-trigger", !!this.triggerElement);
	}
	onTriggerSlotChange(e) {
		let [t] = e.target.assignedElements({ flatten: !0 });
		this.triggerElement && this.triggerElement.removeEventListener("click", this.onTriggerClick), this.triggerElement = t ?? null, this.syncHasTriggerAttribute(), this.triggerElement && this.triggerElement.addEventListener("click", this.onTriggerClick);
	}
	render() {
		let t = !this.hasCustomHeaderSlot() && !this.withoutHeader && !!this.label, r = t && !this.withoutBodyPadding, i = this.querySelector(":scope > [slot=\"footer\"]") !== null;
		return e`
            <slot name="trigger" @slotchange=${this.onTriggerSlotChange}></slot>
            <dialog
                part="panel"
                aria-label=${this.label || n}
                class=${w({
			dialog: !0,
			open: this.open,
			"dialog--wide": this.size === "wide"
		})}
                tabindex="-1"
                @cancel=${this.handleDialogCancel}
                @click=${this.handleDialogClick}
                @pointerdown=${this.handleDialogPointerDown}
            >
                <slot name="header">
                    ${t ? e`
                            <header part="header" class="header">
                                <h2 part="title" class="title">
                                    <slot name="label">${this.label}</slot>
                                </h2>
                                ${this.description ? e`
                                        <p part="description" class="description">
                                            <slot name="description">${this.description}</slot>
                                        </p>
                                    ` : e`<slot name="description" hidden></slot>`}
                                <button type="button" class="close" data-dialog="close" aria-label="Close">
                                    <span class="close-icon" aria-hidden="true">${C(Oe)}</span>
                                </button>
                            </header>
                        ` : n}
                </slot>
                <div
                    part="body"
                    class=${w({
			body: !0,
			"body--padded": r
		})}
                >
                    <slot></slot>
                </div>
                ${i ? e`
                        <footer part="footer" class="footer">
                            <slot name="footer" @slotchange=${this.onFooterSlotChange}></slot>
                        </footer>
                    ` : e`<slot name="footer" @slotchange=${this.onFooterSlotChange} hidden></slot>`}
            </dialog>
        `;
	}
};
s([t({
	type: Boolean,
	reflect: !0
})], O.prototype, "open", void 0), s([t()], O.prototype, "label", void 0), s([t()], O.prototype, "description", void 0), s([t({
	attribute: "disable-pointer-dismissal",
	type: Boolean,
	reflect: !0
})], O.prototype, "disablePointerDismissal", void 0), s([t({
	attribute: "without-header",
	type: Boolean,
	reflect: !0
})], O.prototype, "withoutHeader", void 0), s([t({
	attribute: "without-body-padding",
	type: Boolean,
	reflect: !0
})], O.prototype, "withoutBodyPadding", void 0), s([t({
	attribute: "disable-scroll-lock",
	type: Boolean,
	reflect: !0
})], O.prototype, "disableScrollLock", void 0), s([t({ reflect: !0 })], O.prototype, "size", void 0), s([E("dialog")], O.prototype, "dialogElement", void 0), s([o()], O.prototype, "triggerElement", void 0), O = s([r("pk-dialog")], O);
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/pk-radio-D0eqmnlH.js
var ke = i`
    @layer pk-component {
        :host {
            display: inline-flex;
            vertical-align: middle;
            font-family: var(--pk-font-family);
            font-size: var(--pk-font-size-base);
            line-height: var(--pk-line-height);
        }

        :host([disabled]) {
            cursor: not-allowed;
            opacity: 0.5;
        }

        .item {
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            user-select: none;
            position: relative;
            margin: 0;
        }

        :host([disabled]) .item {
            cursor: not-allowed;
        }

        .item--with-label {
            gap: 0.5rem;
        }

        .control {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1rem;
            height: 1rem;
            border: 1px solid var(--pk-color-slate-400);
            border-radius: 9999px;
            background: var(--pk-color-white);
            transition: border-color 0.12s ease, box-shadow 0.12s ease;
        }

        .item:focus-visible .control,
        :host([data-focus-visible]) .control {
            border-color: var(--pk-color-sky-600);
            box-shadow: 0 0 0 1px var(--pk-color-sky-600), 0 0 4px 0 hsl(from var(--pk-color-sky-600) h s l / 0.7);
        }

        :host([invalid]) .control {
            border-color: var(--pk-color-rose-600);
        }

        :host([invalid]:focus-visible) .control,
        :host([invalid][data-focus-visible]) .control {
            box-shadow: 0 0 0 1px var(--pk-color-rose-600), 0 0 4px 0 hsl(from var(--pk-color-rose-600) h s l / 0.7);
        }

        :host([checked]) .control {
            background: var(--pk-color-gray-50);
            color: #1f2933;
        }

        .indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            color: currentcolor;
        }

        .indicator-dot {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 9999px;
            background: currentcolor;
            transition: opacity 0.12s ease, transform 0.12s ease;
        }

        :host(:not([checked])) .indicator-dot {
            opacity: 0;
            transform: scale(0);
        }

        .input {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
            opacity: 0;
            appearance: none;
        }

        .label {
            line-height: var(--pk-line-height);
            /* Match checkbox / form-control labels (gray-700). */
            color: var(--pk-color-gray-700);
        }

        .label.is-empty {
            display: none;
        }
    }
`, k = class extends T {
	constructor(...e) {
		super(...e), this.value = "", this.checked = !1, this.disabled = !1, this.invalid = !1, this.required = !1, this.tabIndex = -1, this.ariaLabel = null, this.forceDisabled = !1, this.hasDefaultSlotContent = !1;
	}
	static {
		this.styles = ke;
	}
	updated(e) {
		this.input && e.has("checked") && (this.input.checked = this.checked), this.input && e.has("tabIndex") && (this.input.tabIndex = this.tabIndex);
	}
	focusControl(e) {
		this.input.focus(e);
	}
	defaultSlotChanged(e) {
		let t = e.target;
		this.hasDefaultSlotContent = t.assignedNodes({ flatten: !0 }).some((e) => e.nodeType === Node.TEXT_NODE ? e.textContent?.trim() : e.nodeType === Node.ELEMENT_NODE);
	}
	handleChange(e) {
		e.stopPropagation();
		let t = e.target;
		this.disabled || this.forceDisabled || !t.checked || this.dispatchEvent(new CustomEvent("pk-radio-select", {
			detail: { value: this.value },
			bubbles: !0,
			composed: !0
		}));
	}
	render() {
		let t = this.disabled || this.forceDisabled;
		return e`
            <label
                part="base"
                class=${w({
			item: !0,
			"pk-radio-group__item": !0,
			"item--with-label": this.hasDefaultSlotContent
		})}
                data-state=${this.checked ? "checked" : n}
                ?data-disabled=${t}
                aria-disabled=${t ? "true" : n}
            >
                <span part="control" class="control pk-radio-group__control">
                    <span part="indicator" class="indicator pk-radio-group__indicator">
                        <span class="indicator-dot pk-radio-group__indicator-dot"></span>
                    </span>
                </span>
                <input
                    part="input"
                    class="input"
                    type="radio"
                    .checked=${this.checked}
                    ?disabled=${t}
                    ?required=${this.required}
                    value=${this.value}
                    tabindex=${this.tabIndex}
                    aria-label=${this.ariaLabel ?? n}
                    aria-invalid=${this.invalid ? "true" : n}
                    aria-checked=${this.checked ? "true" : "false"}
                    @change=${this.handleChange}
                />
                <span
                    class=${w({
			label: !0,
			"is-empty": !this.hasDefaultSlotContent
		})}
                >
                    <slot @slotchange=${this.defaultSlotChanged}></slot>
                </span>
            </label>
        `;
	}
};
s([t()], k.prototype, "value", void 0), s([t({
	type: Boolean,
	reflect: !0
})], k.prototype, "checked", void 0), s([t({
	type: Boolean,
	reflect: !0
})], k.prototype, "disabled", void 0), s([t({
	type: Boolean,
	reflect: !0
})], k.prototype, "invalid", void 0), s([t({
	type: Boolean,
	reflect: !0
})], k.prototype, "required", void 0), s([t({ type: Number })], k.prototype, "tabIndex", void 0), s([t({ attribute: "aria-label" })], k.prototype, "ariaLabel", void 0), s([t({
	type: Boolean,
	attribute: !1
})], k.prototype, "forceDisabled", void 0), s([E(".input")], k.prototype, "input", void 0), s([o()], k.prototype, "hasDefaultSlotContent", void 0), k = s([r("pk-radio")], k);
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/form-control.styles-CQ93gnVf.js
var Ae = i`
    @layer pk-component {
        .form-control {
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
            width: 100%;
        }

        .form-control__header {
            display: flex;
            flex-direction: column;
            gap: 0.125rem;
            min-width: 0;
        }

        .form-control__label {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            margin: 0;
            color: var(--pk-color-gray-700);
            font-family: var(--pk-font-family);
            font-size: var(--pk-font-size-base);
            font-weight: 700;
            line-height: var(--pk-line-height);
        }

        .form-control__instructions,
        .form-control__hint {
            margin: 0;
            color: var(--pk-color-gray-500);
            font-family: var(--pk-font-family);
            font-size: var(--pk-font-size-base);
            line-height: var(--pk-line-height);
        }

        .form-control__instructions:empty,
        .form-control__hint:empty {
            display: none;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .form-control__input {
            display: flex;
            align-items: stretch;
            position: relative;
            width: 100%;
        }

        .form-control__start,
        .form-control__end {
            display: inline-flex;
            align-items: center;
            flex-shrink: 0;
        }

        .form-control__start {
            margin-inline-end: 6px;
        }

        .form-control__end {
            margin-inline-start: 6px;
        }

        .icon-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 0;
            border: 0;
            background: transparent;
            color: var(--pk-color-gray-500);
            cursor: pointer;
            line-height: 0;
        }

        .icon-button:focus-visible {
            outline: none;
            box-shadow: var(--pk-shadow-focus);
            border-radius: var(--pk-radius-sm);
        }

        .icon-button:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }
    }
`, je = [
	"aria-labelledby",
	"aria-describedby",
	"aria-invalid",
	"aria-errormessage",
	"aria-required",
	"aria-label"
];
function Me(e) {
	return e.hasAttribute("aria-labelledby") || e.hasAttribute("aria-describedby") || e.hasAttribute("aria-errormessage");
}
function Ne(e, t) {
	for (let n of je) {
		let r = e.getAttribute(n);
		r === null ? t.removeAttribute(n) : t.setAttribute(n, r);
	}
}
function Pe({ control: e, labelId: t, instructionsId: n, hasLabel: r, hasInstructions: i, required: a = !1, invalid: o = !1 }) {
	r && t ? e.setAttribute("aria-labelledby", t) : e.removeAttribute("aria-labelledby"), i && n ? e.setAttribute("aria-describedby", n) : e.removeAttribute("aria-describedby"), a ? e.setAttribute("aria-required", "true") : e.removeAttribute("aria-required"), o ? e.setAttribute("aria-invalid", "true") : e.removeAttribute("aria-invalid"), e.removeAttribute("aria-errormessage");
}
var Fe = class {
	constructor(e, t, n) {
		this.host = e, this.getTarget = t, this.onSync = n;
	}
	connect() {
		this.sync(), this.observer = new MutationObserver(() => {
			this.sync();
		}), this.observer.observe(this.host, {
			attributes: !0,
			attributeFilter: [...je]
		});
	}
	disconnect() {
		this.observer?.disconnect(), this.observer = void 0;
	}
	sync() {
		if (!Me(this.host)) {
			this.onSync?.();
			return;
		}
		let e = this.getTarget();
		e && Ne(this.host, e);
	}
}, Ie = class extends Event {
	constructor() {
		super("pk-invalid", {
			bubbles: !0,
			cancelable: !1,
			composed: !0
		});
	}
};
function Le() {
	return {
		observedAttributes: ["custom-error"],
		checkValidity(e) {
			let t = {
				message: "",
				isValid: !0,
				invalidKeys: []
			};
			return e.customError && (t.message = e.customError, t.isValid = !1, t.invalidKeys.push("customError")), t;
		}
	};
}
var A = class extends T {
	static {
		this.formAssociated = !0;
	}
	static get validators() {
		return [Le()];
	}
	static get observedAttributes() {
		let e = new Set(super.observedAttributes ?? []);
		for (let t of this.validators) for (let n of t.observedAttributes ?? []) e.add(n);
		return [...e];
	}
	constructor() {
		super(), this.internals = this.attachInternals(), this.assumeInteractionOn = ["input"], this.validators = [], this.name = null, this.disabled = !1, this.required = !1, this.customError = null, this.valueHasChanged = !1, this.hasInteracted = !1, this.emittedEvents = [], this.emitInvalid = (e) => {
			e.target === this && (this.hasInteracted = !0, this.dispatchEvent(new Ie()));
		}, this.handleInteraction = (e) => {
			this.emittedEvents.includes(e.type) || this.emittedEvents.push(e.type), this.emittedEvents.length >= this.assumeInteractionOn.length && (this.hasInteracted = !0, this.updateValidity());
		}, this.addEventListener("invalid", this.emitInvalid);
	}
	connectedCallback() {
		super.connectedCallback();
		for (let e of this.assumeInteractionOn) this.addEventListener(e, this.handleInteraction);
		this.updateValidity();
	}
	disconnectedCallback() {
		this.hostAriaMirror?.disconnect(), this.hostAriaMirror = void 0;
		for (let e of this.assumeInteractionOn) this.removeEventListener(e, this.handleInteraction);
		this.removeEventListener("invalid", this.emitInvalid), super.disconnectedCallback();
	}
	updated(e) {
		e.has("customError") && this.setCustomValidity(this.customError ?? ""), e.has("disabled") && this.setState("disabled", !!this.disabled), (e.has("value") || e.has("checked") || e.has("disabled") || e.has("required") || e.has("name")) && this.syncFormValue(), this.updateValidity(), super.updated(e), this.syncHostAriaMirror();
	}
	firstUpdated(e) {
		super.firstUpdated(e), this.connectHostAriaMirror();
	}
	getAriaMirrorTarget() {
		return this.input ?? null;
	}
	syncStandaloneAria() {}
	connectHostAriaMirror() {
		this.hostAriaMirror?.disconnect(), this.hostAriaMirror = new Fe(this, () => this.getAriaMirrorTarget(), () => this.syncStandaloneAria()), this.hostAriaMirror.connect();
	}
	syncHostAriaMirror() {
		this.hostAriaMirror?.sync();
	}
	formResetCallback() {
		this.resetValidity(), this.hasInteracted = !1, this.valueHasChanged = !1, this.emittedEvents = [], this.resetToDefaultValue(), this.syncFormValue(), this.updateValidity();
	}
	formDisabledCallback(e) {
		this.disabled = e, this.updateValidity();
	}
	formStateRestoreCallback(e, t) {
		this.restoreFormState(e), this.syncFormValue(), this.updateValidity();
	}
	set form(e) {
		e ? this.setAttribute("form", e) : this.removeAttribute("form");
	}
	get form() {
		return this.internals.form;
	}
	get labels() {
		return this.internals.labels;
	}
	get validity() {
		return this.internals.validity;
	}
	get willValidate() {
		return this.internals.willValidate;
	}
	get validationMessage() {
		return this.internals.validationMessage;
	}
	getForm() {
		return this.internals.form;
	}
	checkValidity() {
		return this.updateValidity(), this.internals.checkValidity();
	}
	reportValidity() {
		return this.updateValidity(), this.hasInteracted = !0, this.internals.reportValidity();
	}
	resetValidity() {
		this.setCustomValidity(""), this.internals.setValidity({}), this.syncCustomStates();
	}
	setCustomValidity(e) {
		if (!e) {
			this.customError = null, this.internals.setValidity({}), this.syncCustomStates();
			return;
		}
		this.customError = e;
		let t = this.validationTarget;
		t instanceof HTMLElement ? this.internals.setValidity({ customError: !0 }, e, t) : this.internals.setValidity({ customError: !0 }, e), this.syncCustomStates();
	}
	get validationTarget() {
		return this.input;
	}
	get allValidators() {
		return [...this.constructor.validators ?? [], ...this.validators ?? []];
	}
	setFormValue(e, t) {
		this.internals.setFormValue(e, t ?? e);
	}
	setValue(e, t) {
		this.setFormValue(e, t ?? e);
	}
	updateValidity() {
		if (this.disabled || this.hasAttribute("disabled") || !this.willValidate) {
			this.internals.setValidity({}), this.syncCustomStates();
			return;
		}
		let e = this.allValidators;
		if (!e.length) return;
		let t = { customError: !!this.customError }, n = "", r = this.validationTarget;
		for (let r of e) {
			let { isValid: e, message: i, invalidKeys: a } = r.checkValidity(this);
			if (!e) {
				n ||= i;
				for (let e of a) t[e] = !0;
			}
		}
		n ||= this.validationMessage, r instanceof HTMLElement ? this.internals.setValidity(t, n, r) : this.internals.setValidity(t, n), this.syncCustomStates();
	}
	syncCustomStates() {
		let e = this.internals.validity.valid;
		this.setState("required", this.required), this.setState("optional", !this.required), this.setState("invalid", !e), this.setState("valid", e), this.setState("user-invalid", !e && this.hasInteracted), this.setState("user-valid", e && this.hasInteracted);
	}
	setState(e, t) {
		let n = this.internals.states;
		n && (t ? n.add(e) : n.delete(e));
	}
	syncFormValue() {}
	resetToDefaultValue() {}
	restoreFormState(e) {}
};
s([t({ reflect: !0 })], A.prototype, "name", void 0), s([t({
	type: Boolean,
	reflect: !0
})], A.prototype, "disabled", void 0), s([t({
	type: Boolean,
	reflect: !0
})], A.prototype, "required", void 0), s([t({
	attribute: "custom-error",
	reflect: !0
})], A.prototype, "customError", void 0), s([t({
	attribute: !1,
	state: !0
})], A.prototype, "valueHasChanged", void 0), s([t({
	attribute: !1,
	state: !0
})], A.prototype, "hasInteracted", void 0);
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/required-validator-I58oJGZ3.js
function Re(e = {}) {
	let { validationElement: t, validationProperty: n } = e;
	!t && typeof document < "u" && (t = Object.assign(document.createElement("input"), { required: !0 })), n ||= "value";
	let r = {
		observedAttributes: ["required"],
		message: t?.validationMessage ?? "Please fill out this field.",
		checkValidity(e) {
			let t = {
				message: "",
				isValid: !0,
				invalidKeys: []
			};
			if (!e.required) return t;
			let i = e[n];
			return i != null && i !== !1 && i !== "" || (t.isValid = !1, t.message = typeof r.message == "function" ? r.message(e) : r.message ?? "", t.invalidKeys.push("valueMissing")), t;
		}
	};
	return r;
}
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/field-labels-pY-gQDwQ.js
function ze(e, t) {
	return t || (e.getAttribute("hint") ?? "");
}
function Be(e, t, n = !1) {
	return !!t || e("instructions", n) || e("hint");
}
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/pk-radio-group-BAqRdR_z.js
var Ve = i`
    @layer pk-component {
        :host {
            display: block;
            font-family: var(--pk-font-family);
            font-size: var(--pk-font-size-base);
            line-height: var(--pk-line-height);
        }

        .group {
            display: grid;
            gap: 0.375rem;
        }

        .group--horizontal {
            grid-auto-flow: column;
            grid-auto-columns: max-content;
            align-items: center;
        }
    }
`, j = class extends A {
	constructor(...e) {
		super(...e), this.assumeInteractionOn = ["change"], this.hasSlotController = new g(this, "instructions", "hint", "label"), this._value = null, this.defaultValue = "", this.orientation = "vertical", this.invalid = !1, this.label = "", this.instructions = "", this.ariaLabel = null, this.items = [], this.syncItems = () => {
			this.items = this.getAllRadios(), this.applySelection();
		}, this.handleRadioClick = (e) => {
			let t = e.target.closest("pk-radio");
			if (!t || t.disabled || t.forceDisabled || this.disabled) return;
			let n = this.value;
			this.value = t.value, this.applySelection(), this.value !== n && this.emitValueChange();
		}, this.handleKeyDown = (e) => {
			if (![
				"ArrowUp",
				"ArrowDown",
				"ArrowLeft",
				"ArrowRight",
				" ",
				"Home",
				"End"
			].includes(e.key) || this.disabled) return;
			let t = this.getEnabledItems();
			if (t.length === 0) return;
			e.preventDefault();
			let n = this.value, r = t.find((e) => e.checked) ?? t[0], i = t.indexOf(r);
			if (e.key !== " ") {
				if (e.key === "Home") i = 0;
				else if (e.key === "End") i = t.length - 1;
				else {
					let n = ["ArrowUp", "ArrowLeft"].includes(e.key) ? -1 : 1;
					i += n, i < 0 && (i = t.length - 1), i >= t.length && (i = 0);
				}
			}
			this.value = t[i].value, this.applySelection(), t[i].focusControl(), this.value !== n && this.emitValueChange();
		};
	}
	static {
		this.shadowRootOptions = {
			mode: "open",
			delegatesFocus: !0
		};
	}
	static {
		this.styles = [Ae, Ve];
	}
	static get validators() {
		return [...super.validators, Re()];
	}
	get value() {
		return this.valueHasChanged ? this._value ?? "" : this._value ?? this.defaultValue ?? "";
	}
	set value(e) {
		this._value = e == null ? null : String(e), this.valueHasChanged = !0;
	}
	syncFormValue() {
		this.setFormValue(this.value || null);
	}
	resetToDefaultValue() {
		this._value = null, this.applySelection();
	}
	restoreFormState(e) {
		typeof e == "string" && (this.value = e, this.applySelection());
	}
	get validationTarget() {
		return this.getAllRadios().find((e) => !e.disabled) ?? this.getAllRadios()[0];
	}
	connectedCallback() {
		this.instructions = ze(this, this.instructions), super.connectedCallback(), this.addEventListener("keydown", this.handleKeyDown), this.addEventListener("click", this.handleRadioClick);
	}
	disconnectedCallback() {
		this.removeEventListener("keydown", this.handleKeyDown), this.removeEventListener("click", this.handleRadioClick), super.disconnectedCallback();
	}
	updated(e) {
		(e.has("value") || e.has("disabled") || e.has("invalid") || e.has("name")) && this.applySelection(), super.updated(e);
	}
	formResetCallback() {
		this._value = null, super.formResetCallback(), this.applySelection();
	}
	focus(e) {
		if (this.disabled) return;
		let t = this.getEnabledItems();
		(t.find((e) => e.checked) ?? t[0])?.focusControl(e);
	}
	getAllRadios() {
		return [...this.querySelectorAll("pk-radio")];
	}
	getEnabledItems() {
		return this.items.filter((e) => !e.disabled && !this.disabled);
	}
	applySelection() {
		let e = this.getAllRadios();
		this.items = e;
		let t = this.getEnabledItems(), n = t.find((e) => e.value === this.value);
		for (let t of e) {
			let e = t.value === this.value, n = t.hasAttribute("disabled") || t.disabled && !t.forceDisabled;
			t.checked = e, t.disabled = this.disabled || n, t.invalid = this.invalid, t.required = this.required, t.forceDisabled = this.disabled;
		}
		if (this.disabled) for (let t of e) t.tabIndex = -1;
		else {
			if (n) for (let e of t) e.tabIndex = e.checked ? 0 : -1;
			else t.length > 0 && t.forEach((e, t) => {
				e.tabIndex = t === 0 ? 0 : -1;
			});
			for (let t of e.filter((e) => e.disabled)) t.tabIndex = -1;
		}
	}
	emitValueChange() {
		this.dispatchEvent(new CustomEvent("pk-change", {
			detail: { value: this.value },
			bubbles: !0,
			composed: !0
		})), this.dispatchEvent(new Event("input", {
			bubbles: !0,
			composed: !0
		})), this.dispatchEvent(new Event("change", {
			bubbles: !0,
			composed: !0
		}));
	}
	render() {
		let t = this.hasSlotController.test("label"), r = Be((e, t) => this.hasSlotController.test(e, t), this.instructions), i = !!this.label || t;
		return e`
            <div part="form-control" class="form-control">
                ${i ? e`
                        <div part="label" class="form-control__label" id="label">
                            <slot name="label">${this.label}</slot>
                        </div>
                    ` : n}

                ${r ? e`
                        <div part="instructions" class="form-control__instructions" id="instructions">
                            <slot name="instructions">${this.instructions}</slot>
                            <slot name="hint"></slot>
                        </div>
                    ` : n}

                <div
                    part="radios"
                    class=${w({
			group: !0,
			"pk-radio-group": !0,
			"group--horizontal": this.orientation === "horizontal",
			"pk-radio-group--horizontal": this.orientation === "horizontal"
		})}
                    role="radiogroup"
                    aria-labelledby=${i ? "label" : n}
                    aria-label=${i ? n : this.ariaLabel ?? n}
                    aria-describedby=${r ? "instructions" : n}
                    aria-invalid=${this.invalid ? "true" : n}
                    aria-required=${this.required ? "true" : n}
                >
                    <slot @slotchange=${this.syncItems}></slot>
                </div>
            </div>
        `;
	}
};
s([o()], j.prototype, "value", null), s([t({
	attribute: "value",
	reflect: !0
})], j.prototype, "defaultValue", void 0), s([t({ reflect: !0 })], j.prototype, "orientation", void 0), s([t({
	type: Boolean,
	reflect: !0
})], j.prototype, "invalid", void 0), s([t()], j.prototype, "label", void 0), s([t()], j.prototype, "instructions", void 0), s([t({ attribute: "aria-label" })], j.prototype, "ariaLabel", void 0), s([E("slot:not([name])")], j.prototype, "defaultSlot", void 0), s([o()], j.prototype, "items", void 0), j = s([r("pk-radio-group")], j);
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/pk-status-CdiRBRyh.js
var He = i`
    @layer pk-component {
        :host {
            display: inline-block;
            flex-shrink: 0;
            width: 0.75rem;
            height: 0.75rem;
            border-radius: 9999px;
            vertical-align: middle;
        }

        .status {
            display: block;
            width: 100%;
            height: 100%;
            border-radius: inherit;
        }

        :host([status='all']) .status { background: linear-gradient(60deg, #184cef, #e5422b); }
        :host([status='on']) .status,
        :host([status='live']) .status,
        :host([status='active']) .status,
        :host([status='enabled']) .status,
        :host([status='teal']) .status,
        :host([status='turquoise']) .status { background: var(--pk-color-teal-550); }
        :host([status='off']) .status,
        :host([status='suspended']) .status,
        :host([status='expired']) .status,
        :host([status='red']) .status { background: var(--pk-color-red-600); }
        :host([status='warning']) .status { background: var(--pk-color-amber-100); }
        :host([status='pending']) .status,
        :host([status='orange']) .status { background: var(--pk-color-orange-400); }
        :host([status='amber']) .status { background: var(--pk-color-amber-500); }
        :host([status='yellow']) .status { background: var(--pk-color-yellow-500); }
        :host([status='lime']) .status { background: var(--pk-color-lime-500); }
        :host([status='green']) .status { background: var(--pk-color-green-600); }
        :host([status='emerald']) .status { background: var(--pk-color-emerald-500); }
        :host([status='cyan']) .status { background: var(--pk-color-cyan-500); }
        :host([status='sky']) .status { background: var(--pk-color-sky-500); }
        :host([status='blue']) .status { background: var(--pk-color-blue-600); }
        :host([status='indigo']) .status { background: var(--pk-color-indigo-500); }
        :host([status='violet']) .status { background: var(--pk-color-violet-500); }
        :host([status='purple']) .status { background: var(--pk-color-purple-500); }
        :host([status='fuchsia']) .status { background: var(--pk-color-fuchsia-500); }
        :host([status='pink']) .status { background: var(--pk-color-pink-500); }
        :host([status='rose']) .status { background: var(--pk-color-rose-500); }
        :host([status='light']) .status { background: var(--pk-color-gray-100); }
        :host([status='gray']) .status,
        :host([status='grey']) .status { background: var(--pk-color-gray-300); }
        :host([status='white']) .status { background: var(--pk-color-white); }
        :host([status='black']) .status { background: var(--pk-color-gray-800); }
        :host([status='disabled']) .status,
        :host([status='inactive']) .status {
            /* Ring color is overridable for inverted / selected surfaces. */
            background: transparent;
            box-shadow: inset 0 0 0 2px var(--pk-status-ring, var(--pk-color-gray-500));
        }
    }
`, Ue = class extends T {
	constructor(...e) {
		super(...e), this.status = "on", this.ariaLabel = null;
	}
	static {
		this.styles = He;
	}
	render() {
		return e`
            <span
                part="base"
                class="status"
                role="status"
                aria-label=${this.ariaLabel ?? n}
            ></span>
        `;
	}
};
s([t({ reflect: !0 })], Ue.prototype, "status", void 0), s([t({ attribute: "aria-label" })], Ue.prototype, "ariaLabel", void 0), Ue = s([r("pk-status")], Ue);
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/pk-status-label-CopQRSZz.js
var We = i`
    @layer pk-component {
        :host {
            display: inline-flex;
            vertical-align: middle;
            max-width: 100%;
            font-family: var(--pk-font-family, system-ui, sans-serif);
        }
        .base {
            --_text: var(--pk-color-gray-900);
            --_tint: var(
                --pk-status-label-neutral-tint,
                var(--pk-color-gray-500)
            );
            display: inline-flex;
            align-items: start;
            justify-content: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem 0.25rem 0.25rem;
            border-radius: 0.6875rem;
            background: var(
                --pk-status-label-background,
                color-mix(in srgb, var(--_tint) 15%, transparent)
            );
            color: var(--pk-status-label-color, var(--_text));
            font-size: 0.6875rem;
            font-weight: 500;
            line-height: 1.1;
            text-transform: uppercase;
            max-width: 100%;
            box-sizing: border-box;
        }
        .label {
            min-width: 0;
            overflow-wrap: anywhere;
        }
        pk-status {
            width: 0.75rem;
            height: 0.75rem;
        }
        .base[data-tone='red'] {
            --_text: var(--pk-color-red-900);
            --_tint: var(--pk-color-red-600);
        }
        .base[data-tone='orange'] {
            --_text: var(--pk-color-orange-900);
            --_tint: var(--pk-color-orange-400);
        }
        .base[data-tone='amber'] {
            --_text: var(--pk-color-amber-900);
            --_tint: var(--pk-color-amber-500);
        }
        .base[data-tone='yellow'] {
            --_text: var(--pk-color-yellow-900);
            --_tint: var(--pk-color-yellow-500);
        }
        .base[data-tone='lime'] {
            --_text: var(--pk-color-lime-900);
            --_tint: var(--pk-color-lime-500);
        }
        .base[data-tone='green'] {
            --_text: var(--pk-color-green-900);
            --_tint: var(--pk-color-green-500);
        }
        .base[data-tone='emerald'] {
            --_text: var(--pk-color-emerald-900);
            --_tint: var(--pk-color-emerald-500);
        }
        .base[data-tone='teal'] {
            --_text: var(--pk-color-teal-900);
            --_tint: var(--pk-color-teal-550);
        }
        .base[data-tone='cyan'] {
            --_text: var(--pk-color-cyan-900);
            --_tint: var(--pk-color-cyan-500);
        }
        .base[data-tone='sky'] {
            --_text: var(--pk-color-sky-900);
            --_tint: var(--pk-color-sky-500);
        }
        .base[data-tone='blue'] {
            --_text: var(--pk-color-blue-900);
            --_tint: var(--pk-color-blue-500);
        }
        .base[data-tone='indigo'] {
            --_text: var(--pk-color-indigo-900);
            --_tint: var(--pk-color-indigo-500);
        }
        .base[data-tone='violet'] {
            --_text: var(--pk-color-violet-900);
            --_tint: var(--pk-color-violet-500);
        }
        .base[data-tone='purple'] {
            --_text: var(--pk-color-purple-900);
            --_tint: var(--pk-color-purple-500);
        }
        .base[data-tone='fuchsia'] {
            --_text: var(--pk-color-fuchsia-900);
            --_tint: var(--pk-color-fuchsia-500);
        }
        .base[data-tone='pink'] {
            --_text: var(--pk-color-pink-900);
            --_tint: var(--pk-color-pink-500);
        }
        .base[data-tone='rose'] {
            --_text: var(--pk-color-rose-900);
            --_tint: var(--pk-color-rose-500);
        }
        .base[data-tone='white'] {
            --_text: var(--pk-color-black);
            --_tint: var(--pk-color-gray-500);
        }
        .base[data-tone='black'] {
            --_text: var(--pk-color-black);
            --_tint: var(--pk-color-gray-1000);
        }
        .base[data-indicator='white'] pk-status::part(base) {
            box-shadow: inset 0 0 0 1px var(--pk-color-gray-300);
        }
    }
`, Ge = {
	red: "red",
	orange: "orange",
	amber: "amber",
	yellow: "yellow",
	lime: "lime",
	green: "green",
	emerald: "emerald",
	teal: "teal",
	cyan: "cyan",
	sky: "sky",
	blue: "blue",
	indigo: "indigo",
	violet: "violet",
	purple: "purple",
	fuchsia: "fuchsia",
	pink: "pink",
	rose: "rose",
	gray: "gray",
	white: "white",
	black: "black",
	all: "gray",
	on: "teal",
	live: "teal",
	active: "teal",
	enabled: "teal",
	off: "red",
	suspended: "red",
	expired: "red",
	warning: "amber",
	pending: "orange",
	turquoise: "teal",
	light: "gray",
	grey: "gray",
	disabled: "gray",
	inactive: "gray"
}, Ke = class extends T {
	constructor(...e) {
		super(...e), this.status = "gray";
	}
	static {
		this.styles = We;
	}
	render() {
		let t = Object.hasOwn(Ge, this.status);
		return e`<span
            part="base"
            class="base"
            data-tone=${t ? Ge[this.status] : "gray"}
            data-indicator=${this.status}
        >
            <pk-status
                part="indicator"
                aria-hidden="true"
                .status=${t ? this.status : "gray"}
            ></pk-status>
            <span part="label" class="label"><slot>${this.status}</slot></span>
        </span>`;
	}
};
s([t({ reflect: !0 })], Ke.prototype, "status", void 0), Ke = s([r("pk-status-label")], Ke);
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/mirror-validator-BkZlEv1Z.js
function qe() {
	return { checkValidity(e) {
		let t = e.input, n = {
			message: "",
			isValid: !0,
			invalidKeys: []
		};
		if (!t) return n;
		let r = !0;
		if ("checkValidity" in t && typeof t.checkValidity == "function" && (r = t.checkValidity()), r) return n;
		if (n.isValid = !1, "validationMessage" in t && typeof t.validationMessage == "string" && (n.message = t.validationMessage), !("validity" in t) || !t.validity) return n.invalidKeys.push("customError"), n;
		for (let e of Object.keys(t.validity)) {
			if (e === "valid") continue;
			let r = e;
			t.validity[r] && n.invalidKeys.push(r);
		}
		return n;
	} };
}
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/active-elements-739kISON.js
function* Je(e = document.activeElement) {
	e != null && (yield e, "shadowRoot" in e && e.shadowRoot && e.shadowRoot.mode !== "closed" && (yield* Je(e.shadowRoot.activeElement)));
}
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/focus-DcVUPVqn.js
var Ye = 0;
function Xe(e = "pk") {
	return Ye += 1, `${e}-${Ye}`;
}
// ../../../plugin-kit/plugin-kit/node_modules/lit-html/directive-helpers.js
var { I: Ze } = _, Qe = (e) => e, $e = (e) => e.strings === void 0, et = () => document.createComment(""), tt = (e, t, n) => {
	let r = e._$AA.parentNode, i = t === void 0 ? e._$AB : t._$AA;
	if (n === void 0) n = new Ze(r.insertBefore(et(), i), r.insertBefore(et(), i), e, e.options);
	else {
		let t = n._$AB.nextSibling, a = n._$AM, o = a !== e;
		if (o) {
			let t;
			n._$AQ?.(e), n._$AM = e, n._$AP !== void 0 && (t = e._$AU) !== a._$AU && n._$AP(t);
		}
		if (t !== i || o) {
			let e = n._$AA;
			for (; e !== t;) {
				let t = Qe(e).nextSibling;
				Qe(r).insertBefore(e, i), e = t;
			}
		}
	}
	return n;
}, nt = (e, t, n = e) => (e._$AI(t, n), e), rt = {}, it = (e, t = rt) => e._$AH = t, at = (e) => e._$AH, ot = (e) => {
	e._$AR(), e._$AA.remove();
}, st = b(class extends h {
	constructor(e) {
		if (super(e), e.type !== m.PROPERTY && e.type !== m.ATTRIBUTE && e.type !== m.BOOLEAN_ATTRIBUTE) throw Error("The `live` directive is not allowed on child or event bindings");
		if (!$e(e)) throw Error("`live` bindings can only contain a single expression");
	}
	render(e) {
		return e;
	}
	update(e, [t]) {
		if (t === v || t === n) return t;
		let r = e.element, i = e.name;
		if (e.type === m.PROPERTY) {
			if (t === r[i]) return v;
		} else if (e.type === m.BOOLEAN_ATTRIBUTE) {
			if (!!t === r.hasAttribute(i)) return v;
		} else if (e.type === m.ATTRIBUTE && r.getAttribute(i) === t + "") return v;
		return it(e), t;
	}
}), ct = (e) => e ?? n, lt = i`
    @layer pk-component {
        :host {
            display: block;
            width: 100%;
            font-family: var(--pk-font-family);
            font-size: var(--pk-font-size-base);
            line-height: var(--pk-line-height);
        }

        .textarea {
            display: block;
            width: 100%;
            min-height: 5rem;
            margin: 0;
            padding: 7px 10px;
            border: var(--pk-input-border);
            border-radius: var(--pk-textarea-border-radius, var(--pk-radius-md));
            background: var(--pk-input-bg);
            background-clip: padding-box;
            /* Craft CP body / field value text. */
            color: var(--pk-color-gray-700);
            font: inherit;
            line-height: 1.4;
            resize: vertical;
            appearance: none;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.12s ease, box-shadow 0.12s ease;
        }

        .textarea::placeholder {
            color: var(--pk-input-placeholder-color, var(--pk-color-gray-400));
        }

        /* Craft: focus is box-shadow only — do not also flip border-color (double ring). */
        :host(:not([invalid]):not(:state(user-invalid))) .textarea:focus,
        :host(:not([invalid]):not(:state(user-invalid))) .textarea:focus-visible,
        :host([data-state='focus-visible']:not([invalid]):not(:state(user-invalid))) .textarea {
            box-shadow: var(--pk-input-focus-shadow);
        }

        .textarea:disabled {
            cursor: not-allowed;
            opacity: 0.5;
            background: var(--pk-color-gray-50);
        }

        :host([invalid]) .textarea,
        :host(:state(user-invalid)) .textarea {
            border-color: var(--pk-color-rose-600);
        }

        :host([invalid]) .textarea:focus,
        :host([invalid]) .textarea:focus-visible,
        :host([invalid][data-state='focus-visible']) .textarea,
        :host(:state(user-invalid)) .textarea:focus,
        :host(:state(user-invalid)) .textarea:focus-visible {
            box-shadow: var(--pk-input-invalid-focus-shadow);
        }

        /* Editable-table cells (v1): flush into the row and fill cell height.
         * Chain height through form-control — percentage on .textarea alone
         * doesn't resolve when the wrapper sizes to content (rows / min-height). */
        :host([fit-cell]),
        :host([data-editable-table-input]) {
            display: block;
            height: 100%;
            min-height: 100%;
            box-sizing: border-box;
            overflow: hidden;
        }

        :host([fit-cell]) .form-control,
        :host([data-editable-table-input]) .form-control {
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 100%;
        }

        :host([fit-cell]) .textarea,
        :host([data-editable-table-input]) .textarea {
            flex: 1 1 auto;
            border: none;
            border-radius: 0;
            background: transparent;
            box-shadow: none;
            height: 100%;
            min-height: 0;
            max-height: 100%;
            /* Match text-cell inset (v1 py-1.5 / px-2). 0.5rem block padding +
             * line-height 1.4 overflows the 34px et cell and shows a scrollbar
             * even for empty / single-line notes. */
            padding: 0.375rem 0.5rem;
            line-height: 1.25;
            overflow-x: hidden;
            overflow-y: auto;
            resize: none;
        }

        :host([fit-cell]:not([invalid]):not(:state(user-invalid))) .textarea:focus,
        :host([fit-cell]:not([invalid]):not(:state(user-invalid))) .textarea:focus-visible,
        :host([fit-cell][data-state='focus-visible']:not([invalid]):not(:state(user-invalid))) .textarea,
        :host([data-editable-table-input]:not([invalid]):not(:state(user-invalid))) .textarea:focus,
        :host([data-editable-table-input]:not([invalid]):not(:state(user-invalid))) .textarea:focus-visible,
        :host([data-editable-table-input][data-state='focus-visible']:not([invalid]):not(:state(user-invalid))) .textarea {
            border: none;
            box-shadow: inset 0 0 0 1px var(--pk-color-gray-200);
        }

        :host([fit-cell][invalid]) .textarea,
        :host([fit-cell]:state(user-invalid)) .textarea,
        :host([fit-cell][invalid]) .textarea:focus,
        :host([fit-cell][invalid]) .textarea:focus-visible,
        :host([fit-cell]:state(user-invalid)) .textarea:focus,
        :host([fit-cell]:state(user-invalid)) .textarea:focus-visible,
        :host([data-editable-table-input][invalid]) .textarea,
        :host([data-editable-table-input]:state(user-invalid)) .textarea,
        :host([data-editable-table-input][invalid]) .textarea:focus,
        :host([data-editable-table-input][invalid]) .textarea:focus-visible,
        :host([data-editable-table-input]:state(user-invalid)) .textarea:focus,
        :host([data-editable-table-input]:state(user-invalid)) .textarea:focus-visible {
            border: none;
            box-shadow: inset 0 0 0 1px var(--pk-color-rose-600);
        }
    }
`, M = class extends A {
	constructor(...e) {
		super(...e), this.assumeInteractionOn = ["blur", "input"], this.hasSlotController = new g(this, "instructions", "hint", "label"), this.controlId = Xe("pk-textarea"), this.placeholder = "", this._value = null, this.defaultValue = null, this.size = "default", this.label = "", this.instructions = "", this.readonly = !1, this.invalid = !1, this.fitCell = !1, this.withLabel = !1, this.withInstructions = !1;
	}
	static {
		this.styles = [Ae, lt];
	}
	static get validators() {
		return [
			...super.validators,
			qe(),
			Re()
		];
	}
	get value() {
		return this.valueHasChanged ? this._value ?? "" : this._value ?? this.defaultValue ?? "";
	}
	set value(e) {
		let t = e ?? "";
		this._value !== t && (this.valueHasChanged = !0, this._value = t);
	}
	connectedCallback() {
		this.instructions = ze(this, this.instructions), this.hasAttribute("with-hint") && (this.withInstructions = !0), super.connectedCallback();
	}
	syncFormValue() {
		this.setValue(this.value || "");
	}
	resetToDefaultValue() {
		this.valueHasChanged = !1, this._value = null;
	}
	restoreFormState(e) {
		typeof e == "string" && (this.value = e);
	}
	formResetCallback() {
		this.valueHasChanged = !1, this._value = null, this.input && (this.input.value = this.defaultValue ?? ""), super.formResetCallback();
	}
	updated(e) {
		(e.has("value") || e.has("defaultValue")) && this.setState("blank", !this.value), super.updated(e);
	}
	syncStandaloneAria() {
		if (!this.input) return;
		let e = this.hasLabelContent(), t = this.hasInstructionsContent();
		Pe({
			control: this.input,
			labelId: `${this.controlId}-label`,
			instructionsId: `${this.controlId}-instructions`,
			hasLabel: e,
			hasInstructions: t,
			required: this.required,
			invalid: this.invalid || !this.internals.validity.valid
		});
	}
	hasLabelContent() {
		return !!this.label || this.hasSlotController.test("label", this.withLabel);
	}
	hasInstructionsContent() {
		return Be((e, t) => this.hasSlotController.test(e, t), this.instructions, this.withInstructions);
	}
	focus(e) {
		this.input?.focus(e);
	}
	blur() {
		this.input?.blur();
	}
	handleInput() {
		this.value = this.input.value, this.dispatchEvent(new Event("input", {
			bubbles: !0,
			composed: !0
		}));
	}
	handleChange(e) {
		this.value = this.input.value, e.stopPropagation(), this.dispatchEvent(new Event("change", {
			bubbles: !0,
			composed: !0
		}));
	}
	render() {
		let t = this.hasLabelContent(), r = this.hasInstructionsContent();
		return e`
            <div part="form-control" class="form-control">
                ${t ? e`
                        <label
                            part="label"
                            class="form-control__label"
                            id=${`${this.controlId}-label`}
                            for=${`${this.controlId}-control`}
                        >
                            <slot name="label">${this.label}</slot>
                        </label>
                    ` : n}

                ${r ? e`
                        <p
                            part="instructions"
                            class="form-control__instructions"
                            id=${`${this.controlId}-instructions`}
                        >
                            <slot name="instructions">${this.instructions}</slot>
                            <slot name="hint"></slot>
                        </p>
                    ` : n}

                <textarea
                    part="textarea"
                    class="textarea"
                    id=${t ? `${this.controlId}-control` : n}
                    rows=${ct(this.fitCell ? this.rows ?? 1 : this.rows)}
                    .value=${st(this.value)}
                    placeholder=${this.placeholder || n}
                    maxlength=${ct(this.maxlength)}
                    ?disabled=${this.disabled}
                    ?readonly=${this.readonly}
                    ?required=${this.required}
                    @input=${this.handleInput}
                    @change=${this.handleChange}
                    @focus=${() => this.dispatchEvent(new Event("focus", {
			bubbles: !0,
			composed: !0
		}))}
                    @blur=${() => this.dispatchEvent(new Event("blur", {
			bubbles: !0,
			composed: !0
		}))}
                ></textarea>
            </div>
        `;
	}
};
s([E("textarea")], M.prototype, "input", void 0), s([t()], M.prototype, "placeholder", void 0), s([o()], M.prototype, "value", null), s([t({
	attribute: "value",
	reflect: !0
})], M.prototype, "defaultValue", void 0), s([t({ reflect: !0 })], M.prototype, "size", void 0), s([t()], M.prototype, "label", void 0), s([t()], M.prototype, "instructions", void 0), s([t({
	type: Boolean,
	reflect: !0
})], M.prototype, "readonly", void 0), s([t({
	type: Boolean,
	reflect: !0
})], M.prototype, "invalid", void 0), s([t({
	type: Boolean,
	reflect: !0,
	attribute: "fit-cell"
})], M.prototype, "fitCell", void 0), s([t({ type: Number })], M.prototype, "rows", void 0), s([t({
	type: Number,
	attribute: "max-length"
})], M.prototype, "maxlength", void 0), s([t({
	attribute: "with-label",
	type: Boolean
})], M.prototype, "withLabel", void 0), s([t({
	attribute: "with-instructions",
	type: Boolean
})], M.prototype, "withInstructions", void 0), M = s([r("pk-textarea")], M);
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/styles-Bz7GOec0.js
var ut = i`
    :host {
        display: block;
        min-width: 0;
        color: var(--pk-color-gray-700, #33404d);
        font: var(--pk-font-size-base, 14px)/1.42
            var(--pk-font-family, system-ui, sans-serif);
    }
    * {
        box-sizing: border-box;
    }
    .toolbar,
    .footer,
    .selection {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }
    .header-shell {
        display: flow-root;
    }
    :host([layout='index']) .header-shell {
        /* Keep the frame's 1px outer border clear of the toolbar's 2px mask. */
        margin-block-end: 3px;
        position: sticky;
        top: var(--pk-data-table-header-offset, 0px);
        z-index: 4;
        background: var(--pk-data-table-toolbar-background, var(--pk-color-white, #fff));
        /* Cover the scroll container's inset and the white gap above the table header. */
        box-shadow:
            0 -2px 0 var(--pk-data-table-toolbar-background, var(--pk-color-white, #fff)),
            0 2px 0 var(--pk-data-table-toolbar-background, var(--pk-color-white, #fff));
    }
    .toolbar {
        margin-bottom: 12px;
    }
    .search {
        flex: 1 1 200px;
        min-width: 140px;
    }
    .toolbar-end {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        margin-inline-start: auto;
    }
    .table-frame {
        padding: 2px;
        background: var(--pk-color-white, #fff);
        border-radius: var(--pk-radius-lg, 5px);
        box-shadow: 0 0 0 1px var(--pk-color-gray-200, #cdd8e4);
    }
    .scroll {
        position: relative;
        overflow: auto;
        border-radius: var(--pk-radius-lg, 5px);
    }
    .scroll:focus-visible {
        outline: 2px solid var(--pk-color-blue-500, #247db1);
        outline-offset: 2px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        text-align: start;
    }
    th,
    td {
        padding: var(--pk-data-table-cell-padding, 7px 12px);
        text-align: start;
        vertical-align: middle;
    }
    th {
        padding-block: 14px;
        background: var(--pk-color-gray-50, #f3f7fc);
        color: var(--pk-color-gray-700, #3f4d5a);
        font-size: inherit;
        font-weight: 700;
        white-space: nowrap;
    }
    thead th:first-child {
        border-start-start-radius: var(--pk-radius-lg, 5px);
        border-end-start-radius: var(--pk-radius-lg, 5px);
    }
    thead th:last-child {
        border-start-end-radius: var(--pk-radius-lg, 5px);
        border-end-end-radius: var(--pk-radius-lg, 5px);
    }
    th:not([data-sorted]):has(.sort):hover {
        background: var(--pk-color-gray-100, #e4edf6);
    }
    tbody tr:last-child td {
        border-bottom: 0;
    }
    tbody tr:hover {
        background: var(--pk-color-gray-50, #f3f7fc);
    }
    tbody tr[data-selectable] {
        cursor: default;
    }
    tbody tr[data-selected],
    tbody tr[data-selected]:hover {
        --pk-data-table-cell-color: var(
            --pk-data-table-selected-color,
            var(--pk-color-white, #fff)
        );
        --pk-data-table-cell-muted-color: var(
            --pk-data-table-selected-color,
            var(--pk-color-white, #fff)
        );
        --pk-status-label-color: var(--pk-data-table-cell-color);
        --pk-status-label-neutral-tint: var(--pk-data-table-cell-color);
        --pk-status-ring: var(--pk-color-gray-200);
        background: var(
            --pk-data-table-selected-background,
            var(--pk-color-gray-500, #606d7b)
        );
        color: var(--pk-data-table-cell-color);
    }
    td {
        color: var(--pk-data-table-cell-color, inherit);
        max-width: 33vw;
        overflow-wrap: break-word;
    }
    .cell-link {
        display: block;
        max-width: 33vw;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .cell-handle {
        display: inline-flex;
        align-items: center;
        max-width: 100%;
        padding-inline-start: 6px;
        border: 1px solid color-mix(in srgb, currentColor 18%, transparent);
        border-radius: var(--pk-radius-md, 4px);
        color: var(--pk-data-table-cell-muted-color, var(--pk-color-gray-550, #596673));
    }
    .cell-handle code {
        font-family: var(--pk-font-family-mono, ui-monospace, monospace);
        font-size: 12px;
        overflow-wrap: anywhere;
    }
    .cell-handle pk-copy-button {
        flex: none;
        --pk-btn-height-default: 24px;
        --pk-btn-icon-size-default: 12px;
        --pk-copy-button-color: currentColor;
        --pk-copy-button-hover-color: currentColor;
        --pk-copy-button-background: transparent;
        --pk-copy-button-hover-background: transparent;
        --pk-copy-button-border-color: transparent;
    }
    .cell-handle .icon {
        width: var(--pk-btn-icon-size-default, 12px);
        height: var(--pk-btn-icon-size-default, 12px);
    }
    .row-actions {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 4px;
    }
    .row-handle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: var(--pk-btn-height-xs, 26px);
        height: var(--pk-btn-height-xs, 26px);
        padding: 0;
        border: 0;
        border-radius: var(--pk-radius-md, 4px);
        background: transparent;
        color: var(--pk-data-table-cell-muted-color, var(--pk-color-gray-550, #596673));
        cursor: move;
        touch-action: none;
        user-select: none;
        font: inherit;
    }
    .row-handle:focus-visible {
        outline: 2px solid var(--pk-color-blue-500, #247db1);
        outline-offset: 2px;
    }
    .row-handle[aria-disabled='true'] {
        cursor: default;
        opacity: 0.4;
    }
    tbody[data-row-dragging] tr {
        position: relative;
        background: var(--pk-color-white, #fff);
    }
    tbody tr[data-dragging] {
        z-index: 2;
        box-shadow: var(--pk-shadow-popup, 0 4px 12px rgb(0 0 0 / 0.15));
    }
    .row-order-hint {
        margin: 0 0 8px;
        color: var(--pk-color-gray-550, #596673);
    }
    .row-action::part(base) {
        color: var(--pk-data-table-cell-muted-color, var(--pk-color-gray-550, #596673));
    }
    .row-action[data-preset='move']::part(base):not(:disabled) {
        cursor: move;
    }
    tbody tr[data-selected] td {
        background: var(
            --pk-data-table-selected-background,
            var(--pk-color-gray-500, #606d7b)
        );
    }
    thead + tbody tr:first-child td {
        border-block-start: 1px solid var(--pk-color-white, #fff);
    }
    tbody tr + tr td {
        border-top: 1px solid transparent;
    }
    tbody tr[data-selected] + tr[data-selected] td {
        border-top-color: var(--pk-color-gray-300);
    }
    tbody tr[data-selected] a {
        color: inherit;
    }
    tbody tr[data-selected] pk-button[slot='trigger']::part(base) {
        color: var(--pk-data-table-cell-color);
    }
    tbody tr[data-selected] pk-checkbox {
        color: var(--pk-color-gray-700, #33404d);
    }

    .nowrap {
        white-space: nowrap;
        max-width: none;
    }
    .end {
        text-align: end;
        font-variant-numeric: tabular-nums;
    }
    .center {
        text-align: center;
    }
    .small {
        width: 1%;
        white-space: nowrap;
    }
    th[data-sortable] {
        padding: 0;
    }
    .sort {
        border: 0;
        background: none;
        color: inherit;
        font: inherit;
        padding-block: 14px;
        padding-inline: 14px 35px;
        position: relative;
        text-align: inherit;
        cursor: pointer;
        display: flex;
        width: 100%;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
    }
    .sort:focus-visible {
        outline: 2px solid var(--pk-color-blue-500, #247db1);
        outline-offset: -2px;
    }
    a {
        color: var(--pk-color-blue-600, #176696);
        text-decoration: none;
    }
    a:hover {
        text-decoration: underline;
    }
    .footer {
        min-height: 48px;
        padding: 8px;
        background: color-mix(
            in srgb,
            var(--pk-color-gray-100, #e4edf6) 75%,
            transparent
        );
        border-radius: 0 0 var(--pk-radius-lg, 5px) var(--pk-radius-lg, 5px);
        color: var(--pk-color-gray-550, #596673);
        font-size: inherit;
        font-weight: 400;
    }
    .pager {
        display: flex;
        gap: 6px;
        align-items: center;
    }
    .filter-input::part(header),
    .search::part(header) {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip-path: inset(50%);
        white-space: nowrap;
    }
    .column-option {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .column-option {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .range > * {
        flex: 1;
        min-width: 0;
    }
    .column-option {
        position: relative;
        padding: 0;
        min-height: 24px;
        gap: 4px;
    }
    .column-list[data-reordering] .column-option {
        transition: transform 120ms ease;
    }
    .column-option pk-checkbox {
        flex: 1;
    }
    select {
        font: inherit;
        color: inherit;
        background: var(--pk-color-white, #fff);
        border: 1px solid var(--pk-color-gray-250, #cbd5df);
        border-radius: var(--pk-radius-md, 4px);
        padding: 5px 8px;
        max-width: 100%;
    }

    .filter-trigger {
        /* Input adornments default to icon dimensions; this is an interactive control. */
        display: inline-flex;
        max-width: none;
        max-height: none;
    }
    .filter-trigger::part(base) {
        background: transparent;
    }
    .filter-trigger[slot="end"] {
        margin-inline-end: calc(-1 * var(--pk-input-padding-inline, 8px));
    }
    .filter-dialog {
        --pk-dialog-width: min(680px, calc(100vw - 32px));
    }
    .filter-dialog::part(panel) {
        color: var(--pk-color-gray-700);
        box-shadow: var(--pk-shadow-popup);
    }
    .filter-dialog[data-anchored]::part(panel) {
        /* Anchored filters behave like a popup, without the modal entrance/exit delay. */
        animation: none;
        margin: 0;
        inset: auto;
        top: var(--filter-top);
        left: var(--filter-left);
        width: var(--filter-width);
        max-height: var(--filter-max-height);
    }
    .filter-dialog[data-anchored]::part(panel)::backdrop {
        animation: none;
        background: transparent;
    }
    .filter-dialog::part(footer) {
        background: var(--pk-color-white);
        border-top: 0;
        padding: 0 24px 24px;
    }
    .filter-builder {
        padding: 24px;
    }
    .filter-intro {
        margin: 0 0 12px;
        color: var(--pk-color-gray-700);
    }
    .filter-rule {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        padding: 7px;
        border: 1px solid var(--pk-color-gray-200);
        background: var(--pk-color-gray-50);
    }
    .filter-rule:first-of-type {
        border-radius: var(--pk-radius-lg) var(--pk-radius-lg) 0 0;
    }
    .filter-rule + .filter-rule {
        border-top: 0;
    }
    .filter-type,
    .filter-operator {
        height: 34px;
        flex: 0 1 150px;
        min-width: 80px;
    }
    .filter-type {
        font-weight: 600;
    }
    .filter-values,
    .filter-value {
        flex: 1 1 160px;
        min-width: 0;
    }
    .filter-values {
        display: flex;
        gap: 8px;
    }
    .filter-values > * {
        flex: 1;
        min-width: 0;
    }
    .filter-values .filter-input {
        width: 100%;
    }
    .filter-remove {
        flex: none;
        margin-inline-start: auto;
    }
    .filter-add {
        padding: 7px;
        border: 1px dashed var(--pk-color-gray-250);
        border-radius: var(--pk-radius-lg);
    }
    .filter-rule + .filter-add {
        border-top: 0;
        border-radius: 0 0 var(--pk-radius-lg) var(--pk-radius-lg);
    }
    .filter-footer {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
    }
    .filter-footer-spacer {
        flex: 1;
    }
    .filter-error {
        margin: 12px 0 0;
        color: var(--pk-color-rose-600);
    }
    .filter-builder slot[name='filters']::slotted(*) {
        margin-block-start: 16px;
    }
    .filter-dialog:not([data-anchored]) .filter-rule {
        flex-wrap: wrap;
    }
    .filter-dialog:not([data-anchored]) .filter-values,
    .filter-dialog:not([data-anchored]) .filter-value {
        flex-basis: calc(100% - 46px);
        order: 2;
    }
    .filter-dialog:not([data-anchored]) .filter-type,
    .filter-dialog:not([data-anchored]) .filter-operator {
        flex: 1 1 40%;
    }
    .filter-dialog:not([data-anchored]) .filter-remove {
        order: 3;
    }
    @media (max-width: 480px) {
        .filter-builder {
            padding: 16px;
        }
        .filter-dialog::part(footer) {
            padding: 0 16px 16px;
        }
        .filter-footer {
            flex-wrap: wrap;
        }
        .filter-footer pk-button {
            font-size: 13px;
        }
    }
    .selection {
        flex: 1 1 auto;
        justify-content: center;
        min-height: 30px;
        color: var(--pk-color-gray-700, #33404d);
    }
    .selection[data-empty] {
        visibility: hidden;
        pointer-events: none;
    }
    .selection-count {
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }
    .selection ::slotted(*) {
        max-width: 100%;
    }

    tbody[data-loading] {
        visibility: hidden;
    }
    .loading-space {
        height: var(--pk-state-panel-min-height, 11rem);
    }
    .loading-indicator {
        position: absolute;
        inset: var(--_pk-data-table-heading-height, 0px) 0 0;
        display: flex;
        align-items: center;
        justify-content: center;
        pointer-events: none;
    }
    .footer-shell {
        min-width: 0;
    }
    :host([layout='index']) .footer-shell {
        position: sticky;
        bottom: var(--pk-data-table-footer-offset, 0px);
        z-index: 3;
        background: var(--pk-color-white);
        border-radius: 0 0 var(--pk-radius-lg, 5px) var(--pk-radius-lg, 5px);
    }
    .scrollbar {
        overflow-x: scroll;
        overflow-y: hidden;
        height: 16px;
        background: var(--pk-color-gray-50);
    }
    .scrollbar > div {
        height: 1px;
    }
    .scrollbar:focus-visible {
        outline: 2px solid var(--pk-color-sky-600);
        outline-offset: -2px;
    }
    .scroll[data-proxy] {
        scrollbar-width: none;
    }
    .scroll[data-proxy]::-webkit-scrollbar {
        display: none;
    }
    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }
    .busy {
        opacity: 0.55;
        pointer-events: none;
    }
    :host([size='compact']) {
        --pk-data-table-cell-padding: 5px 10px;
    }
    :host([size='compact']) th {
        padding-block: 8px;
    }
    :host([size='compact']) th[data-sortable] {
        padding: 0;
    }
    :host([size='compact']) .sort {
        padding-block: 8px;
    }
    .icon {
        display: inline-flex;
        width: 1em;
        height: 1em;
        flex: none;
    }
    .icon svg {
        width: 100%;
        height: 100%;
    }
    .search .direction-icon {
        width: 16px;
        height: 16px;
    }
    .direction-icon {
        width: 20px;
        height: 20px;
    }
    th[data-sorted] {
        background: var(--pk-color-gray-200, #cdd8e4);
    }
    th.end .sort {
        justify-content: flex-end;
    }
    th.center .sort {
        justify-content: center;
    }
    .sort .icon {
        position: absolute;
        inset-inline-end: 14px;
        width: 10px;
        height: 10px;
        color: var(--pk-color-gray-500, #667482);
    }
    .sort .sort-spinner {
        position: absolute;
        inset-inline-end: 11px;
    }
    .footer-actions {
        margin-inline-start: auto;
    }
    .pager {
        gap: 3px;
    }
    .pager > span {
        margin-inline-start: 4px;
    }
    .pager pk-button::part(base) {
        width: 30px;
        height: 30px;
        min-height: 30px;
        padding: 0;
        color: var(--pk-color-gray-700);
        border-color: var(--pk-color-gray-400);
        background: transparent;
        opacity: 1;
    }
    .pager .icon {
        width: 10px;
        height: 10px;
    }
    .view-popover > pk-button,
    .footer-actions pk-button {
        --pk-btn-caret-size: 10px;
    }
    .view-popover {
        --pk-popover-flush-min-width: min(400px, calc(100vw - 20px));
        --pk-popover-flush-max-width: min(400px, calc(100vw - 20px));
        --pk-shadow-popover: var(--pk-shadow-popup);
    }
    .view-popover[open] > pk-button::part(base),
    .footer-actions pk-button[aria-expanded='true']::part(base) {
        background: var(--pk-color-slate-500);
    }
    .view-sort pk-button::part(base) {
        width: 42px;
        padding-inline: 0;
    }
    .pager pk-button:not([disabled])::part(base):hover {
        color: var(--pk-color-gray-800);
        border-color: var(--pk-color-gray-600);
        background: var(--pk-color-gray-200);
    }
    .pager pk-button:not([disabled])::part(base):focus-visible {
        border-color: var(--pk-color-gray-600);
        box-shadow: none;
        outline: 2px solid var(--pk-color-gray-600);
        outline-offset: 2px;
    }
    .pager pk-button:not([disabled])::part(base):active {
        color: var(--pk-color-white);
        border-color: var(--pk-color-gray-600);
        background: var(--pk-color-gray-600);
    }
    .pager pk-button[disabled]::part(base) {
        color: var(--pk-color-gray-300);
        border-color: var(--pk-color-gray-200);
    }
    .view-grid {
        display: grid;
        grid-template-columns: 150px minmax(0, 1fr);
    }
    .view-label {
        padding: 14px;
        background: var(--pk-color-white, #fff);
        border-inline-end: 1px solid var(--pk-color-gray-200, #dbe2e8);
        color: var(--pk-color-gray-600, #526171);
    }
    .view-sort {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px;
        min-width: 0;
    }
    .view-sort pk-select {
        flex: 1;
        min-width: 0;
    }
    .view-sort pk-button-group {
        flex: none;
    }
    .columns-label,
    .column-list {
        border-top: 1px solid var(--pk-color-gray-200, #dbe2e8);
    }
    .column-list {
        padding: 8px 10px;
        max-height: min(360px, 45vh);
        overflow-y: auto;
        overscroll-behavior: contain;
    }
    .column-handle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 20px;
        height: 24px;
        flex: none;
        border: 0;
        padding: 2px;
        background: transparent;
        color: var(--pk-color-gray-500, #667482);
        cursor: move;
        touch-action: none;
    }
    .column-handle:active {
        cursor: move;
    }
    .column-handle:focus-visible {
        outline: 2px solid var(--pk-color-blue-500, #247db1);
        border-radius: 3px;
    }
    .column-list[data-reordering] .column-option[data-dragging] {
        z-index: 1;
        background: var(--pk-color-white, #fff);
        transition: none;
        user-select: none;
    }
    @media (prefers-reduced-motion: reduce) {
        .column-list[data-reordering] .column-option {
            transition: none;
        }
    }
    .view-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 24px;
        background: var(--pk-color-gray-50, #f3f7fc);
    }
    @media (max-width: 520px) {
        .search {
            flex-basis: 100%;
        }
        .selection {
            order: 3;
            flex-basis: 100%;
            justify-content: flex-start;
        }
        .view-grid {
            grid-template-columns: 100px minmax(0, 1fr);
        }
        .view-label {
            padding: 14px 12px;
        }
        .view-sort {
            flex-wrap: wrap;
            padding: 12px;
        }
        .view-sort pk-select {
            flex-basis: 100%;
        }
        .column-list {
            padding-inline: 10px;
        }
    }
`, dt = class extends T {
	constructor(...e) {
		super(...e), this.htmlFor = "";
	}
	static {
		this.styles = [ut, i`
            :host {
                display: block;
                min-width: 0;
            }

            .toolbar {
                margin-bottom: 0;
            }
        `];
	}
	connectedCallback() {
		super.connectedCallback(), this.hasUpdated && this.connectTable();
	}
	disconnectedCallback() {
		this.table?.detachToolbar(this), this.table = void 0, super.disconnectedCallback();
	}
	firstUpdated() {
		this.connectTable();
	}
	updated(e) {
		e.has("htmlFor") && e.get("htmlFor") !== void 0 && (this.table?.detachToolbar(this), this.table = void 0, this.connectTable());
	}
	connectTable(e) {
		let t = e ?? this.findTable();
		t && this.hasUpdated && (this.table && this.table !== t && this.table.detachToolbar(this), this.table = t, t.attachToolbar(this));
	}
	findTable() {
		if (!this.htmlFor) return null;
		let e = this.getRootNode().getElementById?.(this.htmlFor) ?? this.ownerDocument.getElementById(this.htmlFor);
		return e?.localName === "pk-data-table" ? e : null;
	}
	render() {
		return e`<div class="mount"></div>`;
	}
};
s([t({ attribute: "for" })], dt.prototype, "htmlFor", void 0), s([E(".mount")], dt.prototype, "mount", void 0), dt = s([r("pk-data-table-toolbar")], dt);
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/pk-clear-DHxP_2cm.js
var ft = class extends Event {
	constructor() {
		super("pk-clear", {
			bubbles: !0,
			cancelable: !1,
			composed: !0
		});
	}
}, pt = /* @__PURE__ */ new Set([
	"button",
	"submit",
	"reset",
	"checkbox",
	"radio",
	"file",
	"image",
	"hidden"
]), mt = "pk-implicit-submit", ht = (e, t) => {
	if (e.key !== "Enter" || e.defaultPrevented || e.isComposing || e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) return !1;
	let n = (t || "text").toLowerCase();
	return !pt.has(n);
}, gt = (e) => {
	let t = e.closest?.("pk-dialog");
	if (t) {
		let e = t.querySelector("form");
		if (e) return e;
	}
	let n = e.form;
	return n && n.id === "main" ? e.closest?.("form") === n ? null : e.closest("form") : n;
}, _t = (e, t, n) => {
	if (e.disabled || e.readonly || !ht(t, n)) return !1;
	let r = gt(e);
	return !r || r.id === "main" ? !1 : (t.preventDefault(), t.stopPropagation(), r.dispatchEvent(new CustomEvent(mt, {
		bubbles: !1,
		cancelable: !0
	})), !0);
}, vt = i`
    @layer pk-component {
        :host {
            display: block;
            width: 100%;
            font-family: var(--pk-font-family);
            font-size: var(--pk-control-font-default);
            line-height: var(--pk-line-height);
            /*
             * Control chrome tokens — inherit into light-DOM in-control actions
             * (e.g. pk-copy-button[slot=end]) the same way combobox/image-browser
             * size their trailing clear/expand hit targets.
             */
            --pk-input-padding-block: calc((var(--pk-control-height-default) - var(--pk-input-control-line-height, 1.25rem) - 2px) / 2);
            --pk-input-padding-inline: 8px;
            --pk-input-control-gap: 6px;
            --pk-input-decoration-size: 0.75rem;
        }

        :host([data-pk-group-orientation]) {
            display: flex;
            flex-direction: column;
            width: auto;
            flex: 0 1 auto;
            align-self: stretch;
        }

        :host([data-pk-group-orientation]) .form-control {
            gap: 0;
            height: 100%;
        }

        :host([data-pk-group-orientation]) .form-control__input {
            min-height: var(--pk-btn-height-default);
            height: 100%;
        }

        :host([data-pk-group-orientation]) .form-control__start,
        :host([data-pk-group-orientation]) .form-control__end {
            display: none;
        }

        :host([data-pk-group-orientation]) .form-control__input {
            width: 100%;
        }

        :host([data-pk-group-orientation]) .input {
            width: 100%;
        }

        :host([data-pk-group-orientation]) .input {
            min-height: var(--pk-btn-height-default);
            height: 100%;
        }

        :host([data-pk-group-orientation='vertical']) {
            width: 100%;
        }

        :host([data-pk-group-orientation='horizontal'][data-pk-group-join]:not([data-pk-group-divider])) {
            margin-inline-start: var(--pk-bg-horizontal-indent-outlined, 0);
        }

        :host([data-pk-group-orientation='vertical'][data-pk-group-join]:not([data-pk-group-divider])) {
            margin-block-start: var(--pk-bg-vertical-indent-outlined, 0);
        }

        :host([data-pk-group-orientation='horizontal'][data-pk-group-divider][data-pk-group-join]) {
            margin-inline-start: var(--pk-bg-horizontal-indent-outlined, 0);
        }

        :host([data-pk-group-orientation='vertical'][data-pk-group-divider][data-pk-group-join]) {
            margin-block-start: var(--pk-bg-vertical-indent-outlined, 0);
        }

        :host([data-pk-group-orientation='horizontal'][data-pk-group-divider]) .form-control__input {
            border-left-width: 1px;
            border-left-style: solid;
            border-left-color: var(--pk-btn-group-divider-color-outline, var(--pk-input-border-color));
            box-shadow: none;
        }

        :host([data-pk-group-orientation='vertical'][data-pk-group-divider]) .form-control__input {
            border-top-width: 1px;
            border-top-style: solid;
            border-top-color: var(--pk-btn-group-divider-color-outline, var(--pk-input-border-color));
            box-shadow: none;
        }

        :host([data-pk-group-divider]) .form-control__input:focus-within,
        :host([data-pk-group-divider][data-state='focus-visible']) .form-control__input {
            box-shadow: var(--pk-input-focus-shadow);
        }

        :host([data-pk-group-orientation='vertical'][data-pk-group-divider]) .form-control__input:focus-within,
        :host([data-pk-group-orientation='vertical'][data-pk-group-divider][data-state='focus-visible']) .form-control__input {
            box-shadow: var(--pk-input-focus-shadow);
        }

        /* Chrome lives on the flex shell (part=base) so slot=start/end adornments sit
         * inside the border — same visual contract as pk-input-group / v1 InputGroup.
         * Height is content-sized (v1): padding-block + --pk-input-control-line-height + border.
         * Trailing actions (clear, pk-copy-button[slot=end]) stay in flex flow so long
         * values never paint under the button — mirror combobox / image-browser.
         */
        .form-control__input {
            align-items: center;
            gap: var(--pk-input-control-gap);
            padding-inline: var(--pk-input-padding-inline);
            border: var(--pk-input-border);
            border-radius: var(--pk-input-border-radius, var(--pk-radius-sm));
            background: var(--pk-input-bg);
            background-clip: padding-box;
            box-sizing: border-box;
            transition: border-color 0.12s ease, box-shadow 0.12s ease;
        }

        .form-control__start,
        .form-control__end {
            margin: 0;
            color: var(--pk-color-gray-400);
            line-height: 0;
            align-self: stretch;
            align-items: center;
        }

        /* Decorative glyphs only — interactive in-control actions opt out below. */
        .form-control__start ::slotted(*),
        .form-control__end ::slotted(*) {
            display: block;
            max-width: 1.25rem;
            max-height: 1.25rem;
        }

        /* Copy (and similar) inside the field: flex-reserved space, not absolute overlay. */
        .form-control__end:has(::slotted(pk-copy-button)) {
            align-items: stretch;
        }

        .form-control__end ::slotted(pk-copy-button) {
            display: inline-flex;
            align-self: stretch;
            align-items: stretch;
            max-width: none;
            max-height: none;
            /*
             * Pull into trailing padding like combobox expand/clear, but leave a
             * small inset so the glyph is not tight against the field border.
             */
            margin-inline-end: calc(-1 * var(--pk-input-padding-inline) + 4px);
            margin-block: calc(-1 * var(--pk-input-padding-block));
        }

        .input {
            display: block;
            width: 100%;
            margin: 0;
            /* v1 Input default: py-1.5 + text-sm (14px / 1.25rem lh) → 34px with border. */
            padding-block: var(--pk-input-padding-block);
            padding-inline: 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            /* Craft CP body / field value text. */
            color: var(--pk-color-gray-700);
            font: inherit;
            line-height: var(--pk-input-control-line-height, 1.25rem);
            appearance: none;
            box-sizing: border-box;
            outline: none;
        }

        .form-control__input .input {
            flex: 1 1 auto;
            min-width: 0;
        }

        /* Keep the browser from adding a second clear control. */
        .input[data-custom-clear]::-webkit-search-cancel-button {
            -webkit-appearance: none;
            display: none;
        }

        .input::placeholder {
            color: var(--pk-input-placeholder-color, var(--pk-color-gray-400));
        }

        /*
         * Craft text:focus-visible only sets box-shadow (--focus-ring); resting border stays.
         * Do not also set border-color — --pk-input-focus-shadow already includes 0 0 0 1px,
         * so border-color + that ring reads as a double focus treatment.
         */
        :host(:not([invalid]):not(:state(user-invalid))) .form-control__input:focus-within,
        :host([data-state='focus-visible']:not([invalid]):not(:state(user-invalid))) .form-control__input {
            box-shadow: var(--pk-input-focus-shadow);
        }

        .form-control__input:has(.input:disabled) {
            cursor: not-allowed;
            opacity: 0.5;
        }

        .input:disabled {
            cursor: not-allowed;
        }

        :host([invalid]) .form-control__input,
        :host(:state(user-invalid)) .form-control__input {
            border-color: var(--pk-color-rose-600);
        }

        /* Invalid + focus: rose ring (same token as select/combobox), not sky over rose border. */
        :host([invalid]) .form-control__input:focus-within,
        :host([invalid][data-state='focus-visible']) .form-control__input,
        :host(:state(user-invalid)) .form-control__input:focus-within {
            box-shadow: var(--pk-input-invalid-focus-shadow);
        }

        :host([size='xxs']) {
            --pk-input-padding-block: calc((var(--pk-control-height-xxs) - var(--pk-input-control-line-height, 1.25rem) - 2px) / 2);
            --pk-input-padding-inline: 6px;
            --pk-input-control-gap: 4px;
            --pk-input-decoration-size: 0.625rem;
        }

        :host([size='xs']) {
            --pk-input-padding-block: calc((var(--pk-control-height-xs) - var(--pk-input-control-line-height, 1.25rem) - 2px) / 2);
            --pk-input-padding-inline: 6px;
            --pk-input-control-gap: 4px;
            --pk-input-decoration-size: 0.625rem;
        }

        :host([size='xxs']) .input {
            font-size: var(--pk-control-font-xxs);
        }

        :host([size='xs']) .input {
            font-size: var(--pk-control-font-xs);
        }

        :host([size='sm']) {
            --pk-input-padding-block: calc((var(--pk-control-height-sm) - var(--pk-input-control-line-height, 1.25rem) - 2px) / 2);
            --pk-input-padding-inline: 8px;
            --pk-input-control-gap: 4px;
            --pk-input-decoration-size: 0.6875rem;
        }

        :host([size='sm']) .input {
            font-size: var(--pk-control-font-sm);
        }

        :host([size='lg']) {
            --pk-input-padding-block: calc((var(--pk-control-height-lg) - var(--pk-input-control-line-height, 1.25rem) - 2px) / 2);
            --pk-input-padding-inline: 12px;
            --pk-input-control-gap: 8px;
            --pk-input-decoration-size: 0.875rem;
        }

        :host([size='lg']) .input {
            font-size: var(--pk-control-font-lg);
        }

        :host([size='xl']) {
            --pk-input-padding-block: calc((var(--pk-control-height-xl) - var(--pk-input-control-line-height, 1.25rem) - 2px) / 2);
            --pk-input-padding-inline: 16px;
            --pk-input-control-gap: 8px;
            --pk-input-decoration-size: 1rem;
        }

        :host([size='xl']) .input {
            font-size: var(--pk-control-font-xl);
        }

        /* Optical font sizing must not change the control height. */
        :host([mono]) .input {
            font-family: var(--pk-input-mono-font-family);
            font-size: calc(var(--pk-control-font-default) * 0.9);
            line-height: var(--pk-input-control-line-height, 1.25rem);
        }

        :host([mono][size='xxs']) .input {
            font-size: calc(var(--pk-control-font-xxs) * 0.9);
        }

        :host([mono][size='xs']) .input {
            font-size: calc(var(--pk-control-font-xs) * 0.9);
        }

        :host([mono][size='sm']) .input {
            font-size: calc(var(--pk-control-font-sm) * 0.9);
        }

        :host([mono][size='lg']) .input {
            font-size: calc(var(--pk-control-font-lg) * 0.9);
        }

        :host([mono][size='xl']) .input {
            font-size: calc(var(--pk-control-font-xl) * 0.9);
        }

        /* Editable-table cells (v1): flush into the row — no chrome border/radius.
         * Prefer reflected fit-cell (Lit property); data-editable-table-input is a legacy alias.
         * Fill host → form-control → input so the control spans the full td.
         */
        :host([fit-cell]),
        :host([data-editable-table-input]) {
            display: block;
            height: 100%;
            min-height: 100%;
            box-sizing: border-box;
        }

        :host([fit-cell]) .form-control,
        :host([data-editable-table-input]) .form-control {
            height: 100%;
            min-height: 100%;
            gap: 0;
        }

        :host([fit-cell]) .form-control__input,
        :host([data-editable-table-input]) .form-control__input {
            height: 100%;
            min-height: 100%;
            flex: 1 1 auto;
            padding-inline: 0;
            border: none;
            border-radius: 0;
            background: transparent;
            box-shadow: none;
        }

        :host([fit-cell]) .input,
        :host([data-editable-table-input]) .input {
            height: 100%;
            min-height: 100%;
        }

        :host([fit-cell]:not([invalid]):not(:state(user-invalid))) .form-control__input:focus-within,
        :host([fit-cell][data-state='focus-visible']:not([invalid]):not(:state(user-invalid))) .form-control__input,
        :host([data-editable-table-input]:not([invalid]):not(:state(user-invalid))) .form-control__input:focus-within,
        :host([data-editable-table-input][data-state='focus-visible']:not([invalid]):not(:state(user-invalid))) .form-control__input {
            border: none;
            box-shadow: inset 0 0 0 1px var(--pk-color-gray-200);
        }

        :host([fit-cell][invalid]) .form-control__input,
        :host([fit-cell]:state(user-invalid)) .form-control__input,
        :host([data-editable-table-input][invalid]) .form-control__input,
        :host([data-editable-table-input]:state(user-invalid)) .form-control__input {
            border: none;
            box-shadow: inset 0 0 0 1px var(--pk-color-rose-600);
        }

        :host([fit-cell][invalid]) .form-control__input:focus-within,
        :host([fit-cell][invalid][data-state='focus-visible']) .form-control__input,
        :host([fit-cell]:state(user-invalid)) .form-control__input:focus-within,
        :host([data-editable-table-input][invalid]) .form-control__input:focus-within,
        :host([data-editable-table-input][invalid][data-state='focus-visible']) .form-control__input,
        :host([data-editable-table-input]:state(user-invalid)) .form-control__input:focus-within {
            border: none;
            box-shadow: inset 0 0 0 1px var(--pk-color-rose-600);
        }

        :host([data-pk-group-orientation='horizontal'][data-pk-group-join]:not([data-pk-group-divider])) .form-control__input {
            border-left-width: 0;
        }

        :host([data-pk-group-orientation='vertical'][data-pk-group-join]:not([data-pk-group-divider])) .form-control__input {
            border-top-width: 0;
        }

        :host([data-pk-group-orientation='horizontal'][data-pk-group-divider]) .form-control__input {
            border-left-width: 1px;
            border-left-style: solid;
            border-left-color: var(--pk-btn-group-divider-color-outline, var(--pk-input-border-color));
        }

        :host([data-pk-group-orientation='vertical'][data-pk-group-divider]) .form-control__input {
            border-top-width: 1px;
            border-top-style: solid;
            border-top-color: var(--pk-btn-group-divider-color-outline, var(--pk-input-border-color));
        }

        :host([data-pk-group-orientation='horizontal'][data-pk-group-internal-trail]) .form-control__input {
            border-right-width: 0;
        }

        :host([data-pk-group-orientation='vertical'][data-pk-group-internal-trail]) .form-control__input {
            border-bottom-width: 0;
        }

        /*
         * Clear is a flex trailing action (not absolute). Reserves width in the
         * control so values cannot scroll under the glyph — same contract as
         * combobox clear/expand and image-browser clear.
         */
        .clear-button {
            display: inline-flex;
            flex-shrink: 0;
            align-self: stretch;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            width: calc(var(--pk-input-decoration-size) + var(--pk-input-padding-inline));
            margin-block: calc(-1 * var(--pk-input-padding-block));
            /* Match pk-copy-button[slot=end]: pull into padding but leave a 4px glyph inset. */
            margin-inline-end: calc(-1 * var(--pk-input-padding-inline) + 4px);
            font-size: var(--pk-input-decoration-size);
            line-height: 1;
        }
    }
`, N = class extends A {
	constructor(...e) {
		super(...e), this.assumeInteractionOn = ["blur", "input"], this.hasSlotController = new g(this, "instructions", "hint", "label", "start", "end"), this.inputId = Xe("pk-input"), this.type = "text", this._value = null, this.defaultValue = null, this.size = "default", this.label = "", this.instructions = "", this.withClear = !1, this.clearLabel = "Clear", this.placeholder = "", this.readonly = !1, this.invalid = !1, this.fitCell = !1, this.mono = !1, this.autofocus = !1, this.withLabel = !1, this.withInstructions = !1;
	}
	static {
		this.styles = [
			Ae,
			ie(),
			re(".input", "var(--pk-input-border-radius, var(--pk-radius-sm))"),
			c(".input"),
			vt
		];
	}
	static get validators() {
		return [
			...super.validators,
			qe(),
			Re()
		];
	}
	get value() {
		return this.valueHasChanged ? this._value ?? "" : this._value ?? this.defaultValue ?? "";
	}
	set value(e) {
		let t = e ?? "";
		this._value !== t && (this.valueHasChanged = !0, this._value = t);
	}
	connectedCallback() {
		this.instructions = ze(this, this.instructions), this.hasAttribute("with-hint") && (this.withInstructions = !0), super.connectedCallback();
	}
	syncFormValue() {
		this.setValue(this.value || "");
	}
	resetToDefaultValue() {
		this.valueHasChanged = !1, this._value = null;
	}
	restoreFormState(e) {
		typeof e == "string" && (this.value = e);
	}
	formResetCallback() {
		this.valueHasChanged = !1, this._value = null, this.input && (this.input.value = this.defaultValue ?? ""), super.formResetCallback();
	}
	updated(e) {
		(e.has("value") || e.has("defaultValue")) && this.setState("blank", !this.value), super.updated(e);
	}
	syncStandaloneAria() {
		if (!this.input) return;
		let e = !!this.label || this.hasSlotController.test("label", this.withLabel), t = Be((e, t) => this.hasSlotController.test(e, t), this.instructions, this.withInstructions);
		Pe({
			control: this.input,
			labelId: `${this.inputId}-label`,
			instructionsId: `${this.inputId}-instructions`,
			hasLabel: e,
			hasInstructions: t,
			required: this.required,
			invalid: this.invalid || !this.internals.validity.valid
		});
	}
	hasLabelContent() {
		return !!this.label || this.hasSlotController.test("label", this.withLabel);
	}
	hasInstructionsContent() {
		return Be((e, t) => this.hasSlotController.test(e, t), this.instructions, this.withInstructions);
	}
	focus(e) {
		this.input?.focus(e);
	}
	blur() {
		this.input?.blur();
	}
	select() {
		this.input?.select();
	}
	handleInput() {
		this.value = this.input.value, this.dispatchEvent(new Event("input", {
			bubbles: !0,
			composed: !0
		}));
	}
	handleChange(e) {
		this.value = this.input.value, e.stopPropagation(), this.dispatchEvent(new Event("change", {
			bubbles: !0,
			composed: !0
		}));
	}
	handleKeyDown(e) {
		_t(this, e, this.type);
	}
	handleClearClick(e) {
		e.preventDefault(), this.value !== "" && (this.value = "", this.dispatchEvent(new ft()), this.dispatchEvent(new Event("input", {
			bubbles: !0,
			composed: !0
		})), this.dispatchEvent(new Event("change", {
			bubbles: !0,
			composed: !0
		})), this.input.focus());
	}
	render() {
		let t = this.hasLabelContent(), r = this.hasInstructionsContent(), i = this.withClear && !this.disabled && !this.readonly && this.value.length > 0, a = this.hasSlotController.test("start"), o = this.hasSlotController.test("end");
		return e`
            <div part="form-control" class="form-control">
                ${t || r ? e`
                        <div part="header" class="form-control__header">
                            ${t ? e`
                                    <label
                                        part="label"
                                        class="form-control__label"
                                        id=${`${this.inputId}-label`}
                                        for=${`${this.inputId}-control`}
                                    >
                                        <slot name="label">${this.label}</slot>
                                    </label>
                                ` : n}

                            ${r ? e`
                                    <p
                                        part="instructions"
                                        class="form-control__instructions"
                                        id=${`${this.inputId}-instructions`}
                                    >
                                        <slot name="instructions">${this.instructions}</slot>
                                        <slot name="hint"></slot>
                                    </p>
                                ` : n}
                        </div>
                    ` : n}

                <div part="base" class="form-control__input">
                    ${a ? e`
                            <span part="start" class="form-control__start">
                                <slot name="start"></slot>
                            </span>
                        ` : e`<slot name="start" hidden></slot>`}

                    <input
                        part="input"
                        class="input"
                        ?data-custom-clear=${this.withClear}
                        id=${t ? `${this.inputId}-control` : n}
                        type=${this.type}
                        .value=${st(this.value)}
                        placeholder=${this.placeholder || n}
                        pattern=${ct(this.pattern)}
                        minlength=${ct(this.minlength)}
                        maxlength=${ct(this.maxlength)}
                        min=${ct(this.min)}
                        max=${ct(this.max)}
                        step=${ct(this.step)}
                        autocomplete=${ct(this.autocomplete)}
                        ?disabled=${this.disabled}
                        ?readonly=${this.readonly}
                        ?required=${this.required}
                        ?autofocus=${this.autofocus}
                        @input=${this.handleInput}
                        @change=${this.handleChange}
                        @keydown=${this.handleKeyDown}
                        @focus=${() => this.dispatchEvent(new Event("focus", {
			bubbles: !0,
			composed: !0
		}))}
                        @blur=${() => this.dispatchEvent(new Event("blur", {
			bubbles: !0,
			composed: !0
		}))}
                    />

                    ${i ? e`
                            <button
                                part="clear-button"
                                class="icon-button clear-button"
                                type="button"
                                aria-label=${this.clearLabel}
                                @click=${this.handleClearClick}
                            >
                                <slot name="clear-icon">×</slot>
                            </button>
                        ` : n}

                    ${o ? e`
                            <span part="end" class="form-control__end">
                                <slot name="end"></slot>
                            </span>
                        ` : e`<slot name="end" hidden></slot>`}
                </div>
            </div>
        `;
	}
};
s([E("input")], N.prototype, "input", void 0), s([t({ reflect: !0 })], N.prototype, "type", void 0), s([o()], N.prototype, "value", null), s([t({
	attribute: "value",
	reflect: !0
})], N.prototype, "defaultValue", void 0), s([t({ reflect: !0 })], N.prototype, "size", void 0), s([t()], N.prototype, "label", void 0), s([t()], N.prototype, "instructions", void 0), s([t({
	attribute: "with-clear",
	type: Boolean
})], N.prototype, "withClear", void 0), s([t({ attribute: "clear-label" })], N.prototype, "clearLabel", void 0), s([t()], N.prototype, "placeholder", void 0), s([t({
	type: Boolean,
	reflect: !0
})], N.prototype, "readonly", void 0), s([t({
	type: Boolean,
	reflect: !0
})], N.prototype, "invalid", void 0), s([t({
	type: Boolean,
	reflect: !0,
	attribute: "fit-cell"
})], N.prototype, "fitCell", void 0), s([t({
	type: Boolean,
	reflect: !0
})], N.prototype, "mono", void 0), s([t()], N.prototype, "pattern", void 0), s([t({ type: Number })], N.prototype, "minlength", void 0), s([t({ type: Number })], N.prototype, "maxlength", void 0), s([t()], N.prototype, "min", void 0), s([t()], N.prototype, "max", void 0), s([t()], N.prototype, "step", void 0), s([t()], N.prototype, "autocomplete", void 0), s([t({
	type: Boolean,
	reflect: !0
})], N.prototype, "autofocus", void 0), s([t({
	attribute: "with-label",
	type: Boolean
})], N.prototype, "withLabel", void 0), s([t({
	attribute: "with-instructions",
	type: Boolean
})], N.prototype, "withInstructions", void 0), N = s([r("pk-input")], N);
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/pk-checkbox-bfqcQikR.js
function yt() {
	return e`
        <svg xmlns="http://www.w3.org/2000/svg" role="img" aria-hidden="true" viewBox="0 0 640 640">
            <path
                fill="currentColor"
                d="M557.5 192L534.9 214.6L278.9 470.6C266.4 483.1 246.1 483.1 233.6 470.6L105.6 342.6L83 320L128.3 274.7C129.6 276 172.3 318.7 256.3 402.7L489.7 169.3L512.3 146.7L557.6 192z"
            />
        </svg>
    `;
}
function bt() {
	return e`
        <svg xmlns="http://www.w3.org/2000/svg" role="img" aria-hidden="true" viewBox="0 0 640 640">
            <path fill="currentColor" d="M96 352V288H544V352H96z" />
        </svg>
    `;
}
var xt = [i`
    @layer pk-component {
        .control {
            display: inline-flex;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
            position: relative;
            box-sizing: border-box;
            width: var(--pk-checkbox-size);
            height: var(--pk-checkbox-size);
            border: 1px solid var(--pk-checkbox-border-color, #c0cbd9);
            border-radius: var(--pk-radius-sm);
            background: var(--pk-color-white);
            cursor: pointer;
            transition: border-color 0.12s ease, box-shadow 0.12s ease;
        }

        :host([disabled]) .control {
            cursor: not-allowed;
        }

        .input:focus-visible + .control {
            border-color: var(--pk-color-sky-600);
            box-shadow: 0 0 0 1px var(--pk-color-sky-600), 0 0 4px 0 hsl(from var(--pk-color-sky-600) h s l / 0.7);
        }

        :host([invalid]) .control,
        .input[aria-invalid='true'] + .control {
            border-color: var(--pk-color-rose-600);
        }

        :host([invalid]) .input:focus-visible + .control,
        .input[aria-invalid='true']:focus-visible + .control {
            border-color: var(--pk-color-rose-600);
            box-shadow: 0 0 0 1px var(--pk-color-rose-600), 0 0 4px 0 hsl(from var(--pk-color-rose-600) h s l / 0.7);
        }

        .indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--pk-color-gray-900);
        }

        .icon-check,
        .icon-indeterminate {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
        }

        .icon-check svg {
            width: 14px;
            height: 14px;
            transform: translateY(1px) scale(1.2);
        }

        .icon-indeterminate svg {
            width: 12px;
            height: 12px;
        }

        :host([checked]) .icon-check,
        .input:checked + .control .icon-check {
            opacity: 1;
        }

        :host([indeterminate]) .icon-check,
        .input:indeterminate + .control .icon-check {
            opacity: 0;
        }

        :host([indeterminate]) .icon-indeterminate,
        .input:indeterminate + .control .icon-indeterminate {
            opacity: 1;
        }
    }
`, i`
    @layer pk-component {
        :host {
            display: inline-flex;
            vertical-align: middle;
            /* Hit target is the content-sized .root label (Craft checkbox-select), not the host. */
            cursor: default;
            font-family: var(--pk-font-family);
            font-size: var(--pk-font-size-base);
            line-height: var(--pk-line-height);
        }

        :host([disabled]) {
            cursor: not-allowed;
            opacity: 0.5;
        }

        .root {
            display: inline-flex;
            flex-shrink: 0;
            align-items: center;
            justify-content: flex-start;
            gap: var(--pk-control-label-gap);
            /* Content-sized like Craft's <label> beside the checkbox — not full-row. */
            width: fit-content;
            max-width: 100%;
            margin: 0;
            min-height: 0;
            cursor: pointer;
            user-select: none;
            position: relative;
        }

        :host([disabled]) .root {
            cursor: not-allowed;
        }

        .root--with-hint {
            align-items: flex-start;
        }

        .input {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
            opacity: 0;
            appearance: none;
        }

        .text {
            display: flex;
            flex-direction: column;
            gap: 0.125rem;
            min-width: 0;
        }

        .label {
            line-height: max(1rem, var(--pk-checkbox-size));
            /* Match form-control / Craft body labels (gray-700), not gray-900. */
            color: var(--pk-color-gray-700);
            cursor: pointer;
        }

        :host([disabled]) .label {
            cursor: not-allowed;
        }

        :host(.all-option) .label {
            font-weight: 700;
        }

        .hint {
            margin: 0;
            color: var(--pk-color-gray-500);
            font-size: var(--pk-font-size-sm);
            line-height: var(--pk-line-height);
        }

        .hint:empty {
            display: none;
        }
    }
`], P = class extends A {
	constructor(...e) {
		super(...e), this.assumeInteractionOn = ["change"], this.hasSlotController = new g(this, "hint"), this.checked = !1, this.indeterminate = !1, this.disabled = !1, this.invalid = !1, this.checkboxValue = "on", this.defaultChecked = !1, this.ariaLabel = null, this.hint = "", this.withHint = !1, this.hasDefaultSlotContent = !1;
	}
	static {
		this.shadowRootOptions = {
			mode: "open",
			delegatesFocus: !0
		};
	}
	static {
		this.styles = xt;
	}
	static get validators() {
		return [...super.validators, Re({ validationProperty: "checked" })];
	}
	get validationTarget() {
		return this.input;
	}
	syncFormValue() {
		this.setFormValue(this.checked ? this.checkboxValue : null, this.checked ? "on" : "off");
	}
	resetToDefaultValue() {
		this.checked = this.defaultChecked, this.indeterminate = !1;
	}
	restoreFormState(e) {
		this.checked = e === "on" || e === this.checkboxValue;
	}
	updated(e) {
		this.input && (e.has("checkboxValue") && this.syncFormValue(), (e.has("indeterminate") || e.has("checked")) && (this.input.indeterminate = this.indeterminate, this.input.checked = this.checked)), super.updated(e);
	}
	defaultSlotChanged(e) {
		let t = e.target;
		this.hasDefaultSlotContent = t.assignedNodes({ flatten: !0 }).some((e) => e.nodeType === Node.TEXT_NODE ? e.textContent?.trim() : e.nodeType === Node.ELEMENT_NODE);
	}
	handleChange(e) {
		let t = e.target;
		this.checked = t.checked, this.indeterminate = !1, this.dispatchEvent(new CustomEvent("pk-change", {
			detail: { checked: this.checked },
			bubbles: !0,
			composed: !0
		})), this.dispatchEvent(new Event("input", {
			bubbles: !0,
			composed: !0
		})), this.dispatchEvent(new Event("change", {
			bubbles: !0,
			composed: !0
		}));
	}
	render() {
		let t = this.hasDefaultSlotContent, r = !!this.hint || this.hasSlotController.test("hint", this.withHint);
		return e`
            <label
                part="base"
                class=${w({
			root: !0,
			"root--with-hint": r
		})}
            >
                <input
                    part="input"
                    class="input"
                    type="checkbox"
                    .checked=${this.checked}
                    ?disabled=${this.disabled}
                    ?required=${this.required}
                    name=${this.name ?? n}
                    value=${this.checkboxValue}
                    aria-labelledby=${t ? "label" : n}
                    aria-describedby=${r ? "hint" : n}
                    aria-label=${t ? n : this.ariaLabel ?? n}
                    aria-invalid=${this.invalid ? "true" : n}
                    @change=${this.handleChange}
                />
                <span part="control" class="control">
                    <span part="checked-icon" class="icon-check">${yt()}</span>
                    <span part="indeterminate-icon" class="icon-indeterminate">${bt()}</span>
                </span>
                ${t || r ? e`
                        <span class="text">
                            ${t ? e`
                                    <span part="label" class="label" id="label">
                                        <slot @slotchange=${this.defaultSlotChanged}></slot>
                                    </span>
                                ` : e`<slot @slotchange=${this.defaultSlotChanged} hidden></slot>`}
                            ${r ? e`
                                    <span part="hint" class="hint" id="hint">
                                        <slot name="hint">${this.hint}</slot>
                                    </span>
                                ` : n}
                        </span>
                    ` : e`<slot @slotchange=${this.defaultSlotChanged} hidden></slot>`}
            </label>
        `;
	}
};
s([t({
	type: Boolean,
	reflect: !0
})], P.prototype, "checked", void 0), s([t({
	type: Boolean,
	reflect: !0
})], P.prototype, "indeterminate", void 0), s([t({
	type: Boolean,
	reflect: !0
})], P.prototype, "disabled", void 0), s([t({
	type: Boolean,
	reflect: !0
})], P.prototype, "invalid", void 0), s([t()], P.prototype, "checkboxValue", void 0), s([t({
	attribute: "default-checked",
	type: Boolean
})], P.prototype, "defaultChecked", void 0), s([t({ attribute: "aria-label" })], P.prototype, "ariaLabel", void 0), s([t()], P.prototype, "hint", void 0), s([t({
	type: Boolean,
	attribute: "with-hint"
})], P.prototype, "withHint", void 0), s([E(".input")], P.prototype, "input", void 0), s([o()], P.prototype, "hasDefaultSlotContent", void 0), P = s([r("pk-checkbox")], P);
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/popup-placement-animation-ClUYI79z.js
function St(e) {
	let t = e.split("-")[0];
	return t === "inline-start" ? "left" : t === "inline-end" ? "right" : t === "top" || t === "bottom" || t === "left" || t === "right" ? t : "bottom";
}
function Ct(e, t, n, r, i) {
	let a = St(e), o = t.x + t.width / 2 - n.x, s = t.y + t.height / 2 - n.y;
	return Math.abs(i?.y ?? 0) > r && (a === "top" || a === "bottom") ? `${o}px ${t.y + t.height / 2 - n.y}px` : {
		top: `${o}px calc(100% + ${r}px)`,
		bottom: `${o}px ${-r}px`,
		left: `calc(100% + ${r}px) ${s}px`,
		right: `${-r}px ${s}px`
	}[a];
}
function wt(e, t) {
	t ? e.setAttribute("data-side", St(t)) : e.removeAttribute("data-side");
}
function Tt(e, t, n = 100, r) {
	let i = () => e.getAttribute("data-current-placement") ?? t;
	return !r?.requireEvent && e.hasAttribute("data-current-placement") ? Promise.resolve(i()) : new Promise((t) => {
		let a = !1, o = () => {
			a || (a = !0, t(i()));
		};
		e.addEventListener("pk-reposition", o, { once: !0 }), r?.requireEvent || requestAnimationFrame(() => {
			requestAnimationFrame(() => {
				e.hasAttribute("data-current-placement") && o();
			});
		}), window.setTimeout(o, n);
	});
}
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/supports-popover-BCqyOPtO.js
var Et = globalThis.HTMLElement !== void 0 && Object.prototype.hasOwnProperty.call(globalThis.HTMLElement.prototype, "popover"), Dt = Math.min, F = Math.max, Ot = Math.round, kt = Math.floor, I = (e) => ({
	x: e,
	y: e
}), At = {
	left: "right",
	right: "left",
	bottom: "top",
	top: "bottom"
};
function jt(e, t, n) {
	return F(e, Dt(t, n));
}
function Mt(e, t) {
	return typeof e == "function" ? e(t) : e;
}
function Nt(e) {
	return e.split("-")[0];
}
function Pt(e) {
	return e.split("-")[1];
}
function Ft(e) {
	return e === "x" ? "y" : "x";
}
function It(e) {
	return e === "y" ? "height" : "width";
}
function Lt(e) {
	let t = e[0];
	return t === "t" || t === "b" ? "y" : "x";
}
function Rt(e) {
	return Ft(Lt(e));
}
function zt(e, t, n) {
	n === void 0 && (n = !1);
	let r = Pt(e), i = Rt(e), a = It(i), o = i === "x" ? r === (n ? "end" : "start") ? "right" : "left" : r === "start" ? "bottom" : "top";
	return t.reference[a] > t.floating[a] && (o = Jt(o)), [o, Jt(o)];
}
function Bt(e) {
	let t = Jt(e);
	return [
		Vt(e),
		t,
		Vt(t)
	];
}
function Vt(e) {
	return e.includes("start") ? e.replace("start", "end") : e.replace("end", "start");
}
var Ht = ["left", "right"], Ut = ["right", "left"], Wt = ["top", "bottom"], Gt = ["bottom", "top"];
function Kt(e, t, n) {
	switch (e) {
		case "top":
		case "bottom": return n ? t ? Ut : Ht : t ? Ht : Ut;
		case "left":
		case "right": return t ? Wt : Gt;
		default: return [];
	}
}
function qt(e, t, n, r) {
	let i = Pt(e), a = Kt(Nt(e), n === "start", r);
	return i && (a = a.map((e) => e + "-" + i), t && (a = a.concat(a.map(Vt)))), a;
}
function Jt(e) {
	let t = Nt(e);
	return At[t] + e.slice(t.length);
}
function Yt(e) {
	return {
		top: 0,
		right: 0,
		bottom: 0,
		left: 0,
		...e
	};
}
function Xt(e) {
	return typeof e == "number" ? {
		top: e,
		right: e,
		bottom: e,
		left: e
	} : Yt(e);
}
function Zt(e) {
	let { x: t, y: n, width: r, height: i } = e;
	return {
		width: r,
		height: i,
		top: n,
		left: t,
		right: t + r,
		bottom: n + i,
		x: t,
		y: n
	};
}
// ../../../plugin-kit/plugin-kit/node_modules/@floating-ui/core/dist/floating-ui.core.mjs
function Qt(e, t, n) {
	let { reference: r, floating: i } = e, a = Lt(t), o = Rt(t), s = It(o), c = Nt(t), l = a === "y", u = r.x + r.width / 2 - i.width / 2, d = r.y + r.height / 2 - i.height / 2, f = r[s] / 2 - i[s] / 2, p;
	switch (c) {
		case "top":
			p = {
				x: u,
				y: r.y - i.height
			};
			break;
		case "bottom":
			p = {
				x: u,
				y: r.y + r.height
			};
			break;
		case "right":
			p = {
				x: r.x + r.width,
				y: d
			};
			break;
		case "left":
			p = {
				x: r.x - i.width,
				y: d
			};
			break;
		default: p = {
			x: r.x,
			y: r.y
		};
	}
	switch (Pt(t)) {
		case "start":
			p[o] -= f * (n && l ? -1 : 1);
			break;
		case "end": p[o] += f * (n && l ? -1 : 1);
	}
	return p;
}
async function $t(e, t) {
	t === void 0 && (t = {});
	let { x: n, y: r, platform: i, rects: a, elements: o, strategy: s } = e, { boundary: c = "clippingAncestors", rootBoundary: l = "viewport", elementContext: u = "floating", altBoundary: d = !1, padding: f = 0 } = Mt(t, e), p = Xt(f), m = o[d ? u === "floating" ? "reference" : "floating" : u], h = Zt(await i.getClippingRect({
		element: await (i.isElement == null ? void 0 : i.isElement(m)) ?? !0 ? m : m.contextElement || await (i.getDocumentElement == null ? void 0 : i.getDocumentElement(o.floating)),
		boundary: c,
		rootBoundary: l,
		strategy: s
	})), g = u === "floating" ? {
		x: n,
		y: r,
		width: a.floating.width,
		height: a.floating.height
	} : a.reference, _ = await (i.getOffsetParent == null ? void 0 : i.getOffsetParent(o.floating)), v = await (i.isElement == null ? void 0 : i.isElement(_)) && await (i.getScale == null ? void 0 : i.getScale(_)) || {
		x: 1,
		y: 1
	}, y = Zt(i.convertOffsetParentRelativeRectToViewportRelativeRect ? await i.convertOffsetParentRelativeRectToViewportRelativeRect({
		elements: o,
		rect: g,
		offsetParent: _,
		strategy: s
	}) : g);
	return {
		top: (h.top - y.top + p.top) / v.y,
		bottom: (y.bottom - h.bottom + p.bottom) / v.y,
		left: (h.left - y.left + p.left) / v.x,
		right: (y.right - h.right + p.right) / v.x
	};
}
var en = 50, tn = async (e, t, n) => {
	let { placement: r = "bottom", strategy: i = "absolute", middleware: a = [], platform: o } = n, s = o.detectOverflow ? o : {
		...o,
		detectOverflow: $t
	}, c = await (o.isRTL == null ? void 0 : o.isRTL(t)), l = await o.getElementRects({
		reference: e,
		floating: t,
		strategy: i
	}), { x: u, y: d } = Qt(l, r, c), f = r, p = 0, m = {};
	for (let n = 0; n < a.length; n++) {
		let h = a[n];
		if (!h) continue;
		let { name: g, fn: _ } = h, { x: v, y, data: b, reset: x } = await _({
			x: u,
			y: d,
			initialPlacement: r,
			placement: f,
			strategy: i,
			middlewareData: m,
			rects: l,
			platform: s,
			elements: {
				reference: e,
				floating: t
			}
		});
		u = v ?? u, d = y ?? d, m[g] = {
			...m[g],
			...b
		}, x && p < en && (p++, typeof x == "object" && (x.placement && (f = x.placement), x.rects && (l = x.rects === !0 ? await o.getElementRects({
			reference: e,
			floating: t,
			strategy: i
		}) : x.rects), {x: u, y: d} = Qt(l, f, c)), n = -1);
	}
	return {
		x: u,
		y: d,
		placement: f,
		strategy: i,
		middlewareData: m
	};
}, nn = (e) => ({
	name: "arrow",
	options: e,
	async fn(t) {
		let { x: n, y: r, placement: i, rects: a, platform: o, elements: s, middlewareData: c } = t, { element: l, padding: u = 0 } = Mt(e, t) || {};
		if (l == null) return {};
		let d = Xt(u), f = {
			x: n,
			y: r
		}, p = Rt(i), m = It(p), h = await o.getDimensions(l), g = p === "y", _ = g ? "top" : "left", v = g ? "bottom" : "right", y = g ? "clientHeight" : "clientWidth", b = a.reference[m] + a.reference[p] - f[p] - a.floating[m], x = f[p] - a.reference[p], S = await (o.getOffsetParent == null ? void 0 : o.getOffsetParent(l)), C = S ? S[y] : 0;
		C && await (o.isElement == null ? void 0 : o.isElement(S)) || (C = s.floating[y] || a.floating[m]);
		let ee = b / 2 - x / 2, w = C / 2 - h[m] / 2 - 1, te = Dt(d[_], w), ne = Dt(d[v], w), re = te, T = C - h[m] - ne, E = C / 2 - h[m] / 2 + ee, ie = jt(re, E, T), D = !c.arrow && Pt(i) != null && E !== ie && a.reference[m] / 2 - (E < re ? te : ne) - h[m] / 2 < 0, ae = D ? E < re ? E - re : E - T : 0;
		return {
			[p]: f[p] + ae,
			data: {
				[p]: ie,
				centerOffset: E - ie - ae,
				...D && { alignmentOffset: ae }
			},
			reset: D
		};
	}
}), rn = function(e) {
	return e === void 0 && (e = {}), {
		name: "flip",
		options: e,
		async fn(t) {
			var n;
			let { placement: r, middlewareData: i, rects: a, initialPlacement: o, platform: s, elements: c } = t, { mainAxis: l = !0, crossAxis: u = !0, fallbackPlacements: d, fallbackStrategy: f = "bestFit", fallbackAxisSideDirection: p = "none", flipAlignment: m = !0, ...h } = Mt(e, t);
			if ((n = i.arrow) != null && n.alignmentOffset) return {};
			let g = Nt(r), _ = Lt(o), v = Nt(o) === o, y = await (s.isRTL == null ? void 0 : s.isRTL(c.floating)), b = d || (v || !m ? [Jt(o)] : Bt(o)), x = p !== "none";
			!d && x && b.push(...qt(o, m, p, y));
			let S = [o, ...b], C = await s.detectOverflow(t, h), ee = [], w = i.flip?.overflows || [];
			if (l && ee.push(C[g]), u) {
				let e = zt(r, a, y);
				ee.push(C[e[0]], C[e[1]]);
			}
			if (w = [...w, {
				placement: r,
				overflows: ee
			}], !ee.every((e) => e <= 0)) {
				let e = (i.flip?.index || 0) + 1, t = S[e];
				if (t && (u !== "alignment" || _ === Lt(t) || w.every((e) => Lt(e.placement) !== _ || e.overflows[0] > 0))) return {
					data: {
						index: e,
						overflows: w
					},
					reset: { placement: t }
				};
				let n = w.filter((e) => e.overflows[0] <= 0).sort((e, t) => e.overflows[1] - t.overflows[1])[0]?.placement;
				if (!n) switch (f) {
					case "bestFit": {
						let e = w.filter((e) => {
							if (x) {
								let t = Lt(e.placement);
								return t === _ || t === "y";
							}
							return !0;
						}).map((e) => [e.placement, e.overflows.filter((e) => e > 0).reduce((e, t) => e + t, 0)]).sort((e, t) => e[1] - t[1])[0]?.[0];
						e && (n = e);
						break;
					}
					case "initialPlacement": n = o;
				}
				if (r !== n) return { reset: { placement: n } };
			}
			return {};
		}
	};
}, an = /*#__PURE__*/ new Set(["left", "top"]);
async function on(e, t) {
	let { placement: n, platform: r, elements: i } = e, a = await (r.isRTL == null ? void 0 : r.isRTL(i.floating)), o = Nt(n), s = Pt(n), c = Lt(n) === "y", l = an.has(o) ? -1 : 1, u = a && c ? -1 : 1, d = Mt(t, e), { mainAxis: f, crossAxis: p, alignmentAxis: m } = typeof d == "number" ? {
		mainAxis: d,
		crossAxis: 0,
		alignmentAxis: null
	} : {
		mainAxis: d.mainAxis || 0,
		crossAxis: d.crossAxis || 0,
		alignmentAxis: d.alignmentAxis
	};
	return s && typeof m == "number" && (p = s === "end" ? m * -1 : m), c ? {
		x: p * u,
		y: f * l
	} : {
		x: f * l,
		y: p * u
	};
}
var sn = function(e) {
	return e === void 0 && (e = 0), {
		name: "offset",
		options: e,
		async fn(t) {
			var n;
			let { x: r, y: i, placement: a, middlewareData: o } = t, s = await on(t, e);
			return a === o.offset?.placement && (n = o.arrow) != null && n.alignmentOffset ? {} : {
				x: r + s.x,
				y: i + s.y,
				data: {
					...s,
					placement: a
				}
			};
		}
	};
}, cn = function(e) {
	return e === void 0 && (e = {}), {
		name: "shift",
		options: e,
		async fn(t) {
			let { x: n, y: r, placement: i, platform: a } = t, { mainAxis: o = !0, crossAxis: s = !1, limiter: c = { fn: (e) => {
				let { x: t, y: n } = e;
				return {
					x: t,
					y: n
				};
			} }, ...l } = Mt(e, t), u = {
				x: n,
				y: r
			}, d = await a.detectOverflow(t, l), f = Lt(Nt(i)), p = Ft(f), m = u[p], h = u[f];
			if (o) {
				let e = p === "y" ? "top" : "left", t = p === "y" ? "bottom" : "right", n = m + d[e], r = m - d[t];
				m = jt(n, m, r);
			}
			if (s) {
				let e = f === "y" ? "top" : "left", t = f === "y" ? "bottom" : "right", n = h + d[e], r = h - d[t];
				h = jt(n, h, r);
			}
			let g = c.fn({
				...t,
				[p]: m,
				[f]: h
			});
			return {
				...g,
				data: {
					x: g.x - n,
					y: g.y - r,
					enabled: {
						[p]: o,
						[f]: s
					}
				}
			};
		}
	};
}, ln = function(e) {
	return e === void 0 && (e = {}), {
		name: "size",
		options: e,
		async fn(t) {
			var n, r;
			let { placement: i, rects: a, platform: o, elements: s } = t, { apply: c = () => {}, ...l } = Mt(e, t), u = await o.detectOverflow(t, l), d = Nt(i), f = Pt(i), p = Lt(i) === "y", { width: m, height: h } = a.floating, g, _;
			d === "top" || d === "bottom" ? (g = d, _ = f === (await (o.isRTL == null ? void 0 : o.isRTL(s.floating)) ? "start" : "end") ? "left" : "right") : (_ = d, g = f === "end" ? "top" : "bottom");
			let v = h - u.top - u.bottom, y = m - u.left - u.right, b = Dt(h - u[g], v), x = Dt(m - u[_], y), S = !t.middlewareData.shift, C = b, ee = x;
			if ((n = t.middlewareData.shift) != null && n.enabled.x && (ee = y), (r = t.middlewareData.shift) != null && r.enabled.y && (C = v), S && !f) {
				let e = F(u.left, 0), t = F(u.right, 0), n = F(u.top, 0), r = F(u.bottom, 0);
				p ? ee = m - 2 * (e !== 0 || t !== 0 ? e + t : F(u.left, u.right)) : C = h - 2 * (n !== 0 || r !== 0 ? n + r : F(u.top, u.bottom));
			}
			await c({
				...t,
				availableWidth: ee,
				availableHeight: C
			});
			let w = await o.getDimensions(s.floating);
			return m !== w.width || h !== w.height ? { reset: { rects: !0 } } : {};
		}
	};
};
// ../../../plugin-kit/plugin-kit/node_modules/@floating-ui/utils/dist/floating-ui.utils.dom.mjs
function un() {
	return typeof window < "u";
}
function dn(e) {
	return fn(e) ? (e.nodeName || "").toLowerCase() : "#document";
}
function L(e) {
	var t;
	return (e == null || (t = e.ownerDocument) == null ? void 0 : t.defaultView) || window;
}
function R(e) {
	return ((fn(e) ? e.ownerDocument : e.document) || window.document)?.documentElement;
}
function fn(e) {
	return un() ? e instanceof Node || e instanceof L(e).Node : !1;
}
function z(e) {
	return un() ? e instanceof Element || e instanceof L(e).Element : !1;
}
function pn(e) {
	return un() ? e instanceof HTMLElement || e instanceof L(e).HTMLElement : !1;
}
function mn(e) {
	return !un() || typeof ShadowRoot > "u" ? !1 : e instanceof ShadowRoot || e instanceof L(e).ShadowRoot;
}
function hn(e) {
	let { overflow: t, overflowX: n, overflowY: r, display: i } = B(e);
	return /auto|scroll|overlay|hidden|clip/.test(t + r + n) && i !== "inline" && i !== "contents";
}
function gn(e) {
	return /^(table|td|th)$/.test(dn(e));
}
function _n(e) {
	try {
		if (e.matches(":popover-open")) return !0;
	} catch {}
	try {
		return e.matches(":modal");
	} catch {
		return !1;
	}
}
var vn = /transform|translate|scale|rotate|perspective|filter/, yn = /paint|layout|strict|content/, bn = (e) => !!e && e !== "none", xn;
function Sn(e) {
	let t = z(e) ? B(e) : e;
	return bn(t.transform) || bn(t.translate) || bn(t.scale) || bn(t.rotate) || bn(t.perspective) || !wn() && (bn(t.backdropFilter) || bn(t.filter)) || vn.test(t.willChange || "") || yn.test(t.contain || "");
}
function Cn(e) {
	let t = Dn(e);
	for (; pn(t) && !Tn(t);) {
		if (Sn(t)) return t;
		if (_n(t)) return null;
		t = Dn(t);
	}
	return null;
}
function wn() {
	return xn ??= typeof CSS < "u" && CSS.supports && CSS.supports("-webkit-backdrop-filter", "none"), xn;
}
function Tn(e) {
	return /^(html|body|#document)$/.test(dn(e));
}
function B(e) {
	return L(e).getComputedStyle(e);
}
function En(e) {
	return z(e) ? {
		scrollLeft: e.scrollLeft,
		scrollTop: e.scrollTop
	} : {
		scrollLeft: e.scrollX,
		scrollTop: e.scrollY
	};
}
function Dn(e) {
	if (dn(e) === "html") return e;
	let t = e.assignedSlot || e.parentNode || mn(e) && e.host || R(e);
	return mn(t) ? t.host : t;
}
function On(e) {
	let t = Dn(e);
	return Tn(t) ? e.ownerDocument ? e.ownerDocument.body : e.body : pn(t) && hn(t) ? t : On(t);
}
function kn(e, t, n) {
	t === void 0 && (t = []), n === void 0 && (n = !0);
	let r = On(e), i = r === e.ownerDocument?.body, a = L(r);
	if (i) {
		let e = An(a);
		return t.concat(a, a.visualViewport || [], hn(r) ? r : [], e && n ? kn(e) : []);
	}
	return t.concat(r, kn(r, [], n));
}
function An(e) {
	return e.parent && Object.getPrototypeOf(e.parent) ? e.frameElement : null;
}
// ../../../plugin-kit/plugin-kit/node_modules/@floating-ui/dom/dist/floating-ui.dom.mjs
function jn(e) {
	let t = B(e), n = parseFloat(t.width) || 0, r = parseFloat(t.height) || 0, i = pn(e), a = i ? e.offsetWidth : n, o = i ? e.offsetHeight : r, s = Ot(n) !== a || Ot(r) !== o;
	return s && (n = a, r = o), {
		width: n,
		height: r,
		$: s
	};
}
function Mn(e) {
	return z(e) ? e : e.contextElement;
}
function Nn(e) {
	let t = Mn(e);
	if (!pn(t)) return I(1);
	let n = t.getBoundingClientRect(), { width: r, height: i, $: a } = jn(t), o = (a ? Ot(n.width) : n.width) / r, s = (a ? Ot(n.height) : n.height) / i;
	return o && Number.isFinite(o) || (o = 1), s && Number.isFinite(s) || (s = 1), {
		x: o,
		y: s
	};
}
var Pn = /*#__PURE__*/ I(0);
function Fn(e) {
	let t = L(e);
	return !wn() || !t.visualViewport ? Pn : {
		x: t.visualViewport.offsetLeft,
		y: t.visualViewport.offsetTop
	};
}
function In(e, t, n) {
	return t === void 0 && (t = !1), !n || t && n !== L(e) ? !1 : t;
}
function Ln(e, t, n, r) {
	t === void 0 && (t = !1), n === void 0 && (n = !1);
	let i = e.getBoundingClientRect(), a = Mn(e), o = I(1);
	t && (r ? z(r) && (o = Nn(r)) : o = Nn(e));
	let s = In(a, n, r) ? Fn(a) : I(0), c = (i.left + s.x) / o.x, l = (i.top + s.y) / o.y, u = i.width / o.x, d = i.height / o.y;
	if (a) {
		let e = L(a), t = r && z(r) ? L(r) : r, n = e, i = An(n);
		for (; i && r && t !== n;) {
			let e = Nn(i), t = i.getBoundingClientRect(), r = B(i), a = t.left + (i.clientLeft + parseFloat(r.paddingLeft)) * e.x, o = t.top + (i.clientTop + parseFloat(r.paddingTop)) * e.y;
			c *= e.x, l *= e.y, u *= e.x, d *= e.y, c += a, l += o, n = L(i), i = An(n);
		}
	}
	return Zt({
		width: u,
		height: d,
		x: c,
		y: l
	});
}
function Rn(e, t) {
	let n = En(e).scrollLeft;
	return t ? t.left + n : Ln(R(e)).left + n;
}
function zn(e, t) {
	let n = e.getBoundingClientRect();
	return {
		x: n.left + t.scrollLeft - Rn(e, n),
		y: n.top + t.scrollTop
	};
}
function Bn(e) {
	let { elements: t, rect: n, offsetParent: r, strategy: i } = e, a = i === "fixed", o = R(r), s = t ? _n(t.floating) : !1;
	if (r === o || s && a) return n;
	let c = {
		scrollLeft: 0,
		scrollTop: 0
	}, l = I(1), u = I(0), d = pn(r);
	if ((d || !d && !a) && ((dn(r) !== "body" || hn(o)) && (c = En(r)), d)) {
		let e = Ln(r);
		l = Nn(r), u.x = e.x + r.clientLeft, u.y = e.y + r.clientTop;
	}
	let f = o && !d && !a ? zn(o, c) : I(0);
	return {
		width: n.width * l.x,
		height: n.height * l.y,
		x: n.x * l.x - c.scrollLeft * l.x + u.x + f.x,
		y: n.y * l.y - c.scrollTop * l.y + u.y + f.y
	};
}
function Vn(e) {
	return Array.from(e.getClientRects());
}
function Hn(e) {
	let t = R(e), n = En(e), r = e.ownerDocument.body, i = F(t.scrollWidth, t.clientWidth, r.scrollWidth, r.clientWidth), a = F(t.scrollHeight, t.clientHeight, r.scrollHeight, r.clientHeight), o = -n.scrollLeft + Rn(e), s = -n.scrollTop;
	return B(r).direction === "rtl" && (o += F(t.clientWidth, r.clientWidth) - i), {
		width: i,
		height: a,
		x: o,
		y: s
	};
}
var Un = 25;
function Wn(e, t) {
	let n = L(e), r = R(e), i = n.visualViewport, a = r.clientWidth, o = r.clientHeight, s = 0, c = 0;
	if (i) {
		a = i.width, o = i.height;
		let e = wn();
		(!e || e && t === "fixed") && (s = i.offsetLeft, c = i.offsetTop);
	}
	let l = Rn(r);
	if (l <= 0) {
		let e = r.ownerDocument, t = e.body, n = getComputedStyle(t), i = e.compatMode === "CSS1Compat" && parseFloat(n.marginLeft) + parseFloat(n.marginRight) || 0, o = Math.abs(r.clientWidth - t.clientWidth - i);
		o <= Un && (a -= o);
	} else l <= Un && (a += l);
	return {
		width: a,
		height: o,
		x: s,
		y: c
	};
}
function Gn(e, t) {
	let n = Ln(e, !0, t === "fixed"), r = n.top + e.clientTop, i = n.left + e.clientLeft, a = pn(e) ? Nn(e) : I(1);
	return {
		width: e.clientWidth * a.x,
		height: e.clientHeight * a.y,
		x: i * a.x,
		y: r * a.y
	};
}
function Kn(e, t, n) {
	let r;
	if (t === "viewport") r = Wn(e, n);
	else if (t === "document") r = Hn(R(e));
	else if (z(t)) r = Gn(t, n);
	else {
		let n = Fn(e);
		r = {
			x: t.x - n.x,
			y: t.y - n.y,
			width: t.width,
			height: t.height
		};
	}
	return Zt(r);
}
function qn(e, t) {
	let n = Dn(e);
	return n === t || !z(n) || Tn(n) ? !1 : B(n).position === "fixed" || qn(n, t);
}
function Jn(e, t) {
	let n = t.get(e);
	if (n) return n;
	let r = kn(e, [], !1).filter((e) => z(e) && dn(e) !== "body"), i = null, a = B(e).position === "fixed", o = a ? Dn(e) : e;
	for (; z(o) && !Tn(o);) {
		let t = B(o), n = Sn(o);
		!n && t.position === "fixed" && (i = null), (a ? !n && !i : !n && t.position === "static" && i && (i.position === "absolute" || i.position === "fixed") || hn(o) && !n && qn(e, o)) ? r = r.filter((e) => e !== o) : i = t, o = Dn(o);
	}
	return t.set(e, r), r;
}
function Yn(e) {
	let { element: t, boundary: n, rootBoundary: r, strategy: i } = e, a = [...n === "clippingAncestors" ? _n(t) ? [] : Jn(t, this._c) : [].concat(n), r], o = Kn(t, a[0], i), s = o.top, c = o.right, l = o.bottom, u = o.left;
	for (let e = 1; e < a.length; e++) {
		let n = Kn(t, a[e], i);
		s = F(n.top, s), c = Dt(n.right, c), l = Dt(n.bottom, l), u = F(n.left, u);
	}
	return {
		width: c - u,
		height: l - s,
		x: u,
		y: s
	};
}
function Xn(e) {
	let { width: t, height: n } = jn(e);
	return {
		width: t,
		height: n
	};
}
function Zn(e, t, n) {
	let r = pn(t), i = R(t), a = n === "fixed", o = Ln(e, !0, a, t), s = {
		scrollLeft: 0,
		scrollTop: 0
	}, c = I(0);
	function l() {
		c.x = Rn(i);
	}
	if (r || !r && !a) {
		if ((dn(t) !== "body" || hn(i)) && (s = En(t)), r) {
			let e = Ln(t, !0, a, t);
			c.x = e.x + t.clientLeft, c.y = e.y + t.clientTop;
		} else i && l();
	}
	a && !r && i && l();
	let u = i && !r && !a ? zn(i, s) : I(0);
	return {
		x: o.left + s.scrollLeft - c.x - u.x,
		y: o.top + s.scrollTop - c.y - u.y,
		width: o.width,
		height: o.height
	};
}
function Qn(e) {
	return B(e).position === "static";
}
function $n(e, t) {
	if (!pn(e) || B(e).position === "fixed") return null;
	if (t) return t(e);
	let n = e.offsetParent;
	return R(e) === n && (n = n.ownerDocument.body), n;
}
function er(e, t) {
	let n = L(e);
	if (_n(e)) return n;
	if (!pn(e)) {
		let t = Dn(e);
		for (; t && !Tn(t);) {
			if (z(t) && !Qn(t)) return t;
			t = Dn(t);
		}
		return n;
	}
	let r = $n(e, t);
	for (; r && gn(r) && Qn(r);) r = $n(r, t);
	return r && Tn(r) && Qn(r) && !Sn(r) ? n : r || Cn(e) || n;
}
var tr = async function(e) {
	let t = this.getOffsetParent || er, n = this.getDimensions, r = await n(e.floating);
	return {
		reference: Zn(e.reference, await t(e.floating), e.strategy),
		floating: {
			x: 0,
			y: 0,
			width: r.width,
			height: r.height
		}
	};
};
function nr(e) {
	return B(e).direction === "rtl";
}
var rr = {
	convertOffsetParentRelativeRectToViewportRelativeRect: Bn,
	getDocumentElement: R,
	getClippingRect: Yn,
	getOffsetParent: er,
	getElementRects: tr,
	getClientRects: Vn,
	getDimensions: Xn,
	getScale: Nn,
	isElement: z,
	isRTL: nr
};
function ir(e, t) {
	return e.x === t.x && e.y === t.y && e.width === t.width && e.height === t.height;
}
function ar(e, t) {
	let n = null, r, i = R(e);
	function a() {
		var e;
		clearTimeout(r), (e = n) == null || e.disconnect(), n = null;
	}
	function o(s, c) {
		s === void 0 && (s = !1), c === void 0 && (c = 1), a();
		let l = e.getBoundingClientRect(), { left: u, top: d, width: f, height: p } = l;
		if (s || t(), !f || !p) return;
		let m = kt(d), h = kt(i.clientWidth - (u + f)), g = kt(i.clientHeight - (d + p)), _ = kt(u), v = {
			rootMargin: -m + "px " + -h + "px " + -g + "px " + -_ + "px",
			threshold: F(0, Dt(1, c)) || 1
		}, y = !0;
		function b(t) {
			let n = t[0].intersectionRatio;
			if (n !== c) {
				if (!y) return o();
				n ? o(!1, n) : r = setTimeout(() => {
					o(!1, 1e-7);
				}, 1e3);
			}
			n === 1 && !ir(l, e.getBoundingClientRect()) && o(), y = !1;
		}
		try {
			n = new IntersectionObserver(b, {
				...v,
				root: i.ownerDocument
			});
		} catch {
			n = new IntersectionObserver(b, v);
		}
		n.observe(e);
	}
	return o(!0), a;
}
function or(e, t, n, r) {
	r === void 0 && (r = {});
	let { ancestorScroll: i = !0, ancestorResize: a = !0, elementResize: o = typeof ResizeObserver == "function", layoutShift: s = typeof IntersectionObserver == "function", animationFrame: c = !1 } = r, l = Mn(e), u = i || a ? [...l ? kn(l) : [], ...t ? kn(t) : []] : [];
	u.forEach((e) => {
		i && e.addEventListener("scroll", n, { passive: !0 }), a && e.addEventListener("resize", n);
	});
	let d = l && s ? ar(l, n) : null, f = -1, p = null;
	o && (p = new ResizeObserver((e) => {
		let [r] = e;
		r && r.target === l && p && t && (p.unobserve(t), cancelAnimationFrame(f), f = requestAnimationFrame(() => {
			var e;
			(e = p) == null || e.observe(t);
		})), n();
	}), l && !c && p.observe(l), t && p.observe(t));
	let m, h = c ? Ln(e) : null;
	c && g();
	function g() {
		let t = Ln(e);
		h && !ir(h, t) && n(), h = t, m = requestAnimationFrame(g);
	}
	return n(), () => {
		var e;
		u.forEach((e) => {
			i && e.removeEventListener("scroll", n), a && e.removeEventListener("resize", n);
		}), d?.(), (e = p) == null || e.disconnect(), p = null, c && cancelAnimationFrame(m);
	};
}
var sr = sn, cr = cn, lr = rn, ur = ln, dr = nn, fr = (e, t, n) => {
	let r = /* @__PURE__ */ new Map(), i = {
		platform: rr,
		...n
	}, a = {
		...i.platform,
		_c: r
	};
	return tn(e, t, {
		...i,
		platform: a
	});
};
// ../../../plugin-kit/plugin-kit/node_modules/composed-offset-position/dist/composed-offset-position.browser.min.mjs
function pr(e) {
	return hr(e);
}
function mr(e) {
	return e.assignedSlot ? e.assignedSlot : e.parentNode instanceof ShadowRoot ? e.parentNode.host : e.parentNode;
}
function hr(e) {
	for (let t = e; t; t = mr(t)) if (t instanceof Element && getComputedStyle(t).display === "none") return null;
	for (let t = mr(e); t; t = mr(t)) {
		if (!(t instanceof Element)) continue;
		let e = getComputedStyle(t);
		if (e.display !== "contents" && (e.position !== "static" || Sn(e) || t.tagName === "BODY")) return t;
	}
	return null;
}
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/pk-popup-BhAQq8-2.js
function gr(e, t) {
	if (!t) return null;
	let n = e.getRootNode();
	if (n instanceof Document || n instanceof ShadowRoot) {
		let e = n.getElementById(t);
		if (e) return e;
	}
	return e.ownerDocument.getElementById(t);
}
var _r = class extends Event {
	constructor() {
		super("pk-reposition", {
			bubbles: !0,
			cancelable: !1,
			composed: !0
		});
	}
}, vr = i`
    @layer pk-component {
        :host {
            display: contents;
        }

        .popup {
            position: absolute;
            isolation: isolate;
            width: max-content;
            z-index: var(--pk-popup-z-index, 1000);
            /* Never transition coordinates — flip would animate the jump. */
            transition: none;

            /* Reset UA styles for [popover] — see  pk-popup. */
            inset: unset;
            padding: unset;
            margin: unset;
            height: unset;
            color: unset;
            background: unset;
            border: unset;
            overflow: unset;
        }

        .popup-fixed {
            position: fixed;
        }

        .popup:not(.active) {
            display: none;
        }

        /* Prefer visibility over opacity so enter animations are not fighting a
         * 0→1 fade. Matches base-ui isPositioned / hide-until-placed.
         */
        .popup.active:not(.positioned) {
            visibility: hidden;
            pointer-events: none;
        }

        .popup.show {
            animation: pk-popup-surface-in 100ms ease-out;
        }

        .popup.hide {
            animation: pk-popup-surface-out 100ms ease-in forwards;
        }

        @keyframes pk-popup-surface-in {
            from {
                opacity: 0;
                transform: scale(0.95);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes pk-popup-surface-out {
            from {
                opacity: 1;
                transform: scale(1);
            }

            to {
                opacity: 0;
                transform: scale(0.95);
            }
        }

        .arrow {
            position: absolute;
            width: var(--pk-popup-arrow-size, 6px);
            height: var(--pk-popup-arrow-size, 6px);
            rotate: 45deg;
            background: var(--pk-popup-arrow-color, var(--pk-color-white));
            z-index: 1;
        }

        .hover-bridge {
            position: fixed;
            z-index: calc(var(--pk-popup-z-index, 1000) - 1);
            inset: 0;
            clip-path: polygon(
                var(--pk-hover-bridge-top-left-x, 0) var(--pk-hover-bridge-top-left-y, 0),
                var(--pk-hover-bridge-top-right-x, 0) var(--pk-hover-bridge-top-right-y, 0),
                var(--pk-hover-bridge-bottom-right-x, 0) var(--pk-hover-bridge-bottom-right-y, 0),
                var(--pk-hover-bridge-bottom-left-x, 0) var(--pk-hover-bridge-bottom-left-y, 0)
            );
            pointer-events: auto;
        }

        .hover-bridge:not(.hover-bridge-visible) {
            display: none;
        }
    }
`;
function yr(e) {
	return typeof e == "object" && !!e && "getBoundingClientRect" in e;
}
function br(e) {
	return e || (Et ? "absolute" : "fixed");
}
function xr(e, t) {
	if (Et && !yr(e) && t === "scroll") return kn(e).filter((e) => e instanceof Element);
}
var V = class extends T {
	constructor(...e) {
		super(...e), this.anchor = "", this.active = !1, this.boundary = "viewport", this.placement = "bottom-start", this.distance = 4, this.skidding = 0, this.flip = !0, this.flipFallbackPlacements = "", this.flipFallbackStrategy = "best-fit", this.flipPadding = 8, this.shift = !0, this.shiftPadding = 8, this.arrow = !1, this.arrowPlacement = "anchor", this.arrowPadding = 10, this.autoSizePadding = 8, this.anchorTracking = !0, this.hoverBridge = !1, this.anchorElement = null, this.settlingInitialPosition = !1, this.settleGeneration = 0;
	}
	static {
		this.styles = vr;
	}
	disconnectedCallback() {
		this.stop(), super.disconnectedCallback();
	}
	updated(e) {
		super.updated(e), e.has("active") && (this.active ? (this.resolveAnchor(), this.start()) : this.stop()), e.has("anchor") && this.handleAnchorChange(), this.active && !e.has("active") && this.reposition();
	}
	reposition() {
		this.settlingInitialPosition || this.repositionAsync();
	}
	async repositionAsync(e = !0) {
		let t = this.popupElement, n = this.arrow ? this.arrowElement : null;
		if (!this.active || !this.anchorElement || !t) return !1;
		let r = xr(this.anchorElement, this.boundary), i = [sr({
			mainAxis: this.distance,
			crossAxis: this.skidding
		})];
		this.sync ? i.push(ur({ apply: ({ rects: e }) => {
			let n = this.sync === "width" || this.sync === "both", r = this.sync === "height" || this.sync === "both";
			t.style.width = n ? `${e.reference.width}px` : "", t.style.height = r ? `${e.reference.height}px` : "";
		} })) : (t.style.width = "", t.style.height = ""), this.flip && i.push(lr({
			boundary: r,
			fallbackPlacements: this.flipFallbackPlacements ? this.flipFallbackPlacements.split(" ").map((e) => e.trim()).filter(Boolean) : void 0,
			fallbackStrategy: this.flipFallbackStrategy === "best-fit" ? "bestFit" : "initialPlacement",
			padding: this.flipPadding
		})), this.shift && i.push(cr({
			boundary: r,
			padding: this.shiftPadding
		})), this.autoSize ? i.push(ur({
			boundary: r,
			padding: this.autoSizePadding,
			apply: ({ availableHeight: e, availableWidth: n }) => {
				let r = this.autoSize === "horizontal" || this.autoSize === "both", i = this.autoSize === "vertical" || this.autoSize === "both";
				r ? t.style.setProperty("--pk-popup-available-width", `${Math.max(0, Math.floor(n))}px`) : t.style.removeProperty("--pk-popup-available-width"), i ? t.style.setProperty("--pk-popup-available-height", `${Math.max(0, Math.floor(e))}px`) : t.style.removeProperty("--pk-popup-available-height");
			}
		})) : (t.style.removeProperty("--pk-popup-available-width"), t.style.removeProperty("--pk-popup-available-height")), this.arrow && n && i.push(dr({
			element: n,
			padding: this.arrowPadding
		}));
		let a = br(this.positionMethod), o = a === "fixed";
		t.classList.toggle("popup-fixed", o);
		let s = Et ? (e) => rr.getOffsetParent(e, pr) : rr.getOffsetParent, { x: c, y: l, middlewareData: u, placement: d } = await fr(this.anchorElement, t, {
			placement: this.placement,
			middleware: i,
			strategy: a,
			platform: {
				...rr,
				getOffsetParent: s
			}
		});
		if (!this.active || !t.isConnected) return !1;
		let f = {
			top: "bottom",
			right: "left",
			bottom: "top",
			left: "right"
		}[d.split("-")[0]];
		if (this.setAttribute("data-current-placement", d), Object.assign(t.style, {
			left: `${c}px`,
			top: `${l}px`,
			...o ? { position: "fixed" } : { position: "" }
		}), this.anchorElement) {
			let e = this.anchorElement.getBoundingClientRect(), n = t.getBoundingClientRect();
			t.style.setProperty("--pk-anchor-width", `${e.width}px`), t.style.setProperty("--pk-anchor-height", `${e.height}px`);
			let r = Ct(d, e, n, this.distance, u.shift);
			t.style.setProperty("--pk-transform-origin", r);
		}
		if (this.arrow && n) {
			let e = u.arrow?.x, t = u.arrow?.y, r = "", i = "", a = "", o = "";
			if (this.arrowPlacement === "start") {
				let n = typeof e == "number" ? `${this.arrowPadding}px` : "";
				r = typeof t == "number" ? `${this.arrowPadding}px` : "", o = n;
			} else this.arrowPlacement === "end" ? (i = typeof e == "number" ? `${this.arrowPadding}px` : "", a = typeof t == "number" ? `${this.arrowPadding}px` : "") : this.arrowPlacement === "center" ? (o = typeof e == "number" ? "50%" : "", r = typeof t == "number" ? "50%" : "") : (o = typeof e == "number" ? `${e}px` : "", r = typeof t == "number" ? `${t}px` : "");
			Object.assign(n.style, {
				top: r,
				right: i,
				bottom: a,
				left: o,
				transform: "",
				[f]: "calc(-1 * var(--pk-popup-arrow-size, 6px) / 2)"
			});
		}
		return requestAnimationFrame(() => this.updateHoverBridge()), e && this.dispatchEvent(new _r()), !0;
	}
	frames(e) {
		return new Promise((t) => {
			let n = (e) => {
				e <= 0 ? t() : requestAnimationFrame(() => n(e - 1));
			};
			n(e);
		});
	}
	async settleInitialPosition() {
		let e = ++this.settleGeneration, t = this.popupElement;
		t ? (await this.frames(2), this.active && e === this.settleGeneration && (await this.repositionAsync(!1), t.offsetHeight, await this.frames(1), this.active && e === this.settleGeneration && (await this.repositionAsync(!1), this.active && e === this.settleGeneration && (t.classList.add("positioned"), this.settlingInitialPosition = !1, requestAnimationFrame(() => this.updateHoverBridge()), this.dispatchEvent(new _r()))))) : this.settlingInitialPosition = !1;
	}
	resolveAnchor() {
		if (typeof this.anchor == "string" && this.anchor) {
			this.anchorElement = gr(this, this.anchor);
			return;
		}
		if (this.anchor instanceof Element || yr(this.anchor)) {
			this.anchorElement = this.anchor;
			return;
		}
		let e = this.querySelector("[slot=\"anchor\"]");
		e instanceof HTMLSlotElement && (e = e.assignedElements({ flatten: !0 })[0] ?? null), this.anchorElement = e;
	}
	async handleAnchorChange() {
		await this.stop(), this.resolveAnchor(), this.anchorElement && this.active && this.start();
	}
	usesPopoverTopLayer() {
		return Et && this.positionMethod !== "fixed";
	}
	stop() {
		return new Promise((e) => {
			let t = this.popupElement;
			this.trackingFrame !== void 0 && cancelAnimationFrame(this.trackingFrame), this.trackingFrame = void 0, this.settleGeneration += 1, this.settlingInitialPosition = !1, t?.classList.remove("positioned"), this.usesPopoverTopLayer() && t?.hidePopover?.(), this.cleanup ? (this.cleanup(), this.cleanup = void 0, t?.style.removeProperty("--pk-transform-origin"), requestAnimationFrame(() => e())) : e(), this.removeAttribute("data-current-placement");
		});
	}
	releasePositioning() {
		this.cleanup &&= (this.cleanup(), void 0);
	}
	async awaitHidden() {
		await this.stop();
	}
	start() {
		this.anchorElement && this.active && this.isConnected && this.popupElement && (this.popupElement.classList.remove("positioned"), this.settlingInitialPosition = !0, this.usesPopoverTopLayer() && this.popupElement.showPopover?.(), this.anchorTracking && (this.cleanup = or(this.anchorElement, this.popupElement, () => {
			this.settlingInitialPosition || (this.trackingFrame !== void 0 && cancelAnimationFrame(this.trackingFrame), this.trackingFrame = requestAnimationFrame(() => {
				this.trackingFrame = void 0, this.reposition();
			}));
		})), this.settleInitialPosition());
	}
	getContentElement() {
		let e = ((this.shadowRoot?.querySelector("slot:not([name])"))?.assignedElements({ flatten: !0 }) ?? []).find((e) => e instanceof HTMLElement);
		if (e) return e;
		for (let e of this.childNodes) if (e instanceof HTMLElement && e.getAttribute("slot") !== "anchor") return e;
		return null;
	}
	updateHoverBridge() {
		let e = this.popupElement;
		if (!this.hoverBridge || !this.anchorElement || !e) return;
		let t = this.anchorElement.getBoundingClientRect(), n = e.getBoundingClientRect(), r = this.placement.includes("top") || this.placement.includes("bottom"), i = 0, a = 0, o = 0, s = 0, c = 0, l = 0, u = 0, d = 0;
		r ? t.top < n.top ? (i = t.left, a = t.bottom, o = t.right, s = t.bottom, c = n.left, l = n.top, u = n.right, d = n.top) : (i = n.left, a = n.bottom, o = n.right, s = n.bottom, c = t.left, l = t.top, u = t.right, d = t.top) : t.left < n.left ? (i = t.right, a = t.top, o = n.left, s = n.top, c = t.right, l = t.bottom, u = n.left, d = n.bottom) : (i = n.right, a = n.top, o = t.left, s = t.top, c = n.right, l = n.bottom, u = t.left, d = t.bottom), this.style.setProperty("--pk-hover-bridge-top-left-x", `${i}px`), this.style.setProperty("--pk-hover-bridge-top-left-y", `${a}px`), this.style.setProperty("--pk-hover-bridge-top-right-x", `${o}px`), this.style.setProperty("--pk-hover-bridge-top-right-y", `${s}px`), this.style.setProperty("--pk-hover-bridge-bottom-left-x", `${c}px`), this.style.setProperty("--pk-hover-bridge-bottom-left-y", `${l}px`), this.style.setProperty("--pk-hover-bridge-bottom-right-x", `${u}px`), this.style.setProperty("--pk-hover-bridge-bottom-right-y", `${d}px`);
	}
	render() {
		let t = !Et || this.positionMethod === "fixed", r = this.usesPopoverTopLayer();
		return e`
            <slot name="anchor" @slotchange=${() => {
			this.handleAnchorChange();
		}}></slot>
            ${this.hoverBridge ? e`
                <div
                    part="hover-bridge"
                    class=${w({
			"hover-bridge": !0,
			"hover-bridge-visible": this.active
		})}
                    aria-hidden="true"
                ></div>
            ` : n}
            <div
                popover=${r ? "manual" : n}
                part="popup"
                class=${w({
			popup: !0,
			active: this.active,
			"popup-fixed": t
		})}
            >
                ${this.arrow ? e`<div part="arrow" class="arrow"></div>` : n}
                <slot></slot>
            </div>
        `;
	}
};
s([t()], V.prototype, "anchor", void 0), s([t({
	type: Boolean,
	reflect: !0
})], V.prototype, "active", void 0), s([t({ attribute: "position-method" })], V.prototype, "positionMethod", void 0), s([t({ reflect: !0 })], V.prototype, "boundary", void 0), s([t({ reflect: !0 })], V.prototype, "placement", void 0), s([t({ type: Number })], V.prototype, "distance", void 0), s([t({ type: Number })], V.prototype, "skidding", void 0), s([t({ type: Boolean })], V.prototype, "flip", void 0), s([t({ attribute: "flip-fallback-placements" })], V.prototype, "flipFallbackPlacements", void 0), s([t({ attribute: "flip-fallback-strategy" })], V.prototype, "flipFallbackStrategy", void 0), s([t({
	attribute: "flip-padding",
	type: Number
})], V.prototype, "flipPadding", void 0), s([t({ type: Boolean })], V.prototype, "shift", void 0), s([t({
	attribute: "shift-padding",
	type: Number
})], V.prototype, "shiftPadding", void 0), s([t({ type: Boolean })], V.prototype, "arrow", void 0), s([t({ attribute: "arrow-placement" })], V.prototype, "arrowPlacement", void 0), s([t({
	attribute: "arrow-padding",
	type: Number
})], V.prototype, "arrowPadding", void 0), s([t()], V.prototype, "sync", void 0), s([t({ attribute: "auto-size" })], V.prototype, "autoSize", void 0), s([t({
	attribute: "auto-size-padding",
	type: Number
})], V.prototype, "autoSizePadding", void 0), s([t({
	attribute: "anchor-tracking",
	type: Boolean
})], V.prototype, "anchorTracking", void 0), s([t({
	attribute: "hover-bridge",
	type: Boolean
})], V.prototype, "hoverBridge", void 0), s([E(".popup")], V.prototype, "popupElement", void 0), s([E(".arrow")], V.prototype, "arrowElement", void 0), V = s([r("pk-popup")], V);
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/popup-pointer-Bh_d205v.js
function Sr(e) {
	if (e.panel instanceof Element) {
		let t = e.panel.closest("pk-popup");
		if (t) return t;
		let n = e.panel.getRootNode();
		if (n instanceof ShadowRoot && n.host.localName === "pk-popup") return n.host;
	}
	return e.host instanceof HTMLElement ? e.host.shadowRoot?.querySelector("pk-popup") ?? e.host.querySelector(":scope > pk-popup") ?? e.host.querySelector("pk-popup") : null;
}
function Cr(e, t = {}) {
	let n = e.composedPath();
	if (t.host && n.includes(t.host) || t.anchor && n.includes(t.anchor) || t.panel && n.includes(t.panel)) return !0;
	let r = Sr(t);
	return r && n.includes(r) ? !0 : n.some((e) => e instanceof HTMLElement ? r && e.classList.contains("popup") && (e === r || r.contains(e)) ? !0 : t.extraMatches?.(e) ?? !1 : !1);
}
function wr(e, t = {}) {
	return Cr(e, t);
}
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/popup-content-animation.styles-CJ8zGJz-.js
var Tr = i`
    @layer pk-component {
        .pk-popup-content {
            transform-origin: var(--pk-transform-origin, top);
        }

        .pk-popup-content[data-open] {
            animation: pk-popup-content-in 100ms ease-out;
        }

        .pk-popup-content[data-open][data-side='bottom'] {
            animation-name: pk-popup-content-in-bottom;
        }

        .pk-popup-content[data-open][data-side='top'] {
            animation-name: pk-popup-content-in-top;
        }

        .pk-popup-content[data-open][data-side='left'] {
            animation-name: pk-popup-content-in-left;
        }

        .pk-popup-content[data-open][data-side='right'] {
            animation-name: pk-popup-content-in-right;
        }

        /* Exit: fade + zoom only — matches tw-animate animate-out / tooltip motion. */
        .pk-popup-content.closing {
            animation: pk-popup-content-out 100ms ease-in forwards;
        }
    }

    @keyframes pk-popup-content-in {
        from {
            opacity: 0;
            transform: scale(0.95);
        }

        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    @keyframes pk-popup-content-out {
        from {
            opacity: 1;
            transform: scale(1);
        }

        to {
            opacity: 0;
            transform: scale(0.95);
        }
    }

    @keyframes pk-popup-content-in-bottom {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(-0.5rem);
        }

        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    @keyframes pk-popup-content-in-top {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(0.5rem);
        }

        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    @keyframes pk-popup-content-in-left {
        from {
            opacity: 0;
            transform: scale(0.95) translateX(0.5rem);
        }

        to {
            opacity: 1;
            transform: scale(1) translateX(0);
        }
    }

    @keyframes pk-popup-content-in-right {
        from {
            opacity: 0;
            transform: scale(0.95) translateX(-0.5rem);
        }

        to {
            opacity: 1;
            transform: scale(1) translateX(0);
        }
    }
`, Er = [
	ie(),
	Tr,
	i`
        @layer pk-component {
            :host {
                /* Flex column parents stretch cross-axis size — pin to content.
                   (inline-block + align-self; same class of fix as dialog / dropdown.) */
                display: inline-block;
                max-width: 100%;
                align-self: flex-start;
                flex: none;
                vertical-align: middle;
            }

            :host([data-pk-group-orientation]) {
                display: inline-flex;
                vertical-align: middle;
                flex: 0 0 auto;
                align-self: auto;
            }

            :host([data-pk-group-orientation]) ::slotted([slot='trigger']) {
                --pk-bg-start-start-radius: inherit;
                --pk-bg-start-end-radius: inherit;
                --pk-bg-end-start-radius: inherit;
                --pk-bg-end-end-radius: inherit;
            }

            :host([data-pk-group-orientation='horizontal'][data-pk-group-join]) {
                margin-inline-start: var(--pk-bg-horizontal-indent, 0);
            }

            :host([data-pk-group-orientation='vertical'][data-pk-group-join]) {
                margin-block-start: var(--pk-bg-vertical-indent, 0);
            }

            :host([data-pk-group-orientation='horizontal'][data-pk-group-join]:has([slot='trigger'][variant='outline'], [slot='trigger'][variant='dashed'])) {
                margin-inline-start: var(--pk-bg-horizontal-indent-outlined, 0);
            }

            :host([data-pk-group-orientation='vertical'][data-pk-group-join]:has([slot='trigger'][variant='outline'], [slot='trigger'][variant='dashed'])) {
                margin-block-start: var(--pk-bg-vertical-indent-outlined, 0);
            }

            /* Match pk-popup's arrow fill to the panel surface when with-arrow is on. */
            :host([with-arrow]) {
                --pk-popup-arrow-color: var(--pk-color-white);
                --pk-popup-arrow-size: 8px;
            }

            .panel {
                box-sizing: border-box;
                width: 18rem;
                padding: 1rem;
                border-radius: var(--pk-radius-md);
                background: var(--pk-color-white);
                box-shadow: var(--pk-shadow-popover);
                /* Craft CP body text (~gray-700), not gray-900. */
                color: var(--pk-color-gray-700);
            }

            /* Flush panels for command/menu chrome that owns its own inset (variable picker, etc.).
               Match kit v1 PopoverContent min-w 260px / max-w 360px: without min-width,
               width max-content shrinks to short labels and looks narrower than the old picker. */
            :host([flush]) .panel {
                width: max-content;
                min-width: var(--pk-popover-flush-min-width, 16.25rem);
                max-width: min(var(--pk-popover-flush-max-width, 22.5rem), 100vw - 1rem);
                padding: 0;
            }

            .panel[hidden] {
                display: none !important;
            }
        }
    `
], H = class extends T {
	constructor(...e) {
		super(...e), this.open = !1, this.label = "", this.placement = "bottom", this.sideOffset = 4, this.flush = !1, this.withArrow = !1, this.for = "", this.anchor = null, this.triggerElement = null, this.closing = !1, this.panelAnimated = !1, this.triggerId = Xe("pk-popover-trigger"), this.dismissRegistered = !1, this.syncingOpenSideEffects = !1, this.exitAnimationPromise = null, this.handleToggleClick = (e) => {
			e.preventDefault(), e.stopPropagation(), !this.closing && (this.open = !this.open);
		}, this.onDocumentPointerDown = (e) => {
			this.isPointerInside(e) || this.closing || this.closePopover("light-dismiss");
		}, this.onDocumentKeyDown = (e) => {
			e.key === "Escape" && se(this) && !this.closing && (e.preventDefault(), e.stopPropagation(), this.closePopover("escape"));
		};
	}
	static {
		this.styles = Er;
	}
	get panelElement() {
		return this.popupElement?.getContentElement() ?? null;
	}
	disconnectedCallback() {
		this.closePopover("api", !0), super.disconnectedCallback();
	}
	willUpdate(e) {
		e.has("open") && this.open === !1 && e.get("open") === !0 && !this.syncingOpenSideEffects && !this.closing && (this.closing = !0, this.panelAnimated = !1);
	}
	async updated(e) {
		if (super.updated(e), !e.has("open") || this.syncingOpenSideEffects) return;
		let t = e.get("open");
		t !== this.open && (t !== void 0 || this.open !== !1) && (this.open ? await this.openPopover() : await this.closePopover("api"));
	}
	onTriggerSlotChange(e) {
		let [t] = e.target.assignedElements({ flatten: !0 });
		this.unbindTrigger(this.triggerElement), this.triggerElement = t ?? null, this.bindTrigger(this.triggerElement);
	}
	bindTrigger(e) {
		e && (e.id ||= this.triggerId, e.setAttribute("aria-haspopup", "dialog"), e.addEventListener("click", this.handleToggleClick), this.syncExpanded());
	}
	unbindTrigger(e) {
		e?.removeEventListener("click", this.handleToggleClick);
	}
	async openPopover() {
		if (!this.getAnchor()) return;
		if (this.exitAnimationPromise && await this.exitAnimationPromise, this.dismissRegistered && this.open) {
			this.panelElement && (this.panelElement.hidden = !1), this.syncExpanded();
			return;
		}
		if (!this.dispatchEvent(new ce())) {
			this.syncingOpenSideEffects = !0, this.open = !1, this.syncingOpenSideEffects = !1;
			return;
		}
		this.syncingOpenSideEffects = !0, this.open = !0, this.syncingOpenSideEffects = !1, this.closing = !1, this.panelAnimated = !1, this.panelElement && (this.panelElement.hidden = !1, wt(this.panelElement, this.placement)), this.syncExpanded(), this.registerDismissHandlers(), await this.updateComplete;
		let e = await Tt(this.popupElement, this.placement, 300, { requireEvent: !0 });
		this.panelElement && wt(this.panelElement, e), this.panelAnimated = !0, this.dispatchEvent(new le()), this.dispatchEvent(new CustomEvent("pk-open-change", {
			detail: { open: !0 },
			bubbles: !0,
			composed: !0
		}));
	}
	async closePopover(e = "unknown", t = !1) {
		if (this.exitAnimationPromise) return this.exitAnimationPromise;
		if (!this.dismissRegistered && !this.closing && !this.open) return;
		let n = new ue(e);
		if (!this.dispatchEvent(n)) {
			this.syncingOpenSideEffects = !0, this.open = !0, this.syncingOpenSideEffects = !1, this.closing = !1, this.panelAnimated = !0;
			return;
		}
		this.unregisterDismissHandlers(), this.closing = !0, this.panelAnimated = !1, this.open && (this.syncingOpenSideEffects = !0, this.open = !1, this.syncingOpenSideEffects = !1);
		let r = async () => {
			t || (await this.updateComplete, await this.waitForExitAnimation());
			let n = !t && e !== "light-dismiss" && this.isConnected && ([...Je()].some((e) => this.contains(e) || this.panelElement?.contains(e)) || (e === "close-button" || e === "escape") && document.activeElement === document.body);
			this.closing = !1, this.panelAnimated = !1, this.panelElement && (this.panelElement.hidden = !0, this.panelElement.removeAttribute("data-side")), this.syncExpanded(), n && this.getAnchorElement()?.focus({ preventScroll: !0 }), this.dispatchEvent(new de()), this.dispatchEvent(new CustomEvent("pk-open-change", {
				detail: { open: !1 },
				bubbles: !0,
				composed: !0
			}));
		};
		return this.exitAnimationPromise = r().finally(() => {
			this.exitAnimationPromise = null;
		}), this.exitAnimationPromise;
	}
	waitForExitAnimation() {
		let e = this.panelElement;
		return e ? new Promise((t) => {
			let n = !1, r = () => {
				n || (n = !0, e.removeEventListener("animationend", i), window.clearTimeout(a), e.classList.remove("closing"), t());
			}, i = (t) => {
				t.target === e && t.animationName.startsWith("pk-popup-content-out") && r();
			};
			e.classList.add("closing"), e.addEventListener("animationend", i);
			let a = window.setTimeout(r, 150);
		}) : Promise.resolve();
	}
	getAnchor() {
		return this.anchor ? this.anchor : this.for ? gr(this, this.for) : this.triggerElement ? this.triggerElement : null;
	}
	registerDismissHandlers() {
		this.dismissRegistered || (ae(this), this.dismissRegistered = !0, document.addEventListener("pointerdown", this.onDocumentPointerDown, !0), document.addEventListener("keydown", this.onDocumentKeyDown, !0));
	}
	unregisterDismissHandlers() {
		this.dismissRegistered &&= (oe(this), !1), document.removeEventListener("pointerdown", this.onDocumentPointerDown, !0), document.removeEventListener("keydown", this.onDocumentKeyDown, !0);
	}
	isPointerInside(e) {
		return wr(e, {
			host: this,
			anchor: this.getAnchorElement(),
			panel: this.panelElement
		});
	}
	getAnchorElement() {
		return this.anchor instanceof HTMLElement ? this.anchor : this.triggerElement ? this.triggerElement : this.for ? gr(this, this.for) : null;
	}
	syncExpanded() {
		this.triggerElement?.setAttribute("aria-expanded", this.open ? "true" : "false");
	}
	render() {
		let t = this.getAnchor();
		return e`
            <slot name="trigger" @slotchange=${this.onTriggerSlotChange}></slot>
            <pk-popup
                .active=${this.open || this.closing}
                .anchor=${t ?? ""}
                .placement=${this.placement}
                .distance=${this.sideOffset}
                .arrow=${this.withArrow}
                flip
                shift
            >
                <div
                    part="panel"
                    role="dialog"
                    aria-label=${this.label || this.triggerElement?.getAttribute("aria-label") || this.triggerElement?.textContent?.trim() || n}
                    class=${w({
			panel: !0,
			"pk-popup-content": !0,
			closing: this.closing
		})}
                    ?hidden=${!this.open && !this.closing}
                    data-open=${this.panelAnimated && !this.closing ? "" : n}
                    tabindex=${this.open ? "-1" : n}
                >
                    <slot></slot>
                </div>
            </pk-popup>
        `;
	}
};
s([t({
	type: Boolean,
	reflect: !0
})], H.prototype, "open", void 0), s([t()], H.prototype, "label", void 0), s([t({ reflect: !0 })], H.prototype, "placement", void 0), s([t({
	attribute: "side-offset",
	type: Number
})], H.prototype, "sideOffset", void 0), s([t({
	type: Boolean,
	reflect: !0
})], H.prototype, "flush", void 0), s([t({
	attribute: "with-arrow",
	type: Boolean,
	reflect: !0
})], H.prototype, "withArrow", void 0), s([t({ reflect: !0 })], H.prototype, "for", void 0), s([t({ attribute: !1 })], H.prototype, "anchor", void 0), s([E("pk-popup")], H.prototype, "popupElement", void 0), s([o()], H.prototype, "triggerElement", void 0), s([o()], H.prototype, "closing", void 0), s([o()], H.prototype, "panelAnimated", void 0), H = s([r("pk-popover")], H);
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/sync-listbox-separators-saLEtPpJ.js
var Dr = /* @__PURE__ */ new Set([
	"ArrowDown",
	"ArrowUp",
	"ArrowLeft",
	"ArrowRight",
	"Home",
	"End",
	"Enter",
	" ",
	"Escape"
]);
function Or(e) {
	return e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey;
}
function kr(e) {
	return e.filter((e) => !e.hasAttribute("disabled") && !e.hasAttribute("hidden") && e.getAttribute("aria-disabled") !== "true" && e.getAttribute("aria-hidden") !== "true");
}
function Ar(e, t, n) {
	if (n) {
		n(t);
		return;
	}
	let r = e[t];
	r instanceof HTMLElement && "focusControl" in r && typeof r.focusControl == "function" ? r.focusControl() : r?.focus();
}
function jr(e) {
	if (!e) return;
	let t = e.shadowRoot?.querySelector(".option");
	t instanceof HTMLButtonElement ? t.click() : e.click();
}
function Mr(e, t) {
	let n = kr(t.items), r = t.loop === !0;
	if (n.length === 0) return t.currentIndex;
	let i = Math.max(0, t.currentIndex), a = n[i] ?? n[0];
	switch (i = n.indexOf(a), i < 0 && (i = 0), e.key) {
		case "ArrowDown":
		case "ArrowRight": return e.preventDefault(), i = r && i >= n.length - 1 ? 0 : Math.min(i + 1, n.length - 1), Ar(n, i, t.focusItem), t.onSelect(i), i;
		case "ArrowUp":
		case "ArrowLeft": return e.preventDefault(), i = r && i <= 0 ? n.length - 1 : Math.max(i - 1, 0), Ar(n, i, t.focusItem), t.onSelect(i), i;
		case "Home": return e.preventDefault(), i = 0, Ar(n, i, t.focusItem), t.onSelect(i), i;
		case "End": return e.preventDefault(), i = n.length - 1, Ar(n, i, t.focusItem), t.onSelect(i), i;
		case "Enter":
		case " ": return t.multiselect || (e.preventDefault(), jr(n[i])), i;
		case "Escape": return e.preventDefault(), t.onClose?.(), i;
		default: return i;
	}
}
function Nr(e, t) {
	let n = "", r = 0, i = () => {
		n = "", window.clearTimeout(r);
	};
	return {
		handleKey: (a) => {
			if (a.key.length !== 1 || a.ctrlKey || a.metaKey || a.altKey) return;
			n += a.key.toLowerCase(), window.clearTimeout(r), r = window.setTimeout(i, 750);
			let o = kr(e);
			for (let e = 0; e < o.length; e += 1) if ((o[e]?.textContent ?? "").trim().toLowerCase().startsWith(n)) {
				t(e), a.preventDefault();
				return;
			}
		},
		reset: i
	};
}
var Pr = (e) => e.hidden || e.hasAttribute("data-pk-filter-empty"), Fr = (e) => {
	let t = [...e.querySelectorAll(":scope > pk-option, :scope > pk-option-group, :scope > pk-separator")], n = (e, n) => {
		for (let r = e + n; n < 0 ? r >= 0 : r < t.length; r += n) {
			let e = t[r];
			if (e && e.localName !== "pk-separator") return e;
		}
		return null;
	};
	for (let e = 0; e < t.length; e += 1) {
		let r = t[e];
		if (!r || r.localName !== "pk-separator") continue;
		let i = n(e, -1), a = n(e, 1);
		r.hidden = !i || !a || Pr(i) || Pr(a);
	}
}, Ir = [Tr, i`
    ${u}
    @layer pk-component {
        :host {
            display: inline-block;
            position: relative;
            width: fit-content;
            max-width: 100%;
            align-self: flex-start;
            flex: none;
            color: var(--pk-color-gray-700);
            font-family: var(--pk-font-family);
            font-size: var(--pk-font-size-base);
            line-height: var(--pk-line-height);
            --pk-select-trigger-border-width: 1px;
            --pk-select-trigger-min-height: var(--pk-control-height-default);
            --pk-select-item-min-height: var(--pk-input-height);
            --pk-select-item-padding-block: calc((var(--pk-control-height-default) - var(--pk-input-control-line-height, 1.25rem) - 2px) / 2);
            --pk-select-item-padding-inline: 10px;
            --pk-select-item-padding-inline-end: 2rem;
            --pk-select-item-font-size: var(--pk-control-font-default);
            --pk-select-item-line-height: var(--pk-input-control-line-height, 1.25rem);
            --pk-select-item-indicator-size: 0.75rem;
            --pk-select-item-indicator-inset: 0.5rem;
            /* v1 SelectLabel default: text-xs → 12px */
            --pk-select-group-label-font-size: 12px;
            --pk-select-decoration-size: 0.875rem;
        }

        :host([width='full']) {
            display: block;
            width: 100%;
        }

        :host([width='full']) .control {
            width: 100%;
        }

        :host([width='full']) button.control .icon {
            margin-inline-start: auto;
        }

        .control {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            /* Fill the host when consumers set min-width/width on :host. */
            width: 100%;
            max-width: 100%;
            min-width: 0;
            min-height: var(--pk-select-trigger-min-height);
            margin: 0;
            padding: var(--pk-select-item-padding-block) var(--pk-select-item-padding-inline);
            border: var(--pk-select-trigger-border-width) solid transparent;
            border-radius: var(--pk-radius-lg);
            --pk-select-fill: var(--pk-color-slate-250);
            --pk-select-fill-hover: var(--pk-color-slate-300);
            background: var(--pk-select-fill);
            color: var(--pk-color-gray-700);
            font: inherit;
            font-size: var(--pk-select-item-font-size);
            line-height: var(--pk-input-control-line-height, 1.25rem);
            white-space: nowrap;
            cursor: pointer;
            outline: none;
            box-sizing: border-box;
            transition: border-color 0.12s ease, box-shadow 0.12s ease, background 0.12s ease;
        }

        button.control {
            appearance: none;
            -webkit-appearance: none;
            text-align: left;
            background-color: var(--pk-select-fill);
            border: var(--pk-select-trigger-border-width) solid transparent;
        }

        :host(:not([disabled])) .control:hover:not(.is-disabled):not(:disabled),
        :host(:not([disabled])) button.control:hover:not(.is-disabled):not(:disabled) {
            background: var(--pk-select-fill-hover);
        }

        :host(:not([disabled])) button.control:hover:not(.is-disabled):not(:disabled) {
            background-color: var(--pk-select-fill-hover);
        }

        :host(:not([invalid]):not(:state(user-invalid))) button.control:focus-visible,
        :host(:not([invalid]):not(:state(user-invalid))[data-state='focus-visible']) button.control {
            border-color: var(--pk-color-sky-600);
            box-shadow: var(--pk-input-focus-shadow);
        }

        .control.is-disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }

        .trigger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            min-width: 0;
            padding: 0;
            border: 0;
            background: transparent;
            color: inherit;
            font: inherit;
            cursor: inherit;
            outline: none;
        }

        .control > .trigger:not(.trigger--icon) {
            flex: 0 1 auto;
            justify-content: flex-start;
        }

        .trigger--icon {
            width: 1.25rem;
        }

        .trigger-start {
            display: none;
            flex: 0 0 auto;
            align-items: center;
            /* Decorations must not reserve inline text descender space. */
            line-height: 0;
        }

        .trigger-start.has-decoration {
            display: inline-flex;
        }

        .control-start,
        .control-end {
            display: inline-flex;
            align-items: center;
            flex-shrink: 0;
            line-height: 0;
            color: var(--pk-color-gray-600);
        }

        slot[name='start']::slotted(svg),
        slot[name='end']::slotted(svg) {
            width: var(--pk-select-decoration-size);
            height: var(--pk-select-decoration-size);
        }

        .value {
            flex: 1 1 auto;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            text-align: left;
        }

        .icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            line-height: 0;
            pointer-events: none;
            color: var(--pk-color-gray-600);
        }

        /* Extra space before the expand chevron (control gap stays for start icon ↔ label). */
        .control > .icon,
        .control > .trigger--icon {
            margin-inline-start: 0.25rem;
        }

        .icon svg {
            display: block;
            width: 0.75rem;
            height: 0.75rem;
        }

        .tags {
            display: flex;
            flex: 0 1 auto;
            flex-wrap: wrap;
            gap: 0.25rem;
            min-width: 0;
        }

        .tag {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            max-width: 10ch;
            padding: 0.125rem 0.375rem;
            border-radius: var(--pk-radius-sm);
            background: var(--pk-color-gray-200);
            color: var(--pk-color-gray-800);
            font-size: 12px;
            line-height: 1.3;
        }

        .tag-label {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .tag-remove {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 0.875rem;
            height: 0.875rem;
            padding: 0;
            border: 0;
            border-radius: var(--pk-radius-sm);
            background: transparent;
            color: var(--pk-color-gray-600);
            cursor: pointer;
        }

        .tag-remove:hover {
            background: rgb(0 0 0 / 8%);
        }

        .clear-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.25rem;
            height: 1.25rem;
            padding: 0;
            border: 0;
            border-radius: var(--pk-radius-sm);
            background: transparent;
            color: var(--pk-color-gray-600);
            cursor: pointer;
            flex-shrink: 0;
        }

        .clear-button:hover {
            background: rgb(0 0 0 / 6%);
            color: var(--pk-color-gray-800);
        }

        .value-input {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .panel ::slotted(pk-separator) {
            margin: 4px 0;
        }

        .panel {
            width: max-content;
            min-width: var(--pk-select-anchor-width, 8rem);
            max-height: 16rem;
            overflow: auto;
            padding: 0;
            border: 0;
            border-radius: var(--pk-radius-md);
            background: var(--pk-color-white);
            box-shadow: var(--pk-shadow-popup);
            color: var(--pk-color-gray-700);
            outline: none;
        }

        .panel[hidden] {
            display: none !important;
        }

        :host([invalid]) .control,
        :host(:state(user-invalid)) .control {
            border-color: var(--pk-color-rose-600);
        }

        :host([invalid]) button.control:focus-visible,
        :host([invalid][data-state='focus-visible']) button.control,
        :host(:state(user-invalid)) button.control:focus-visible,
        :host(:state(user-invalid)[data-state='focus-visible']) button.control {
            border-color: var(--pk-color-rose-600);
            box-shadow: var(--pk-input-invalid-focus-shadow);
        }

        :host([size='xxs']) {
            --pk-select-trigger-min-height: var(--pk-control-height-xxs);
            --pk-select-item-min-height: 0;
            --pk-select-item-padding-block: calc((var(--pk-control-height-xxs) - var(--pk-input-control-line-height, 1.25rem) - 2px) / 2);
            --pk-select-item-padding-inline: 8px;
            --pk-select-item-padding-inline-end: 1.75rem;
            --pk-select-item-font-size: var(--pk-control-font-xxs);
            --pk-select-item-line-height: var(--pk-input-control-line-height, 1.25rem);
            /* v1 SelectLabel xs: text-[11px] */
            --pk-select-group-label-font-size: 11px;
            --pk-select-decoration-size: 0.625rem;
        }

        :host([size='xs']) {
            --pk-select-trigger-min-height: var(--pk-control-height-xs);
            --pk-select-item-min-height: 0;
            --pk-select-item-padding-block: calc((var(--pk-control-height-xs) - var(--pk-input-control-line-height, 1.25rem) - 2px) / 2);
            --pk-select-item-padding-inline: 8px;
            --pk-select-item-padding-inline-end: 1.75rem;
            --pk-select-item-font-size: var(--pk-control-font-xs);
            --pk-select-item-line-height: var(--pk-input-control-line-height, 1.25rem);
            /* v1 SelectLabel xs: text-[11px] */
            --pk-select-group-label-font-size: 11px;
            --pk-select-decoration-size: 0.625rem;
        }

        :host([size='xxs']) .control {
            border-radius: var(--pk-radius-sm);
            /* Keep the trigger line box consistent with the shared control scale. */
            line-height: var(--pk-select-item-line-height, 1.25);
        }

        :host([size='xs']) .control {
            border-radius: var(--pk-radius-sm);
            /* Keep the trigger line box consistent with the shared control scale. */
            line-height: var(--pk-select-item-line-height, 1.25);
        }

        :host([size='xxs']) .icon svg {
            width: 0.625rem;
            height: 0.625rem;
        }

        :host([size='xs']) .icon svg {
            width: 0.625rem;
            height: 0.625rem;
        }

        /* Options live in light DOM; ::slotted pushes size tokens onto each pk-option
         * host so the open listbox matches the trigger (inheritance alone is flaky when
         * the panel is promoted to the popover top layer). */
        :host([size='xxs']) ::slotted(pk-option) {
            --pk-select-item-min-height: 0;
            --pk-select-item-padding-block: calc((var(--pk-control-height-xxs) - var(--pk-input-control-line-height, 1.25rem) - 2px) / 2);
            --pk-select-item-padding-inline: 8px;
            --pk-select-item-padding-inline-end: 1.75rem;
            --pk-select-item-font-size: var(--pk-control-font-xxs);
            --pk-select-item-line-height: var(--pk-input-control-line-height, 1.25rem);
            --pk-select-item-indicator-size: 0.625rem;
        }

        :host([size='xs']) ::slotted(pk-option) {
            --pk-select-item-min-height: 0;
            --pk-select-item-padding-block: calc((var(--pk-control-height-xs) - var(--pk-input-control-line-height, 1.25rem) - 2px) / 2);
            --pk-select-item-padding-inline: 8px;
            --pk-select-item-padding-inline-end: 1.75rem;
            --pk-select-item-font-size: var(--pk-control-font-xs);
            --pk-select-item-line-height: var(--pk-input-control-line-height, 1.25rem);
            --pk-select-item-indicator-size: 0.625rem;
        }

        :host([size='xxs']) .panel {
            max-height: 12rem;
        }

        :host([size='xs']) .panel {
            max-height: 12rem;
        }

        /* Editable-table cells only (class set by pk-editable-table) — compact chip + menu. */
        :host(.cell-pk-control) {
            --pk-select-trigger-min-height: 0;
            --pk-select-item-min-height: 0;
            --pk-select-item-padding-block: 5px;
            --pk-select-item-padding-inline: 8px;
            --pk-select-item-padding-inline-end: 1.5rem;
            --pk-select-item-font-size: 11px;
            --pk-select-item-line-height: 1.2;
            /* Compact table chip — match xxs label size. */
            --pk-select-group-label-font-size: 11px;
            --pk-select-decoration-size: 0.625rem;
            --pk-select-item-indicator-size: 0.625rem;
        }

        :host(.cell-pk-control) .control {
            border-radius: var(--pk-radius-sm);
            line-height: var(--pk-select-item-line-height, 1.2);
        }

        :host(.cell-pk-control) ::slotted(pk-option) {
            --pk-select-item-min-height: 0;
            --pk-select-item-padding-block: 5px;
            --pk-select-item-padding-inline: 8px;
            --pk-select-item-padding-inline-end: 1.5rem;
            --pk-select-item-font-size: 11px;
            --pk-select-item-line-height: 1.2;
            --pk-select-item-indicator-size: 0.625rem;
        }

        :host(.cell-pk-control) .panel {
            max-height: 11rem;
        }

        :host([size='sm']) {
            --pk-select-trigger-min-height: var(--pk-control-height-sm);
            --pk-select-item-min-height: 0;
            --pk-select-item-padding-block: calc((var(--pk-control-height-sm) - var(--pk-input-control-line-height, 1.25rem) - 2px) / 2);
            --pk-select-item-padding-inline: 10px;
            --pk-select-item-padding-inline-end: 1.75rem;
            --pk-select-item-font-size: var(--pk-control-font-sm);
            --pk-select-item-indicator-inset: 0.625rem;
            /* v1 SelectLabel sm: text-[12px] */
            --pk-select-group-label-font-size: 12px;
            --pk-select-decoration-size: 0.6875rem;
        }

        :host([size='sm']) .control {
            border-radius: var(--pk-radius-md);
        }

        :host([size='sm']) .icon svg {
            width: 0.6875rem;
            height: 0.6875rem;
        }

        :host([size='lg']) {
            --pk-select-trigger-min-height: var(--pk-control-height-lg);
            --pk-select-item-padding-block: calc((var(--pk-control-height-lg) - var(--pk-input-control-line-height, 1.25rem) - 2px) / 2);
            --pk-select-item-padding-inline: 12px;
            --pk-select-item-font-size: var(--pk-control-font-lg);
            --pk-select-item-indicator-inset: 0.75rem;
            /* v1 SelectLabel lg: text-sm → 14px */
            --pk-select-group-label-font-size: 14px;
            --pk-select-decoration-size: 1rem;
        }

        :host([size='xl']) {
            --pk-select-trigger-min-height: var(--pk-control-height-xl);
            --pk-select-item-padding-block: calc((var(--pk-control-height-xl) - var(--pk-input-control-line-height, 1.25rem) - 2px) / 2);
            --pk-select-item-padding-inline: 14px;
            --pk-select-item-padding-inline-end: 2.25rem;
            --pk-select-item-font-size: var(--pk-control-font-xl);
            --pk-select-item-indicator-inset: 0.875rem;
            /* v1 SelectLabel xl: text-base → 16px */
            --pk-select-group-label-font-size: 16px;
            --pk-select-decoration-size: 1.125rem;
        }

        :host([size='xl']) .icon svg {
            width: 0.875rem;
            height: 0.875rem;
        }
    }
`], Lr = S(l.chevronDown), U = class extends A {
	constructor(...e) {
		super(...e), this.assumeInteractionOn = ["blur", "input"], this.open = !1, this.multiple = !1, this.placement = "bottom-start", this.sideOffset = 4, this.clearable = !1, this.withClear = !1, this.invalid = !1, this.size = "default", this.placeholder = "", this.value = "", this.defaultValue = "", this.values = [], this.defaultValues = [], this.ariaLabel = null, this.loopFocus = !1, this.hasSlotController = new g(this, "start", "end"), this.listboxId = Xe("pk-select-listbox"), this.triggerId = Xe("pk-select-trigger"), this.options = [], this.highlightedIndex = 0, this.dismissRegistered = !1, this.panelEventTarget = null, this.typeToSelect = Nr([], () => {}), this.closing = !1, this.panelAnimated = !1, this.handleOptionsMutation = (e = {}) => {
			let t = this.getOptionElements(), n = t.length !== this.options.length || t.some((e, t) => e !== this.options[t]);
			this.options = t, n && this.open && this.observeOptionSizes(), this.applySelection(), this.updateTypeToSelect(), e.render !== !1 && n && this.requestUpdate();
		}, this.syncOptions = () => {
			this.handleOptionsMutation({ render: !0 });
		}, this.handlePopupReposition = () => {
			this.fitPanelToOptions(!this.panelAnimated);
		}, this.togglePanel = (e) => {
			e?.preventDefault(), e?.stopPropagation(), !(this.disabled || this.closing) && (this.open ? this.closePanel("api") : this.openPanel());
		}, this.onDocumentPointerDown = (e) => {
			this.isPointerInside(e) || this.closePanel("light-dismiss");
		}, this.onDocumentKeyDown = (e) => {
			if (this.open) {
				if (e.key === "Escape") {
					if (!se(this)) return;
					e.preventDefault(), e.stopPropagation(), this.closePanel("escape");
				} else (Dr.has(e.key) || Or(e)) && Cr(e, {
					anchor: this.getPopupAnchor(),
					panel: this.panelElement
				}) && (e.preventDefault(), e.stopPropagation(), this.onListboxKeyDown(e));
			}
		}, this.handleOptionSelect = (e) => {
			let { value: t } = e.detail;
			this.multiple ? this.values = this.values.includes(t) ? this.values.filter((e) => e !== t) : [...this.values, t] : (this.value = t, this.closePanel("api")), this.applySelection(), this.emitValueChange();
		}, this.handleOptionHighlight = (e) => {
			if (!this.open) return;
			let t = this.getEnabledVisibleOptions().findIndex((t) => t.value === e.detail.value);
			t !== -1 && t !== this.highlightedIndex && (this.highlightedIndex = t, this.syncHighlight(!1));
		}, this.onKeyDown = (e) => {
			this.open ? this.onListboxKeyDown(e) : (e.key === "ArrowDown" || e.key === "Enter" || e.key === " ") && (e.preventDefault(), this.openPanel());
		}, this.handleListboxKeyDownEvent = (e) => {
			this.open && this.onListboxKeyDown(e.detail.keyboardEvent);
		};
	}
	static {
		this.styles = Ir;
	}
	static get validators() {
		return [
			...super.validators,
			qe(),
			{
				observedAttributes: ["required"],
				checkValidity: (e) => {
					let t = e, n = {
						message: "Please select an item in the list.",
						isValid: !0,
						invalidKeys: []
					};
					return !t.required || !(t.multiple ? t.values.length === 0 : !t.value) || (n.isValid = !1, n.invalidKeys.push("valueMissing")), n;
				}
			}
		];
	}
	get panelElement() {
		return this.popupElement?.getContentElement() ?? null;
	}
	connectedCallback() {
		this.refreshOptions(), super.connectedCallback(), this.addEventListener("pk-listbox-keydown", this.handleListboxKeyDownEvent), this.addEventListener("keydown", this.onKeyDown), this.optionsObserver = new MutationObserver(() => {
			this.handleOptionsMutation({ render: !0 });
		}), this.optionsObserver.observe(this, {
			childList: !0,
			subtree: !0
		});
	}
	disconnectedCallback() {
		this.unbindPanelEvents(), this.removeEventListener("pk-listbox-keydown", this.handleListboxKeyDownEvent), this.removeEventListener("keydown", this.onKeyDown), this.optionsObserver?.disconnect(), this.optionResizeObserver?.disconnect(), cancelAnimationFrame(this.optionResizeFrame ?? 0), this.optionResizeFrame = void 0, this.closePanel("api"), super.disconnectedCallback();
	}
	updated(e) {
		(e.has("value") || e.has("values") || e.has("multiple")) && this.applySelection(), (e.has("values") || e.has("multiple")) && this.syncFormValue(), super.updated(e);
	}
	getOptionElements() {
		let e = this.popupElement?.getContentElement()?.querySelectorAll("pk-option");
		return e && e.length > 0 ? [...e] : [...this.querySelectorAll("pk-option")];
	}
	refreshOptions() {
		this.handleOptionsMutation({ render: !1 });
	}
	bindPanelEvents() {
		let e = this.panelElement;
		e && e !== this.panelEventTarget && (this.unbindPanelEvents(), this.panelEventTarget = e, e.addEventListener("pk-option-select", this.handleOptionSelect), e.addEventListener("pk-option-highlight", this.handleOptionHighlight), e.addEventListener("pk-listbox-keydown", this.handleListboxKeyDownEvent));
	}
	unbindPanelEvents() {
		this.panelEventTarget &&= (this.panelEventTarget.removeEventListener("pk-option-select", this.handleOptionSelect), this.panelEventTarget.removeEventListener("pk-option-highlight", this.handleOptionHighlight), this.panelEventTarget.removeEventListener("pk-listbox-keydown", this.handleListboxKeyDownEvent), null);
	}
	get validationTarget() {
		return this.input ?? this.triggerButton ?? this.controlElement;
	}
	getAriaMirrorTarget() {
		return this.triggerButton ?? this.controlElement ?? null;
	}
	syncFormValue() {
		if (!this.name) this.setFormValue(null);
		else if (this.multiple) {
			let e = new FormData();
			for (let t of this.values) e.append(this.name, t);
			this.setFormValue(e);
		} else this.setFormValue(this.value || "");
	}
	resetToDefaultValue() {
		this.multiple ? this.values = [...this.defaultValues] : this.value = this.defaultValue, this.applySelection();
	}
	restoreFormState(e) {
		e instanceof FormData && this.name ? this.values = e.getAll(this.name).map(String) : typeof e == "string" && (this.value = e);
	}
	isOptionInHiddenGroup(e) {
		return !!e.closest("pk-option-group")?.hidden;
	}
	getVisibleOptions() {
		return this.options.filter((e) => !this.isOptionInHiddenGroup(e));
	}
	getEnabledVisibleOptions() {
		return this.getVisibleOptions().filter((e) => !e.disabled);
	}
	isSelected(e) {
		return this.multiple ? this.values.includes(e) : this.value === e;
	}
	applySelection() {
		let e = this.getVisibleOptions();
		for (let t of this.options) t.selected = this.isSelected(t.value), t.hidden = !e.includes(t), t.optionId = `${this.listboxId}-option-${t.value}`;
		for (let e of this.querySelectorAll("pk-option-group")) {
			let t = [...e.querySelectorAll("pk-option")];
			e.hidden = t.length > 0 && t.every((e) => e.hidden);
		}
		Fr(this), this.syncValueInput(), this.syncTriggerDecorations(), this.open && this.syncHighlight();
	}
	syncValueInput() {
		this.input && (this.multiple ? (this.input.value = this.values.join(","), this.input.required = this.required) : (this.input.value = this.value, this.input.required = this.required));
	}
	getDisplayValue() {
		if (this.multiple) {
			let e = this.getSelectedOptions().map((e) => e.getLabel());
			return e.length > 0 ? e.join(", ") : this.placeholder;
		}
		return this.options.find((e) => e.value === this.value)?.getLabel() || this.placeholder;
	}
	getSelectedOptions() {
		return this.options.filter((e) => this.isSelected(e.value));
	}
	syncTriggerDecorations() {
		let e = this.triggerStartElement;
		if (!e || this.multiple) return;
		e.replaceChildren(), e.classList.remove("has-decoration");
		let t = this.options.find((e) => e.value === this.value);
		if (t) {
			for (let n of t.getStartElements()) e.append(n.cloneNode(!0));
			e.classList.toggle("has-decoration", e.childElementCount > 0);
		}
	}
	hasSelection() {
		return this.multiple ? this.values.length > 0 : this.options.some((e) => e.value === this.value) || !!this.value;
	}
	syncHighlightedIndexToSelection() {
		if (this.multiple) return;
		let e = this.getEnabledVisibleOptions();
		if (e.length === 0) return;
		let t = e.findIndex((e) => e.value === this.value);
		t >= 0 && (this.highlightedIndex = t);
	}
	observeOptionSizes() {
		this.optionResizeObserver?.disconnect(), cancelAnimationFrame(this.optionResizeFrame ?? 0), this.optionResizeFrame = void 0, this.optionResizeObserver ??= new ResizeObserver(() => {
			cancelAnimationFrame(this.optionResizeFrame ?? 0), this.optionResizeFrame = requestAnimationFrame(() => {
				this.optionResizeFrame = void 0, this.fitPanelToOptions();
			});
		});
		for (let e of this.options) this.optionResizeObserver.observe(e);
	}
	fitPanelToOptions(e = !1) {
		let t = this.panelElement;
		if (!this.open || this.closing || !t || !t.offsetHeight) return;
		let n = getComputedStyle(t), r = Number.parseFloat(n.maxHeight), i = Number.parseFloat(n.getPropertyValue("--pk-popup-available-height")), a = Math.min(Number.isFinite(r) ? r : Infinity, Number.isFinite(i) ? i : Infinity), o = Number.parseFloat(n.borderTopWidth) || 0, s = Number.parseFloat(n.borderBottomWidth) || 0, c = Number.parseFloat(n.paddingTop) || 0, l = Number.parseFloat(n.paddingBottom) || 0, u = t.getBoundingClientRect(), d = u.height / t.offsetHeight, f = this.options.filter((e) => e.getClientRects().length > 0).map((e) => {
			let n = e.getBoundingClientRect();
			return {
				option: e,
				top: (n.top - u.top) / d + t.scrollTop - o,
				bottom: (n.bottom - u.top) / d + t.scrollTop - o
			};
		});
		if (!f.length) {
			t.style.removeProperty("height");
			return;
		}
		let p = Math.max(0, a - o - s), m = 0;
		if (e) {
			let e = this.getEnabledVisibleOptions()[this.highlightedIndex], t = f.findIndex((t) => t.option === e);
			for (; m < t && f[t].bottom - (m ? f[m].top - c : 0) + l > p;) m++;
		} else {
			let e = f.findIndex((e) => e.bottom > t.scrollTop + c + .5);
			m = Math.max(0, e);
		}
		let h = m ? f[m].top - c : 0, g = 0;
		for (let e = m; e < f.length; e++) {
			let t = f[e].bottom - h + l;
			if (t > p + .5) break;
			g = t;
		}
		g = Math.min(a, (g || p) + o + s);
		let _ = `${Math.max(0, Math.round(g * 100) / 100)}px`;
		t.style.height !== _ && (t.style.height = _), e && (t.scrollTop = h);
	}
	syncHighlight(e = !0) {
		let t = this.getEnabledVisibleOptions();
		for (let e of this.options) e.highlighted = !1, e.focusIndex = -1;
		if (t.length === 0) {
			this.highlightedIndex = 0;
			return;
		}
		this.highlightedIndex >= t.length && (this.highlightedIndex = 0);
		let n = t[this.highlightedIndex];
		n && this.panelElement && (n.highlighted = !0, n.focusIndex = 0, e && xe(n, this.panelElement, "vertical", "auto"));
	}
	updateTypeToSelect() {
		this.typeToSelect = Nr(this.getEnabledVisibleOptions(), (e) => {
			this.highlightedIndex = e, this.syncHighlight(), this.getEnabledVisibleOptions()[e]?.focusControl();
		});
	}
	getPopupAnchor() {
		return this.controlElement ?? null;
	}
	getActiveDescendantId() {
		return this.getEnabledVisibleOptions()[this.highlightedIndex]?.optionId || null;
	}
	async show() {
		this.open || this.closing || this.disabled || await this.openPanel();
	}
	async hide(e = "api") {
		this.open && !this.closing && await this.closePanel(e);
	}
	openPanel() {
		let e = this.getPopupAnchor();
		if (!e || this.closing) return Promise.resolve();
		this.dispatchEvent(new ce()), this.closing = !1, this.panelAnimated = !1, this.open = !0, this.popupElement.active = !0, this.applySelection(), this.syncHighlightedIndexToSelection(), this.panelElement && (this.panelElement.style.removeProperty("height"), this.panelElement.hidden = !1, wt(this.panelElement, this.placement));
		let t = e.getBoundingClientRect().width;
		return this.style.setProperty("--pk-select-anchor-width", `${t}px`), this.registerDismissHandlers(), this.syncHighlight(), this.updateTypeToSelect(), this.updateComplete.then(async () => {
			let e = await Tt(this.popupElement, this.placement, 300, { requireEvent: !0 });
			this.panelElement && wt(this.panelElement, e), this.fitPanelToOptions(!0), this.observeOptionSizes(), this.panelAnimated = !0, this.bindPanelEvents(), this.refreshOptions(), this.getEnabledVisibleOptions()[this.highlightedIndex]?.focusControl(), this.dispatchEvent(new le()), this.dispatchEvent(new CustomEvent("pk-open-change", {
				detail: { open: !0 },
				bubbles: !0,
				composed: !0
			}));
		});
	}
	async closePanel(e = "unknown") {
		if (!this.open || this.closing) return;
		let t = new ue(e);
		this.dispatchEvent(t) && (this.typeToSelect.reset(), this.optionResizeObserver?.disconnect(), cancelAnimationFrame(this.optionResizeFrame ?? 0), this.optionResizeFrame = void 0, this.unbindPanelEvents(), this.unregisterDismissHandlers(), this.closing = !0, this.panelAnimated = !1, await this.waitForExitAnimation(), this.open = !1, this.closing = !1, this.panelAnimated = !1, this.panelElement && (this.panelElement.hidden = !0, this.panelElement.removeAttribute("data-side")), this.popupElement.active = !1, this.dispatchEvent(new de()), this.dispatchEvent(new CustomEvent("pk-open-change", {
			detail: { open: !1 },
			bubbles: !0,
			composed: !0
		})), this.shouldReturnFocusToTrigger(e) ? this.triggerButton?.focus({ preventScroll: !0 }) : this.triggerButton?.blur());
	}
	waitForExitAnimation() {
		let e = this.panelElement;
		return e ? new Promise((t) => {
			let n = !1, r = () => {
				n || (n = !0, e.removeEventListener("animationend", i), window.clearTimeout(a), e.classList.remove("closing"), t());
			}, i = (t) => {
				t.target === e && t.animationName.startsWith("pk-popup-content-out") && r();
			};
			e.classList.add("closing"), e.addEventListener("animationend", i);
			let a = window.setTimeout(r, 150);
		}) : Promise.resolve();
	}
	shouldReturnFocusToTrigger(e) {
		return e !== "light-dismiss" && e !== "pointer-dismiss";
	}
	registerDismissHandlers() {
		ae(this), this.dismissRegistered = !0, document.addEventListener("pointerdown", this.onDocumentPointerDown, !0), document.addEventListener("keydown", this.onDocumentKeyDown, !0);
	}
	unregisterDismissHandlers() {
		this.dismissRegistered &&= (oe(this), !1), document.removeEventListener("pointerdown", this.onDocumentPointerDown, !0), document.removeEventListener("keydown", this.onDocumentKeyDown, !0);
	}
	isPointerInside(e) {
		return wr(e, {
			anchor: this.getPopupAnchor(),
			panel: this.panelElement
		});
	}
	removeTag(e, t) {
		t.preventDefault(), t.stopPropagation(), this.values = this.values.filter((t) => t !== e), this.applySelection(), this.emitValueChange();
	}
	handleClear(e) {
		e.preventDefault(), e.stopPropagation(), this.multiple ? this.values = [] : this.value = "", this.applySelection(), this.dispatchEvent(new ft()), this.emitValueChange(), this.triggerButton?.focus();
	}
	emitValueChange() {
		this.dispatchEvent(new CustomEvent("pk-change", {
			detail: { value: this.multiple ? [...this.values] : this.value },
			bubbles: !0,
			composed: !0
		})), this.dispatchEvent(new Event("input", {
			bubbles: !0,
			composed: !0
		})), this.dispatchEvent(new Event("change", {
			bubbles: !0,
			composed: !0
		}));
	}
	onListboxKeyDown(e) {
		let t = this.getEnabledVisibleOptions();
		this.highlightedIndex = Mr(e, {
			items: t,
			currentIndex: this.highlightedIndex,
			multiselect: this.multiple,
			loop: this.loopFocus,
			onSelect: (e) => {
				this.highlightedIndex = e, this.syncHighlight();
			},
			focusItem: (e) => {
				t[e]?.focusControl();
			},
			onClose: () => {
				this.closePanel("escape");
			}
		}), e.key.length !== 1 || e.ctrlKey || e.metaKey || e.altKey || this.typeToSelect.handleKey(e);
	}
	renderTags() {
		return this.getSelectedOptions().map((t) => e`
            <span class="tag" part="tag">
                <span class="tag-label">${t.getLabel()}</span>
                <button
                    type="button"
                    class="tag-remove"
                    part="tag-remove"
                    aria-label=${`Remove ${t.getLabel()}`}
                    @click=${(e) => this.removeTag(t.value, e)}
                >
                    ×
                </button>
            </span>
        `);
	}
	renderChevronIcon() {
		return e`
            <span class="icon" aria-hidden="true">${C(Lr)}</span>
        `;
	}
	renderHostDecorationSlot(t) {
		return this.hasSlotController.test(t) ? e`
            <span part=${t} class=${t === "start" ? "control-start" : "control-end"}>
                <slot name=${t}></slot>
            </span>
        ` : e`<slot name=${t} hidden></slot>`;
	}
	render() {
		let t = this.getDisplayValue(), r = !this.hasSelection(), i = (this.clearable || this.withClear) && this.hasSelection() && !this.disabled;
		return e`
            <input
                class="value-input"
                part="value-input"
                tabindex="-1"
                aria-hidden="true"
                .value=${this.multiple ? this.values.join(",") : this.value}
                ?required=${this.required}
                @input=${() => this.updateValidity()}
            />
            ${this.multiple ? e`
                    <div
                        part="control"
                        class=${w({
			control: !0,
			"is-disabled": this.disabled
		})}
                    >
                        ${this.renderHostDecorationSlot("start")}
                        ${this.hasSelection() ? e`
                                <div class="tags" part="tags">${this.renderTags()}</div>
                                ${i ? e`
                                        <button
                                            type="button"
                                            class="clear-button"
                                            part="clear-button"
                                            aria-label="Clear selection"
                                            @click=${this.handleClear}
                                        >
                                            ×
                                        </button>
                                    ` : n}
                            ` : e`
                                <button
                                    part="trigger"
                                    type="button"
                                    class="trigger"
                                    id=${this.triggerId}
                                    ?disabled=${this.disabled}
                                    aria-label=${this.ariaLabel ?? n}
                                    aria-haspopup="listbox"
                                    aria-expanded=${this.open ? "true" : "false"}
                                    aria-controls=${this.listboxId}
                                    @click=${this.togglePanel}
                                >
                                    <span class="value is-placeholder">${this.placeholder}</span>
                                </button>
                            `}
                        ${this.renderHostDecorationSlot("end")}
                        <button
                            type="button"
                            class="trigger trigger--icon"
                            part="trigger expand-button"
                            aria-label="Toggle options"
                            ?disabled=${this.disabled}
                            @click=${this.togglePanel}
                        >
                            ${this.renderChevronIcon()}
                        </button>
                    </div>
                ` : e`
                    <button
                        part="control"
                        type="button"
                        class=${w({
			control: !0,
			"is-disabled": this.disabled
		})}
                        id=${this.triggerId}
                        ?disabled=${this.disabled}
                        aria-label=${this.ariaLabel ?? n}
                        aria-haspopup="listbox"
                        aria-expanded=${this.open ? "true" : "false"}
                        aria-controls=${this.listboxId}
                        @click=${this.togglePanel}
                    >
                        ${this.renderHostDecorationSlot("start")}
                        <span part="trigger-start" class="trigger-start"></span>
                        <span
                            class=${w({
			value: !0,
			"is-placeholder": r
		})}
                        >${t}</span>
                        ${i ? e`
                                <span
                                    class="clear-button"
                                    part="clear-button"
                                    role="button"
                                    tabindex="-1"
                                    aria-label="Clear selection"
                                    @click=${this.handleClear}
                                >
                                    ×
                                </span>
                            ` : n}
                        ${this.renderHostDecorationSlot("end")}
                        ${this.renderChevronIcon()}
                    </button>
                `}
            <pk-popup
                .anchor=${this.getPopupAnchor() ?? ""}
                .placement=${this.placement}
                .distance=${this.sideOffset}
                .sync=${"width"}
                auto-size="vertical"
                @pk-reposition=${this.handlePopupReposition}
                flip
                shift
            >
                <div
                    part="panel"
                    class=${w({
			panel: !0,
			"pk-popup-content": !0,
			closing: this.closing
		})}
                    id=${this.listboxId}
                    role="listbox"
                    aria-multiselectable=${this.multiple ? "true" : "false"}
                    tabindex="-1"
                    ?hidden=${!this.open && !this.closing}
                    data-open=${this.panelAnimated && !this.closing ? "" : n}
                    @slotchange=${this.syncOptions}
                >
                    <slot></slot>
                </div>
            </pk-popup>
        `;
	}
};
s([t({
	type: Boolean,
	reflect: !0
})], U.prototype, "open", void 0), s([t({
	type: Boolean,
	reflect: !0
})], U.prototype, "multiple", void 0), s([t({ reflect: !0 })], U.prototype, "placement", void 0), s([t({
	attribute: "side-offset",
	type: Number
})], U.prototype, "sideOffset", void 0), s([t({
	type: Boolean,
	reflect: !0
})], U.prototype, "clearable", void 0), s([t({
	attribute: "with-clear",
	type: Boolean
})], U.prototype, "withClear", void 0), s([t({
	type: Boolean,
	reflect: !0
})], U.prototype, "invalid", void 0), s([t({ reflect: !0 })], U.prototype, "size", void 0), s([t({ reflect: !0 })], U.prototype, "width", void 0), s([t()], U.prototype, "placeholder", void 0), s([t()], U.prototype, "value", void 0), s([t({ attribute: "default-value" })], U.prototype, "defaultValue", void 0), s([t({
	type: Array,
	attribute: !1
})], U.prototype, "values", void 0), s([t({ attribute: !1 })], U.prototype, "defaultValues", void 0), s([t({ attribute: "aria-label" })], U.prototype, "ariaLabel", void 0), s([t({
	attribute: "loop-focus",
	type: Boolean
})], U.prototype, "loopFocus", void 0), s([E(".trigger-start")], U.prototype, "triggerStartElement", void 0), s([E("pk-popup")], U.prototype, "popupElement", void 0), s([E(".control")], U.prototype, "controlElement", void 0), s([E("button.control, .control > button.trigger")], U.prototype, "triggerButton", void 0), s([E(".value-input")], U.prototype, "input", void 0), s([o()], U.prototype, "highlightedIndex", void 0), s([o()], U.prototype, "closing", void 0), s([o()], U.prototype, "panelAnimated", void 0), U = s([r("pk-select")], U);
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/pk-option-Br87xU6D.js
function Rr(e, t) {
	let n = String(e ?? ""), r = String(t ?? "").trim();
	if (!r) return [{
		text: n,
		match: !1
	}];
	let i = n.toLowerCase(), a = r.toLowerCase(), o = [], s = 0, c = i.indexOf(a);
	for (; c !== -1;) c > s && o.push({
		text: n.slice(s, c),
		match: !1
	}), o.push({
		text: n.slice(c, c + r.length),
		match: !0
	}), s = c + r.length, c = i.indexOf(a, s);
	return s < n.length && o.push({
		text: n.slice(s),
		match: !1
	}), o.length > 0 ? o : [{
		text: n,
		match: !1
	}];
}
var zr = i`
    @layer pk-component {
        :host {
            display: block;
            /*
             * Slotted option labels inherit type metrics from this host (same
             * Craft-vs-Tailwind trap as pk-dropdown-item). Size tokens arrive via
             * pk-select ::slotted(pk-option) custom properties.
             */
            font-family: var(--pk-font-family);
            font-size: var(--pk-select-item-font-size, var(--pk-font-size-base));
            line-height: var(--pk-select-item-line-height, 1.4);
            color: var(--pk-color-gray-700);
        }

        .option {
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
            margin: 0;
            min-height: var(--pk-select-item-min-height, var(--pk-input-height));
            padding-block: var(--pk-select-item-padding-block, 6px);
            padding-inline-start: var(--pk-select-item-padding-inline, 10px);
            padding-inline-end: var(--pk-select-item-padding-inline-end, 2rem);
            border: var(--pk-select-trigger-border-width, 1px) solid transparent;
            background: transparent;
            color: inherit;
            font: inherit;
            font-family: var(--pk-font-family);
            font-size: var(--pk-select-item-font-size, var(--pk-font-size-base));
            line-height: var(--pk-select-item-line-height, 1.4);
            text-align: left;
            white-space: nowrap;
            cursor: default;
            user-select: none;
            outline: none;
            box-sizing: border-box;
        }

        .start {
            display: none;
            flex: 0 0 auto;
            align-items: center;
            /* Decorations must not reserve inline text descender space. */
            line-height: 0;
        }

        :host([data-has-start]) .start {
            display: inline-flex;
        }

        :host([hidden]) {
            display: none !important;
        }

        .option:focus-visible,
        :host([highlighted]) .option {
            background: var(--pk-color-slate-100);
        }

        :host([disabled]) .option,
        .option[aria-disabled='true'] {
            pointer-events: none;
            opacity: 0.5;
        }

        .check {
            position: absolute;
            inset-inline-end: var(--pk-select-item-indicator-inset, 0.5rem);
            top: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            width: var(--pk-select-item-indicator-size, 0.75rem);
            height: var(--pk-select-item-indicator-size, 0.75rem);
            color: var(--pk-color-gray-700);
            pointer-events: none;
            transform: translateY(-50%);
            line-height: 0;
        }

        :host([selected]) .check {
            display: inline-flex;
        }

        .check svg {
            display: block;
            width: var(--pk-select-item-indicator-size, 0.75rem);
            height: var(--pk-select-item-indicator-size, 0.75rem);
            flex-shrink: 0;
            pointer-events: none;
        }

        .label {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            /* Allow custom multi-line option layouts (title + subtitle) to stack. */
            white-space: normal;
        }

        .match {
            padding: 0;
            border-radius: 2px;
            background: var(--pk-color-blue-100);
            color: inherit;
        }
    }
`, Br = S(l.check), W = class extends T {
	constructor(...e) {
		super(...e), this.value = "", this.label = "", this.disabled = !1, this.selected = !1, this.highlighted = !1, this.hidden = !1, this.focusIndex = -1, this.optionId = "", this.matchQuery = "";
	}
	static {
		this.styles = zr;
	}
	focusControl(e = !0) {
		this.shadowRoot?.querySelector(".option")?.focus({ preventScroll: e });
	}
	getLabel() {
		return this.label.trim() ? this.label.trim() : this.getDefaultSlotText();
	}
	getSearchText() {
		return this.getDefaultSlotText() || this.getLabel();
	}
	getDefaultSlotText() {
		return [...this.childNodes].filter((e) => e.nodeType === Node.TEXT_NODE || e instanceof Element && !e.getAttribute("slot")).map((e) => (e.textContent ?? "").trim()).filter(Boolean).join(" ");
	}
	hasRichLabelContent() {
		return [...this.children].some((e) => e instanceof HTMLElement ? !e.slot || e.slot === "" : !1);
	}
	getStartElements() {
		return [...this.querySelectorAll(":scope > [slot=\"start\"]")].filter((e) => e instanceof HTMLElement);
	}
	firstUpdated() {
		(this.shadowRoot?.querySelector("slot[name=\"start\"]"))?.addEventListener("slotchange", () => this.syncStartDecoration()), this.syncStartDecoration();
	}
	syncStartDecoration() {
		this.toggleAttribute("data-has-start", this.getStartElements().length > 0);
	}
	handleClick() {
		this.disabled || this.dispatchEvent(new CustomEvent("pk-option-select", {
			detail: { value: this.value },
			bubbles: !0,
			composed: !0
		}));
	}
	handleMouseEnter() {
		this.disabled || this.hidden || this.dispatchEvent(new CustomEvent("pk-option-highlight", {
			detail: { value: this.value },
			bubbles: !0,
			composed: !0
		}));
	}
	handleKeyDown(e) {
		if (!(/* @__PURE__ */ new Set([
			"ArrowDown",
			"ArrowUp",
			"ArrowLeft",
			"ArrowRight",
			"Home",
			"End",
			"Enter",
			" ",
			"Escape"
		])).has(e.key)) return;
		let t = this.closest("pk-select, pk-combobox, pk-autocomplete"), n = t ? null : this.closest("[role=\"listbox\"]");
		if (!t && !n) return;
		e.preventDefault(), e.stopPropagation();
		let r = new CustomEvent("pk-listbox-keydown", {
			detail: { keyboardEvent: e },
			bubbles: !0
		});
		t ? t.dispatchEvent(r) : n.dispatchEvent(r);
	}
	renderLabel() {
		let t = this.matchQuery.trim();
		if (!t || this.hasRichLabelContent()) return e`
                <span part="label" class="label">
                    <slot></slot>
                </span>
            `;
		let n = Rr(this.getLabel(), t);
		return e`
            <span part="label" class="label">
                ${n.map((t) => t.match ? e`<mark class="match">${t.text}</mark>` : e`<span>${t.text}</span>`)}
            </span>
        `;
	}
	render() {
		return e`
            <button
                part="option"
                type="button"
                class="option"
                role="option"
                id=${this.optionId || n}
                ?disabled=${this.disabled}
                aria-disabled=${this.disabled ? "true" : n}
                aria-selected=${this.selected ? "true" : "false"}
                tabindex=${this.focusIndex}
                @click=${this.handleClick}
                @mouseenter=${this.handleMouseEnter}
                @keydown=${this.handleKeyDown}
            >
                <span part="start" class="start">
                    <slot name="start"></slot>
                </span>
                ${this.renderLabel()}
                <span part="check" class="check" aria-hidden="true">${C(Br)}</span>
            </button>
        `;
	}
};
s([t()], W.prototype, "value", void 0), s([t()], W.prototype, "label", void 0), s([t({
	type: Boolean,
	reflect: !0
})], W.prototype, "disabled", void 0), s([t({
	type: Boolean,
	reflect: !0
})], W.prototype, "selected", void 0), s([t({
	type: Boolean,
	reflect: !0
})], W.prototype, "highlighted", void 0), s([t({
	type: Boolean,
	reflect: !0
})], W.prototype, "hidden", void 0), s([t({
	type: Number,
	attribute: "focus-index"
})], W.prototype, "focusIndex", void 0), s([t()], W.prototype, "optionId", void 0), s([t({ attribute: !1 })], W.prototype, "matchQuery", void 0), W = s([r("pk-option")], W);
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/pk-button-group-BY12Lbk7.js
var Vr = i`
    @layer pk-component {
        :host {
            display: inline-flex;
            width: fit-content;
            vertical-align: middle;
        }

        .group {
            display: flex;
            position: relative;
            isolation: isolate;
            flex-wrap: nowrap;
            gap: 0;
            width: fit-content;
            max-width: 100%;
            align-items: stretch;
        }

        :host([orientation='horizontal']) .group {
            flex-direction: row;
        }

        :host([orientation='vertical']) .group {
            flex-direction: column;
            align-items: stretch;
        }

        :host([orientation='vertical']) ::slotted(pk-button),
        :host([orientation='vertical']) ::slotted(pk-toggle),
        :host([orientation='vertical']) ::slotted(pk-input),
        :host([orientation='vertical']) ::slotted(pk-input-group),
        :host([orientation='vertical']) ::slotted(.button-group-text),
        :host([orientation='vertical']) ::slotted(select.button-group-select) {
            align-self: stretch;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        @media (hover: hover) {
            .group > :hover,
            ::slotted(:hover) {
                z-index: 1;
            }
        }

        .group > :focus-visible,
        ::slotted(:focus-visible),
        ::slotted(:focus-within),
        ::slotted([aria-checked='true']),
        ::slotted([checked]) {
            position: relative;
            z-index: 2;
        }

        /* Flush join: filled controls sit edge-to-edge; outlined controls overlap 1px to collapse borders */
        :host([orientation='horizontal']) {
            --pk-bg-horizontal-indent: 0;
            --pk-bg-horizontal-indent-outlined: -1px;
            --pk-btn-group-gap: 1px;
            --pk-btn-group-divider-color-outline: var(--pk-color-slate-400);
            --pk-btn-group-divider-color-dashed: var(--pk-color-slate-500);
        }

        :host([orientation='vertical']) {
            --pk-bg-vertical-indent: 0;
            --pk-bg-vertical-indent-outlined: -1px;
            --pk-btn-group-gap: 1px;
            --pk-btn-group-divider-color-outline: var(--pk-color-slate-400);
            --pk-btn-group-divider-color-dashed: var(--pk-color-slate-500);
        }

        :host([separators][orientation='horizontal']) {
            --pk-bg-horizontal-indent: 0;
        }

        :host([separators][orientation='vertical']) {
            --pk-bg-vertical-indent: 0;
        }

        ::slotted([data-pk-group-orientation='horizontal']:not([data-pk-group-item-first]):not([data-pk-group-item-last])) {
            --pk-bg-start-start-radius: 0;
            --pk-bg-start-end-radius: 0;
            --pk-bg-end-start-radius: 0;
            --pk-bg-end-end-radius: 0;
        }

        ::slotted([data-pk-group-orientation='horizontal'][data-pk-group-item-first]:not([data-pk-group-item-last])) {
            --pk-bg-start-end-radius: 0;
            --pk-bg-end-end-radius: 0;
        }

        ::slotted([data-pk-group-orientation='horizontal'][data-pk-group-item-last]:not([data-pk-group-item-first])) {
            --pk-bg-start-start-radius: 0;
            --pk-bg-end-start-radius: 0;
        }

        ::slotted([data-pk-group-orientation='vertical']:not([data-pk-group-item-first]):not([data-pk-group-item-last])) {
            --pk-bg-start-start-radius: 0;
            --pk-bg-start-end-radius: 0;
            --pk-bg-end-start-radius: 0;
            --pk-bg-end-end-radius: 0;
        }

        ::slotted([data-pk-group-orientation='vertical'][data-pk-group-item-first]:not([data-pk-group-item-last])) {
            --pk-bg-end-start-radius: 0;
            --pk-bg-end-end-radius: 0;
        }

        ::slotted([data-pk-group-orientation='vertical'][data-pk-group-item-last]:not([data-pk-group-item-first])) {
            --pk-bg-start-start-radius: 0;
            --pk-bg-start-end-radius: 0;
        }

        :host([exclusive]) ::slotted(pk-button[aria-pressed='true']) {
            --pk-btn-fill: var(--pk-color-gray-500);
            --pk-btn-fill-hover: var(--pk-color-gray-550);
            --pk-btn-fill-active: var(--pk-color-gray-600);
            --pk-btn-on: var(--pk-color-white);
        }

        :host:has(::slotted(pk-button-group-separator)) {
            --pk-btn-group-separator-color: transparent;
        }

        ::slotted(pk-input),
        ::slotted(pk-input-group) {
            width: auto;
            flex: 0 1 auto;
            align-self: stretch;
        }

        ::slotted(pk-popover),
        ::slotted(pk-dropdown-menu) {
            display: inline-flex;
            align-self: auto;
            flex: 0 0 auto;
        }

        ::slotted(.button-group-text) {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-height: var(--pk-btn-height-default);
            padding: 0 0.625rem;
            border-width: 1px;
            border-style: solid;
            border-color: var(--pk-color-slate-400);
            background: var(--pk-color-gray-100);
            color: var(--pk-color-gray-700);
            font-family: var(--pk-font-family);
            font-size: var(--pk-font-size-sm);
            font-weight: 500;
            line-height: var(--pk-line-height);
            box-sizing: border-box;
            white-space: nowrap;
            border-top-left-radius: var(--pk-bg-start-start-radius, var(--pk-radius-lg));
            border-top-right-radius: var(--pk-bg-start-end-radius, var(--pk-radius-lg));
            border-bottom-left-radius: var(--pk-bg-end-start-radius, var(--pk-radius-lg));
            border-bottom-right-radius: var(--pk-bg-end-end-radius, var(--pk-radius-lg));
        }

        :host([orientation='horizontal']) ::slotted([data-pk-group-orientation='horizontal'][data-pk-group-join]:not([data-pk-group-divider]).button-group-text),
        :host([orientation='horizontal']) ::slotted([data-pk-group-orientation='horizontal'][data-pk-group-join]:not([data-pk-group-divider])select.button-group-select),
        :host([orientation='horizontal']) ::slotted([data-pk-group-orientation='horizontal'][data-pk-group-join]:not([data-pk-group-divider])pk-input-group) {
            margin-inline-start: var(--pk-bg-horizontal-indent-outlined, 0);
            border-left-width: 0;
        }

        :host([orientation='vertical']) ::slotted([data-pk-group-orientation='vertical'][data-pk-group-join]:not([data-pk-group-divider]).button-group-text),
        :host([orientation='vertical']) ::slotted([data-pk-group-orientation='vertical'][data-pk-group-join]:not([data-pk-group-divider])select.button-group-select),
        :host([orientation='vertical']) ::slotted([data-pk-group-orientation='vertical'][data-pk-group-join]:not([data-pk-group-divider])pk-input-group) {
            margin-block-start: var(--pk-bg-vertical-indent-outlined, 0);
            border-top-width: 0;
        }

        ::slotted(select.button-group-select) {
            display: block;
            margin: 0;
            padding: 0 10px;
            border-width: 1px;
            border-style: solid;
            border-color: var(--pk-color-slate-400);
            background: var(--pk-color-white);
            color: var(--pk-color-gray-900);
            font-family: var(--pk-font-family);
            font-size: var(--pk-font-size-base);
            line-height: 1.4;
            appearance: none;
            box-sizing: border-box;
            align-self: stretch;
            min-height: var(--pk-btn-height-default);
            height: var(--pk-btn-height-default);
            border-top-left-radius: var(--pk-bg-start-start-radius, var(--pk-radius-lg));
            border-top-right-radius: var(--pk-bg-start-end-radius, var(--pk-radius-lg));
            border-bottom-left-radius: var(--pk-bg-end-start-radius, var(--pk-radius-lg));
            border-bottom-right-radius: var(--pk-bg-end-end-radius, var(--pk-radius-lg));
        }

        ::slotted(pk-separator) {
            align-self: stretch;
        }

        ::slotted(pk-separator[orientation='vertical']),
        ::slotted(pk-button-group-separator) {
            height: auto;
            flex-shrink: 0;
        }

        :host([orientation='horizontal']) ::slotted([data-pk-group-orientation='horizontal'][data-pk-group-divider][data-pk-group-join].button-group-text),
        :host([orientation='horizontal']) ::slotted([data-pk-group-orientation='horizontal'][data-pk-group-divider][data-pk-group-join]select.button-group-select),
        :host([orientation='horizontal']) ::slotted([data-pk-group-orientation='horizontal'][data-pk-group-divider][data-pk-group-join]pk-input-group) {
            margin-inline-start: var(--pk-bg-horizontal-indent-outlined, 0);
        }

        :host([orientation='vertical']) ::slotted([data-pk-group-orientation='vertical'][data-pk-group-divider][data-pk-group-join].button-group-text),
        :host([orientation='vertical']) ::slotted([data-pk-group-orientation='vertical'][data-pk-group-divider][data-pk-group-join]select.button-group-select),
        :host([orientation='vertical']) ::slotted([data-pk-group-orientation='vertical'][data-pk-group-divider][data-pk-group-join]pk-input-group) {
            margin-block-start: var(--pk-bg-vertical-indent-outlined, 0);
        }

        :host([orientation='horizontal']) ::slotted(.button-group-text[data-pk-group-divider]),
        :host([orientation='horizontal']) ::slotted(select.button-group-select[data-pk-group-divider]),
        :host([orientation='horizontal']) ::slotted(pk-input-group[data-pk-group-divider]) {
            border-left-width: 1px;
            border-left-style: solid;
            border-left-color: var(--pk-btn-group-divider-color-outline, var(--pk-color-slate-400));
            box-shadow: none;
        }

        :host([orientation='vertical']) ::slotted(.button-group-text[data-pk-group-divider]),
        :host([orientation='vertical']) ::slotted(select.button-group-select[data-pk-group-divider]),
        :host([orientation='vertical']) ::slotted(pk-input-group[data-pk-group-divider]) {
            border-top-width: 1px;
            border-top-style: solid;
            border-top-color: var(--pk-btn-group-divider-color-outline, var(--pk-color-slate-400));
            box-shadow: none;
        }

        :host([orientation='horizontal']) ::slotted([data-pk-group-internal-trail].button-group-text),
        :host([orientation='horizontal']) ::slotted([data-pk-group-internal-trail].button-group-select),
        :host([orientation='horizontal']) ::slotted([data-pk-group-internal-trail]select.button-group-select),
        :host([orientation='horizontal']) ::slotted([data-pk-group-internal-trail]pk-input-group) {
            border-right-width: 0;
        }

        :host([orientation='vertical']) ::slotted([data-pk-group-internal-trail].button-group-text),
        :host([orientation='vertical']) ::slotted([data-pk-group-internal-trail].button-group-select),
        :host([orientation='vertical']) ::slotted([data-pk-group-internal-trail]select.button-group-select),
        :host([orientation='vertical']) ::slotted([data-pk-group-internal-trail]pk-input-group) {
            border-bottom-width: 0;
        }
    }
`, Hr = "data-pk-group-join", Ur = "data-pk-group-divider", Wr = "data-pk-group-item-first", Gr = "data-pk-group-item-last", Kr = "data-pk-group-internal-trail", qr = "data-pk-group-btn-last", Jr = "data-pk-group-orientation", Yr = /* @__PURE__ */ new Set(["PK-SEPARATOR", "PK-BUTTON-GROUP-SEPARATOR"]), Xr = /* @__PURE__ */ new Set(["PK-POPOVER", "PK-DROPDOWN-MENU"]), Zr = {
	fromAttribute(e) {
		return e === null || e !== "false";
	},
	toAttribute(e) {
		return e ? "" : "false";
	}
};
function Qr(e) {
	return Yr.has(e.tagName);
}
function $r(e) {
	return Xr.has(e.tagName) ? e.querySelector("[slot=\"trigger\"]") ?? e : e;
}
function ei(e) {
	if (Xr.has(e.tagName)) {
		let t = $r(e);
		return t === e ? [e] : [e, t];
	}
	return [e];
}
function ti(e, t) {
	return e === t ? [e] : [e, t];
}
function ni(e) {
	let t = $r(e);
	return [.../* @__PURE__ */ new Set([...ei(e), ...ti(e, t)])];
}
function ri(e) {
	for (let t of ni(e)) t.removeAttribute(Hr), t.removeAttribute(Ur), t.removeAttribute(Wr), t.removeAttribute(Gr), t.removeAttribute(Kr), t.removeAttribute(qr), t.removeAttribute(Jr);
}
function ii(e) {
	return $r(e).hasAttribute("group-trigger") || e.hasAttribute("group-trigger");
}
function ai(e) {
	let t = [], n = [];
	for (let r of e) Qr(r) ? (n.length && t.push(n), n = []) : n.push(r);
	return n.length && t.push(n), t;
}
var oi = class extends T {
	constructor(...e) {
		super(...e), this.orientation = "horizontal", this.separators = !0, this.exclusive = !1, this.label = "";
	}
	static {
		this.styles = Vr;
	}
	firstUpdated() {
		this.scheduleSyncGroupLayout();
	}
	updated(e) {
		e.has("orientation") && (this.setAttribute("aria-orientation", this.orientation), this.scheduleSyncGroupLayout()), e.has("label") && (this.label ? this.setAttribute("aria-label", this.label) : this.removeAttribute("aria-label")), (e.has("separators") || e.has("exclusive")) && this.scheduleSyncGroupLayout();
	}
	scheduleSyncGroupLayout() {
		this.syncGroupLayout(), queueMicrotask(() => this.syncGroupLayout());
	}
	handleSlotChange() {
		this.scheduleSyncGroupLayout();
	}
	syncGroupLayout() {
		let e = this.defaultSlot?.assignedElements({ flatten: !0 }) ?? [], t = ai(e);
		for (let t of e) ri(t);
		for (let t of e) for (let e of ni(t)) e.setAttribute(Jr, this.orientation);
		for (let e = 0; e < t.length; e++) {
			let n = t[e], r = e === 0, i = e === t.length - 1;
			for (let e = 0; e < n.length; e++) {
				let t = n[e], a = $r(t), o = ti(t, a), s = n.length === 1, c = e === 0, l = e === n.length - 1;
				for (let e of o) s ? (r && e.setAttribute(Wr, ""), i && e.setAttribute(Gr, "")) : (c && e.setAttribute(Wr, ""), l && e.setAttribute(Gr, ""));
				if (c) {
					if (this.separators && !l) for (let e of ei(t)) e.setAttribute(Kr, "");
				} else {
					for (let e of ei(t)) e.setAttribute(Hr, ""), this.separators && e.setAttribute(Ur, "");
					if (this.separators && !l) for (let e of ei(t)) e.setAttribute(Kr, "");
					ii(t) && a.setAttribute(qr, "");
				}
			}
		}
	}
	render() {
		return e`
            <div part="base" class="group" role="group" aria-label=${this.label || n}>
                <slot @slotchange=${this.handleSlotChange}></slot>
            </div>
        `;
	}
};
s([t({ reflect: !0 })], oi.prototype, "orientation", void 0), s([t({
	type: Boolean,
	reflect: !0,
	converter: Zr
})], oi.prototype, "separators", void 0), s([t({
	type: Boolean,
	reflect: !0
})], oi.prototype, "exclusive", void 0), s([t()], oi.prototype, "label", void 0), s([E("slot:not([name])")], oi.prototype, "defaultSlot", void 0), oi = s([r("pk-button-group")], oi);
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/pk-dropdown-item-DPM5KJ--.js
var G = {
	default: i`
        --pk-dropdown-item-padding-block: 8px;
        --pk-dropdown-item-padding-inline: 12px;
        --pk-dropdown-item-gap: 0.625rem;
        --pk-dropdown-item-font-size: var(--pk-font-size-base);
        --pk-dropdown-item-line-height: 1.5;
        --pk-dropdown-item-icon-size: 12px;
        --pk-dropdown-label-padding-inline: 12px;
        --pk-dropdown-label-font-size: 13px;
        --pk-dropdown-details-font-size: var(--pk-font-size-sm);
    `,
	xs: i`
        --pk-dropdown-item-padding-block: 3px;
        --pk-dropdown-item-padding-inline: 8px;
        --pk-dropdown-item-gap: 0.375rem;
        --pk-dropdown-item-font-size: 12px;
        --pk-dropdown-item-line-height: 1.5;
        --pk-dropdown-item-icon-size: 10px;
        --pk-dropdown-label-padding-inline: 8px;
        --pk-dropdown-label-font-size: 11px;
        --pk-dropdown-details-font-size: 11px;
    `,
	sm: i`
        --pk-dropdown-item-padding-block: 4px;
        --pk-dropdown-item-padding-inline: 10px;
        --pk-dropdown-item-gap: 0.4375rem;
        --pk-dropdown-item-font-size: 13px;
        --pk-dropdown-item-line-height: 1.5;
        --pk-dropdown-item-icon-size: 12px;
        --pk-dropdown-label-padding-inline: 10px;
        --pk-dropdown-label-font-size: 11px;
        --pk-dropdown-details-font-size: 12px;
    `,
	lg: i`
        --pk-dropdown-item-padding-block: 10px;
        --pk-dropdown-item-padding-inline: 14px;
        --pk-dropdown-item-gap: 0.75rem;
        --pk-dropdown-item-font-size: 16px;
        --pk-dropdown-item-line-height: 1.5;
        --pk-dropdown-item-icon-size: 14px;
        --pk-dropdown-label-padding-inline: 14px;
        --pk-dropdown-label-font-size: 14px;
        --pk-dropdown-details-font-size: var(--pk-font-size-sm);
    `,
	xl: i`
        --pk-dropdown-item-padding-block: 12px;
        --pk-dropdown-item-padding-inline: 16px;
        --pk-dropdown-item-gap: 0.75rem;
        --pk-dropdown-item-font-size: 18px;
        --pk-dropdown-item-line-height: 1.5;
        --pk-dropdown-item-icon-size: 16px;
        --pk-dropdown-label-padding-inline: 16px;
        --pk-dropdown-label-font-size: 15px;
        --pk-dropdown-details-font-size: var(--pk-font-size-base);
    `
}, si = i`
    @layer pk-component {
        :host {
            ${G.default}
        }

        :host([size='xs']) {
            ${G.xs}
        }

        :host([size='sm']) {
            ${G.sm}
        }

        :host([size='lg']) {
            ${G.lg}
        }

        :host([size='xl']) {
            ${G.xl}
        }
    }
`, ci = i`
    @layer pk-component {
        .panel[data-size='default'],
        .submenu-panel[data-size='default'] {
            ${G.default}
        }

        .panel[data-size='xs'],
        .submenu-panel[data-size='xs'] {
            ${G.xs}
        }

        .panel[data-size='sm'],
        .submenu-panel[data-size='sm'] {
            ${G.sm}
        }

        .panel[data-size='lg'],
        .submenu-panel[data-size='lg'] {
            ${G.lg}
        }

        .panel[data-size='xl'],
        .submenu-panel[data-size='xl'] {
            ${G.xl}
        }
    }
`;
i`
    ${si}
    ${ci}
`;
var li = [
	Tr,
	ci,
	i`
    @layer pk-component {
        :host {
            display: block;
            position: relative;
            /*
             * Slotted label text inherits from this host (light DOM), not from
             * shadow .item — pin size-token metrics so Craft CP / Tailwind /
             * bare hosts all get the same item rhythm.
             */
            font-size: var(--pk-dropdown-item-font-size, var(--pk-font-size-base));
            line-height: var(--pk-dropdown-item-line-height, 1.5);
            color: var(--text-color, var(--pk-color-gray-700));
        }

        .item {
            display: flex;
            align-items: center;
            gap: var(--pk-dropdown-item-gap, 0.625rem);
            width: 100%;
            margin: 0;
            padding: var(--pk-dropdown-item-padding-block, 8px) var(--pk-dropdown-item-padding-inline, 12px);
            border: 0;
            background: transparent;
            color: inherit;
            font: inherit;
            font-size: var(--pk-dropdown-item-font-size, var(--pk-font-size-base));
            /* Explicit — do not let font:inherit re-leak page line-height. */
            line-height: var(--pk-dropdown-item-line-height, 1.5);
            font-weight: normal;
            text-align: left;
            white-space: nowrap;
            cursor: default;
            user-select: none;
            outline: none;
            box-sizing: border-box;
        }

        .item:hover:not([disabled]):not([aria-disabled='true']),
        :host([data-highlighted]) .item,
        :host([submenu-open]) .item {
            background: var(--pk-color-slate-100);
        }

        .item:focus-visible {
            background: var(--pk-color-slate-100);
        }

        .item[aria-disabled='true'] {
            pointer-events: none;
            opacity: 0.5;
        }

        .label {
            flex: 1 1 auto;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .prefix {
            display: inline-flex;
            flex: 0 0 auto;
            align-items: center;
            justify-content: center;
            width: var(--pk-dropdown-item-icon-size, 12px);
            height: var(--pk-dropdown-item-icon-size, 12px);
            line-height: 0;
        }

        .prefix--empty {
            display: none;
        }

        .prefix ::slotted(*) {
            display: inline-flex;
            flex: 0 0 auto;
            align-items: center;
            justify-content: center;
            width: var(--pk-dropdown-item-icon-size, 12px);
            height: var(--pk-dropdown-item-icon-size, 12px);
            /* Kill pk-icon text-baseline nudge inside the padded flex row. */
            vertical-align: 0;
        }

        .prefix ::slotted(svg),
        .prefix ::slotted(*) svg,
        .prefix ::slotted(.pk-dropdown-item__prefix-icon) {
            display: block;
            width: var(--pk-dropdown-item-icon-size, 12px) !important;
            height: var(--pk-dropdown-item-icon-size, 12px) !important;
            max-width: var(--pk-dropdown-item-icon-size, 12px);
            max-height: var(--pk-dropdown-item-icon-size, 12px);
            flex-shrink: 0;
            pointer-events: none;
        }

        .details {
            margin-left: auto;
            color: var(--pk-color-gray-500);
            font-size: var(--pk-dropdown-details-font-size, var(--pk-font-size-sm));
            letter-spacing: 0.04em;
        }

        .details:empty {
            display: none;
        }

        .check {
            display: inline-flex;
            flex: 0 0 auto;
            align-items: center;
            justify-content: center;
            width: 12px;
            height: 12px;
            color: var(--pk-color-gray-700);
        }

        .check svg {
            display: block;
            width: 12px;
            height: 12px;
            flex-shrink: 0;
            pointer-events: none;
        }

        .submenu-icon {
            display: inline-flex;
            flex: 0 0 auto;
            align-items: center;
            justify-content: center;
            width: 1rem;
            color: var(--pk-color-gray-700);
        }

        .submenu-icon svg {
            display: block;
            width: 1em;
            height: 1em;
            flex-shrink: 0;
            pointer-events: none;
        }

        .check {
            opacity: 0;
        }

        :host([checked]) .check {
            opacity: 1;
        }

        :host([type='checkbox']) .check,
        :host([type='radio']) .check {
            margin-left: auto;
        }

        :host([type='checkbox'][checked]) .check,
        :host([type='radio'][checked]) .check {
            opacity: 1;
        }

        .submenu-icon:empty {
            display: none;
        }

        :host([destructive]) .item {
            color: var(--pk-color-error);
        }

        :host([destructive]) .item:hover:not([disabled]):not([aria-disabled='true']),
        :host([destructive]) .item:focus-visible {
            color: var(--pk-color-error);
        }

        .submenu-panel {
            width: max-content;
            min-width: 8rem;
            max-height: var(--pk-popup-available-height, calc(100dvh - 20px));
            overflow: auto;
            overscroll-behavior: contain;
            padding: 4px 0;
            border-radius: var(--pk-radius-md);
            background: var(--pk-color-white);
            box-shadow: var(--pk-shadow-popup);
            /* Match root menu panel — Craft body text, not gray-900. */
            color: var(--text-color, var(--pk-color-gray-700));
        }

        .submenu-panel ::slotted(pk-dropdown-item),
        .submenu-panel ::slotted(pk-dropdown-separator),
        .submenu-panel ::slotted(pk-dropdown-label) {
            display: block;
        }

        .submenu-panel[hidden] {
            display: none !important;
        }
    }
`
], ui, di = S(f), fi = S(p), K = class extends T {
	static {
		ui = this;
	}
	constructor(...e) {
		super(...e), this.value = "", this.type = "normal", this.radioGroup = "", this.disabled = !1, this.destructive = !1, this.checked = !1, this.submenuOpen = !1, this.active = !1, this.submenuAnimated = !1, this.hasSlotController = new g(this, "submenu", "details", "start", "prefix"), this.handleMouseEnter = () => {
			this.hasSubmenu() && !this.disabled && (this.notifyParentOfOpening(), this.submenuOpen = !0);
		}, this.handleHostClick = (e) => {
			this.disabled && (e.preventDefault(), e.stopImmediatePropagation());
		};
	}
	static {
		this.styles = li;
	}
	connectedCallback() {
		super.connectedCallback(), this.syncRole(), this.syncSubmenuAria(), this.addEventListener("click", this.handleHostClick), this.addEventListener("mouseenter", this.handleMouseEnter);
	}
	disconnectedCallback() {
		this.removeEventListener("click", this.handleHostClick), this.removeEventListener("mouseenter", this.handleMouseEnter), this.closeSubmenu(), super.disconnectedCallback();
	}
	updated(e) {
		(e.has("type") || e.has("checked")) && this.syncRole(), (e.has("submenuOpen") || e.size === 0) && this.syncSubmenuAria(), e.has("submenuOpen") && (this.submenuOpen ? this.ensureSubmenuSurface() : this.submenuAnimated = !1);
	}
	hasSubmenu() {
		return this.hasSlotController.test("submenu");
	}
	syncSubmenuAria() {
		let e = this.hasSubmenu();
		e ? this.setAttribute("aria-haspopup", "menu") : this.removeAttribute("aria-haspopup"), this.setAttribute("aria-expanded", e && this.submenuOpen ? "true" : "false");
	}
	focusControl() {
		this.shadowRoot?.querySelector(".item")?.focus({ preventScroll: !0 });
	}
	focus(e) {
		let t = this.shadowRoot?.querySelector(".item");
		t ? t.focus(e) : super.focus(e);
	}
	get submenuElement() {
		return this.submenuPanelElement ?? null;
	}
	closeSubmenu() {
		this.submenuAnimated = !1, this.submenuOpen = !1;
	}
	openSubmenu() {
		this.hasSubmenu() && !this.disabled && this.isConnected && (this.notifyParentOfOpening(), this.submenuOpen = !0);
	}
	notifyParentOfOpening() {
		this.dispatchEvent(new CustomEvent("pk-submenu-open", {
			bubbles: !0,
			composed: !0,
			detail: { item: this }
		}));
		let e = this.parentElement;
		if (e) for (let t of e.children) t !== this && t instanceof ui && t.getAttribute("slot") === this.getAttribute("slot") && t.submenuOpen && (t.submenuOpen = !1);
	}
	ensureSubmenuSurface() {
		this.hasSubmenu() && !this.disabled && (this.submenuAnimated = !0, this.updateComplete.then(() => {
			this.submenuOpen && this.submenuPanelElement && (this.submenuPanelElement.hidden = !1, wt(this.submenuPanelElement, "right-start"), Tt(this.submenuPopupElement, "right-start").then((e) => {
				wt(this.submenuPanelElement, e);
			}));
		}));
	}
	syncRole() {
		this.type === "checkbox" ? (this.setAttribute("role", "menuitemcheckbox"), this.setAttribute("aria-checked", this.checked ? "true" : "false")) : this.type === "radio" ? (this.setAttribute("role", "menuitemradio"), this.setAttribute("aria-checked", this.checked ? "true" : "false")) : (this.setAttribute("role", "menuitem"), this.removeAttribute("aria-checked"));
	}
	handleClick(e) {
		this.disabled ? (e.preventDefault(), e.stopImmediatePropagation()) : this.hasSubmenu() && (e.preventDefault(), this.openSubmenu());
	}
	render() {
		let t = this.hasSubmenu(), r = this.type === "checkbox" || this.type === "radio", i = this.hasSlotController.test("start") || this.hasSlotController.test("prefix");
		return e`
            <button
                part="item"
                type="button"
                class="item"
                ?disabled=${this.disabled}
                aria-disabled=${this.disabled ? "true" : n}
                @click=${this.handleClick}
            >
                <span
                    part="prefix"
                    class=${i ? "prefix" : "prefix prefix--empty"}
                >
                    <slot name="start"></slot>
                    <slot name="prefix"></slot>
                </span>
                <span part="label" class="label"><slot></slot></span>
                <span class="details"><slot name="details"></slot></span>
                ${r ? e`<span part="check" class="check" aria-hidden="true">${C(di)}</span>` : n}
                ${t ? e`<span class="submenu-icon" aria-hidden="true">${C(fi)}</span>` : n}
            </button>
            ${t ? e`
                <pk-popup
                    .active=${this.submenuOpen}
                    .anchor=${this}
                    placement="right-start"
                    .distance=${0}
                    .skidding=${-4}
                    flip
                    shift
                    auto-size="vertical"
                    .autoSizePadding=${10}
                    hover-bridge
                    style="--pk-popup-z-index: 1001"
                >
                    <div
                        part="submenu"
                        class="submenu-panel pk-popup-content"
                        role="menu"
                        data-size=${pi(this)}
                        ?hidden=${!this.submenuOpen}
                        data-open=${this.submenuAnimated ? "" : n}
                        aria-orientation="vertical"
                    >
                        <slot name="submenu"></slot>
                    </div>
                </pk-popup>
            ` : n}
        `;
	}
};
s([t()], K.prototype, "value", void 0), s([t({ reflect: !0 })], K.prototype, "type", void 0), s([t({ attribute: "radio-group" })], K.prototype, "radioGroup", void 0), s([t({
	type: Boolean,
	reflect: !0
})], K.prototype, "disabled", void 0), s([t({
	type: Boolean,
	reflect: !0
})], K.prototype, "destructive", void 0), s([t({
	type: Boolean,
	reflect: !0
})], K.prototype, "checked", void 0), s([t({
	attribute: "submenu-open",
	type: Boolean,
	reflect: !0
})], K.prototype, "submenuOpen", void 0), s([t({ type: Boolean })], K.prototype, "active", void 0), s([o()], K.prototype, "submenuAnimated", void 0), s([E(".submenu-panel")], K.prototype, "submenuPanelElement", void 0), s([E("pk-popup")], K.prototype, "submenuPopupElement", void 0), K = ui = s([r("pk-dropdown-item")], K);
function pi(e) {
	let t = e.parentElement?.getAttribute("data-size");
	if (t === "xs" || t === "sm" || t === "default" || t === "lg" || t === "xl") return t;
	let n = e.closest("pk-dropdown-menu")?.getAttribute("size");
	return n === "xs" || n === "sm" || n === "lg" || n === "xl" ? n : "default";
}
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/pk-dropdown-menu-yZrSKVIW.js
var mi = [
	ie(),
	si,
	ci,
	i`
        @layer pk-component {
            /* Standalone: keep a real box so the trigger is not a flex-stretched
               child of the page (display:contents flattened pk-button to full card width).
               Button groups override below — same as legacy + React MenuButton inline-flex wrap. */
            :host {
                display: inline-block;
                position: relative;
                width: fit-content;
                max-width: 100%;
                align-self: flex-start;
                vertical-align: middle;
            }

            :host([data-pk-group-orientation]) {
                display: inline-flex;
                vertical-align: middle;
                flex: 0 0 auto;
                width: auto;
                max-width: none;
                align-self: auto;
            }

            /* Belt-and-suspenders if a parent still flattens layout onto the trigger. */
            ::slotted([slot='trigger']) {
                width: fit-content;
                max-width: 100%;
                flex: 0 0 auto;
                align-self: flex-start;
            }

            :host([data-pk-group-orientation]) ::slotted([slot='trigger']) {
                --pk-bg-start-start-radius: inherit;
                --pk-bg-start-end-radius: inherit;
                --pk-bg-end-start-radius: inherit;
                --pk-bg-end-end-radius: inherit;
                align-self: auto;
                max-width: none;
            }

            :host([data-pk-group-orientation='horizontal'][data-pk-group-join]) {
                margin-inline-start: var(--pk-bg-horizontal-indent, 0);
            }

            :host([data-pk-group-orientation='vertical'][data-pk-group-join]) {
                margin-block-start: var(--pk-bg-vertical-indent, 0);
            }

            :host([data-pk-group-orientation='horizontal'][data-pk-group-join]:has([slot='trigger'][variant='outline'], [slot='trigger'][variant='dashed'])) {
                margin-inline-start: var(--pk-bg-horizontal-indent-outlined, 0);
            }

            :host([data-pk-group-orientation='vertical'][data-pk-group-join]:has([slot='trigger'][variant='outline'], [slot='trigger'][variant='dashed'])) {
                margin-block-start: var(--pk-bg-vertical-indent-outlined, 0);
            }

            /* Menu panel — hug content; do not stretch to trigger/anchor width. */
            .panel {
                display: flex;
                flex-direction: column;
                width: max-content;
                min-width: 8rem;
                max-height: var(--pk-popup-available-height, calc(100dvh - 20px));
                margin: 0;
                overflow: auto;
                overscroll-behavior: contain;
                padding: 4px 0;
                border: 0;
                border-radius: var(--pk-radius-md);
                background: var(--pk-color-white);
                box-shadow: var(--pk-shadow-popup);
                /* v1 DropdownMenuItem had no face color — inherited Craft body
                 * (--text-color ≈ gray-700). Do not force gray-900 (too dark). */
                color: var(--text-color, var(--pk-color-gray-700));
                outline: none;
                text-align: start;
                user-select: none;
                /* Match v1 Base UI: popup writes --pk-transform-origin from the
                 * anchor center on the connecting edge (e.g. top-right for
                 * bottom-end). Keyword edge centers made end-aligned menus
                 * scale from the middle of the panel. */
                transform-origin: var(--pk-transform-origin, top);
            }

            .panel.show {
                animation: pk-dropdown-menu-show 100ms ease;
            }

            .panel.hide {
                animation: pk-dropdown-menu-show 100ms ease reverse;
            }

            .panel[hidden] {
                display: none !important;
            }

            ::slotted(pk-dropdown-item),
            ::slotted(pk-dropdown-separator),
            ::slotted(pk-dropdown-label),
            .panel > pk-dropdown-item,
            .panel > pk-dropdown-separator,
            .panel > pk-dropdown-label {
                display: block;
            }

            ::slotted([data-menu-item]) {
                display: flex;
                align-items: center;
                gap: 0.625rem;
                width: 100%;
                margin: 0;
                padding: 8px 12px;
                border: 0;
                background: transparent;
                color: inherit;
                font: inherit;
                font-size: var(--pk-font-size-base);
                text-align: left;
                white-space: nowrap;
                cursor: default;
                user-select: none;
                outline: none;
                box-sizing: border-box;
            }

            ::slotted([data-menu-item]:hover:not([disabled])) {
                background: var(--pk-color-slate-100);
            }

            ::slotted([data-menu-item]:focus-visible) {
                background: var(--pk-color-slate-100);
            }

            ::slotted([data-menu-item][disabled]) {
                pointer-events: none;
                opacity: 0.5;
            }

            ::slotted(pk-dropdown-item[destructive]),
            ::slotted([data-destructive]) {
                color: var(--pk-color-error);
            }

            ::slotted([data-menu-separator]) {
                display: block;
                height: 1px;
                margin: 4px 0;
                background: var(--pk-color-slate-200);
                border: 0;
                padding: 0;
            }
        }

        /* Outside @layer so constructed stylesheets resolve the name reliably. */
        @keyframes pk-dropdown-menu-show {
            from {
                scale: 0.9;
                opacity: 0;
            }

            to {
                scale: 1;
                opacity: 1;
            }
        }
    `
], hi = /* @__PURE__ */ new Set(), q = class extends T {
	constructor(...e) {
		super(...e), this.open = !1, this.size = "default", this.placement = "bottom-start", this.sideOffset = 4, this.distance = 4, this.skidding = 0, this.for = "", this.userTypedQuery = "", this.userTypedTimeout = 0, this.openSubmenuStack = [], this.openedByKeyboard = !1, this.triggerElement = null, this.handleMenuClick = (e) => {
			let t = this.resolveMenuItem(e);
			t && !t.disabled && (t.hasSubmenu() ? (t.submenuOpen || (this.closeSiblingSubmenus(t), this.addToSubmenuStack(t), t.openSubmenu()), e.stopPropagation()) : this.makeSelection(t));
		}, this.handleSubmenuOpening = (e) => {
			let t = e.detail?.item;
			t instanceof K && (this.closeSiblingSubmenus(t), this.addToSubmenuStack(t));
		}, this.handleGlobalMouseMove = (e) => {
			let t = this.getCurrentSubmenuItem();
			if (!t?.submenuOpen || !t.submenuElement) return;
			let n = t.submenuElement, r = e.composedPath(), i = t.matches(":hover"), a = !!n.matches(":hover"), o = i || r.some((e) => e === t), s = a || r.some((e) => e instanceof HTMLElement && e.closest("[part=\"submenu\"]") === n);
			!o && !s && window.setTimeout(() => {
				!i && !a && (t.submenuOpen = !1);
			}, 100);
		}, this.handleTriggerClick = (e) => {
			let t = this.getTrigger();
			t && e.composedPath().includes(t) && (e.preventDefault(), e.stopPropagation(), this.openedByKeyboard = !1, this.open = !this.open);
		}, this.handleExternalTriggerClick = (e) => {
			e.preventDefault(), e.stopPropagation(), this.openedByKeyboard = !1, this.open = !this.open;
		}, this.handleTriggerKeyDown = (e) => {
			let t = this.getTrigger();
			t && e.composedPath().includes(t) && (this.open || (e.key === "ArrowDown" || e.key === "ArrowUp") && (e.preventDefault(), e.stopPropagation(), this.openedByKeyboard = !0, this.open = !0));
		}, this.handleDocumentKeyDown = (e) => {
			let t = this.isRtl();
			if (e.key === "Escape" && this.open && se(this)) {
				e.preventDefault(), e.stopPropagation(), this.open = !1, this.getTrigger()?.focus({ preventScroll: !0 });
				return;
			}
			if (!this.open) return;
			let n = [...Je()].find((e) => e.localName === "pk-dropdown-item"), r = n?.localName === "pk-dropdown-item", i = this.getCurrentSubmenuItem(), a = !!i, o, s, c;
			a && i ? (o = this.getSubmenuItems(i), s = o.find((e) => e.active || e === n), c = s ? o.indexOf(s) : -1) : (o = this.getItems(), s = o.find((e) => e.active || e === n), c = s ? o.indexOf(s) : -1);
			let l;
			if (e.key === "ArrowUp" && (e.preventDefault(), e.stopPropagation(), l = c > 0 ? o[c - 1] : o[o.length - 1]), e.key === "ArrowDown" && (e.preventDefault(), e.stopPropagation(), l = c !== -1 && c < o.length - 1 ? o[c + 1] : o[0]), e.key === (t ? "ArrowLeft" : "ArrowRight") && r && s && s.hasSubmenu()) e.preventDefault(), e.stopPropagation(), this.closeSiblingSubmenus(s), s.openSubmenu(), this.addToSubmenuStack(s), window.setTimeout(() => {
				let e = this.getSubmenuItems(s);
				e.length > 0 && this.setActiveItem(e, e[0]);
			}, 0);
			else if (e.key === (t ? "ArrowRight" : "ArrowLeft") && a) {
				e.preventDefault(), e.stopPropagation();
				let t = this.removeFromSubmenuStack();
				t && (t.submenuOpen = !1, window.setTimeout(() => {
					t.focus({ preventScroll: !0 }), t.active = !0, (t.slot === "submenu" && t.parentElement instanceof K ? this.getSubmenuItems(t.parentElement) : this.getItems()).forEach((e) => {
						e !== t && (e.active = !1);
					});
				}, 0));
			} else if ((e.key === "Home" || e.key === "End") && (e.preventDefault(), e.stopPropagation(), l = e.key === "Home" ? o[0] : o[o.length - 1]), e.key === "Tab") this.open = !1;
			else {
				if (e.key.length === 1 && !(e.metaKey || e.ctrlKey || e.altKey) && (e.key !== " " || this.userTypedQuery !== "")) {
					window.clearTimeout(this.userTypedTimeout), this.userTypedTimeout = window.setTimeout(() => {
						this.userTypedQuery = "";
					}, 1e3), this.userTypedQuery += e.key;
					let t = this.userTypedQuery.trim().toLowerCase();
					l = o.find((e) => (e.textContent || "").trim().toLowerCase().startsWith(t));
				}
				l ? (e.preventDefault(), e.stopPropagation(), this.setActiveItem(o, l)) : (e.key === "Enter" || e.key === " " && this.userTypedQuery === "") && r && s && (e.preventDefault(), e.stopPropagation(), s.hasSubmenu() ? (this.closeSiblingSubmenus(s), s.openSubmenu(), this.addToSubmenuStack(s), window.setTimeout(() => {
					let e = this.getSubmenuItems(s);
					e.length > 0 && this.setActiveItem(e, e[0]);
				}, 0)) : this.makeSelection(s));
			}
		}, this.handleDocumentPointerDown = (e) => {
			let t = e.composedPath(), n = this.getTrigger();
			t.some((e) => e === this || e === n) || (this.open = !1);
		};
	}
	static {
		this.styles = mi;
	}
	get panelElement() {
		return this.menuElement ?? null;
	}
	get popup() {
		return this.popupElement ?? null;
	}
	connectedCallback() {
		super.connectedCallback(), this.addEventListener("click", this.handleTriggerClick, !0), this.addEventListener("keydown", this.handleTriggerKeyDown);
	}
	firstUpdated() {
		let e = () => {
			this.for ? this.resolveExternalTrigger() : this.syncSlottedTrigger();
		};
		queueMicrotask(e), requestAnimationFrame(e);
	}
	disconnectedCallback() {
		window.clearTimeout(this.userTypedTimeout), this.removeEventListener("click", this.handleTriggerClick, !0), this.removeEventListener("keydown", this.handleTriggerKeyDown), this.unbindTrigger(this.triggerElement), this.triggerElement = null, this.closeAllSubmenus(), this.popupElement && (this.popupElement.active = !1), this.menuElement?.classList.remove("show", "hide"), document.removeEventListener("keydown", this.handleDocumentKeyDown), document.removeEventListener("pointerdown", this.handleDocumentPointerDown, !0), document.removeEventListener("mousemove", this.handleGlobalMouseMove), oe(this), hi.delete(this), super.disconnectedCallback();
	}
	async updated(e) {
		if (super.updated(e), e.has("for") && this.resolveExternalTrigger(), e.has("open") && this.syncTriggerExpanded(), !e.has("open")) return;
		let t = e.get("open");
		t !== this.open && (t !== void 0 || this.open !== !1) && (this.open ? await this.showMenu() : (this.closeAllSubmenus(), await this.hideMenu("unknown")));
	}
	getItems(e = !1) {
		let t = (this.defaultSlot?.assignedElements({ flatten: !0 }) ?? []).filter((e) => e.localName === "pk-dropdown-item");
		return e ? t : t.filter((e) => !e.disabled);
	}
	getSubmenuItems(e, t = !1) {
		let n = ((e.shadowRoot?.querySelector("slot[name=\"submenu\"]"))?.assignedElements({ flatten: !0 }) ?? [...e.children].filter((e) => e.getAttribute("slot") === "submenu")).filter((e) => e.localName === "pk-dropdown-item");
		return t ? n : n.filter((e) => !e.disabled);
	}
	getTrigger() {
		return this.for ? gr(this, this.for) ?? this.triggerElement : this.querySelector("[slot=\"trigger\"]") ?? this.triggerElement;
	}
	getAnchor() {
		return this.getTrigger() ?? "";
	}
	resolveExternalTrigger() {
		this.unbindTrigger(this.triggerElement), this.triggerElement = this.for ? gr(this, this.for) : null, this.bindTrigger(this.triggerElement), this.requestUpdate();
	}
	onTriggerSlotChange(e) {
		if (this.for) return;
		let [t] = e.target.assignedElements({ flatten: !0 });
		this.unbindTrigger(this.triggerElement), this.triggerElement = t ?? null, this.bindTrigger(this.triggerElement), this.requestUpdate();
	}
	syncSlottedTrigger() {
		let e = this.renderRoot.querySelector("slot[name=\"trigger\"]");
		e && this.onTriggerSlotChange({ target: e });
	}
	bindTrigger(e) {
		e && (e.setAttribute("aria-haspopup", "menu"), this.for && (e.addEventListener("click", this.handleExternalTriggerClick), e.addEventListener("keydown", this.handleTriggerKeyDown)), this.syncTriggerExpanded());
	}
	unbindTrigger(e) {
		e?.removeEventListener("click", this.handleExternalTriggerClick), e?.removeEventListener("keydown", this.handleTriggerKeyDown);
	}
	syncTriggerExpanded() {
		this.getTrigger()?.setAttribute("aria-expanded", this.open ? "true" : "false");
	}
	closeAfterSelect(e = "api") {
		this.open = !1;
	}
	makeSelection(e) {
		let t = this.getTrigger();
		if (e.disabled) return;
		e.type === "checkbox" && (e.checked = !e.checked), e.type === "radio" && !e.checked && (e.checked = !0);
		let n = {
			value: e.value,
			type: e.type,
			checked: e.checked,
			radioGroup: e.radioGroup
		};
		e.dispatchEvent(new CustomEvent("pk-select", {
			detail: n,
			bubbles: !1,
			composed: !1,
			cancelable: !0
		}));
		let r = new CustomEvent("pk-select", {
			detail: n,
			bubbles: !0,
			composed: !0,
			cancelable: !0
		});
		this.dispatchEvent(r), r.defaultPrevented || (this.open = !1, t?.focus({ preventScroll: !0 }));
	}
	resolveMenuItem(e) {
		let t = e.target;
		if (t instanceof K) return t;
		if (t instanceof Element) {
			let e = t.closest("pk-dropdown-item");
			if (e instanceof K) return e;
		}
		return e.composedPath().find((e) => e instanceof K) ?? null;
	}
	whenClosed() {
		return this.open ? new Promise((e) => {
			this.addEventListener("pk-after-hide", () => {
				this.popupElement.stop().then(() => e());
			}, { once: !0 });
		}) : this.popupElement?.active ? this.popupElement.stop() : Promise.resolve();
	}
	forceDismissCleanup() {
		this.open = !1, this.popupElement.active = !1, this.menuElement?.classList.remove("show", "hide"), this.closeAllSubmenus(), document.removeEventListener("keydown", this.handleDocumentKeyDown), document.removeEventListener("pointerdown", this.handleDocumentPointerDown, !0), document.removeEventListener("mousemove", this.handleGlobalMouseMove), oe(this), hi.delete(this);
	}
	isRtl() {
		return getComputedStyle(this).direction === "rtl";
	}
	addToSubmenuStack(e) {
		let t = this.openSubmenuStack.indexOf(e);
		t === -1 ? this.openSubmenuStack.push(e) : this.openSubmenuStack = this.openSubmenuStack.slice(0, t + 1);
	}
	removeFromSubmenuStack() {
		return this.openSubmenuStack.pop();
	}
	getCurrentSubmenuItem() {
		return this.openSubmenuStack.length > 0 ? this.openSubmenuStack[this.openSubmenuStack.length - 1] : void 0;
	}
	closeAllSubmenus() {
		this.getItems(!0).forEach((e) => {
			e.submenuOpen = !1, e.active = !1;
		}), this.openSubmenuStack = [];
	}
	closeSiblingSubmenus(e) {
		let t = e.closest("pk-dropdown-item:not([slot=\"submenu\"])");
		(t instanceof K ? this.getSubmenuItems(t, !0) : this.getItems(!0)).forEach((t) => {
			t !== e && t.submenuOpen && (t.submenuOpen = !1);
		}), this.openSubmenuStack.includes(e) || this.openSubmenuStack.push(e);
	}
	setActiveItem(e, t) {
		e.forEach((e) => {
			e.active = e === t, e === t ? e.setAttribute("data-highlighted", "") : e.removeAttribute("data-highlighted");
		}), t.focus({ preventScroll: !0 }), t.scrollIntoView({ block: "nearest" });
	}
	async showMenu() {
		if (!this.popupElement || !this.menuElement) return;
		this.for && !this.triggerElement?.isConnected && this.resolveExternalTrigger();
		let e = new ce();
		if (!this.dispatchEvent(e)) {
			this.open = !1;
			return;
		}
		if (this.popupElement.active && (this.popupElement.active = !1, this.menuElement.classList.remove("show", "hide"), await this.updateComplete), hi.forEach((e) => {
			e !== this && (e.open = !1);
		}), this.popupElement.active = !0, this.open = !0, hi.add(this), ae(this), document.addEventListener("keydown", this.handleDocumentKeyDown), document.addEventListener("pointerdown", this.handleDocumentPointerDown, !0), document.addEventListener("mousemove", this.handleGlobalMouseMove), await this.updateComplete, await Tt(this.popupElement, this.placement, 100, { requireEvent: !0 }), !this.open) {
			this.popupElement.active = !1, hi.delete(this), oe(this), document.removeEventListener("keydown", this.handleDocumentKeyDown), document.removeEventListener("pointerdown", this.handleDocumentPointerDown, !0), document.removeEventListener("mousemove", this.handleGlobalMouseMove);
			return;
		}
		this.menuElement.classList.remove("hide"), await Se(this.menuElement, "show");
		let t = this.getItems();
		t.length > 0 && (this.openedByKeyboard ? this.setActiveItem(t, t[0]) : (t.forEach((e) => {
			e.active = !1, e.removeAttribute("data-highlighted");
		}), this.menuElement.focus({ preventScroll: !0 }))), this.openedByKeyboard = !1, this.dispatchEvent(new le()), this.dispatchEvent(new CustomEvent("pk-open-change", {
			detail: { open: !0 },
			bubbles: !0,
			composed: !0
		}));
	}
	async hideMenu(e) {
		if (!this.popupElement || !this.menuElement) return;
		let t = new ue(e);
		this.dispatchEvent(t) ? (this.open = !1, hi.delete(this), oe(this), document.removeEventListener("keydown", this.handleDocumentKeyDown), document.removeEventListener("pointerdown", this.handleDocumentPointerDown, !0), document.removeEventListener("mousemove", this.handleGlobalMouseMove), this.userTypedQuery = "", window.clearTimeout(this.userTypedTimeout), this.getItems(!0).forEach((e) => {
			e.active = !1, e.removeAttribute("data-highlighted");
		}), this.menuElement.classList.remove("show"), await Se(this.menuElement, "hide"), this.popupElement.active = !1, this.dispatchEvent(new de()), this.dispatchEvent(new CustomEvent("pk-open-change", {
			detail: { open: !1 },
			bubbles: !0,
			composed: !0
		}))) : this.open = !0;
	}
	render() {
		let t = this.hasUpdated ? this.popupElement?.active : this.open, n = this.for ? this.getAnchor() : "";
		return e`
            <pk-popup
                .anchor=${n}
                placement=${this.placement}
                .distance=${this.distance || this.sideOffset}
                .skidding=${this.skidding}
                ?active=${t}
                flip
                shift
                .shiftPadding=${10}
                auto-size="vertical"
                .autoSizePadding=${10}
            >
                <slot
                    name="trigger"
                    slot="anchor"
                    @slotchange=${this.onTriggerSlotChange}
                ></slot>

                <div
                    id="menu"
                    part="panel"
                    class="panel"
                    role="menu"
                    tabindex="-1"
                    aria-orientation="vertical"
                    data-size=${this.size}
                    @click=${this.handleMenuClick}
                    @pk-submenu-open=${this.handleSubmenuOpening}
                >
                    <slot></slot>
                </div>
            </pk-popup>
        `;
	}
};
s([t({
	type: Boolean,
	reflect: !0
})], q.prototype, "open", void 0), s([t({ reflect: !0 })], q.prototype, "size", void 0), s([t({ reflect: !0 })], q.prototype, "placement", void 0), s([t({
	attribute: "side-offset",
	type: Number
})], q.prototype, "sideOffset", void 0), s([t({ type: Number })], q.prototype, "distance", void 0), s([t({ type: Number })], q.prototype, "skidding", void 0), s([t({ reflect: !0 })], q.prototype, "for", void 0), s([E("slot:not([name])")], q.prototype, "defaultSlot", void 0), s([E("#menu")], q.prototype, "menuElement", void 0), s([E("pk-popup")], q.prototype, "popupElement", void 0), q = s([r("pk-dropdown-menu")], q);
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/components/icon/pk-icon.js
var gi = class extends T {
	constructor(...e) {
		super(...e), this.icon = "", this.name = "", this.unsubscribeRegistry = null;
	}
	static {
		this.styles = i`
        :host {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: none;
            /* Square em box + slight baseline nudge for inline text. Flex
             * parents (e.g. button slots) should zero vertical-align. */
            width: 1em;
            height: 1em;
            line-height: 1;
            vertical-align: -0.125em;
        }

        svg {
            display: block;
            width: 100%;
            height: 100%;
            fill: currentColor;
            /* Allow intentional path overhang past the icon canvas. */
            overflow: visible;
        }
    `;
	}
	connectedCallback() {
		super.connectedCallback(), this.unsubscribeRegistry = ne(() => {
			this.requestUpdate();
		});
	}
	disconnectedCallback() {
		this.unsubscribeRegistry?.(), this.unsubscribeRegistry = null, super.disconnectedCallback();
	}
	render() {
		let t = y(this.icon || this.name);
		return t ? e`${C(d(t, { title: this.label }))}` : n;
	}
};
s([t()], gi.prototype, "icon", void 0), s([t()], gi.prototype, "name", void 0), s([t()], gi.prototype, "label", void 0), gi = s([r("pk-icon")], gi);
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/pk-state-panel-D9aZunDN.js
var _i = i`
    @layer pk-component {
        :host {
            display: flex;
            flex: 1 1 auto;
            box-sizing: border-box;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: var(--pk-state-panel-min-height, var(--_pk-state-panel-min-height));
            padding: var(--pk-state-panel-padding, var(--_pk-state-panel-padding));
            color: var(--pk-state-panel-body-color, var(--pk-color-gray-500));
            font-family: var(--pk-font-family);
            font-size: var(--_pk-state-panel-font-size);
            line-height: var(--pk-line-height);
            text-align: center;
            --pk-state-panel-accent: var(--pk-color-slate-500);
            --pk-state-panel-icon-background: color-mix(
                in srgb,
                var(--pk-color-slate-200) 55%,
                transparent
            );
            --_pk-state-panel-font-size: var(--pk-font-size-sm);
            --_pk-state-panel-title-size: var(--pk-font-size-base);
            --_pk-state-panel-content-width: 32rem;
            --_pk-state-panel-min-height: 11rem;
            --_pk-state-panel-padding: 2rem 0.875rem;
            --_pk-state-panel-icon-shell-size: 2.125rem;
            --_pk-state-panel-icon-size: 1.0625rem;
            --_pk-state-panel-icon-radius: var(--pk-radius-lg);
            --_pk-state-panel-icon-margin-bottom: 0.625rem;
            --_pk-state-panel-body-margin-top: 0.375rem;
            --_pk-state-panel-details-margin-top: 0.75rem;
            --_pk-state-panel-details-font-size: 0.75rem;
            --_pk-state-panel-details-content-margin-top: 0.625rem;
            --_pk-state-panel-details-max-height: 14rem;
            --_pk-state-panel-details-pre-padding: 0.625rem;
            --_pk-state-panel-details-pre-radius: var(--pk-radius-md);
            --_pk-state-panel-details-pre-font-size: 0.6875rem;
            --_pk-state-panel-copy-button-size: var(--pk-btn-height-xs);
            --_pk-state-panel-copy-icon-size: var(--pk-btn-icon-size-xs);
            --_pk-state-panel-copy-inset: 0.375rem;
            --_pk-state-panel-copy-radius: var(--pk-radius-md);
            --_pk-state-panel-copy-status-size: 0.6875rem;
            --_pk-state-panel-actions-gap: 0.4375rem;
            --_pk-state-panel-actions-margin-top: 1rem;
            --_pk-state-panel-action-height: var(--pk-btn-height-sm);
            --_pk-state-panel-action-font: var(--pk-btn-font-sm);
            --_pk-state-panel-action-padding-inline: var(--pk-btn-padding-inline-sm);
            --_pk-state-panel-action-icon-size: var(--pk-btn-icon-size-sm);
            --_pk-state-panel-action-icon-gap: var(--pk-btn-icon-gap-sm);
            --_pk-state-panel-action-caret-size: var(--pk-btn-caret-size-sm);
            --_pk-state-panel-action-radius: var(--pk-btn-radius-sm);
        }

        :host([size='sm']) {
            --_pk-state-panel-font-size: var(--pk-btn-font-xs);
            --_pk-state-panel-title-size: var(--pk-font-size-sm);
            --_pk-state-panel-content-width: 28rem;
            --_pk-state-panel-min-height: 8rem;
            --_pk-state-panel-padding: 1.25rem 0.75rem;
            --_pk-state-panel-icon-shell-size: 1.75rem;
            --_pk-state-panel-icon-size: 0.875rem;
            --_pk-state-panel-icon-radius: var(--pk-radius-md);
            --_pk-state-panel-icon-margin-bottom: 0.5rem;
            --_pk-state-panel-body-margin-top: 0.25rem;
            --_pk-state-panel-details-margin-top: 0.625rem;
            --_pk-state-panel-details-font-size: 0.6875rem;
            --_pk-state-panel-details-content-margin-top: 0.5rem;
            --_pk-state-panel-details-max-height: 10rem;
            --_pk-state-panel-details-pre-padding: 0.5rem;
            --_pk-state-panel-details-pre-radius: var(--pk-radius-sm);
            --_pk-state-panel-details-pre-font-size: 0.6875rem;
            --_pk-state-panel-copy-button-size: var(--pk-btn-height-xxs);
            --_pk-state-panel-copy-icon-size: var(--pk-btn-icon-size-xxs);
            --_pk-state-panel-copy-inset: 0.3125rem;
            --_pk-state-panel-copy-radius: var(--pk-radius-sm);
            --_pk-state-panel-copy-status-size: 0.6875rem;
            --_pk-state-panel-actions-gap: 0.375rem;
            --_pk-state-panel-actions-margin-top: 0.75rem;
            --_pk-state-panel-action-height: var(--pk-btn-height-xs);
            --_pk-state-panel-action-font: var(--pk-btn-font-xs);
            --_pk-state-panel-action-padding-inline: var(--pk-btn-padding-inline-xs);
            --_pk-state-panel-action-icon-size: var(--pk-btn-icon-size-xs);
            --_pk-state-panel-action-icon-gap: var(--pk-btn-icon-gap-xs);
            --_pk-state-panel-action-caret-size: var(--pk-btn-caret-size-xs);
            --_pk-state-panel-action-radius: var(--pk-btn-radius-xs);
        }

        :host([size='lg']) {
            --_pk-state-panel-font-size: var(--pk-font-size-base);
            --_pk-state-panel-title-size: 1rem;
            --_pk-state-panel-content-width: 35rem;
            --_pk-state-panel-min-height: 14rem;
            --_pk-state-panel-padding: 2.5rem 1rem;
            --_pk-state-panel-icon-shell-size: 2.5rem;
            --_pk-state-panel-icon-size: 1.25rem;
            --_pk-state-panel-icon-radius: 0.625rem;
            --_pk-state-panel-icon-margin-bottom: 0.75rem;
            --_pk-state-panel-body-margin-top: 0.5rem;
            --_pk-state-panel-details-margin-top: 1rem;
            --_pk-state-panel-details-font-size: 0.8125rem;
            --_pk-state-panel-details-content-margin-top: 0.75rem;
            --_pk-state-panel-details-max-height: 18rem;
            --_pk-state-panel-details-pre-padding: 0.75rem;
            --_pk-state-panel-details-pre-radius: var(--pk-radius-lg);
            --_pk-state-panel-details-pre-font-size: 0.75rem;
            --_pk-state-panel-copy-button-size: var(--pk-btn-height-sm);
            --_pk-state-panel-copy-icon-size: var(--pk-btn-icon-size-sm);
            --_pk-state-panel-copy-inset: 0.5rem;
            --_pk-state-panel-copy-radius: var(--pk-radius-lg);
            --_pk-state-panel-copy-status-size: 0.75rem;
            --_pk-state-panel-actions-gap: 0.5rem;
            --_pk-state-panel-actions-margin-top: 1.25rem;
            --_pk-state-panel-action-height: 2.125rem;
            --_pk-state-panel-action-font: var(--pk-font-size-base);
            --_pk-state-panel-action-padding-inline: 9px;
            --_pk-state-panel-action-icon-size: 14px;
            --_pk-state-panel-action-icon-gap: 6px;
            --_pk-state-panel-action-caret-size: 12px;
            --_pk-state-panel-action-radius: var(--pk-radius-lg);
        }

        :host([variant='info']) {
            --pk-state-panel-accent: var(--pk-color-sky-600);
            --pk-state-panel-icon-background: var(--pk-color-sky-50);
        }

        :host([variant='success']) {
            --pk-state-panel-accent: var(--pk-color-teal-600);
            --pk-state-panel-icon-background: var(--pk-color-teal-50);
        }

        :host([variant='warning']) {
            --pk-state-panel-accent: var(--pk-color-amber-600);
            --pk-state-panel-icon-background: var(--pk-color-amber-50);
        }

        :host([variant='error']) {
            --pk-state-panel-accent: var(--pk-color-rose-600);
            --pk-state-panel-icon-background: color-mix(
                in srgb,
                var(--pk-color-rose-500) 12%,
                transparent
            );
        }

        .panel {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: min(
                100%,
                var(--pk-state-panel-content-width, var(--_pk-state-panel-content-width))
            );
            min-width: 0;
        }

        .icon-shell {
            display: inline-flex;
            flex: 0 0 auto;
            align-items: center;
            justify-content: center;
            width: var(--pk-state-panel-icon-shell-size, var(--_pk-state-panel-icon-shell-size));
            height: var(--pk-state-panel-icon-shell-size, var(--_pk-state-panel-icon-shell-size));
            margin-bottom: var(--_pk-state-panel-icon-margin-bottom);
            border-radius: var(--pk-state-panel-icon-radius, var(--_pk-state-panel-icon-radius));
            background: var(--pk-state-panel-icon-background);
            color: var(--pk-state-panel-accent);
        }

        .icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: var(--pk-state-panel-icon-size, var(--_pk-state-panel-icon-size));
            height: var(--pk-state-panel-icon-size, var(--_pk-state-panel-icon-size));
            font-size: var(--pk-state-panel-icon-size, var(--_pk-state-panel-icon-size));
        }

        .icon svg,
        .icon pk-icon,
        .icon ::slotted(*) {
            display: block;
            width: 100%;
            height: 100%;
            fill: currentColor;
        }

        .title {
            margin: 0;
            color: var(--pk-state-panel-title-color, var(--pk-color-gray-900));
            font-size: var(--pk-state-panel-title-size, var(--_pk-state-panel-title-size));
            font-weight: 600;
            line-height: 1.4;
        }

        .title ::slotted(*) {
            margin: 0;
            color: inherit;
            font: inherit;
        }

        .body {
            max-width: 100%;
            margin-top: var(--_pk-state-panel-body-margin-top);
        }

        .body ::slotted(*) {
            margin-block: 0;
        }

        .details {
            width: 100%;
            margin-top: var(--_pk-state-panel-details-margin-top);
            color: var(--pk-state-panel-accent);
            font-size: var(--_pk-state-panel-details-font-size);
        }

        summary {
            width: fit-content;
            margin-inline: auto;
            border-radius: var(--pk-radius-sm);
            cursor: pointer;
        }

        summary:focus-visible,
        .copy:focus-visible {
            outline: none;
            box-shadow: var(--pk-shadow-focus);
        }

        .details-content {
            position: relative;
            margin-top: var(--_pk-state-panel-details-content-margin-top);
            color: var(--pk-color-gray-800);
            text-align: start;
        }

        .details-scroll {
            min-width: 0;
        }

        .details-content ::slotted(pre) {
            box-sizing: border-box;
            max-height: var(
                --pk-state-panel-details-max-height,
                var(--_pk-state-panel-details-max-height)
            );
            margin: 0;
            padding: var(--_pk-state-panel-details-pre-padding);
            overflow: auto;
            border: 1px solid var(--pk-color-gray-200);
            border-radius: var(--_pk-state-panel-details-pre-radius);
            background: var(--pk-color-white);
            color: var(--pk-color-gray-800);
            font:
                var(--_pk-state-panel-details-pre-font-size)/1.5 ui-monospace,
                SFMono-Regular,
                Consolas,
                'Liberation Mono',
                monospace;
            overflow-wrap: anywhere;
            user-select: text;
            white-space: pre-wrap;
        }

        :host([copyable]) .details-content ::slotted(pre) {
            max-height: none;
            padding-inline-end: calc(
                var(--_pk-state-panel-details-pre-padding) +
                    var(--_pk-state-panel-copy-button-size) +
                    var(--_pk-state-panel-copy-inset)
            );
            overflow: visible;
            border: 0;
            border-radius: 0;
            background: transparent;
        }

        :host([copyable]) .details-content {
            overflow: hidden;
            border: 1px solid var(--pk-color-gray-200);
            border-radius: var(--_pk-state-panel-details-pre-radius);
            background: var(--pk-color-white);
        }

        :host([copyable]) .details-scroll {
            position: relative;
            max-height: var(
                --pk-state-panel-details-max-height,
                var(--_pk-state-panel-details-max-height)
            );
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
            margin-block-start: var(--_pk-state-panel-copy-inset);
            margin-inline-end: var(--_pk-state-panel-copy-inset);
            pointer-events: auto;
            --pk-btn-height-default: var(--_pk-state-panel-copy-button-size);
            --pk-btn-icon-size-default: var(--_pk-state-panel-copy-icon-size);
            --pk-btn-radius-default: var(--_pk-state-panel-copy-radius);
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
            font-size: var(--_pk-state-panel-copy-status-size);
            text-align: start;
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

        .actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: var(--_pk-state-panel-actions-gap);
            margin-top: var(--_pk-state-panel-actions-margin-top);
        }

        .actions slot::slotted(pk-button) {
            --pk-btn-height-default: var(--_pk-state-panel-action-height);
            --pk-btn-font-default: var(--_pk-state-panel-action-font);
            --pk-btn-padding-inline-default: var(--_pk-state-panel-action-padding-inline);
            --pk-btn-icon-size-default: var(--_pk-state-panel-action-icon-size);
            --pk-btn-icon-gap-default: var(--_pk-state-panel-action-icon-gap);
            --pk-btn-caret-size-default: var(--_pk-state-panel-action-caret-size);
            --pk-btn-radius-default: var(--_pk-state-panel-action-radius);
        }
    }
`, vi = 3e3, yi = {
	empty: l.emptySet,
	info: l.circleInfo,
	success: l.circleCheck,
	warning: l.triangleExclamation,
	error: l.triangleExclamation
}, J = class extends T {
	constructor(...e) {
		super(...e), this.hasSlotController = new g(this, "title", "icon", "details", "actions"), this.variant = "empty", this.size = "default", this.heading = "", this.headingLevel = 2, this.icon = "", this.hideIcon = !1, this.announce = "off", this.detailsLabel = "Details", this.detailsOpen = !1, this.copyable = !1, this.copyLabel = "Copy details", this.copiedLabel = "Details copied.", this.copyErrorLabel = "Copy failed. Select the details and copy them manually.", this.copyStatus = "", this.copyFailed = !1;
	}
	static {
		this.styles = _i;
	}
	disconnectedCallback() {
		window.clearTimeout(this.copyStatusResetTimer), super.disconnectedCallback();
	}
	hasTitle() {
		return !!this.heading || this.hasSlotController.test("title");
	}
	get detailsValue() {
		return (this.detailsSlot?.assignedNodes({ flatten: !0 }) ?? []).map((e) => e.textContent ?? "").join("").trim();
	}
	resetCopyStatusLater() {
		window.clearTimeout(this.copyStatusResetTimer), this.copyStatusResetTimer = window.setTimeout(() => {
			this.copyStatus = "", this.copyFailed = !1;
		}, vi);
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
		if (!e) this.showCopyError(), this.dispatchEvent(new te());
		else if (this.copyButton) this.copyButton.value = e, await this.copyButton.copy();
		else try {
			await ee(e), this.showCopySuccess();
			let t = new x(e);
			this.dispatchEvent(t);
		} catch {
			this.showCopyError(), this.selectDetails(), this.dispatchEvent(new te());
		}
	}
	handleDetailsToggle(e) {
		this.detailsOpen = e.currentTarget.open;
	}
	renderIcon() {
		if (this.hideIcon) return n;
		let t;
		return t = this.hasSlotController.test("icon") ? e`<slot name="icon"></slot>` : this.icon ? e`<pk-icon .icon=${this.icon}></pk-icon>` : C(S(yi[this.variant] ?? yi.empty)), e`
            <span part="icon-shell" class="icon-shell" aria-hidden="true">
                <span part="icon" class="icon">${t}</span>
            </span>
        `;
	}
	render() {
		let t = this.announce === "assertive" ? "alert" : this.announce === "polite" ? "status" : n, r = this.announce === "off" ? n : this.announce, i = this.hasSlotController.test("details"), a = this.hasSlotController.test("actions");
		return e`
            <div
                part="base"
                class="panel"
                role=${t}
                aria-live=${r}
                aria-atomic=${this.announce === "off" ? n : "true"}
            >
                ${this.renderIcon()}

                ${this.hasTitle() ? e`
                        <div
                            part="title"
                            class="title"
                            role="heading"
                            aria-level=${this.headingLevel}
                        >
                            ${this.hasSlotController.test("title") ? e`<slot name="title"></slot>` : this.heading}
                        </div>
                    ` : n}

                <div part="body" class="body"><slot></slot></div>

                ${i ? e`
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

                ${a ? e`<div part="actions" class="actions"><slot name="actions"></slot></div>` : n}
            </div>
        `;
	}
};
s([t({ reflect: !0 })], J.prototype, "variant", void 0), s([t({ reflect: !0 })], J.prototype, "size", void 0), s([t()], J.prototype, "heading", void 0), s([t({
	type: Number,
	attribute: "heading-level"
})], J.prototype, "headingLevel", void 0), s([t()], J.prototype, "icon", void 0), s([t({
	type: Boolean,
	attribute: "hide-icon",
	reflect: !0
})], J.prototype, "hideIcon", void 0), s([t({ reflect: !0 })], J.prototype, "announce", void 0), s([t({ attribute: "details-label" })], J.prototype, "detailsLabel", void 0), s([t({
	type: Boolean,
	attribute: "details-open",
	reflect: !0
})], J.prototype, "detailsOpen", void 0), s([t({
	type: Boolean,
	reflect: !0
})], J.prototype, "copyable", void 0), s([t({ attribute: "copy-label" })], J.prototype, "copyLabel", void 0), s([t({ attribute: "copied-label" })], J.prototype, "copiedLabel", void 0), s([t({ attribute: "copy-error-label" })], J.prototype, "copyErrorLabel", void 0), s([E("slot[name=\"details\"]")], J.prototype, "detailsSlot", void 0), s([E("pk-copy-button.copy")], J.prototype, "copyButton", void 0), s([o()], J.prototype, "copyStatus", void 0), s([o()], J.prototype, "copyFailed", void 0), J = s([r("pk-state-panel")], J);
// ../../../plugin-kit/plugin-kit/node_modules/lit-html/directives/repeat.js
var bi = (e, t, n) => {
	let r = /* @__PURE__ */ new Map();
	for (let i = t; i <= n; i++) r.set(e[i], i);
	return r;
}, xi = b(class extends h {
	constructor(e) {
		if (super(e), e.type !== m.CHILD) throw Error("repeat() can only be used in text expressions");
	}
	dt(e, t, n) {
		let r;
		n === void 0 ? n = t : t !== void 0 && (r = t);
		let i = [], a = [], o = 0;
		for (let t of e) i[o] = r ? r(t, o) : o, a[o] = n(t, o), o++;
		return {
			values: a,
			keys: i
		};
	}
	render(e, t, n) {
		return this.dt(e, t, n).values;
	}
	update(e, [t, n, r]) {
		let i = at(e), { values: a, keys: o } = this.dt(t, n, r);
		if (!Array.isArray(i)) return this.ut = o, a;
		let s = this.ut ??= [], c = [], l, u, d = 0, f = i.length - 1, p = 0, m = a.length - 1;
		for (; d <= f && p <= m;) if (i[d] === null) d++;
		else if (i[f] === null) f--;
		else if (s[d] === o[p]) c[p] = nt(i[d], a[p]), d++, p++;
		else if (s[f] === o[m]) c[m] = nt(i[f], a[m]), f--, m--;
		else if (s[d] === o[m]) c[m] = nt(i[d], a[m]), tt(e, c[m + 1], i[d]), d++, m--;
		else if (s[f] === o[p]) c[p] = nt(i[f], a[p]), tt(e, i[d], i[f]), f--, p++;
		else if (l === void 0 && (l = bi(o, p, m), u = bi(s, d, f)), l.has(s[d])) {
			if (l.has(s[f])) {
				let t = u.get(o[p]), n = t === void 0 ? null : i[t];
				if (n === null) {
					let t = tt(e, i[d]);
					nt(t, a[p]), c[p] = t;
				} else c[p] = nt(n, a[p]), tt(e, i[d], n), i[t] = null;
				p++;
			} else ot(i[f]), f--;
		} else ot(i[d]), d++;
		for (; p <= m;) {
			let t = tt(e, c[m + 1]);
			nt(t, a[p]), c[p++] = t;
		}
		for (; d <= f;) {
			let e = i[d++];
			e !== null && ot(e);
		}
		return this.ut = o, it(e, c), v;
	}
}), Si = "important", Ci = " !" + Si, wi = b(class extends h {
	constructor(e) {
		if (super(e), e.type !== m.ATTRIBUTE || e.name !== "style" || e.strings?.length > 2) throw Error("The `styleMap` directive must be used in the `style` attribute and must be the only part in the attribute.");
	}
	render(e) {
		return Object.keys(e).reduce((t, n) => {
			let r = e[n];
			return r == null ? t : t + `${n = n.includes("-") ? n : n.replace(/(?:^(webkit|moz|ms|o)|)(?=[A-Z])/g, "-$&").toLowerCase()}:${r};`;
		}, "");
	}
	update(e, [t]) {
		let { style: n } = e.element;
		if (this.ft === void 0) return this.ft = new Set(Object.keys(t)), this.render(t);
		for (let e of this.ft) t[e] ?? (this.ft.delete(e), e.includes("-") ? n.removeProperty(e) : n[e] = null);
		for (let e in t) {
			let r = t[e];
			if (r != null) {
				this.ft.add(e);
				let t = typeof r == "string" && r.endsWith(Ci);
				e.includes("-") || t ? n.setProperty(e, t ? r.slice(0, -11) : r, t ? Si : "") : n[e] = r;
			}
		}
		return v;
	}
});
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/pk-data-table-Bua5q8A-.js
function Ti(e) {
	let t;
	switch (e.type) {
		case "text":
			t = [
				"contains",
				"equals",
				"not-equals",
				"not-contains",
				"starts-with",
				"ends-with",
				"empty",
				"not-empty"
			];
			break;
		case "number-range":
		case "date-range":
			t = [
				"between",
				"equals",
				"not-equals",
				"greater-than",
				"at-least",
				"less-than",
				"at-most",
				"empty",
				"not-empty"
			];
			break;
		case "multi-select":
			t = [
				"in",
				"not-in",
				"empty",
				"not-empty"
			];
			break;
		case "boolean":
			t = [
				"equals",
				"empty",
				"not-empty"
			];
			break;
		default: t = [
			"equals",
			"not-equals",
			"empty",
			"not-empty"
		];
	}
	let n = e.operators?.filter((e) => t.includes(e));
	return n?.length ? n : t;
}
function Ei(e, t) {
	let n = Ti(e);
	return t && n.includes(t) ? t : n[0];
}
var Di = (e) => e !== "empty" && e !== "not-empty", Oi = (e) => Array.isArray(e) ? e.every((e) => !e.trim()) : !e.trim();
function ki(e, t, n, r, i) {
	let a = Ei(e, r), o = t == null || t === "" || Array.isArray(t) && !t.length;
	if (a === "empty") return o;
	if (a === "not-empty") return !o;
	if (Oi(n)) return !0;
	if (e.type === "number-range" || e.type === "date-range") {
		if (o) return !1;
		let r = e.type === "date-range", i = r ? Date.parse(String(t)) : Number(t), [s = "", c = ""] = Array.isArray(n) ? n : [n], l = r ? Date.parse(s) : Number(s), u = r ? Date.parse(c) + 864e5 - 1 : Number(c);
		if (!Number.isFinite(i)) return !1;
		if (a === "between") return (!s || i >= l) && (!c || i <= u);
		if (!Number.isFinite(l)) return !1;
		let d = r ? l + 864e5 - 1 : l;
		return a === "equals" ? i >= l && i <= d : a === "not-equals" ? i < l || i > d : a === "greater-than" ? i > d : a === "at-least" ? i >= l : a === "less-than" ? i < l : i <= d;
	}
	let s = t == null ? "" : String(t), c = Array.isArray(n) ? n : [n];
	if (a === "in" || a === "not-in") {
		let e = c.includes(s);
		return a === "in" ? e : !e;
	}
	let l = e.type === "text" ? s.toLocaleLowerCase(i) : s, u = e.type === "text" ? String(n).toLocaleLowerCase(i) : String(n);
	switch (a) {
		case "equals": return l === u;
		case "not-equals": return l !== u;
		case "not-contains": return !l.includes(u);
		case "starts-with": return l.startsWith(u);
		case "ends-with": return l.endsWith(u);
		default: return l.includes(u);
	}
}
var Ai = {
	search: "Search",
	clearSearch: "Clear search",
	pagination: "{label} pagination",
	scrollColumns: "Scroll {label} columns",
	sortDirection: "Sort direction",
	filters: "Filters",
	filterResults: "Filter results",
	addFilter: "Add a filter",
	allFiltersAdded: "All filters added",
	filterMatch: "Match all of the following conditions:",
	filterType: "Filter type",
	filterOperator: "{label}: Operator",
	removeFilter: "Remove {label} filter",
	applyFilters: "Apply",
	cancelFilters: "Cancel",
	filterRangeError: "{label}: the end must be on or after the start.",
	filterValueError: "{label}: enter a valid value.",
	filterContains: "contains",
	filterNotContains: "does not contain",
	filterEquals: "equals",
	filterNotEquals: "does not equal",
	filterStartsWith: "begins with",
	filterEndsWith: "ends with",
	filterIn: "is one of",
	filterNotIn: "is not one of",
	filterBetween: "is between",
	filterGreaterThan: "is greater than",
	filterAtLeast: "is at least",
	filterLessThan: "is less than",
	filterAtMost: "is at most",
	filterAfter: "is after",
	filterOnOrAfter: "is on or after",
	filterBefore: "is before",
	filterOnOrBefore: "is on or before",
	filterEmpty: "is empty",
	filterNotEmpty: "has a value",
	columns: "Table Columns",
	view: "View",
	sortBy: "Sort by",
	defaultOrder: "Default order",
	ascending: "Ascending",
	descending: "Descending",
	defaults: "Use defaults",
	close: "Close",
	reorder: "Reorder {label}",
	reorderHelp: "Drag to reorder, or use the Up and Down arrow keys.",
	reordered: "{label} moved to position {position} of {count}.",
	export: "Export",
	exporting: "Exporting…",
	clear: "Clear filters",
	all: "All",
	yes: "Yes",
	no: "No",
	from: "From",
	to: "To",
	previous: "Previous",
	next: "Next",
	empty: "No records yet",
	noResults: "No matching records",
	loading: "Loading…",
	updating: "Updating…",
	error: "Unable to load records",
	retry: "Retry",
	selectPage: "Select this page",
	selectRow: "Select {id}",
	selected: "{count} selected",
	range: "{start}–{end} of {total}",
	unknownRange: "{start}–{end}",
	actions: "Actions for {id}",
	editAction: "Edit",
	moveAction: "Move",
	deleteAction: "Delete",
	rowAction: "{label}: {id}",
	copyHandle: "Copy {label}",
	copiedHandle: "Copied {label}",
	rowOrder: "Row order",
	reorderRow: "Reorder {label}",
	reorderRowHelp: "Drag to reorder, or use Up and Down. Home moves to the start of this page; End moves to the end. Escape cancels a drag.",
	reorderRowsFiltered: "Clear search and filters to reorder rows.",
	rowReordered: "{label} moved to position {position} of {count} on this page.",
	rowReorderCancelled: "Row move cancelled.",
	moveUp: "Move {label} earlier",
	moveDown: "Move {label} later",
	sort: "Sort by {label}"
}, ji = () => ({
	search: "",
	sort: [],
	filters: {},
	pageIndex: 0,
	pageSize: 25
}), Mi = (e, t) => `cell-${typeof e}-${encodeURIComponent(String(e))}-${encodeURIComponent(t)}`, Ni = (e, t) => t.value ? t.value(e) : e[t.id];
function Y(e) {
	return e == null ? "" : Array.isArray(e) ? e.join(", ") : String(e);
}
function Pi(e, t, n, r, i) {
	let a = r.search.trim().toLocaleLowerCase(i), o = e.filter((e) => a && !t.some((t) => t.searchable !== !1 && Y(Ni(e, t)).toLocaleLowerCase(i).includes(a)) ? !1 : n.every((t) => {
		let n = r.filters[t.id];
		return n == null || ki(t, t.value ? t.value(e) : e[t.id], n, r.filterOperators?.[t.id], i);
	})), s = new Intl.Collator(i, {
		numeric: !0,
		sensitivity: "base"
	});
	o.sort((e, n) => {
		for (let i of r.sort) {
			let r = t.find((e) => e.id === i.id && e.sortable);
			if (!r) continue;
			let a = Ni(e, r), o = Ni(n, r), c = r.type === "date" ? (Date.parse(Y(a)) || 0) - (Date.parse(Y(o)) || 0) : r.type === "number" && Number.isFinite(Number(a)) && Number.isFinite(Number(o)) ? Number(a) - Number(o) : typeof a == "number" && typeof o == "number" ? a - o : s.compare(Y(a), Y(o));
			if (c) return i.direction === "asc" ? c : -c;
		}
		return 0;
	});
	let c = r.pageIndex * r.pageSize;
	return {
		rows: o.slice(c, c + r.pageSize),
		total: o.length,
		hasNext: c + r.pageSize < o.length
	};
}
var Fi = class {
	constructor() {
		this.generation = 0;
	}
	cancel() {
		this.generation++, this.abort?.abort();
	}
	async load(e, t) {
		this.cancel();
		let n = this.generation, r = this.abort = new AbortController();
		try {
			let i = await e(structuredClone(t), { signal: r.signal });
			return n === this.generation ? i : void 0;
		} catch (e) {
			if (n === this.generation) throw e;
			return;
		}
	}
}, Ii = {
	contains: "filterContains",
	"not-contains": "filterNotContains",
	equals: "filterEquals",
	"not-equals": "filterNotEquals",
	"starts-with": "filterStartsWith",
	"ends-with": "filterEndsWith",
	in: "filterIn",
	"not-in": "filterNotIn",
	between: "filterBetween",
	"greater-than": "filterGreaterThan",
	"at-least": "filterAtLeast",
	"less-than": "filterLessThan",
	"at-most": "filterAtMost",
	empty: "filterEmpty",
	"not-empty": "filterNotEmpty"
}, Li = class {
	constructor(e) {
		this.host = e, this.rules = [], this.error = "", this.opened = !1, this.closing = !1, this.preserveUnknown = !0, this.signature = "", this.position = () => {
			let e = this.host.toolbarRoot.querySelector(".search") ?? this.host.toolbarRoot.querySelector(".toolbar"), t = this.dialog;
			if (!e || !t) return;
			let n = e.getBoundingClientRect(), r = this.host.ownerDocument.defaultView, i = n.width >= 450 && n.bottom >= 0 && r.innerHeight - n.bottom >= 240;
			t.toggleAttribute("data-anchored", i), t.style.setProperty("--filter-left", `${Math.max(8, Math.min(n.left + 1, r.innerWidth - n.width - 8))}px`), t.style.setProperty("--filter-top", `${n.bottom}px`), t.style.setProperty("--filter-width", `${n.width - 2}px`), t.style.setProperty("--filter-max-height", `${Math.max(200, r.innerHeight - n.bottom - 12)}px`);
		}, e.addController(this);
	}
	text(e, t = {}) {
		return (this.host.labels[e] ?? Ai[e]).replace(/\{(\w+)\}/g, (e, n) => String(t[n] ?? e));
	}
	get dialog() {
		return this.host.renderRoot.querySelector(".filter-dialog");
	}
	get trigger() {
		return this.host.toolbarRoot.querySelector(".filter-trigger");
	}
	get available() {
		return this.host.filters.filter((e) => !this.rules.some((t) => t.id === e.id));
	}
	get querySignature() {
		return JSON.stringify([
			this.host.query.filters,
			this.host.query.filterOperators,
			this.host.filters
		]);
	}
	hostUpdated() {
		this.opened && !this.closing && this.signature !== this.querySignature && (this.resetDraft(), this.host.requestUpdate());
	}
	hostDisconnected() {
		this.stopObserving(), this.pendingAdd = void 0, this.opened = !1, this.closing = !1;
	}
	resetDraft() {
		this.rules = this.host.filters.filter((e) => Object.hasOwn(this.host.query.filters, e.id)).map((e) => ({
			id: e.id,
			operator: Ei(e, this.host.query.filterOperators?.[e.id]),
			value: structuredClone(this.host.query.filters[e.id])
		})), this.error = "", this.preserveUnknown = !0, this.signature = this.querySignature;
	}
	stopObserving() {
		this.positionFrame !== void 0 && cancelAnimationFrame(this.positionFrame), this.positionFrame = void 0, this.resizeObserver?.disconnect(), this.resizeObserver = void 0;
		let e = this.host.ownerDocument.defaultView;
		e?.removeEventListener("resize", this.position), e?.removeEventListener("scroll", this.position, !0);
	}
	async show() {
		if (this.opened || this.closing) return;
		this.resetDraft(), this.opened = !0, this.host.requestUpdate(), await this.host.updateComplete;
		let e = this.dialog;
		if (!e || !this.host.isConnected) return;
		await e.updateComplete, this.position(), this.resizeObserver = new ResizeObserver(() => {
			this.positionFrame !== void 0 && cancelAnimationFrame(this.positionFrame), this.positionFrame = requestAnimationFrame(() => {
				this.positionFrame = void 0, this.position();
			});
		});
		let t = this.host.toolbarRoot.querySelector(".search") ?? this.host.toolbarRoot.querySelector(".toolbar");
		t && this.resizeObserver.observe(t), this.host.ownerDocument.defaultView?.addEventListener("resize", this.position), this.host.ownerDocument.defaultView?.addEventListener("scroll", this.position, !0), await e.show(), this.focusRule(this.rules[0]?.id);
	}
	focusRule(e) {
		((e ? this.host.renderRoot.querySelector(`.filter-rule[data-id="${CSS.escape(e)}"] .filter-type`) : this.host.renderRoot.querySelector(".add-filter")) ?? this.host.renderRoot.querySelector(".filter-apply"))?.focus({ preventScroll: !0 });
	}
	afterHide(e) {
		e.target === this.dialog && (this.stopObserving(), this.opened = !1, this.closing = !1, this.error = "", this.host.requestUpdate(), setTimeout(() => {
			!this.opened && this.host.isConnected && this.trigger?.focus({ preventScroll: !0 });
		}, 0));
	}
	async close() {
		this.closing || (this.closing = !0, await this.dialog?.hide());
	}
	change(e, t) {
		this.rules = this.rules.map((n) => n.id === e ? {
			...n,
			...t
		} : n), this.error = "", this.host.requestUpdate();
	}
	async add(e) {
		let t = this.available.find((t) => t.id === e);
		t && (this.rules = [...this.rules, {
			id: e,
			operator: Ei(t),
			value: ""
		}], this.host.requestUpdate(), await this.host.updateComplete, this.focusRule(e));
	}
	async remove(e) {
		let t = this.rules.findIndex((t) => t.id === e);
		this.rules = this.rules.filter((t) => t.id !== e), this.error = "", this.host.requestUpdate(), await this.host.updateComplete, this.focusRule(this.rules[t]?.id ?? this.rules[t - 1]?.id);
	}
	clearDraft() {
		this.rules = [], this.preserveUnknown = !1, this.error = "", this.host.requestUpdate(), this.host.updateComplete.then(() => this.focusRule());
	}
	operatorLabel(e, t) {
		if (e.type === "date-range") {
			let e = {
				"greater-than": "filterAfter",
				"at-least": "filterOnOrAfter",
				"less-than": "filterBefore",
				"at-most": "filterOnOrBefore"
			};
			if (e[t]) return this.text(e[t]);
		}
		return this.text(Ii[t]);
	}
	async apply() {
		if (this.closing) return;
		let e = this.preserveUnknown ? { ...this.host.query.filters } : {}, t = this.preserveUnknown ? { ...this.host.query.filterOperators } : {};
		for (let n of this.host.filters) delete e[n.id], delete t[n.id];
		for (let n of this.rules) {
			let r = this.host.filters.find((e) => e.id === n.id);
			if (r && !(Di(n.operator) && Oi(n.value))) {
				if (Di(n.operator) && (r.type === "number-range" || r.type === "date-range")) {
					let e = (Array.isArray(n.value) ? n.value : [n.value]).map((e) => e ? r.type === "date-range" ? Date.parse(e) : Number(e) : void 0);
					if (e.some((e) => e !== void 0 && !Number.isFinite(e)) ? this.error = this.text("filterValueError", { label: r.label }) : n.operator === "between" && e[0] != null && e[1] != null && e[0] > e[1] && (this.error = this.text("filterRangeError", { label: r.label })), this.error) {
						this.host.requestUpdate(), await this.host.updateComplete, this.focusRule(n.id);
						return;
					}
				}
				e[n.id] = Di(n.operator) ? structuredClone(n.value) : "", n.operator !== Ei(r) && (t[n.id] = n.operator);
			}
		}
		let n = JSON.stringify([e, t]) !== JSON.stringify([this.host.query.filters, this.host.query.filterOperators ?? {}]);
		this.closing = !0, n && this.host.setQuery({
			filters: e,
			filterOperators: t
		}), await this.dialog?.hide();
	}
	renderValue(t, r) {
		if (!Di(r.operator)) return n;
		if (t.type === "select" || t.type === "multi-select" || t.type === "boolean") {
			let n = t.type === "boolean" ? [{
				value: "true",
				label: this.text("yes")
			}, {
				value: "false",
				label: this.text("no")
			}] : t.options ?? [];
			return e`<pk-select
                class="filter-value"
                width="full"
                aria-label=${t.label}
                .placeholder=${this.text("all")}
                .multiple=${t.type === "multi-select"}
                .value=${Array.isArray(r.value) ? "" : r.value}
                .values=${Array.isArray(r.value) ? r.value : r.value ? [r.value] : []}
                @change=${(e) => {
				let n = e.currentTarget;
				this.change(r.id, { value: t.type === "multi-select" ? [...n.values] : n.value });
			}}
                >${n.map((t) => e`<pk-option .value=${t.value}>${t.label}</pk-option>`)}</pk-select
            >`;
		}
		let i = r.operator === "between", a = i ? Array.isArray(r.value) ? r.value : [r.value, ""] : [Array.isArray(r.value) ? r.value[0] ?? "" : r.value];
		return e`<div class="filter-values">
            ${a.map((n, o) => e`<pk-input
                        class="filter-input"
                        type=${t.type === "date-range" ? "date" : t.type === "number-range" ? "number" : "text"}
                        .label=${i ? `${t.label}: ${this.text(o ? "to" : "from")}` : t.label}
                        .placeholder=${i ? this.text(o ? "to" : "from") : ""}
                        .value=${n}
                        @input=${(e) => {
			let t = e.currentTarget.value, n = [...a];
			n[o] = t, this.change(r.id, { value: i ? n : t });
		}}
                    ></pk-input>`)}
        </div>`;
	}
	renderTrigger(t) {
		if (!this.host.filters.length && !this.host.showFilters) return n;
		let r = Object.keys(this.host.query.filters).length + (this.host.query.criteria && Object.keys(this.host.query.criteria).length ? 1 : 0);
		return e`<pk-button
            class="filter-trigger"
            slot=${t ?? n}
            variant="transparent"
            size="sm"
            ?data-active=${this.opened || r > 0}
            aria-label=${`${this.text("filters")}${r ? ` (${r})` : ""}`}
            aria-haspopup="dialog"
            aria-expanded=${this.opened ? "true" : "false"}
            @click=${() => this.show()}
            ><svg
                class="direction-icon"
                aria-hidden="true"
                viewBox="0 0 24 24"
                fill="currentColor"
            >
                <path
                    d="M3 4h18a1 1 0 0 1 .8 1.6L15 14v6a1 1 0 0 1-1.5.86l-4-2.4A1 1 0 0 1 9 17.6V14L2.2 5.6A1 1 0 0 1 3 4Z"
                /></svg
            >${r ? e`<span>${r}</span>` : n}</pk-button
        >`;
	}
	renderDialog() {
		return e`<pk-dialog
            class="filter-dialog"
            .label=${this.text("filterResults")}
            without-header
            @pk-after-hide=${(e) => this.afterHide(e)}
            @keydown=${(e) => {
			e.key === "Enter" && e.composedPath().some((e) => e.localName === "pk-input") && !e.composedPath().some((e) => e.localName === "pk-select") && (e.preventDefault(), this.apply());
		}}
            ><div class="filter-builder" part="filter-builder">
                ${this.rules.length ? e`<p class="filter-intro">${this.text("filterMatch")}</p>` : n}
                ${xi(this.rules, (e) => e.id, (t, r) => {
			let i = this.host.filters.find((e) => e.id === t.id);
			return i ? e`<div
                            class="filter-rule"
                            part="filter-rule"
                            data-id=${t.id}
                            role="group"
                            aria-label=${i.label}
                        >
                            <pk-select
                                class="filter-type"
                                aria-label=${`${i.label}: ${this.text("filterType")}`}
                                width="full"
                                .value=${t.id}
                                @change=${(e) => {
				this.pendingType = {
					id: t.id,
					nextId: e.currentTarget.value
				};
			}}
                                @pk-after-hide=${(e) => {
				if (e.target !== e.currentTarget || this.pendingType?.id !== t.id) return;
				let n = this.pendingType.nextId;
				this.pendingType = void 0;
				let r = this.host.filters.find((e) => e.id === n);
				r && n !== t.id && (this.change(t.id, {
					id: n,
					operator: Ei(r),
					value: ""
				}), this.host.updateComplete.then(() => this.focusRule(n)));
			}}
                            >
                                ${[i, ...this.available].map((t) => e`<pk-option .value=${t.id}>${t.label}</pk-option>`)}
                            </pk-select>
                            <pk-select
                                class="filter-operator"
                                width="full"
                                .value=${t.operator}
                                aria-label=${this.text("filterOperator", { label: i.label })}
                                @change=${(e) => {
				let n = e.currentTarget.value;
				this.change(t.id, {
					operator: n,
					value: n === "between" ? Array.isArray(t.value) ? t.value : [t.value, ""] : i.type === "multi-select" ? t.value : Array.isArray(t.value) ? t.value[0] ?? "" : t.value
				});
			}}
                            >
                                ${Ti(i).map((t) => e`<pk-option .value=${t}>${this.operatorLabel(i, t)}</pk-option>`)}
                            </pk-select>
                            ${this.renderValue(i, t)}
                            <pk-button
                                class="filter-remove"
                                variant="transparent"
                                size="sm"
                                aria-label=${this.text("removeFilter", { label: i.label })}
                                @click=${() => this.remove(t.id)}
                                ><span class="icon" aria-hidden="true"
                                    >${C(S(l.xmark))}</span
                                ></pk-button
                            >
                        </div>` : n;
		})}
                ${this.host.filters.length ? e`<div class="filter-add">
                              <pk-select
                                  class="add-filter"
                                  aria-label=${this.text("addFilter")}
                                  .placeholder=${this.available.length ? this.text("addFilter") : this.text("allFiltersAdded")}
                                  .disabled=${!this.available.length}
                                  .value=${""}
                                  @change=${(e) => {
			let t = e.currentTarget, n = t.value;
			t.value = "", this.pendingAdd = n;
		}}
                                  @pk-after-hide=${(e) => {
			if (e.target !== e.currentTarget || !this.pendingAdd) return;
			let t = this.pendingAdd;
			this.pendingAdd = void 0, this.add(t);
		}}
                                  >${this.available.map((t) => e`<pk-option .value=${t.id}>${t.label}</pk-option>`)}</pk-select
                              >
                          </div>` : n}
                <slot name="filters"></slot>
                ${this.error ? e`<p class="filter-error" role="alert">${this.error}</p>` : n}
            </div>
            <div slot="footer" class="filter-footer" part="filter-footer">
                <pk-button
                    variant="transparent"
                    .disabled=${!this.rules.length && !Object.keys(this.host.query.filters).length}
                    @click=${() => this.clearDraft()}
                    >${this.text("clear")}</pk-button
                >
                <span class="filter-footer-spacer"></span>
                <pk-button @click=${() => this.close()}
                    >${this.text("cancelFilters")}</pk-button
                >
                <pk-button
                    class="filter-apply"
                    variant="secondary"
                    @click=${() => this.apply()}
                    >${this.text("applyFilters")}</pk-button
                >
            </div></pk-dialog
        >`;
	}
}, Ri = class {
	constructor(e, t, n) {
		this.enabled = e, this.commit = t, this.cancelled = n;
	}
	get active() {
		return !!this.session;
	}
	start(e) {
		if (!this.enabled() || this.session || e.button !== 0 || !e.isPrimary) return;
		let t = e.currentTarget, n = t.closest("tr"), r = n?.parentElement;
		if (!n || !(r instanceof HTMLTableSectionElement)) return;
		let i = r.getBoundingClientRect(), a = [...r.rows].map((e) => {
			let t = e.getBoundingClientRect();
			return {
				element: e,
				top: t.top - i.top,
				height: t.height
			};
		}), o = a.findIndex((e) => e.element === n);
		if (o < 0 || a.length < 2) return;
		e.preventDefault(), t.focus({ preventScroll: !0 });
		let s = (e) => {
			let t = this.session;
			t && e.pointerId === t.pointerId && (e.preventDefault(), t.pointerY = e.clientY, !(!t.moved && Math.abs(e.clientY - t.startY) < 4) && (t.moved = !0, r.setAttribute("data-row-dragging", ""), n.setAttribute("data-dragging", ""), this.preview()));
		}, c = (e) => {
			let t = this.session;
			if (!t || e.pointerId !== t.pointerId) return;
			let n = e.type === "pointerup" && t.moved && this.enabled();
			this.cancel(!1), n && this.commit(t.fromIndex, t.toIndex);
		}, l = (e) => {
			e.key === "Escape" && (e.preventDefault(), e.stopPropagation(), this.cancel());
		}, u = () => this.cancel(), d = t.ownerDocument, f = d.defaultView;
		t.addEventListener("pointermove", s), t.addEventListener("pointerup", c), t.addEventListener("pointercancel", u), t.addEventListener("lostpointercapture", u), d.addEventListener("keydown", l, !0), f.addEventListener("blur", u), f.addEventListener("resize", u), this.session = {
			handle: t,
			body: r,
			width: i.width,
			rows: a,
			fromIndex: o,
			toIndex: o,
			pointerId: e.pointerId,
			startY: e.clientY,
			pointerY: e.clientY,
			grabOffset: e.clientY - n.getBoundingClientRect().top,
			moved: !1,
			scrollParents: this.scrollParents(r),
			cleanup: () => {
				t.removeEventListener("pointermove", s), t.removeEventListener("pointerup", c), t.removeEventListener("pointercancel", u), t.removeEventListener("lostpointercapture", u), d.removeEventListener("keydown", l, !0), f.removeEventListener("blur", u), f.removeEventListener("resize", u);
			}
		}, t.setPointerCapture(e.pointerId);
	}
	cancel(e = !0) {
		let t = this.session;
		if (this.session = void 0, this.frame !== void 0 && cancelAnimationFrame(this.frame), this.frame = void 0, t) {
			t.cleanup(), t.body.removeAttribute("data-row-dragging");
			for (let e of t.rows) e.element.style.removeProperty("transform"), e.element.removeAttribute("data-dragging");
			t.handle.hasPointerCapture(t.pointerId) && t.handle.releasePointerCapture(t.pointerId), e && t.moved && this.cancelled();
		}
	}
	checkGeometry() {
		let e = this.session;
		e && (Math.abs(e.body.getBoundingClientRect().width - e.width) > .5 || e.rows.some((e) => Math.abs(e.element.getBoundingClientRect().height - e.height) > .5)) && this.cancel();
	}
	preview() {
		let e = this.session;
		if (!e?.moved) return;
		if (!this.enabled()) {
			this.cancel();
			return;
		}
		let { rows: t, fromIndex: n, body: r } = e, i = t[n], a = t.at(-1), o = e.pointerY - e.grabOffset - r.getBoundingClientRect().top, s = Math.max(0, Math.min(o, a.top + a.height - i.height)), c = o + i.height / 2;
		e.toIndex = t.filter((e, t) => t !== n && c > e.top + e.height / 2).length;
		for (let [r, a] of t.entries()) {
			let t = r === n ? s - a.top : r > n && r <= e.toIndex ? -i.height : r < n && r >= e.toIndex ? i.height : 0;
			a.element.style.transform = `translateY(${t}px)`;
		}
		this.frame !== void 0 && cancelAnimationFrame(this.frame), this.frame = requestAnimationFrame(() => {
			this.frame = void 0, this.session === e && (this.autoScroll(e), this.preview());
		});
	}
	scrollParents(e) {
		let t = [], n = e.ownerDocument, r = e;
		for (; r;) {
			r instanceof HTMLElement && /(auto|scroll)/.test(getComputedStyle(r).overflowY) && r.scrollHeight > r.clientHeight && t.push(r);
			let e = r.getRootNode();
			r = r.parentElement ?? (e instanceof ShadowRoot ? e.host : null);
		}
		return n.scrollingElement instanceof HTMLElement && !t.includes(n.scrollingElement) && t.push(n.scrollingElement), t;
	}
	autoScroll(e) {
		let t = e.handle.ownerDocument.defaultView;
		for (let n of e.scrollParents) {
			let r = n === n.ownerDocument.scrollingElement, i = n.getBoundingClientRect(), a = r ? 0 : Math.max(0, i.top), o = r ? t.innerHeight : Math.min(t.innerHeight, i.bottom), s = e.pointerY < a + 32 ? -10 : e.pointerY > o - 32 ? 10 : 0;
			if (s && (s < 0 ? n.scrollTop > 0 : n.scrollTop + n.clientHeight < n.scrollHeight)) {
				n.scrollTop += s;
				break;
			}
		}
	}
}, zi = /* @__PURE__ */ new WeakMap(), Bi = (e) => typeof e == "object" && !!e && !Array.isArray(e);
function Vi(e, t, n) {
	let r = Bi(e) ? e : n, i = t.map((e) => e.id), a = (e, t) => [...new Set((Array.isArray(e) ? e : t).filter((e) => typeof e == "string" && i.includes(e)))], o = a(r.hiddenColumns, n.hiddenColumns).filter((e) => t.find((t) => t.id === e)?.hideable !== !1);
	return i.length && o.length === i.length && o.splice(o.indexOf(i[0]), 1), {
		hiddenColumns: i.filter((e) => o.includes(e)),
		columnOrder: [.../* @__PURE__ */ new Set([...a(r.columnOrder, n.columnOrder), ...i])]
	};
}
function Hi(e, t) {
	if (!Array.isArray(e)) return [];
	let n = /* @__PURE__ */ new Set();
	return e.flatMap((e) => !Bi(e) || typeof e.id != "string" || n.has(e.id) || !t.some((t) => t.id === e.id && t.sortable) || e.direction !== "asc" && e.direction !== "desc" ? [] : (n.add(e.id), [{
		id: e.id,
		direction: e.direction
	}]));
}
function Ui(e, t) {
	zi.get(e)?.disconnect();
	let n = `pk:data-table:${JSON.stringify([
		t.version ?? 1,
		String(t.userId),
		t.key
	])}`, r;
	try {
		r = t.storage ?? e.ownerDocument.defaultView?.localStorage;
	} catch {}
	let i = () => {
		if (e.manualOrder) return [];
		let t = Hi(e.defaultSort, e.columns), n = e.columns.find((e) => e.sortable);
		return t.length ? t : n ? [{
			id: n.id,
			direction: "asc"
		}] : [];
	}, a = () => {
		if (e.manualOrder) return [];
		let t = Hi(e.query.sort, e.columns);
		return t.length ? t : i();
	};
	try {
		let i = r?.getItem(n), a = i ? JSON.parse(i) : void 0;
		if (Bi(a) && (e.view = Vi(a.view, e.columns, e.view), t.restoreSort !== !1 && !e.manualOrder)) {
			let t = Hi(a.sort, e.columns);
			t.length && (e.query = {
				...e.query,
				sort: t
			});
		}
	} catch {}
	let o = !0, s = !1, c = JSON.stringify(a()), l = () => {
		s = !1, c = JSON.stringify(a());
		try {
			r?.removeItem(n);
		} catch {}
	}, u = () => {
		if (s = !1, !o) return;
		let t = {
			view: Vi(e.view, e.columns, e.defaultView),
			sort: a()
		}, u = {
			view: Vi(e.defaultView, e.columns, e.defaultView),
			sort: i()
		};
		if (c = JSON.stringify(t.sort), JSON.stringify(t) === JSON.stringify(u)) l();
		else try {
			r?.setItem(n, JSON.stringify(t));
		} catch {}
	}, d = () => {
		!s && o && (s = !0, queueMicrotask(() => {
			s && u();
		}));
	}, f = (t) => {
		t.target === e && d();
	}, p = (t) => {
		t.target === e && JSON.stringify(a()) !== c && d();
	};
	e.addEventListener("pk-view-change", f), e.addEventListener("pk-query-change", p);
	let m = {
		storageKey: n,
		clear: l,
		disconnect() {
			s && u(), o = !1, e.removeEventListener("pk-view-change", f), e.removeEventListener("pk-query-change", p), zi.get(e) === m && zi.delete(e);
		}
	};
	return zi.set(e, m), m;
}
var X = class extends T {
	constructor(...e) {
		super(...e), this.filterController = new Li(this), this.label = "Records", this.rows = [], this.columns = [], this.filters = [], this.rowKey = "id", this.query = ji(), this.view = {
			hiddenColumns: [],
			columnOrder: []
		}, this.mode = "client", this.pagination = "offset", this.hasNext = !1, this.loading = !1, this.error = "", this.searchable = !0, this.showFilters = !1, this.columnControls = !0, this.hideToolbar = !1, this.hideFooter = !1, this.selectable = !1, this.manualOrder = !1, this.reorderDisabled = !1, this.selectedKeys = [], this.exportFormats = [], this.exporting = !1, this.defaultView = {
			hiddenColumns: [],
			columnOrder: []
		}, this.defaultSort = [], this.searchDelay = 250, this.size = "default", this.layout = "embedded", this.locale = "", this.timeZone = "", this.labels = {}, this.dragOffsets = {}, this.reorderAnnouncement = "", this.rowOrderAnnouncement = "", this.rowOrder = new Ri(() => this.canReorderRows, (e, t) => this.reorderRows(e, t), () => {
			this.rowOrderAnnouncement = this.text("rowReorderCancelled");
		}), this.tableScrollWidth = 0, this.hasHorizontalOverflow = !1, this.sortLoading = !1, this.viewClosePending = !1, this.requests = new Fi();
	}
	static {
		this.styles = ut;
	}
	connectedCallback() {
		if (super.connectedCallback(), this.id) {
			let e = `pk-data-table-toolbar[for="${CSS.escape(this.id)}"]`;
			(this.getRootNode().querySelector?.(e) ?? this.ownerDocument.querySelector(e))?.connectTable?.(this);
		}
		this.hasUpdated && this.observeLayout(), this.hasUpdated && this.mode === "remote" && this.dataSource && this.refresh();
	}
	disconnectedCallback() {
		this.rowOrder.cancel(!1), this.cancelColumnDrag(), this.resizeObserver?.disconnect(), this.layoutFrame !== void 0 && cancelAnimationFrame(this.layoutFrame), this.layoutFrame = void 0, this.resizeObserver = void 0, this.pagingFocus = void 0, clearTimeout(this.searchTimer), this.requests.cancel(), super.disconnectedCallback();
	}
	willUpdate(e) {
		this.rowOrder.active && this.rowOrder.cancel(), this.drag && (e.has("columns") || e.has("view")) && this.cancelColumnDrag(), (e.has("query") || e.has("rows") || e.has("selectable")) && (this.selectionAnchor = void 0, this.checkboxGesture = void 0), (this.manualOrder && this.query.sort.length || !this.query.sort.length && this.resolvedDefaultSort.length) && (this.query = {
			...this.query,
			sort: this.resolvedDefaultSort
		}, this.mode === "remote" && (this.loading = !0), this.emit("pk-query-change", { query: this.query }));
		let t = Number.isFinite(this.query.pageSize) ? Math.max(1, Math.floor(this.query.pageSize)) : 25, n = Number.isFinite(this.query.pageIndex) ? Math.max(0, Math.floor(this.query.pageIndex)) : 0;
		if ((t !== this.query.pageSize || n !== this.query.pageIndex) && (this.query = {
			...this.query,
			pageSize: t,
			pageIndex: n
		}), e.has("query")) {
			this.requests.cancel(), clearTimeout(this.searchTimer), this.searchDraft = void 0;
			let t = e.get("query");
			this.sortLoading = !this.manualOrder && !!(t?.sort.length && JSON.stringify(t.sort) !== JSON.stringify(this.query.sort)), t && JSON.stringify([
				t.criteria,
				t.search,
				t.filters,
				t.filterOperators,
				t.sort
			]) !== JSON.stringify([
				this.query.criteria,
				this.query.search,
				this.query.filters,
				this.query.filterOperators,
				this.query.sort
			]) && this.setSelection([]);
		}
		if (e.has("loading") && !this.loading && (this.sortLoading = !1), (e.has("dataSource") || e.has("mode")) && (this.requests.cancel(), this.loaded = void 0), e.has("mode") && this.mode === "client" && (this.loading = !1, this.error = ""), this.mode === "client" && (e.has("rows") || e.has("query"))) {
			let e = Pi(this.rows, this.columns, this.filters, this.query, this.locale || void 0), t = Math.max(0, Math.ceil((e.total ?? 0) / this.query.pageSize) - 1);
			this.query.pageIndex > t && (this.query = {
				...this.query,
				pageIndex: t
			}, this.emit("pk-query-change", { query: this.query }));
		}
	}
	attachToolbar(e) {
		this.toolbarHost = e, this.requestUpdate();
	}
	detachToolbar(e) {
		this.toolbarHost === e && (e.mount && a(n, e.mount, { host: this }), this.toolbarHost = void 0, this.requestUpdate());
	}
	get toolbarRoot() {
		return this.toolbarHost?.renderRoot ?? this.renderRoot;
	}
	updated(e) {
		this.toolbarHost?.mount && a(this.renderToolbar(), this.toolbarHost.mount, { host: this }), this.resizeObserver || this.observeLayout(), this.measureLayout(), this.restorePagingFocus(), (e.has("query") || e.has("dataSource") || e.has("mode")) && this.mode === "remote" && this.dataSource && this.refresh();
	}
	observeLayout() {
		this.resizeObserver?.disconnect(), this.resizeObserver = new ResizeObserver(() => {
			this.rowOrder.checkGeometry(), this.layoutFrame !== void 0 && cancelAnimationFrame(this.layoutFrame), this.layoutFrame = requestAnimationFrame(() => {
				this.layoutFrame = void 0, this.measureLayout();
			});
		});
		for (let e of this.renderRoot.querySelectorAll(".scroll, table, thead")) this.resizeObserver.observe(e);
	}
	measureLayout() {
		let e = this.renderRoot.querySelector(".scroll");
		if (!e) return;
		let t = this.renderRoot.querySelector("thead");
		e.style.setProperty("--_pk-data-table-heading-height", `${t?.offsetHeight ?? 0}px`), this.tableScrollWidth = e.scrollWidth, this.hasHorizontalOverflow = e.scrollWidth > e.clientWidth + 1;
		let n = this.renderRoot.querySelector(".scrollbar");
		n && (n.scrollLeft = e.scrollLeft);
	}
	syncHorizontalScroll(e, t) {
		let n = this.renderRoot.querySelector(t);
		n && (n.scrollLeft = e.currentTarget.scrollLeft);
	}
	async restorePagingFocus() {
		if (!this.pagingFocus || this.loading) return;
		let e = this.pagingFocus, t = [...this.renderRoot.querySelectorAll(".pager pk-button")];
		if (await Promise.all(t.map((e) => e.updateComplete)), this.pagingFocus !== e || this.loading || !this.isConnected) return;
		this.pagingFocus = void 0;
		let { direction: n, button: r } = e, i = this.ownerDocument.activeElement;
		for (; i?.shadowRoot?.activeElement;) i = i.shadowRoot.activeElement;
		if (i && i !== this.ownerDocument.body && i !== r && i !== r.shadowRoot?.activeElement) return;
		let a = t[+(n > 0)];
		((a && !a.disabled ? a : t.find((e) => !e.disabled)) ?? this.renderRoot.querySelector(".scroll"))?.focus({ preventScroll: !0 });
	}
	text(e, t = {}) {
		return (this.labels[e] ?? Ai[e]).replace(/\{(\w+)\}/g, (e, n) => String(t[n] ?? e));
	}
	emit(e, t = {}) {
		this.dispatchEvent(new CustomEvent(e, {
			detail: t,
			bubbles: !0,
			composed: !0
		}));
	}
	async refresh() {
		if (this.mode !== "remote" || !this.dataSource) {
			this.emit("pk-retry");
			return;
		}
		this.loading = !0, this.error = "";
		let e = this.manualOrder ? {
			...this.query,
			sort: []
		} : this.query;
		try {
			let t = await this.requests.load(this.dataSource, e);
			if (!t) return;
			if (!Array.isArray(t.rows)) throw TypeError("DataTable dataSource must return rows.");
			this.loaded = t, this.loading = !1, this.emit("pk-load", {
				result: t,
				query: e
			});
		} catch (e) {
			this.error = this.text("error"), this.loading = !1, this.emit("pk-load-error", { error: e });
		}
	}
	get resolvedDefaultSort() {
		if (this.manualOrder) return [];
		if (this.defaultSort.length) return this.defaultSort.map((e) => ({ ...e }));
		let e = this.columns.find((e) => e.sortable);
		return e ? [{
			id: e.id,
			direction: "asc"
		}] : [];
	}
	setQuery(e) {
		if (this.manualOrder && "sort" in e) {
			let { sort: t, ...n } = e;
			if (e = n, !Object.keys(e).length) return;
		}
		this.requests.cancel(), "sort" in e && !e.sort?.length && (e = {
			...e,
			sort: this.resolvedDefaultSort
		});
		let t = "search" in e || "sort" in e || "filters" in e || "filterOperators" in e || "criteria" in e, n = t || "pageSize" in e;
		this.query = {
			...this.query,
			...e,
			...n ? {
				pageIndex: 0,
				cursor: void 0
			} : {}
		}, t && (this.pagingFocus = void 0, this.setSelection([])), this.mode === "remote" && (this.loading = !0), this.emit("pk-query-change", { query: this.query });
	}
	setSelection(e) {
		e.length || (this.selectionAnchor = void 0), (this.selectedKeys.length !== 0 || e.length !== 0) && (this.selectedKeys = e, this.emit("pk-selection-change", { selectedKeys: e }));
	}
	canSelectRow(e) {
		return this.selectable && !this.loading && !this.error && this.isRowSelectable?.(e) !== !1;
	}
	isRowControl(e) {
		for (let t of e.composedPath()) {
			if (t === e.currentTarget) break;
			if (t instanceof Element && t.matches("a, button, input, select, textarea, label, summary, [contenteditable]:not([contenteditable=\"false\"]), [role=\"button\"], [role=\"link\"], [role=\"checkbox\"], [role=\"switch\"], [tabindex], [data-table-interactive]")) return !0;
		}
		return !1;
	}
	isSelectionCommand(e) {
		return !!(/Mac/.test(navigator.platform) ? e.metaKey : e.ctrlKey);
	}
	isSelectionClick(e) {
		return e.button === 0 && (!(e.metaKey || e.ctrlKey) || this.isSelectionCommand(e));
	}
	selectPage(e) {
		let t = this.result.rows.filter((e) => this.canSelectRow(e)).map((e) => this.key(e)), n = e ? t[0] : this.selectionAnchor;
		this.setSelection(e ? [.../* @__PURE__ */ new Set([...this.selectedKeys, ...t])] : this.selectedKeys.filter((e) => !t.includes(e))), this.selectionAnchor = n;
	}
	selectRow(e, t) {
		if (!this.canSelectRow(e)) return;
		let n = this.key(e), r = this.result.rows.map((e) => this.key(e)), i = this.selectionAnchor === void 0 ? -1 : r.indexOf(this.selectionAnchor), a = r.indexOf(n);
		if (t.shiftKey && i !== -1 && a !== -1) {
			let e = this.result.rows.slice(Math.min(i, a), Math.max(i, a) + 1).filter((e) => this.canSelectRow(e)).map((e) => this.key(e)), t = this.selectedKeys.filter((e) => !r.includes(e));
			this.setSelection([.../* @__PURE__ */ new Set([...t, ...e])]);
		} else this.isSelectionCommand(t) ? (this.selectionAnchor = n, this.setSelection([n])) : this.selectedKeys.includes(n) ? (this.selectionAnchor === n && (this.selectionAnchor = void 0), this.setSelection(this.selectedKeys.filter((e) => e !== n))) : (this.selectionAnchor = n, this.setSelection([...this.selectedKeys, n]));
	}
	clickRow(e, t) {
		this.isSelectionClick(e) && this.canSelectRow(t) && !this.isRowControl(e) && (this.selectRow(t, e), e.currentTarget.querySelector("pk-checkbox")?.focus({ preventScroll: !0 }));
	}
	clickRowCheckbox(e, t) {
		this.isSelectionClick(e) ? (e.shiftKey || this.isSelectionCommand(e)) && !(e.composedPath()[0] instanceof HTMLInputElement) ? (e.preventDefault(), this.checkboxGesture = void 0, this.selectRow(t, e), e.currentTarget.focus({ preventScroll: !0 })) : this.checkboxGesture = {
			key: this.key(t),
			shiftKey: e.shiftKey,
			metaKey: e.metaKey,
			ctrlKey: e.ctrlKey
		} : (e.preventDefault(), this.checkboxGesture = void 0);
	}
	changeRowCheckbox(e) {
		let t = this.checkboxGesture?.key === this.key(e) ? this.checkboxGesture : {};
		this.checkboxGesture = void 0, this.selectRow(e, t);
	}
	keyRow(e, t) {
		if (!this.canSelectRow(t) || e.defaultPrevented) return;
		let n = e.currentTarget, r = n.querySelector("pk-checkbox");
		if (!e.composedPath().includes(r) && e.target !== n) return;
		let i = this.isSelectionCommand(e), a = this.result.rows.filter((e) => this.canSelectRow(e));
		if (i && e.key.toLowerCase() === "a") {
			e.preventDefault(), this.selectPage(!0);
			return;
		}
		if (e.key === "Enter" || e.key === " ") {
			e.preventDefault(), !i && !e.shiftKey && this.selectRow(t, {});
			return;
		}
		if (![
			"ArrowUp",
			"ArrowDown",
			"Home",
			"End"
		].includes(e.key)) return;
		e.preventDefault();
		let o = a.findIndex((e) => this.key(e) === this.key(t)), s = e.key === "Home" || i && e.key === "ArrowUp", c = e.key === "End" || i && e.key === "ArrowDown", l = a[s ? 0 : c ? a.length - 1 : Math.max(0, Math.min(a.length - 1, o + (e.key === "ArrowUp" ? -1 : 1)))];
		if (!l) return;
		let u = this.result.rows.findIndex((e) => this.key(e) === this.key(l)), d = this.renderRoot.querySelectorAll("tbody tr")[u];
		if (d) {
			let e = this.layout === "index" ? this.renderRoot.querySelector(".footer-shell")?.offsetHeight ?? 0 : 0, t = this.renderRoot.querySelector(".header-shell"), n = this.layout === "index" ? t ? t.offsetHeight + (Number.parseFloat(getComputedStyle(t).top) || 0) + 2 : (Number.parseFloat(getComputedStyle(this).getPropertyValue("--pk-data-table-header-offset")) || 0) + 2 : 0;
			d.style.scrollMarginBlockStart = `${n}px`, d.style.scrollMarginBlockEnd = `${e}px`;
		}
		d?.querySelector("pk-checkbox")?.focus({ preventScroll: !0 }), d?.scrollIntoView({
			block: "nearest",
			inline: "nearest"
		});
	}
	accessibleRowLabel(e) {
		return (typeof this.rowLabel == "function" ? this.rowLabel(e) : this.rowLabel ? Y(e[this.rowLabel]) : "") || String(this.key(e));
	}
	key(e) {
		let t = typeof this.rowKey == "function" ? this.rowKey(e) : e[this.rowKey];
		if (typeof t != "string" && typeof t != "number") throw TypeError("DataTable rows require a stable string or number rowKey.");
		return t;
	}
	get result() {
		return this.mode === "client" ? Pi(this.rows, this.columns, this.filters, this.manualOrder ? {
			...this.query,
			sort: []
		} : this.query, this.locale || void 0) : this.dataSource ? this.loaded ?? { rows: [] } : {
			rows: this.rows,
			total: this.total,
			hasNext: this.hasNext,
			nextCursor: this.nextCursor,
			previousCursor: this.previousCursor
		};
	}
	get rowOrderFiltered() {
		return !!(this.query.search.trim() || this.searchDraft?.trim() || Object.keys(this.query.filters).length || Object.keys(this.query.criteria ?? {}).length);
	}
	get canReorderRows() {
		let e = this.mode === "client" ? Math.min(this.query.pageSize, this.rows.length - this.query.pageIndex * this.query.pageSize) : this.dataSource ? this.loaded?.rows.length ?? 0 : this.rows.length;
		return this.manualOrder && !this.reorderDisabled && !this.loading && !this.error && !this.rowOrderFiltered && e > 1;
	}
	reorderRows(e, t) {
		if (!this.canReorderRows || e === t) return;
		let n = [...this.result.rows];
		if (e < 0 || t < 0 || e >= n.length || t >= n.length) return;
		let r = n.map((e) => this.key(e)), [i] = n.splice(e, 1);
		n.splice(t, 0, i);
		let a = [...this.mode === "remote" && this.dataSource ? this.loaded.rows : this.rows], o;
		if (this.mode === "client") {
			let e = new Set(r), t = 0;
			o = this.rows.map((r) => e.has(this.key(r)) ? n[t++] : r), this.rows = o;
		} else this.dataSource ? (o = n, this.loaded = {
			...this.loaded,
			rows: o
		}) : (o = n, this.rows = o);
		this.selectionAnchor = void 0, this.rowOrderAnnouncement = this.text("rowReordered", {
			label: this.accessibleRowLabel(i),
			position: t + 1,
			count: n.length
		});
		let s = {
			rowKey: this.key(i),
			fromIndex: e,
			toIndex: t,
			orderedKeys: n.map((e) => this.key(e)),
			previousKeys: r,
			rows: [...o],
			previousRows: a,
			query: structuredClone(this.query)
		}, c = this.renderRoot.querySelectorAll(".row-handle")[e], l = this.renderRoot instanceof ShadowRoot && this.renderRoot.activeElement === c;
		this.emit("pk-row-reorder", s), (async () => {
			if (await this.updateComplete, !l || !this.isConnected || !c?.isConnected || this.loading || this.error || !this.manualOrder) return;
			let e = this.ownerDocument.activeElement;
			if (e !== this && e !== this.ownerDocument.body) return;
			let t = this.renderRoot.activeElement;
			t && t !== c || (c.focus({ preventScroll: !0 }), c.scrollIntoView({
				block: "nearest",
				inline: "nearest"
			}));
		})();
	}
	keyRowOrder(e, t) {
		if (e.altKey || e.ctrlKey || e.metaKey || ![
			"Home",
			"End",
			"ArrowUp",
			"ArrowDown"
		].includes(e.key) || (e.preventDefault(), e.stopPropagation(), this.rowOrder.active || !this.canReorderRows)) return;
		let n = this.result.rows, r = n.findIndex((e) => this.key(e) === this.key(t)), i = e.key === "Home" ? 0 : e.key === "End" ? n.length - 1 : e.key === "ArrowUp" ? Math.max(0, r - 1) : e.key === "ArrowDown" ? Math.min(n.length - 1, r + 1) : void 0;
		i !== void 0 && this.reorderRows(r, i);
	}
	get orderedColumns() {
		let e = this.view.columnOrder.map((e) => this.columns.find((t) => t.id === e)).filter((e) => !!e);
		return [.../* @__PURE__ */ new Set([...e, ...this.columns])];
	}
	setView(e) {
		this.view = e, this.emit("pk-view-change", { view: e });
	}
	toggleColumn(e) {
		let t = new Set(this.view.hiddenColumns);
		!t.has(e) && this.columns.filter((e) => e.hideable === !1 || !t.has(e.id)).length <= 1 || (t.has(e) ? t.delete(e) : t.add(e), this.setView({
			...this.view,
			hiddenColumns: [...t]
		}));
	}
	moveColumn(e, t) {
		let n = this.orderedColumns.map((e) => e.id), r = n.indexOf(e);
		r + t < 0 || r + t >= n.length || (n.splice(r, 1), n.splice(r + t, 0, e), this.reorderAnnouncement = this.text("reordered", {
			label: this.columns.find((t) => t.id === e)?.label ?? e,
			position: r + t + 1,
			count: n.length
		}), this.setView({
			...this.view,
			columnOrder: n
		}));
	}
	icon(t, r) {
		return e`<span
            class="icon"
            slot=${r ?? n}
            aria-hidden="true"
            >${C(S(t))}</span
        >`;
	}
	directionIcon(t) {
		return e`<svg
            class="direction-icon"
            viewBox="0 0 24 24"
            aria-hidden="true"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
            <path
                d=${t === "asc" ? "M5 20V4m-3 3 3-3 3 3" : "M5 4v16m-3-3 3 3 3-3"}
            />
            <path d="M12 5h9M12 10h6M12 15h3M12 20h1" />
        </svg>`;
	}
	startColumnDrag(e, t) {
		if (e.button !== 0) return;
		let n = this.toolbarRoot.querySelector(".column-list");
		if (!n) return;
		e.preventDefault();
		let r = e.currentTarget;
		r.focus();
		let i = n.getBoundingClientRect(), a = [...n.querySelectorAll(".column-option")].map((e) => {
			let t = e.getBoundingClientRect();
			return {
				id: e.dataset.column,
				top: t.top - i.top + n.scrollTop,
				height: t.height
			};
		});
		this.drag = {
			id: t,
			startY: e.clientY,
			pointerY: e.clientY,
			startScroll: n.scrollTop,
			moved: !1,
			targetIndex: a.findIndex((e) => e.id === t),
			pointerId: e.pointerId,
			handle: r,
			list: n,
			rows: a
		}, r.setPointerCapture(e.pointerId);
	}
	dragColumn(e) {
		this.drag && e.pointerId === this.drag.pointerId && (this.drag.pointerY = e.clientY, !(Math.abs(e.clientY - this.drag.startY) < 4 && !this.drag.moved) && (this.drag.moved = !0, this.previewColumnDrag()));
	}
	previewColumnDrag() {
		let e = this.drag;
		if (!e?.moved) return;
		let { list: t, rows: n } = e, r = t.getBoundingClientRect(), i = n.findIndex((t) => t.id === e.id), a = n[i], o = a.top + e.pointerY - e.startY + t.scrollTop - e.startScroll, s = Math.max(Math.max(n[0].top, t.scrollTop), Math.min(o, n.at(-1).top + n.at(-1).height - a.height, t.scrollTop + t.clientHeight - a.height)), c = s + a.height / 2;
		e.targetIndex = n.reduce((e, t, r) => Math.abs(c - t.top - t.height / 2) < Math.abs(c - n[e].top - n[e].height / 2) ? r : e, 0), this.dragOffsets = Object.fromEntries(n.map((t, n) => [t.id, n === i ? s - a.top : n > i && n <= e.targetIndex ? -a.height : n < i && n >= e.targetIndex ? a.height : 0]));
		let l = e.pointerY < r.top + 24 ? -8 : e.pointerY > r.bottom - 24 ? 8 : 0;
		this.dragFrame !== void 0 && cancelAnimationFrame(this.dragFrame), this.dragFrame = void 0, l && (l < 0 ? t.scrollTop > 0 : t.scrollTop + t.clientHeight < t.scrollHeight) && (this.dragFrame = requestAnimationFrame(() => {
			this.dragFrame = void 0, t.scrollTop += l, this.previewColumnDrag();
		}));
	}
	cancelColumnDrag() {
		let e = this.drag;
		this.drag = void 0, this.dragFrame !== void 0 && cancelAnimationFrame(this.dragFrame), this.dragFrame = void 0, this.dragOffsets = {}, e?.handle.hasPointerCapture(e.pointerId) && e.handle.releasePointerCapture(e.pointerId);
	}
	endColumnDrag(e, t) {
		let n = this.drag;
		n && e.pointerId === n.pointerId && (this.cancelColumnDrag(), t && n.moved && this.moveColumn(n.id, n.targetIndex - n.rows.findIndex((e) => e.id === n.id)));
	}
	finishViewChildren(e) {
		let t = e.currentTarget.closest("pk-popover");
		if (!t || e.target !== t) return;
		if (this.drag && e.detail.source === "escape") {
			e.preventDefault(), this.cancelColumnDrag();
			return;
		}
		this.cancelColumnDrag();
		let n = t.querySelector("pk-select");
		n?.open && (e.preventDefault(), !this.viewClosePending && (this.viewClosePending = !0, n.addEventListener("pk-after-hide", () => {
			this.viewClosePending = !1, t.closePopover(e.detail.source);
		}, { once: !0 }), n.hide()));
	}
	renderView() {
		let t = this.query.sort[0], r = this.manualOrder ? [] : this.columns.filter((e) => e.sortable), i = this.columns.filter((e) => e.hideable === !1 || !this.view.hiddenColumns.includes(e.id)).length;
		return e`<pk-popover
            class="view-popover"
            .label=${this.text("view")}
            @pk-hide=${this.finishViewChildren}
            placement="bottom-end"
            flush
        >
            <pk-button slot="trigger" with-caret
                >${this.icon(l.slidersOutline, "start")}${this.text("view")}</pk-button
            >
            <div class="view-grid">
                ${r.length ? e`<div class="view-label">
                                  ${this.text("sortBy")}
                              </div>
                              <div
                                  class="view-sort"
                                  role="group"
                                  aria-label=${this.text("sortBy")}
                              >
                                  <pk-select
                                      aria-label=${this.text("sortBy")}
                                      .value=${t?.id ?? ""}
                                      @pk-change=${(e) => {
			let n = e.detail.value;
			n && this.setQuery({ sort: [{
				id: n,
				direction: t?.direction ?? "asc"
			}] });
		}}
                                      >${r.map((t) => e`<pk-option value=${t.id}>${t.label}</pk-option>`)}</pk-select
                                  >
                                  <pk-button-group
                                      .label=${this.text("sortDirection")}
                                      >${["asc", "desc"].map((n) => e`<pk-button .disabled=${!t} variant=${t?.direction === n ? "secondary" : "default"} aria-label=${this.text(n === "asc" ? "ascending" : "descending")} aria-pressed=${t?.direction === n ? "true" : "false"} @click=${() => t && this.setQuery({ sort: [{
			id: t.id,
			direction: n
		}] })}>${this.directionIcon(n)}</pk-button>`)}</pk-button-group
                                  >
                              </div>` : n}
                <div class="view-label columns-label">
                    ${this.text("columns")}
                </div>
                <div
                    class="column-list"
                    role="group"
                    aria-label=${this.text("columns")}
                    ?data-reordering=${this.drag?.moved}
                >
                    <span id="reorder-help" class="sr-only"
                        >${this.text("reorderHelp")}</span
                    >
                    ${xi(this.orderedColumns, (e) => e.id, (t) => e`<div
                                class="column-option"
                                data-column=${t.id}
                                ?data-dragging=${this.drag?.moved && this.drag.id === t.id}
                                style=${wi({ transform: this.drag?.moved ? `translateY(${this.dragOffsets[t.id] ?? 0}px)` : void 0 })}
                            >
                                <button
                                    class="column-handle"
                                    type="button"
                                    aria-label=${this.text("reorder", { label: t.label })}
                                    aria-describedby="reorder-help"
                                    @pointerdown=${(e) => this.startColumnDrag(e, t.id)}
                                    @pointermove=${(e) => this.dragColumn(e)}
                                    @pointerup=${(e) => this.endColumnDrag(e, !0)}
                                    @pointercancel=${(e) => this.endColumnDrag(e, !1)}
                                    @lostpointercapture=${(e) => this.endColumnDrag(e, !1)}
                                    @keydown=${(e) => {
			this.drag && e.key === "Escape" ? (e.preventDefault(), e.stopPropagation(), this.cancelColumnDrag()) : this.drag || (e.key === "ArrowUp" || e.key === "ArrowDown") && (e.preventDefault(), this.moveColumn(t.id, e.key === "ArrowUp" ? -1 : 1));
		}}
                                >
                                    ${this.icon(l.gripMove)}
                                </button>
                                <pk-checkbox
                                    .checked=${t.hideable === !1 || !this.view.hiddenColumns.includes(t.id)}
                                    .disabled=${t.hideable === !1 || !this.view.hiddenColumns.includes(t.id) && i <= 1}
                                    @change=${() => this.toggleColumn(t.id)}
                                    >${t.label}</pk-checkbox
                                >
                            </div>`)}
                </div>
            </div>
            <div class="view-footer">
                <pk-button
                    variant="transparent"
                    @click=${() => {
			this.setView(structuredClone(this.defaultView)), this.setQuery({ sort: structuredClone(this.defaultSort) });
		}}
                    >${this.text("defaults")}</pk-button
                ><pk-button
                    @click=${(e) => e.currentTarget.closest("pk-popover")?.closePopover("close-button")}
                    >${this.text("close")}</pk-button
                >
            </div>
            <span role="status" class="sr-only"
                >${this.reorderAnnouncement}</span
            >
        </pk-popover>`;
	}
	renderExport() {
		return this.exportFormats.length ? e`<pk-dropdown-menu
            placement="top-end"
            @pk-select=${(e) => {
			if (this.loading || this.exporting || !this.exportFormats.some((t) => t.value === e.detail.value && !t.disabled)) return;
			let t = {
				format: e.detail.value,
				query: structuredClone(this.query),
				view: structuredClone(this.view),
				columnIds: this.orderedColumns.filter((e) => e.hideable === !1 || !this.view.hiddenColumns.includes(e.id)).map((e) => e.id),
				selectedKeys: [...this.selectedKeys]
			};
			this.emit("pk-export", t);
		}}
            ><pk-button
                slot="trigger"
                with-caret
                size="sm"
                .disabled=${this.loading || this.exporting}
                .loading=${this.exporting}
                aria-label=${this.text(this.exporting ? "exporting" : "export")}
                >${this.text(this.exporting ? "exporting" : "export")}</pk-button
            >${this.exportFormats.map((t) => e`<pk-dropdown-item value=${t.value} .disabled=${!!t.disabled}>${t.label}</pk-dropdown-item>`)}</pk-dropdown-menu
        >` : n;
	}
	sortColumn(e) {
		if (this.manualOrder) return;
		let t = this.query.sort[0];
		this.setQuery({ sort: [{
			id: e,
			direction: t?.id === e && t.direction === "asc" ? "desc" : "asc"
		}] });
	}
	inputSearch(e) {
		if (e.target !== e.currentTarget) return;
		let t = e.currentTarget.value;
		this.searchDraft = t, clearTimeout(this.searchTimer), t ? this.searchTimer = setTimeout(() => this.setQuery({ search: t }), this.searchDelay) : this.query.search && this.setQuery({ search: "" });
	}
	renderToolbar() {
		return this.hideToolbar ? n : e`<div class="toolbar" part="toolbar">
            <slot name="toolbar-start"></slot>
            ${this.searchable ? e`<pk-input class="search" type="search" with-clear .clearLabel=${this.text("clearSearch")} .label=${this.text("search")} placeholder=${this.text("search")} .value=${this.searchDraft ?? this.query.search} @input=${this.inputSearch}>${this.icon(l.magnifyingGlass, "start")}${this.filterController.renderTrigger("end")}</pk-input>` : n}
            <div class="toolbar-end">
                ${this.searchable ? n : this.filterController.renderTrigger()}
                <slot name="toolbar-controls"></slot>
                ${this.columnControls ? this.renderView() : n}
                <slot name="toolbar-end"></slot>
            </div>
        </div>`;
	}
	renderCell(t, r) {
		let i = Ni(t, r), a = this.key(t), o = {
			row: t,
			column: r,
			value: i,
			rowKey: a
		};
		if (r.renderCell) return r.renderCell(o);
		if (r.type === "custom") return e`<slot name=${Mi(a, r.id)}
                >${Y(i)}</slot
            >`;
		if (i == null || i === "") return "—";
		if (r.type === "handle") {
			let t = Y(i);
			return e`<span class="cell-handle" part="handle">
                <code>${t}</code><pk-copy-button
                    .value=${t}
                    .ariaLabel=${this.text("copyHandle", { label: t })}
                    .copiedLabel=${this.text("copiedHandle", { label: t })}
                    >${this.icon(l.clipboard, "icon")}</pk-copy-button>
            </span>`;
		}
		if (r.type === "number" && Number.isFinite(Number(i))) return new Intl.NumberFormat(this.locale || void 0, r.numberOptions).format(Number(i));
		if (r.type === "date") {
			let t = i instanceof Date ? i : new Date(Y(i));
			return Number.isFinite(t.getTime()) ? e`<time datetime=${t.toISOString()}
                >${new Intl.DateTimeFormat(this.locale || void 0, {
				...this.timeZone ? { timeZone: this.timeZone } : {},
				...r.dateOptions
			}).format(t)}</time
            >` : "—";
		}
		if (r.type === "status") return e`<pk-status-label
                .status=${r.status?.(t) ?? Y(i)}
                >${Y(i)}</pk-status-label
            >`;
		if (r.type === "link") {
			let a = r.href?.(t) ?? Y(i);
			try {
				if (![
					"http:",
					"https:",
					"mailto:",
					"tel:"
				].includes(new URL(a, document.baseURI).protocol)) return Y(i);
			} catch {
				return Y(i);
			}
			return e`<a
                href=${a}
                class=${r.wrap === !0 ? n : "cell-link"}
                title=${Y(i)}
                >${Y(i)}</a
            >`;
		}
		return Y(i);
	}
	actionLabel(e) {
		return e.label ?? (e.preset ? this.text(`${e.preset}Action`) : e.id);
	}
	activateAction(e, t) {
		e.disabled || this.loading || this.error || this.emit("pk-row-action", {
			action: e.id,
			row: t,
			rowKey: this.key(t)
		});
	}
	renderRowActions(t, r) {
		if (!r.length) return n;
		let i = (e) => (e.display ?? (e.preset ? "inline" : "menu")) === "inline", a = r.filter((e) => !i(e));
		return e`<div class="row-actions" part="row-actions">
            ${r.filter(i).map((r) => {
			let i = this.text("rowAction", {
				label: this.actionLabel(r),
				id: this.accessibleRowLabel(t)
			});
			return e`<pk-button
                    class="row-action"
                    data-preset=${r.preset ?? n}
                    size="sm"
                    variant="none"
                    aria-label=${i}
                    .disabled=${!!r.disabled}
                    @click=${() => this.activateAction(r, t)}
                    >${r.preset ? this.icon({
				edit: l.pen,
				move: l.gripMove,
				delete: l.xmark
			}[r.preset], "start") : this.actionLabel(r)}</pk-button>`;
		})}
            ${a.length ? e`<pk-dropdown-menu placement="bottom-end"
                @pk-select=${(e) => {
			e.stopPropagation();
			let n = a.find((t) => t.id === e.detail.value);
			n && this.activateAction(n, t);
		}}>
                <pk-button slot="trigger" size="sm" variant="none"
                    aria-label=${this.text("actions", { id: this.accessibleRowLabel(t) })}
                    >${this.icon(l.ellipsis, "start")}</pk-button>
                ${a.map((t) => e`<pk-dropdown-item
                    value=${t.id}
                    .disabled=${!!t.disabled}
                    >${this.actionLabel(t)}</pk-dropdown-item>`)}
            </pk-dropdown-menu>` : n}
        </div>`;
	}
	page(e, t) {
		let n = this.renderRoot.querySelectorAll(".pager pk-button")[+(e > 0)];
		n && (this.pagingFocus = {
			direction: e,
			button: n
		}), this.setQuery({
			pageIndex: Math.max(0, this.query.pageIndex + e),
			cursor: this.pagination === "cursor" ? e > 0 ? t.nextCursor : t.previousCursor : void 0
		});
	}
	render() {
		let t = this.result, r = this.orderedColumns.filter((e) => e.hideable === !1 || !this.view.hiddenColumns.includes(e.id)), i = t.rows.filter((e) => this.isRowSelectable?.(e) !== !1).map((e) => this.key(e)), a = new Set(this.selectable ? this.selectedKeys : []), o = i.length > 0 && i.every((e) => a.has(e)), s = t.rows.length ? this.query.pageIndex * this.query.pageSize + 1 : 0, c = t.rows.length ? s + t.rows.length - 1 : 0, u = this.pagination === "cursor" ? !!t.nextCursor : t.total == null ? !!t.hasNext : c < t.total, d = this.pagination === "cursor" ? this.query.pageIndex > 0 && t.previousCursor !== void 0 : this.query.pageIndex > 0, f = this.loading || !!this.error, p = this.loading && this.sortLoading, m = this.loading && !p, h = this.layout === "index" && !this.hideFooter && this.hasHorizontalOverflow, g = this.text(t.total == null ? "unknownRange" : "range", {
			start: s,
			end: c,
			total: t.total ?? 0
		});
		return e`
            ${this.toolbarHost ? n : e`<div class="header-shell" part="header-shell">
                ${this.renderToolbar()}
            </div>`}
            ${this.filterController.renderDialog()}
            ${this.manualOrder ? e`
                <span id="row-order-help" class="sr-only">${this.text("reorderRowHelp")}</span>
                ${this.rowOrderFiltered ? e`<p class="row-order-hint">${this.text("reorderRowsFiltered")}</p>` : n}
                <span role="status" class="sr-only">${this.rowOrderAnnouncement}</span>
            ` : n}
            <div role="status" aria-live="polite" class="sr-only">
                ${this.loading ? this.text(t.rows.length ? "updating" : "loading") : a.size ? `${g}. ${this.text("selected", { count: a.size })}` : g}
            </div>
            ${this.error ? e`<pk-state-panel variant="error" .heading=${this.error}><pk-button slot="actions" @click=${() => this.refresh()}>${this.text("retry")}</pk-button></pk-state-panel>` : n}
            <div class="table-frame">
                <div
                    class="scroll"
                    part="scroll"
                    ?data-proxy=${h}
                    @scroll=${(e) => this.syncHorizontalScroll(e, ".scrollbar")}
                    role="region"
                    aria-label=${this.label}
                    tabindex="0"
                    aria-busy=${this.loading ? "true" : "false"}
                >
                    <table part="table">
                        <caption class="sr-only">
                            ${this.label}
                        </caption>
                        <thead>
                            <tr>
                                ${this.selectable ? e`<th class="small"><pk-checkbox aria-label=${this.text("selectPage")} .checked=${o} .indeterminate=${!o && i.some((e) => a.has(e))} .disabled=${f || i.length === 0} @change=${() => this.selectPage(!o)}></pk-checkbox></th>` : n}
                                ${r.map((t) => {
			let r = !this.manualOrder && this.query.sort.find((e) => e.id === t.id);
			return e`<th
                                        scope="col"
                                        style=${wi({ width: t.width })}
                                        class=${t.align ?? (t.type === "number" ? "end" : "")}
                                        ?data-sorted=${!!r}
                                        ?data-sortable=${!(!t.sortable || this.manualOrder)}
                                        aria-sort=${r ? r.direction === "asc" ? "ascending" : "descending" : n}
                                    >
                                        ${t.sortable && !this.manualOrder ? e`<button class="sort" aria-label=${this.text("sort", { label: t.label })} @click=${() => this.sortColumn(t.id)}>${t.label}${r ? p ? e`<pk-spinner class="sort-spinner" size="xs" aria-hidden="true"></pk-spinner>` : this.icon(r.direction === "asc" ? l.chevronUp : l.chevronDown) : n}</button>` : t.label}
                                    </th>`;
		})}
                                ${this.rowActions ? e`<th class="small"><span class="sr-only">${this.text("actions", { id: "" })}</span></th>` : n}
                                ${this.manualOrder ? e`<th class="small" scope="col"><span class="sr-only">${this.text("rowOrder")}</span></th>` : n}
                            </tr>
                        </thead>
                        <tbody class=${f && !p ? "busy" : ""} ?inert=${f} ?data-loading=${m}>
                            ${xi(t.rows, (e) => this.key(e), (t) => {
			let i = this.rowActions?.(t) ?? [];
			return e`<tr
                                        ?data-selected=${this.selectable && a.has(this.key(t))}
                                        ?data-selectable=${this.canSelectRow(t)}
                                        @click=${(e) => this.clickRow(e, t)}
                                        @keydown=${(e) => this.keyRow(e, t)}
                                        @mousedown=${(e) => {
				e.button === 0 && this.canSelectRow(t) && (e.shiftKey || e.metaKey || e.ctrlKey) && !this.isRowControl(e) && e.preventDefault();
			}}
                                    >
                                        ${this.selectable ? e`<td class="small">
                                                      <pk-checkbox
                                                          aria-label=${this.text("selectRow", { id: this.accessibleRowLabel(t) })}
                                                          .checked=${st(a.has(this.key(t)))}
                                                          .disabled=${f || this.isRowSelectable?.(t) === !1}
                                                          @mousedown=${(e) => {
				e.shiftKey && (e.preventDefault(), e.currentTarget.focus({ preventScroll: !0 }));
			}}
                                                          @click=${(e) => this.clickRowCheckbox(e, t)}
                                                          @change=${() => this.changeRowCheckbox(t)}
                                                      ></pk-checkbox>
                                                  </td>` : n}
                                        ${r.map((n) => e`<td part="cell" class=${`${n.align ?? (n.type === "number" ? "end" : "")} ${n.wrap ?? ![
				"number",
				"date",
				"status",
				"handle"
			].includes(n.type ?? "text") ? "" : "nowrap"}`}>${this.renderCell(t, n)}</td>`)}
                                        ${this.rowActions ? e`<td class="small">
                                                      ${this.renderRowActions(t, i)}
                                                  </td>` : n}
                                        ${this.manualOrder ? e`<td class="small">
                                            <button class="row-handle" part="row-handle" type="button"
                                                aria-label=${this.text("reorderRow", { label: this.accessibleRowLabel(t) })}
                                                aria-describedby="row-order-help"
                                                aria-disabled=${this.canReorderRows ? "false" : "true"}
                                                @pointerdown=${(e) => this.rowOrder.start(e)}
                                                @keydown=${(e) => this.keyRowOrder(e, t)}
                                                >${this.icon(l.gripMove)}</button>
                                        </td>` : n}
                                    </tr>`;
		})}
                        </tbody>
                    </table>
                    ${m ? e`
                        ${t.rows.length ? n : e`<div class="loading-space" aria-hidden="true"></div>`}
                        <div class="loading-indicator" aria-hidden="true"><pk-spinner></pk-spinner></div>
                    ` : n}
                    ${!t.rows.length && !m && !this.error ? e`<slot name="empty"><pk-state-panel .heading=${this.text(this.query.search || Object.keys(this.query.filters).length ? "noResults" : "empty")}></pk-state-panel></slot>` : n}
                </div>
                ${this.hideFooter ? n : e`<div class="footer-shell" part="footer-shell">
                              ${h ? e`<div class="scrollbar" part="scrollbar" role="region" aria-label=${this.text("scrollColumns", { label: this.label })} tabindex="0" @scroll=${(e) => this.syncHorizontalScroll(e, ".scroll")}><div style=${wi({ width: `${this.tableScrollWidth}px` })}></div></div>` : n}
                              <div class="footer" part="footer">
                                  <nav
                                      class="pager"
                                      aria-label=${this.text("pagination", { label: this.label })}
                                  >
                                      <pk-button
                                          size="sm"
                                          variant="outline"
                                          aria-label=${this.text("previous")}
                                          .disabled=${!d || this.loading}
                                          @click=${() => this.page(-1, t)}
                                          >${this.icon(l.chevronLeft)}</pk-button
                                      >
                                      <pk-button
                                          size="sm"
                                          variant="outline"
                                          aria-label=${this.text("next")}
                                          .disabled=${!u || this.loading}
                                          @click=${() => this.page(1, t)}
                                          >${this.icon(l.chevronRight)}</pk-button
                                      >
                                      <span>${g}</span>
                                  </nav>
                                  ${this.selectable ? e`<div
                                                class="selection"
                                                part="selection"
                                                ?data-empty=${a.size === 0}
                                                ?inert=${f || a.size === 0}
                                                aria-hidden=${a.size === 0 ? "true" : n}
                                            >
                                                <span class="selection-count"
                                                    >${this.text("selected", { count: a.size })}</span
                                                >
                                                <slot
                                                    name="selection-actions"
                                                ></slot>
                                            </div>` : n}
                                  <slot name="footer"></slot>
                                  <div class="footer-actions">
                                      ${this.renderExport()}
                                  </div>
                              </div>
                          </div>`}
            </div>
        `;
	}
};
s([t()], X.prototype, "label", void 0), s([t({ attribute: !1 })], X.prototype, "rows", void 0), s([t({ attribute: !1 })], X.prototype, "columns", void 0), s([t({ attribute: !1 })], X.prototype, "filters", void 0), s([t({ attribute: !1 })], X.prototype, "rowKey", void 0), s([t({ attribute: !1 })], X.prototype, "query", void 0), s([t({ attribute: !1 })], X.prototype, "view", void 0), s([t()], X.prototype, "mode", void 0), s([t()], X.prototype, "pagination", void 0), s([t({ attribute: !1 })], X.prototype, "dataSource", void 0), s([t({ type: Number })], X.prototype, "total", void 0), s([t({
	type: Boolean,
	attribute: "has-next"
})], X.prototype, "hasNext", void 0), s([t({ attribute: "next-cursor" })], X.prototype, "nextCursor", void 0), s([t({ attribute: "previous-cursor" })], X.prototype, "previousCursor", void 0), s([t({ type: Boolean })], X.prototype, "loading", void 0), s([t()], X.prototype, "error", void 0), s([t({ type: Boolean })], X.prototype, "searchable", void 0), s([t({
	type: Boolean,
	attribute: "show-filters"
})], X.prototype, "showFilters", void 0), s([t({
	type: Boolean,
	attribute: "column-controls"
})], X.prototype, "columnControls", void 0), s([t({
	type: Boolean,
	attribute: "hide-toolbar"
})], X.prototype, "hideToolbar", void 0), s([t({
	type: Boolean,
	attribute: "hide-footer"
})], X.prototype, "hideFooter", void 0), s([t({ type: Boolean })], X.prototype, "selectable", void 0), s([t({
	type: Boolean,
	attribute: "manual-order"
})], X.prototype, "manualOrder", void 0), s([t({
	type: Boolean,
	attribute: "reorder-disabled"
})], X.prototype, "reorderDisabled", void 0), s([t({ attribute: !1 })], X.prototype, "selectedKeys", void 0), s([t({ attribute: !1 })], X.prototype, "isRowSelectable", void 0), s([t({ attribute: !1 })], X.prototype, "rowActions", void 0), s([t({ attribute: !1 })], X.prototype, "exportFormats", void 0), s([t({ type: Boolean })], X.prototype, "exporting", void 0), s([t({ attribute: !1 })], X.prototype, "defaultView", void 0), s([t({ attribute: !1 })], X.prototype, "defaultSort", void 0), s([t({
	type: Number,
	attribute: "search-delay"
})], X.prototype, "searchDelay", void 0), s([t({ reflect: !0 })], X.prototype, "size", void 0), s([t({ reflect: !0 })], X.prototype, "layout", void 0), s([t({ attribute: !1 })], X.prototype, "rowLabel", void 0), s([t()], X.prototype, "locale", void 0), s([t({ attribute: "time-zone" })], X.prototype, "timeZone", void 0), s([t({ attribute: !1 })], X.prototype, "labels", void 0), s([o()], X.prototype, "loaded", void 0), s([o()], X.prototype, "searchDraft", void 0), s([o()], X.prototype, "dragOffsets", void 0), s([o()], X.prototype, "reorderAnnouncement", void 0), s([o()], X.prototype, "rowOrderAnnouncement", void 0), s([o()], X.prototype, "tableScrollWidth", void 0), s([o()], X.prototype, "hasHorizontalOverflow", void 0), X = s([r("pk-data-table")], X);
// src/web/assets/cp/src/commenters.ts
var Z = (e, t = {}) => window.Craft?.t("comments", e, t) ?? e, Wi = {
	trusted: "green",
	normal: "disabled",
	held: "orange",
	blocked: "red"
}, Q = {
	cell: "display:flex;align-items:center;gap:10px;min-width:0",
	avatar: "display:inline-flex;flex:none;align-items:center;justify-content:center;box-sizing:border-box;width:32px;height:32px;border-radius:50%;font-size:12px;font-weight:600;line-height:1;color:var(--white);object-fit:cover",
	guestAvatar: "color:var(--medium-text-color);background-color:var(--white);box-shadow:inset 0 0 0 1px var(--gray-300)",
	name: "display:flex;align-items:center;gap:6px;min-width:0",
	guestPill: "flex:none;padding:0 6px;border-radius:999px;font-size:11px;font-weight:500;line-height:18px;color:var(--medium-text-color);background-color:var(--gray-100)",
	secondary: "color:var(--light-text-color);font-size:12px",
	truncate: "max-width:190px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap",
	attention: "font-weight:600;color:var(--amber-700)"
};
function $(e, t = {}, n) {
	let r = document.createElement(e);
	return Object.entries(t).forEach(([e, t]) => r.setAttribute(e, t)), n !== void 0 && (r.textContent = n), r;
}
function Gi(e) {
	if (typeof e.photo == "string" && e.photo !== "") return $("img", {
		src: e.photo,
		alt: "",
		style: Q.avatar
	});
	let t = e.guest ? Q.guestAvatar : `background-color:var(--comments-avatar-${Number(e.tone) || 0})`;
	return $("span", {
		style: `${Q.avatar};${t}`,
		"aria-hidden": "true"
	}, String(e.initials ?? ""));
}
function Ki(e) {
	if (typeof e != "string" || e === "") return "—";
	let t = new Date(e), n = Math.round((Date.now() - t.getTime()) / 6e4);
	return n < 60 ? Z("{count} min", { count: Math.max(1, n) }) : n < 1440 ? Z("{count} h", { count: Math.round(n / 60) }) : n < 10080 ? Z("{count} d", { count: Math.round(n / 1440) }) : t.toLocaleDateString(void 0, {
		day: "numeric",
		month: "short",
		year: "numeric"
	});
}
function qi(e) {
	let t = JSON.parse(e.dataset.settings ?? "[]"), n = new X();
	n.id = "comments-commenters-table", n.label = e.dataset.label ?? Z("Commenters"), n.rowKey = "id", n.mode = "remote", n.pagination = "offset", n.searchable = !0, n.showFilters = !1, n.selectable = !0, n.layout = "index", n.rowLabel = "name", n.defaultSort = [{
		id: "lastComment",
		direction: "desc"
	}], n.query = {
		...ji(),
		pageSize: Number(e.dataset.pageSize ?? 50),
		sort: n.defaultSort
	}, n.columns = [
		{
			id: "name",
			label: Z("Commenter"),
			type: "custom",
			sortable: !0,
			hideable: !1,
			width: "15rem",
			renderCell: ({ row: e }) => {
				let t = $("div", { style: `${Q.cell};min-width:200px` });
				t.append(Gi(e));
				let n = $("div", { style: "display:grid;gap:1px;min-width:0;line-height:1.35" }), r = $("div", { style: Q.name });
				return r.append($("a", {
					href: String(e.url),
					style: `font-weight:600;color:var(--text-color);${Q.truncate}`
				}, String(e.name))), e.guest && r.append($("span", { style: Q.guestPill }, Z("Guest"))), n.append(r), e.email && n.append($("div", {
					style: `color:var(--light-text-color);font-size:13px;${Q.truncate}`,
					title: String(e.email)
				}, String(e.email))), t.append(n), t;
			}
		},
		{
			id: "type",
			label: Z("Type"),
			type: "text",
			wrap: !1
		},
		{
			id: "comments",
			label: Z("Comments"),
			type: "number",
			sortable: !0,
			align: "end"
		},
		{
			id: "waiting",
			label: Z("Waiting"),
			type: "custom",
			sortable: !0,
			align: "end",
			renderCell: ({ row: e }) => $("span", Number(e.waiting) > 0 ? { style: Q.attention } : {}, String(e.waiting ?? 0))
		},
		{
			id: "removed",
			label: Z("Removed"),
			type: "number",
			sortable: !0,
			align: "end"
		},
		{
			id: "reviewSetting",
			label: Z("Review setting"),
			type: "custom",
			wrap: !1,
			renderCell: ({ row: e }) => $("pk-status-label", { status: Wi[String(e.reviewSetting)] ?? "disabled" }, String(e.reviewSettingLabel ?? ""))
		},
		{
			id: "lastComment",
			label: Z("Last comment"),
			type: "custom",
			sortable: !0,
			renderCell: ({ row: e }) => {
				let t = $("div", { style: "min-width:0;white-space:nowrap" });
				return t.append($("div", {}, Ki(e.lastComment))), e.lastOn && t.append($("div", {
					style: `${Q.secondary};${Q.truncate}`,
					title: String(e.lastOn)
				}, String(e.lastOn))), t;
			}
		},
		{
			id: "firstSeen",
			label: Z("First seen"),
			type: "custom",
			sortable: !0,
			wrap: !1,
			renderCell: ({ row: e }) => $("span", {}, typeof e.firstSeen == "string" ? new Date(e.firstSeen).toLocaleDateString(void 0, {
				day: "numeric",
				month: "short",
				year: "numeric"
			}) : "—")
		}
	], n.defaultView = {
		hiddenColumns: ["type", "firstSeen"],
		columnOrder: []
	}, n.view = structuredClone(n.defaultView), n.dataSource = async (t, { signal: n }) => {
		let r = new URL(e.dataset.endpoint, window.location.href);
		r.searchParams.set("source", e.dataset.source ?? "all"), r.searchParams.set("page", String(t.pageIndex + 1)), t.search && r.searchParams.set("search", t.search), t.sort[0] && (r.searchParams.set("sort", t.sort[0].id), r.searchParams.set("direction", t.sort[0].direction));
		let i = await fetch(r, {
			signal: n,
			credentials: "same-origin",
			headers: { Accept: "application/json" }
		});
		if (!i.ok) throw Error(Z("Couldn’t load commenters. Reload the page and try again."));
		let a = await i.json();
		return {
			rows: a.rows,
			total: a.total
		};
	};
	let r = $("pk-button", {
		slot: "selection-actions",
		size: "small"
	}, Z("Change review setting…")), i = $("pk-dialog", { label: Z("Change review setting") }), a = $("pk-radio-group", { label: Z("Review setting") });
	t.forEach((e) => {
		let t = $("pk-radio", { value: e.value });
		t.append($("strong", {}, e.label), $("span", { style: `display:block;${Q.secondary}` }, e.instructions)), a.append(t);
	});
	let o = $("pk-textarea", {
		label: Z("Note"),
		instructions: Z("Optional. Recorded in each commenter’s history."),
		rows: "2"
	}), s = $("pk-button", {
		slot: "footer",
		variant: "primary"
	}, Z("Apply")), c = $("pk-button", { slot: "footer" }, Z("Cancel"));
	i.append(a, o, c, s), document.body.append(i), r.addEventListener("click", () => {
		a.value = "", o.value = "", i.open = !0;
	}), c.addEventListener("click", () => {
		i.open = !1;
	}), s.addEventListener("click", async () => {
		if (a.value && (a.value !== "blocked" || window.confirm(e.dataset.blockConfirm ?? ""))) try {
			let t = await window.Craft.sendActionRequest("POST", e.dataset.setAction, { data: {
				identityIds: n.selectedKeys,
				reviewSetting: a.value,
				note: o.value
			} });
			i.open = !1, window.Craft.cp.displaySuccess(t.data.message ?? ""), window.location.reload();
		} catch (e) {
			window.Craft.cp.displayError(e.response?.data?.message);
		}
	}), n.append(r);
	let l = document.querySelector("#header");
	l && new ResizeObserver(() => n.style.setProperty("--pk-data-table-header-offset", `${l.offsetHeight}px`)).observe(l), Ui(n, {
		key: "comments:commenters",
		userId: e.dataset.userId ?? ""
	}), e.replaceChildren(n);
}
function Ji(e) {
	let t = e.querySelector("input[name=\"reviewSetting\"]:checked")?.value;
	e.addEventListener("submit", (n) => {
		let r = e.querySelector("input[name=\"reviewSetting\"]:checked");
		r && r.value !== t && r.dataset.commentsConfirm && !window.confirm(r.dataset.commentsConfirm) && n.preventDefault();
	});
}
document.querySelectorAll("[data-comments-commenters]").forEach(qi), document.querySelectorAll("form.comments-review-setting").forEach(Ji);

//# sourceMappingURL=commenters.js.map