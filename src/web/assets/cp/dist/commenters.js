import { C as e, S as t, _ as n, a as r, b as i, d as a, f as o, g as s, i as c, o as l, p as u, s as d, v as f, w as p, x as m, y as h } from "./chunks/pk-copy-button-Dfji6-PV.js";
import { a as g, i as _, n as v, o as y, r as b, s as x, t as S } from "./chunks/directive.js";
import { a as C, c as w, d as T, i as E, l as ee, n as te, o as D, r as ne, s as re, t as ie, u as ae } from "./chunks/pk-state-panel-BjL_ch5N.js";
import { t as oe } from "./chunks/repeat.js";
// node_modules/@verbb/plugin-kit-web/dist/chunks/dismissible-stack-D_0W3Azi.js
var O = [];
function se(e) {
	O.push(e);
}
function ce(e) {
	for (let t = O.length - 1; t >= 0; --t) if (O[t] === e) {
		O.splice(t, 1);
		break;
	}
}
function le(e) {
	return O.length > 0 && O[O.length - 1] === e;
}
function ue(e) {
	let t = O.lastIndexOf(e);
	return t === -1 ? [] : O.slice(t + 1);
}
// node_modules/@verbb/plugin-kit-web/dist/chunks/overlay-lifecycle-C582MGBJ.js
var de = class extends Event {
	constructor() {
		super("pk-show", {
			bubbles: !0,
			cancelable: !1,
			composed: !0
		});
	}
}, fe = class extends Event {
	constructor() {
		super("pk-after-show", {
			bubbles: !0,
			cancelable: !1,
			composed: !0
		});
	}
}, pe = class extends Event {
	constructor(e = "unknown") {
		super("pk-hide", {
			bubbles: !0,
			cancelable: !0,
			composed: !0
		}), this.detail = { source: e };
	}
}, me = class extends Event {
	constructor() {
		super("pk-after-hide", {
			bubbles: !0,
			cancelable: !1,
			composed: !0
		});
	}
};
// node_modules/@verbb/plugin-kit-web/dist/chunks/scroll-lock-DE1pElMQ.js
function he(e, t) {
	return {
		top: Math.round(e.getBoundingClientRect().top - t.getBoundingClientRect().top),
		left: Math.round(e.getBoundingClientRect().left - t.getBoundingClientRect().left)
	};
}
var ge = /* @__PURE__ */ new Set(), _e = null, ve = /* @__PURE__ */ new Set([
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
function ye(e) {
	for (let t of e.composedPath()) if (t instanceof HTMLElement && ge.has(t)) return !0;
	return !1;
}
function be(e) {
	if (!(e instanceof HTMLElement)) return !1;
	if (e.isContentEditable) return !0;
	let t = e.tagName;
	return t === "INPUT" || t === "TEXTAREA" || t === "SELECT";
}
function xe() {
	let e = document.body, t = e.style.overflow;
	e.style.setProperty("overflow", "hidden", "important");
	let n = (e) => {
		ye(e) || e.preventDefault();
	}, r = (e) => {
		ye(e) || e.preventDefault();
	}, i = (e) => {
		ve.has(e.key) && (ye(e) || be(e.target) || e.preventDefault());
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
function Se(e) {
	ge.add(e), ge.size === 1 && (document.documentElement.classList.add("pk-scroll-lock"), document.documentElement.style.setProperty("--pk-scroll-lock-size", "0px"), _e = xe());
}
function Ce(e) {
	ge.delete(e), ge.size === 0 && (_e?.(), _e = null, document.documentElement.classList.remove("pk-scroll-lock"), document.documentElement.style.removeProperty("--pk-scroll-lock-size"), document.documentElement.style.removeProperty("--pk-scroll-lock-gutter"));
}
function we(e, t, n = "vertical", r = "smooth") {
	let i = he(e, t), a = i.top + t.scrollTop, o = i.left + t.scrollLeft, s = t.scrollLeft, c = t.scrollLeft + t.offsetWidth, l = t.scrollTop, u = t.scrollTop + t.offsetHeight;
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
// node_modules/@verbb/plugin-kit-web/dist/chunks/animate-with-class-CjTZI0NU.js
function Te(e, t, n = 500) {
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
// node_modules/@verbb/plugin-kit-web/dist/chunks/pk-dialog-CwDbc9sJ.js
var Ee = ".modal-shade, .modal", De = (e) => {
	let t = getComputedStyle(e);
	return t.display !== "none" && t.visibility !== "hidden" && Number.parseFloat(t.opacity || "1") > 0;
}, Oe = (e) => Array.from(e.querySelectorAll(Ee)).filter((e) => e instanceof HTMLElement && !e.closest("pk-dialog") && De(e));
function ke(e, t = {}) {
	let n = t.getDocument?.() ?? document, r = t.root ?? n.body, i = new Set(t.ignoreExisting ? Oe(n) : []), a = () => Oe(n).some((e) => !i.has(e)), o = a(), s = () => {
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
var Ae = p`
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
`, je, Me = l(r.xmark), Ne = () => {
	let e = document.activeElement;
	for (; e?.shadowRoot?.activeElement;) e = e.shadowRoot.activeElement;
	return e instanceof HTMLElement ? e : null;
}, Pe = (e, t) => {
	let n = t;
	for (; n;) {
		if (n === e) return !0;
		n = n.parentNode ?? (n instanceof ShadowRoot ? n.host : null);
	}
	return !1;
}, k = class extends m {
	static {
		je = this;
	}
	constructor(...e) {
		super(...e), this.open = !1, this.label = "", this.description = "", this.disablePointerDismissal = !1, this.withoutHeader = !1, this.withoutBodyPadding = !1, this.disableScrollLock = !1, this.size = "default", this.triggerElement = null, this.previouslyFocused = null, this.yieldingToHostModal = !1, this.hostModalObserver = null, this.yieldBox = null, this.handleDocumentKeyDown = (e) => {
			this.yieldingToHostModal || e.key === "Escape" && this.open && le(this) && (e.preventDefault(), e.stopPropagation(), this.requestClose("escape"));
		}, this.onHostModalPresenceChange = (e) => {
			e ? this.yieldToHostModal() : this.restoreFromHostModal();
		}, this.showing = !1, this.isClosing = !1, this.transitionId = 0, this.handleDialogCancel = (e) => {
			e.preventDefault(), !this.dialogElement.classList.contains("hide") && le(this) && this.requestClose("escape");
		}, this.handleDialogClick = (e) => {
			e.composedPath().some((e) => e instanceof Element && e.matches("[data-dialog=\"close\"], [data-dialog-close]")) && (e.stopPropagation(), this.requestClose("close-button"));
		}, this.handleDialogPointerDown = async (e) => {
			e.target === this.dialogElement && le(this) && (this.disablePointerDismissal ? await Te(this.dialogElement, "pulse") : this.requestClose("pointer-dismiss"));
		}, this.onTriggerClick = (e) => {
			e.preventDefault(), this.open = !0;
		}, this.onFooterSlotChange = () => {
			this.requestUpdate();
		};
	}
	static {
		this.styles = Ae;
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
		if (!this.yieldingToHostModal || (this.yieldingToHostModal = !1, this.clearYieldPosition(), !this.open || !this.dialogElement)) return;
		let e = ue(this).filter((e) => e instanceof je), t = e.length ? Ne() : null;
		try {
			this.dialogElement.open && this.dialogElement.close(), this.dialogElement.showModal();
		} catch {}
		e.forEach((e) => e.reenterTopLayer()), t?.isConnected && e.some((e) => Pe(e, t)) && t.focus({ preventScroll: !0 });
	}
	reenterTopLayer() {
		if (this.open && !this.isClosing && !this.yieldingToHostModal && this.dialogElement?.matches(":modal")) try {
			this.dialogElement.close(), this.dialogElement.showModal();
		} catch {}
	}
	firstUpdated() {
		this.open && this.show();
	}
	disconnectedCallback() {
		this.transitionId++, this.showing = !1, this.isClosing = !1, this.disableScrollLock || Ce(this), this.removeOpenListeners(), super.disconnectedCallback();
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
			let e = new de();
			if (!this.dispatchEvent(e)) {
				this.open = !1;
				return;
			}
			if (this.addOpenListeners(), this.dialogElement.open || (this.previouslyFocused = document.activeElement), this.open = !0, this.dialogElement.open || this.dialogElement.showModal(), this.disableScrollLock || Se(this), requestAnimationFrame(() => {
				if (t !== this.transitionId || !this.isConnected) return;
				let e = this.querySelector("[autofocus]");
				e ? (e.shadowRoot?.querySelector("input, textarea, select, button") ?? e).focus({ preventScroll: !0 }) : this.dialogElement.focus({ preventScroll: !0 });
			}), await Te(this.dialogElement, "show"), t !== this.transitionId || !this.isConnected) return;
			this.dispatchEvent(new CustomEvent("pk-open-change", {
				detail: { open: !0 },
				bubbles: !0,
				composed: !0
			})), this.dispatchEvent(new fe());
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
		let t = new pe(typeof e == "string" ? e : "close-button");
		if (!this.dispatchEvent(t)) {
			this.open = !0, await Te(this.dialogElement, "pulse");
			return;
		}
		let n = ++this.transitionId;
		if (this.isClosing = !0, this.showing = !1, this.open = !1, this.dialogElement.classList.remove("show"), this.removeOpenListeners(), await Te(this.dialogElement, "hide"), n !== this.transitionId || !this.isConnected) return;
		this.isClosing = !1, this.dialogElement.close(), this.disableScrollLock || Ce(this);
		let r = this.previouslyFocused;
		this.previouslyFocused = null, r?.isConnected && window.setTimeout(() => {
			n === this.transitionId && !this.open && r.focus({ preventScroll: !0 });
		}, 0), this.dispatchEvent(new me()), this.dispatchEvent(new CustomEvent("pk-open-change", {
			detail: { open: !1 },
			bubbles: !0,
			composed: !0
		}));
	}
	forceOverlayReset() {
		if (this.transitionId++, this.showing = !1, this.isClosing = !1, this.open = !1, this.yieldingToHostModal = !1, this.clearYieldPosition(), this.removeOpenListeners(), this.dialogElement?.open) try {
			this.dialogElement.close();
		} catch {}
		this.dialogElement?.classList.remove("hide", "show", "pulse"), this.disableScrollLock || Ce(this);
	}
	addOpenListeners() {
		document.addEventListener("keydown", this.handleDocumentKeyDown), se(this), this.hostModalObserver?.disconnect(), this.hostModalObserver = ke(this.onHostModalPresenceChange, { ignoreExisting: !0 });
	}
	removeOpenListeners() {
		document.removeEventListener("keydown", this.handleDocumentKeyDown), ce(this), this.hostModalObserver?.disconnect(), this.hostModalObserver = null, this.yieldingToHostModal = !1, this.clearYieldPosition();
	}
	syncHasTriggerAttribute() {
		this.toggleAttribute("data-has-trigger", !!this.triggerElement);
	}
	onTriggerSlotChange(e) {
		let [t] = e.target.assignedElements({ flatten: !0 });
		this.triggerElement && this.triggerElement.removeEventListener("click", this.onTriggerClick), this.triggerElement = t ?? null, this.syncHasTriggerAttribute(), this.triggerElement && this.triggerElement.addEventListener("click", this.onTriggerClick);
	}
	render() {
		let e = !this.hasCustomHeaderSlot() && !this.withoutHeader && !!this.label, t = e && !this.withoutBodyPadding, n = this.querySelector(":scope > [slot=\"footer\"]") !== null;
		return x`
            <slot name="trigger" @slotchange=${this.onTriggerSlotChange}></slot>
            <dialog
                part="panel"
                aria-label=${this.label || _}
                class=${d({
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
                    ${e ? x`
                            <header part="header" class="header">
                                <h2 part="title" class="title">
                                    <slot name="label">${this.label}</slot>
                                </h2>
                                ${this.description ? x`
                                        <p part="description" class="description">
                                            <slot name="description">${this.description}</slot>
                                        </p>
                                    ` : x`<slot name="description" hidden></slot>`}
                                <button type="button" class="close" data-dialog="close" aria-label="Close">
                                    <span class="close-icon" aria-hidden="true">${u(Me)}</span>
                                </button>
                            </header>
                        ` : _}
                </slot>
                <div
                    part="body"
                    class=${d({
			body: !0,
			"body--padded": t
		})}
                >
                    <slot></slot>
                </div>
                ${n ? x`
                        <footer part="footer" class="footer">
                            <slot name="footer" @slotchange=${this.onFooterSlotChange}></slot>
                        </footer>
                    ` : x`<slot name="footer" @slotchange=${this.onFooterSlotChange} hidden></slot>`}
            </dialog>
        `;
	}
};
t([i({
	type: Boolean,
	reflect: !0
})], k.prototype, "open", void 0), t([i()], k.prototype, "label", void 0), t([i()], k.prototype, "description", void 0), t([i({
	attribute: "disable-pointer-dismissal",
	type: Boolean,
	reflect: !0
})], k.prototype, "disablePointerDismissal", void 0), t([i({
	attribute: "without-header",
	type: Boolean,
	reflect: !0
})], k.prototype, "withoutHeader", void 0), t([i({
	attribute: "without-body-padding",
	type: Boolean,
	reflect: !0
})], k.prototype, "withoutBodyPadding", void 0), t([i({
	attribute: "disable-scroll-lock",
	type: Boolean,
	reflect: !0
})], k.prototype, "disableScrollLock", void 0), t([i({ reflect: !0 })], k.prototype, "size", void 0), t([f("dialog")], k.prototype, "dialogElement", void 0), t([h()], k.prototype, "triggerElement", void 0), k = je = t([e("pk-dialog")], k);
// node_modules/@verbb/plugin-kit-web/dist/chunks/pk-radio-DGABEd3l.js
var Fe = p`
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

        /* The control sits beside the first line, so a label that wraps or carries a description below it
           doesn't pull the control down to its middle. The offset centres it on that first line. */
        .item--with-label {
            align-items: flex-start;
            gap: 0.5rem;
        }

        .item--with-label .control {
            margin-block-start: calc((var(--pk-line-height) * 1em - 1rem) / 2);
        }

        .control {
            display: inline-flex;
            flex-shrink: 0;
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
`, A = class extends m {
	constructor(...e) {
		super(...e), this.value = "", this.checked = !1, this.disabled = !1, this.invalid = !1, this.required = !1, this.tabIndex = -1, this.ariaLabel = null, this.forceDisabled = !1, this.hasDefaultSlotContent = !1;
	}
	static {
		this.styles = Fe;
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
		let e = this.disabled || this.forceDisabled;
		return x`
            <label
                part="base"
                class=${d({
			item: !0,
			"pk-radio-group__item": !0,
			"item--with-label": this.hasDefaultSlotContent
		})}
                data-state=${this.checked ? "checked" : _}
                ?data-disabled=${e}
                aria-disabled=${e ? "true" : _}
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
                    ?disabled=${e}
                    ?required=${this.required}
                    value=${this.value}
                    tabindex=${this.tabIndex}
                    aria-label=${this.ariaLabel ?? _}
                    aria-invalid=${this.invalid ? "true" : _}
                    aria-checked=${this.checked ? "true" : "false"}
                    @change=${this.handleChange}
                />
                <span
                    class=${d({
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
t([i()], A.prototype, "value", void 0), t([i({
	type: Boolean,
	reflect: !0
})], A.prototype, "checked", void 0), t([i({
	type: Boolean,
	reflect: !0
})], A.prototype, "disabled", void 0), t([i({
	type: Boolean,
	reflect: !0
})], A.prototype, "invalid", void 0), t([i({
	type: Boolean,
	reflect: !0
})], A.prototype, "required", void 0), t([i({ type: Number })], A.prototype, "tabIndex", void 0), t([i({ attribute: "aria-label" })], A.prototype, "ariaLabel", void 0), t([i({
	type: Boolean,
	attribute: !1
})], A.prototype, "forceDisabled", void 0), t([f(".input")], A.prototype, "input", void 0), t([h()], A.prototype, "hasDefaultSlotContent", void 0), A = t([e("pk-radio")], A);
// node_modules/@verbb/plugin-kit-web/dist/chunks/pk-radio-group-BAqRdR_z.js
var Ie = p`
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
`, j = class extends ee {
	constructor(...e) {
		super(...e), this.assumeInteractionOn = ["change"], this.hasSlotController = new c(this, "instructions", "hint", "label"), this._value = null, this.defaultValue = "", this.orientation = "vertical", this.invalid = !1, this.label = "", this.instructions = "", this.ariaLabel = null, this.items = [], this.syncItems = () => {
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
		this.styles = [T, Ie];
	}
	static get validators() {
		return [...super.validators, w()];
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
		this.instructions = re(this, this.instructions), super.connectedCallback(), this.addEventListener("keydown", this.handleKeyDown), this.addEventListener("click", this.handleRadioClick);
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
		let e = this.hasSlotController.test("label"), t = D((e, t) => this.hasSlotController.test(e, t), this.instructions), n = !!this.label || e;
		return x`
            <div part="form-control" class="form-control">
                ${n ? x`
                        <div part="label" class="form-control__label" id="label">
                            <slot name="label">${this.label}</slot>
                        </div>
                    ` : _}

                ${t ? x`
                        <div part="instructions" class="form-control__instructions" id="instructions">
                            <slot name="instructions">${this.instructions}</slot>
                            <slot name="hint"></slot>
                        </div>
                    ` : _}

                <div
                    part="radios"
                    class=${d({
			group: !0,
			"pk-radio-group": !0,
			"group--horizontal": this.orientation === "horizontal",
			"pk-radio-group--horizontal": this.orientation === "horizontal"
		})}
                    role="radiogroup"
                    aria-labelledby=${n ? "label" : _}
                    aria-label=${n ? _ : this.ariaLabel ?? _}
                    aria-describedby=${t ? "instructions" : _}
                    aria-invalid=${this.invalid ? "true" : _}
                    aria-required=${this.required ? "true" : _}
                >
                    <slot @slotchange=${this.syncItems}></slot>
                </div>
            </div>
        `;
	}
};
t([h()], j.prototype, "value", null), t([i({
	attribute: "value",
	reflect: !0
})], j.prototype, "defaultValue", void 0), t([i({ reflect: !0 })], j.prototype, "orientation", void 0), t([i({
	type: Boolean,
	reflect: !0
})], j.prototype, "invalid", void 0), t([i()], j.prototype, "label", void 0), t([i()], j.prototype, "instructions", void 0), t([i({ attribute: "aria-label" })], j.prototype, "ariaLabel", void 0), t([f("slot:not([name])")], j.prototype, "defaultSlot", void 0), t([h()], j.prototype, "items", void 0), j = t([e("pk-radio-group")], j);
// node_modules/@verbb/plugin-kit-web/dist/chunks/pk-status-CdiRBRyh.js
var Le = p`
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
`, Re = class extends m {
	constructor(...e) {
		super(...e), this.status = "on", this.ariaLabel = null;
	}
	static {
		this.styles = Le;
	}
	render() {
		return x`
            <span
                part="base"
                class="status"
                role="status"
                aria-label=${this.ariaLabel ?? _}
            ></span>
        `;
	}
};
t([i({ reflect: !0 })], Re.prototype, "status", void 0), t([i({ attribute: "aria-label" })], Re.prototype, "ariaLabel", void 0), Re = t([e("pk-status")], Re);
// node_modules/@verbb/plugin-kit-web/dist/chunks/pk-status-label-CopQRSZz.js
var ze = p`
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
`, Be = {
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
}, Ve = class extends m {
	constructor(...e) {
		super(...e), this.status = "gray";
	}
	static {
		this.styles = ze;
	}
	render() {
		let e = Object.hasOwn(Be, this.status);
		return x`<span
            part="base"
            class="base"
            data-tone=${e ? Be[this.status] : "gray"}
            data-indicator=${this.status}
        >
            <pk-status
                part="indicator"
                aria-hidden="true"
                .status=${e ? this.status : "gray"}
            ></pk-status>
            <span part="label" class="label"><slot>${this.status}</slot></span>
        </span>`;
	}
};
t([i({ reflect: !0 })], Ve.prototype, "status", void 0), Ve = t([e("pk-status-label")], Ve);
// node_modules/@verbb/plugin-kit-web/dist/chunks/active-elements-739kISON.js
function* He(e = document.activeElement) {
	e != null && (yield e, "shadowRoot" in e && e.shadowRoot && e.shadowRoot.mode !== "closed" && (yield* He(e.shadowRoot.activeElement)));
}
// node_modules/@verbb/plugin-kit-web/dist/chunks/pk-textarea-CpAAH8YI.js
var Ue = p`
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
`, M = class extends ee {
	constructor(...e) {
		super(...e), this.assumeInteractionOn = ["blur", "input"], this.hasSlotController = new c(this, "instructions", "hint", "label"), this.controlId = E("pk-textarea"), this.placeholder = "", this._value = null, this.defaultValue = null, this.size = "default", this.label = "", this.instructions = "", this.readonly = !1, this.invalid = !1, this.fitCell = !1, this.withLabel = !1, this.withInstructions = !1;
	}
	static {
		this.styles = [T, Ue];
	}
	static get validators() {
		return [
			...super.validators,
			C(),
			w()
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
		this.instructions = re(this, this.instructions), this.hasAttribute("with-hint") && (this.withInstructions = !0), super.connectedCallback();
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
		ae({
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
		return D((e, t) => this.hasSlotController.test(e, t), this.instructions, this.withInstructions);
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
		let e = this.hasLabelContent(), t = this.hasInstructionsContent();
		return x`
            <div part="form-control" class="form-control">
                ${e ? x`
                        <label
                            part="label"
                            class="form-control__label"
                            id=${`${this.controlId}-label`}
                            for=${`${this.controlId}-control`}
                        >
                            <slot name="label">${this.label}</slot>
                        </label>
                    ` : _}

                ${t ? x`
                        <p
                            part="instructions"
                            class="form-control__instructions"
                            id=${`${this.controlId}-instructions`}
                        >
                            <slot name="instructions">${this.instructions}</slot>
                            <slot name="hint"></slot>
                        </p>
                    ` : _}

                <textarea
                    part="textarea"
                    class="textarea"
                    id=${e ? `${this.controlId}-control` : _}
                    rows=${te(this.fitCell ? this.rows ?? 1 : this.rows)}
                    .value=${ne(this.value)}
                    placeholder=${this.placeholder || _}
                    maxlength=${te(this.maxlength)}
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
t([f("textarea")], M.prototype, "input", void 0), t([i()], M.prototype, "placeholder", void 0), t([h()], M.prototype, "value", null), t([i({
	attribute: "value",
	reflect: !0
})], M.prototype, "defaultValue", void 0), t([i({ reflect: !0 })], M.prototype, "size", void 0), t([i()], M.prototype, "label", void 0), t([i()], M.prototype, "instructions", void 0), t([i({
	type: Boolean,
	reflect: !0
})], M.prototype, "readonly", void 0), t([i({
	type: Boolean,
	reflect: !0
})], M.prototype, "invalid", void 0), t([i({
	type: Boolean,
	reflect: !0,
	attribute: "fit-cell"
})], M.prototype, "fitCell", void 0), t([i({ type: Number })], M.prototype, "rows", void 0), t([i({
	type: Number,
	attribute: "max-length"
})], M.prototype, "maxlength", void 0), t([i({
	attribute: "with-label",
	type: Boolean
})], M.prototype, "withLabel", void 0), t([i({
	attribute: "with-instructions",
	type: Boolean
})], M.prototype, "withInstructions", void 0), M = t([e("pk-textarea")], M);
// node_modules/@verbb/plugin-kit-web/dist/chunks/styles-Bz7GOec0.js
var We = p`
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
`, Ge = class extends m {
	constructor(...e) {
		super(...e), this.htmlFor = "";
	}
	static {
		this.styles = [We, p`
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
		return x`<div class="mount"></div>`;
	}
};
t([i({ attribute: "for" })], Ge.prototype, "htmlFor", void 0), t([f(".mount")], Ge.prototype, "mount", void 0), Ge = t([e("pk-data-table-toolbar")], Ge);
// node_modules/@verbb/plugin-kit-web/dist/chunks/pk-checkbox-bfqcQikR.js
function Ke() {
	return x`
        <svg xmlns="http://www.w3.org/2000/svg" role="img" aria-hidden="true" viewBox="0 0 640 640">
            <path
                fill="currentColor"
                d="M557.5 192L534.9 214.6L278.9 470.6C266.4 483.1 246.1 483.1 233.6 470.6L105.6 342.6L83 320L128.3 274.7C129.6 276 172.3 318.7 256.3 402.7L489.7 169.3L512.3 146.7L557.6 192z"
            />
        </svg>
    `;
}
function qe() {
	return x`
        <svg xmlns="http://www.w3.org/2000/svg" role="img" aria-hidden="true" viewBox="0 0 640 640">
            <path fill="currentColor" d="M96 352V288H544V352H96z" />
        </svg>
    `;
}
var Je = [p`
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
`, p`
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
`], N = class extends ee {
	constructor(...e) {
		super(...e), this.assumeInteractionOn = ["change"], this.hasSlotController = new c(this, "hint"), this.checked = !1, this.indeterminate = !1, this.disabled = !1, this.invalid = !1, this.checkboxValue = "on", this.defaultChecked = !1, this.ariaLabel = null, this.hint = "", this.withHint = !1, this.hasDefaultSlotContent = !1;
	}
	static {
		this.shadowRootOptions = {
			mode: "open",
			delegatesFocus: !0
		};
	}
	static {
		this.styles = Je;
	}
	static get validators() {
		return [...super.validators, w({ validationProperty: "checked" })];
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
		let e = this.hasDefaultSlotContent, t = !!this.hint || this.hasSlotController.test("hint", this.withHint);
		return x`
            <label
                part="base"
                class=${d({
			root: !0,
			"root--with-hint": t
		})}
            >
                <input
                    part="input"
                    class="input"
                    type="checkbox"
                    .checked=${this.checked}
                    ?disabled=${this.disabled}
                    ?required=${this.required}
                    name=${this.name ?? _}
                    value=${this.checkboxValue}
                    aria-labelledby=${e ? "label" : _}
                    aria-describedby=${t ? "hint" : _}
                    aria-label=${e ? _ : this.ariaLabel ?? _}
                    aria-invalid=${this.invalid ? "true" : _}
                    @change=${this.handleChange}
                />
                <span part="control" class="control">
                    <span part="checked-icon" class="icon-check">${Ke()}</span>
                    <span part="indeterminate-icon" class="icon-indeterminate">${qe()}</span>
                </span>
                ${e || t ? x`
                        <span class="text">
                            ${e ? x`
                                    <span part="label" class="label" id="label">
                                        <slot @slotchange=${this.defaultSlotChanged}></slot>
                                    </span>
                                ` : x`<slot @slotchange=${this.defaultSlotChanged} hidden></slot>`}
                            ${t ? x`
                                    <span part="hint" class="hint" id="hint">
                                        <slot name="hint">${this.hint}</slot>
                                    </span>
                                ` : _}
                        </span>
                    ` : x`<slot @slotchange=${this.defaultSlotChanged} hidden></slot>`}
            </label>
        `;
	}
};
t([i({
	type: Boolean,
	reflect: !0
})], N.prototype, "checked", void 0), t([i({
	type: Boolean,
	reflect: !0
})], N.prototype, "indeterminate", void 0), t([i({
	type: Boolean,
	reflect: !0
})], N.prototype, "disabled", void 0), t([i({
	type: Boolean,
	reflect: !0
})], N.prototype, "invalid", void 0), t([i()], N.prototype, "checkboxValue", void 0), t([i({
	attribute: "default-checked",
	type: Boolean
})], N.prototype, "defaultChecked", void 0), t([i({ attribute: "aria-label" })], N.prototype, "ariaLabel", void 0), t([i()], N.prototype, "hint", void 0), t([i({
	type: Boolean,
	attribute: "with-hint"
})], N.prototype, "withHint", void 0), t([f(".input")], N.prototype, "input", void 0), t([h()], N.prototype, "hasDefaultSlotContent", void 0), N = t([e("pk-checkbox")], N);
// node_modules/@verbb/plugin-kit-web/dist/chunks/popup-placement-animation-ClUYI79z.js
function Ye(e) {
	let t = e.split("-")[0];
	return t === "inline-start" ? "left" : t === "inline-end" ? "right" : t === "top" || t === "bottom" || t === "left" || t === "right" ? t : "bottom";
}
function Xe(e, t, n, r, i) {
	let a = Ye(e), o = t.x + t.width / 2 - n.x, s = t.y + t.height / 2 - n.y;
	return Math.abs(i?.y ?? 0) > r && (a === "top" || a === "bottom") ? `${o}px ${t.y + t.height / 2 - n.y}px` : {
		top: `${o}px calc(100% + ${r}px)`,
		bottom: `${o}px ${-r}px`,
		left: `calc(100% + ${r}px) ${s}px`,
		right: `${-r}px ${s}px`
	}[a];
}
function Ze(e, t) {
	t ? e.setAttribute("data-side", Ye(t)) : e.removeAttribute("data-side");
}
function Qe(e, t, n = 100, r) {
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
// node_modules/@verbb/plugin-kit-web/dist/chunks/supports-popover-BCqyOPtO.js
var $e = globalThis.HTMLElement !== void 0 && Object.prototype.hasOwnProperty.call(globalThis.HTMLElement.prototype, "popover"), P = Math.min, F = Math.max, et = Math.round, tt = Math.floor, I = (e) => ({
	x: e,
	y: e
}), nt = {
	left: "right",
	right: "left",
	bottom: "top",
	top: "bottom"
};
function rt(e, t, n) {
	return F(e, P(t, n));
}
function it(e, t) {
	return typeof e == "function" ? e(t) : e;
}
function at(e) {
	return e.split("-")[0];
}
function ot(e) {
	return e.split("-")[1];
}
function st(e) {
	return e === "x" ? "y" : "x";
}
function ct(e) {
	return e === "y" ? "height" : "width";
}
function L(e) {
	let t = e[0];
	return t === "t" || t === "b" ? "y" : "x";
}
function lt(e) {
	return st(L(e));
}
function ut(e, t, n) {
	n === void 0 && (n = !1);
	let r = ot(e), i = lt(e), a = ct(i), o = i === "x" ? r === (n ? "end" : "start") ? "right" : "left" : r === "start" ? "bottom" : "top";
	return t.reference[a] > t.floating[a] && (o = yt(o)), [o, yt(o)];
}
function dt(e) {
	let t = yt(e);
	return [
		ft(e),
		t,
		ft(t)
	];
}
function ft(e) {
	return e.includes("start") ? e.replace("start", "end") : e.replace("end", "start");
}
var pt = ["left", "right"], mt = ["right", "left"], ht = ["top", "bottom"], gt = ["bottom", "top"];
function _t(e, t, n) {
	switch (e) {
		case "top":
		case "bottom": return n ? t ? mt : pt : t ? pt : mt;
		case "left":
		case "right": return t ? ht : gt;
		default: return [];
	}
}
function vt(e, t, n, r) {
	let i = ot(e), a = _t(at(e), n === "start", r);
	return i && (a = a.map((e) => e + "-" + i), t && (a = a.concat(a.map(ft)))), a;
}
function yt(e) {
	let t = at(e);
	return nt[t] + e.slice(t.length);
}
function bt(e) {
	return {
		top: e.top ?? 0,
		right: e.right ?? 0,
		bottom: e.bottom ?? 0,
		left: e.left ?? 0
	};
}
function xt(e) {
	return typeof e == "number" ? {
		top: e,
		right: e,
		bottom: e,
		left: e
	} : bt(e);
}
function St(e) {
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
// node_modules/@floating-ui/core/dist/floating-ui.core.mjs
function Ct(e, t, n) {
	let { reference: r, floating: i } = e, a = L(t), o = lt(t), s = ct(o), c = at(t), l = a === "y", u = r.x + r.width / 2 - i.width / 2, d = r.y + r.height / 2 - i.height / 2, f = r[s] / 2 - i[s] / 2, p;
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
	let m = ot(t);
	return m && (p[o] += f * (m === "end" ? 1 : -1) * (n && l ? -1 : 1)), p;
}
async function wt(e, t) {
	t === void 0 && (t = {});
	let { x: n, y: r, platform: i, rects: a, elements: o, strategy: s } = e, { boundary: c = "clippingAncestors", rootBoundary: l = "viewport", elementContext: u = "floating", altBoundary: d = !1, padding: f = 0 } = it(t, e), p = xt(f), m = o[d ? u === "floating" ? "reference" : "floating" : u], h = St(await i.getClippingRect({
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
	}, y = St(i.convertOffsetParentRelativeRectToViewportRelativeRect ? await i.convertOffsetParentRelativeRectToViewportRelativeRect({
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
var Tt = 50, Et = async (e, t, n) => {
	let { placement: r = "bottom", strategy: i = "absolute", middleware: a = [], platform: o } = n, s = o.detectOverflow ? o : {
		...o,
		detectOverflow: wt
	}, c = await (o.isRTL == null ? void 0 : o.isRTL(t)), l = await o.getElementRects({
		reference: e,
		floating: t,
		strategy: i
	}), { x: u, y: d } = Ct(l, r, c), f = r, p = 0, m = {};
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
		}, x && p < Tt && (p++, typeof x == "object" && (x.placement && (f = x.placement), x.rects && (l = x.rects === !0 ? await o.getElementRects({
			reference: e,
			floating: t,
			strategy: i
		}) : x.rects), {x: u, y: d} = Ct(l, f, c)), n = -1);
	}
	return {
		x: u,
		y: d,
		placement: f,
		strategy: i,
		middlewareData: m
	};
}, Dt = (e) => ({
	name: "arrow",
	options: e,
	async fn(t) {
		let { x: n, y: r, placement: i, rects: a, platform: o, elements: s, middlewareData: c } = t, { element: l, padding: u = 0 } = it(e, t) || {};
		if (l == null) return {};
		let d = xt(u), f = {
			x: n,
			y: r
		}, p = lt(i), m = ct(p), h = await o.getDimensions(l), g = p === "y", _ = g ? "top" : "left", v = g ? "bottom" : "right", y = g ? "clientHeight" : "clientWidth", b = a.reference[m] + a.reference[p] - f[p] - a.floating[m], x = f[p] - a.reference[p], S = await (o.getOffsetParent == null ? void 0 : o.getOffsetParent(l)), C = S ? S[y] : 0;
		C && await (o.isElement == null ? void 0 : o.isElement(S)) || (C = s.floating[y] || a.floating[m]);
		let w = b / 2 - x / 2, T = C / 2 - h[m] / 2 - 1, E = P(d[_], T), ee = P(d[v], T), te = C - h[m] - ee, D = C / 2 - h[m] / 2 + w, ne = rt(E, D, te), re = !c.arrow && ot(i) != null && D !== ne && a.reference[m] / 2 - (D < E ? E : ee) - h[m] / 2 < 0, ie = re ? D < E ? D - E : D - te : 0;
		return {
			[p]: f[p] + ie,
			data: {
				[p]: ne,
				centerOffset: D - ne - ie,
				...re && { alignmentOffset: ie }
			},
			reset: re
		};
	}
}), Ot = function(e) {
	return e === void 0 && (e = {}), {
		name: "flip",
		options: e,
		async fn(t) {
			var n;
			let { placement: r, middlewareData: i, rects: a, initialPlacement: o, platform: s, elements: c } = t, { mainAxis: l = !0, crossAxis: u = !0, fallbackPlacements: d, fallbackStrategy: f = "bestFit", fallbackAxisSideDirection: p = "none", flipAlignment: m = !0, ...h } = it(e, t);
			if ((n = i.arrow) != null && n.alignmentOffset) return {};
			let g = at(r), _ = L(o), v = at(o) === o, y = await (s.isRTL == null ? void 0 : s.isRTL(c.floating)), b = d || (v || !m ? [yt(o)] : dt(o)), x = p !== "none";
			!d && x && b.push(...vt(o, m, p, y));
			let S = [o, ...b], C = await s.detectOverflow(t, h), w = [], T = i.flip?.overflows || [];
			if (l && w.push(C[g]), u) {
				let e = ut(r, a, y);
				w.push(C[e[0]], C[e[1]]);
			}
			if (T = [...T, {
				placement: r,
				overflows: w
			}], !w.every((e) => e <= 0)) {
				let e = (i.flip?.index || 0) + 1, t = S[e];
				if (t && (u !== "alignment" || _ === L(t) || T.every((e) => L(e.placement) !== _ || e.overflows[0] > 0))) return {
					data: {
						index: e,
						overflows: T
					},
					reset: { placement: t }
				};
				let n = T.filter((e) => e.overflows[0] <= 0).sort((e, t) => e.overflows[1] - t.overflows[1])[0]?.placement;
				if (!n) switch (f) {
					case "bestFit": {
						let e = T.filter((e) => {
							if (x) {
								let t = L(e.placement);
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
}, kt = /*#__PURE__*/ new Set(["left", "top"]);
async function At(e, t) {
	let { placement: n, platform: r, elements: i } = e, a = await (r.isRTL == null ? void 0 : r.isRTL(i.floating)), o = at(n), s = ot(n), c = L(n) === "y", l = kt.has(o) ? -1 : 1, u = a && c ? -1 : 1, d = it(t, e), { mainAxis: f, crossAxis: p, alignmentAxis: m } = typeof d == "number" ? {
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
var jt = function(e) {
	return e === void 0 && (e = 0), {
		name: "offset",
		options: e,
		async fn(t) {
			var n;
			let { x: r, y: i, placement: a, middlewareData: o } = t, s = await At(t, e);
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
}, Mt = function(e) {
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
			} }, ...l } = it(e, t), u = {
				x: n,
				y: r
			}, d = await a.detectOverflow(t, l), f = L(i), p = st(f), m = u[p], h = u[f], g = (e, t) => rt(t + d[e === "y" ? "top" : "left"], t, t - d[e === "y" ? "bottom" : "right"]);
			o && (m = g(p, m)), s && (h = g(f, h));
			let _ = c.fn({
				...t,
				[p]: m,
				[f]: h
			});
			return {
				..._,
				data: {
					x: _.x - n,
					y: _.y - r,
					enabled: {
						[p]: o,
						[f]: s
					}
				}
			};
		}
	};
}, Nt = function(e) {
	return e === void 0 && (e = {}), {
		name: "size",
		options: e,
		async fn(t) {
			let { placement: n, rects: r, platform: i, elements: a } = t, { apply: o = () => {}, ...s } = it(e, t), c = await i.detectOverflow(t, s), l = at(n), u = ot(n), d = L(n) === "y", { width: f, height: p } = r.floating, m, h;
			l === "top" || l === "bottom" ? (m = l, h = u === (await (i.isRTL == null ? void 0 : i.isRTL(a.floating)) ? "start" : "end") ? "left" : "right") : (h = l, m = u === "end" ? "top" : "bottom");
			let g = p - c.top - c.bottom, _ = f - c.left - c.right, v = P(p - c[m], g), y = P(f - c[h], _), b = t.middlewareData.shift, x = !b, S = v, C = y;
			b != null && b.enabled.x && (C = _), b != null && b.enabled.y && (S = g), x && !u && (d ? C = f - 2 * F(c.left, c.right) : S = p - 2 * F(c.top, c.bottom)), await o({
				...t,
				availableWidth: C,
				availableHeight: S
			});
			let w = await i.getDimensions(a.floating);
			return f !== w.width || p !== w.height ? { reset: { rects: !0 } } : {};
		}
	};
};
// node_modules/@floating-ui/utils/dist/floating-ui.utils.dom.mjs
function Pt() {
	return typeof window < "u";
}
function Ft(e) {
	return It(e) ? (e.nodeName || "").toLowerCase() : "#document";
}
function R(e) {
	var t;
	return (e == null || (t = e.ownerDocument) == null ? void 0 : t.defaultView) || window;
}
function z(e) {
	return ((It(e) ? e.ownerDocument : e.document) || window.document)?.documentElement;
}
function It(e) {
	return Pt() ? e instanceof Node || e instanceof R(e).Node : !1;
}
function B(e) {
	return Pt() ? e instanceof Element || e instanceof R(e).Element : !1;
}
function Lt(e) {
	return Pt() ? e instanceof HTMLElement || e instanceof R(e).HTMLElement : !1;
}
function Rt(e) {
	return !Pt() || typeof ShadowRoot > "u" ? !1 : e instanceof ShadowRoot || e instanceof R(e).ShadowRoot;
}
function zt(e) {
	let { overflow: t, overflowX: n, overflowY: r, display: i } = V(e);
	return /auto|scroll|overlay|hidden|clip/.test(t + r + n) && i !== "inline" && i !== "contents";
}
function Bt(e) {
	return /^(table|td|th)$/.test(Ft(e));
}
function Vt(e) {
	try {
		if (e.matches(":popover-open")) return !0;
	} catch {}
	try {
		return e.matches(":modal");
	} catch {
		return !1;
	}
}
var Ht = /transform|translate|scale|rotate|perspective|filter/, Ut = /paint|layout|strict|content/, Wt = (e) => !!e && e !== "none", Gt;
function Kt(e) {
	let t = B(e) ? V(e) : e;
	return Wt(t.transform) || Wt(t.translate) || Wt(t.scale) || Wt(t.rotate) || Wt(t.perspective) || !Jt() && (Wt(t.backdropFilter) || Wt(t.filter)) || Ht.test(t.willChange || "") || Ut.test(t.contain || "");
}
function qt(e) {
	let t = Zt(e);
	for (; Lt(t) && !Yt(t);) {
		if (Kt(t)) return t;
		if (Vt(t)) return null;
		t = Zt(t);
	}
	return null;
}
function Jt() {
	return Gt ??= typeof CSS < "u" && CSS.supports && CSS.supports("-webkit-backdrop-filter", "none"), Gt;
}
function Yt(e) {
	return /^(html|body|#document)$/.test(Ft(e));
}
function V(e) {
	return R(e).getComputedStyle(e);
}
function Xt(e) {
	return B(e) ? {
		scrollLeft: e.scrollLeft,
		scrollTop: e.scrollTop
	} : {
		scrollLeft: e.scrollX,
		scrollTop: e.scrollY
	};
}
function Zt(e) {
	if (Ft(e) === "html") return e;
	let t = e.assignedSlot || e.parentNode || Rt(e) && e.host || z(e);
	return Rt(t) ? t.host : t;
}
function Qt(e) {
	let t = Zt(e);
	return Yt(t) ? (e.ownerDocument || e).body : Lt(t) && zt(t) ? t : Qt(t);
}
function $t(e, t, n) {
	t === void 0 && (t = []), n === void 0 && (n = !0);
	let r = Qt(e), i = r === e.ownerDocument?.body, a = R(r);
	if (i) {
		let e = en(a);
		return t.concat(a, a.visualViewport || [], zt(r) ? r : [], e && n ? $t(e) : []);
	}
	return t.concat(r, $t(r, [], n));
}
function en(e) {
	return e.parent && Object.getPrototypeOf(e.parent) ? e.frameElement : null;
}
// node_modules/@floating-ui/dom/dist/floating-ui.dom.mjs
function tn(e) {
	let t = V(e), n = parseFloat(t.width) || 0, r = parseFloat(t.height) || 0, i = Lt(e), a = i ? e.offsetWidth : n, o = i ? e.offsetHeight : r, s = et(n) !== a || et(r) !== o;
	return s && (n = a, r = o), {
		width: n,
		height: r,
		$: s
	};
}
function nn(e) {
	return B(e) ? e : e.contextElement;
}
function rn(e) {
	let t = nn(e);
	if (!Lt(t)) return I(1);
	let n = t.getBoundingClientRect(), { width: r, height: i, $: a } = tn(t), o = (a ? et(n.width) : n.width) / r, s = (a ? et(n.height) : n.height) / i;
	return o && Number.isFinite(o) || (o = 1), s && Number.isFinite(s) || (s = 1), {
		x: o,
		y: s
	};
}
var an = /*#__PURE__*/ I(0);
function on(e) {
	let t = R(e);
	return !Jt() || !t.visualViewport ? an : {
		x: t.visualViewport.offsetLeft,
		y: t.visualViewport.offsetTop
	};
}
function sn(e, t, n) {
	return t === void 0 && (t = !1), !!n && t && n === R(e);
}
function cn(e, t, n, r) {
	t === void 0 && (t = !1), n === void 0 && (n = !1);
	let i = e.getBoundingClientRect(), a = nn(e), o = I(1);
	t && (r ? B(r) && (o = rn(r)) : o = rn(e));
	let s = sn(a, n, r) ? on(a) : I(0), c = (i.left + s.x) / o.x, l = (i.top + s.y) / o.y, u = i.width / o.x, d = i.height / o.y;
	if (a && r) {
		let e = R(a), t = B(r) ? R(r) : r, n = e, i = en(n);
		for (; i && t !== n;) {
			let e = rn(i), t = i.getBoundingClientRect(), r = V(i), a = t.left + (i.clientLeft + parseFloat(r.paddingLeft)) * e.x, o = t.top + (i.clientTop + parseFloat(r.paddingTop)) * e.y;
			c *= e.x, l *= e.y, u *= e.x, d *= e.y, c += a, l += o, n = R(i), i = en(n);
		}
	}
	return St({
		width: u,
		height: d,
		x: c,
		y: l
	});
}
function ln(e, t) {
	let n = Xt(e).scrollLeft;
	return t ? t.left + n : cn(z(e)).left + n;
}
function un(e, t) {
	let n = e.getBoundingClientRect();
	return {
		x: n.left + t.scrollLeft - ln(e, n),
		y: n.top + t.scrollTop
	};
}
function dn(e) {
	let { elements: t, rect: n, offsetParent: r, strategy: i } = e, a = i === "fixed", o = z(r), s = t ? Vt(t.floating) : !1;
	if (r === o || s && a) return n;
	let c = {
		scrollLeft: 0,
		scrollTop: 0
	}, l = I(1), u = I(0), d = Lt(r);
	if ((d || !a) && ((Ft(r) !== "body" || zt(o)) && (c = Xt(r)), d)) {
		let e = cn(r);
		l = rn(r), u.x = e.x + r.clientLeft, u.y = e.y + r.clientTop;
	}
	let f = o && !d && !a ? un(o, c) : I(0);
	return {
		width: n.width * l.x,
		height: n.height * l.y,
		x: n.x * l.x - c.scrollLeft * l.x + u.x + f.x,
		y: n.y * l.y - c.scrollTop * l.y + u.y + f.y
	};
}
function fn(e) {
	return e.getClientRects ? Array.from(e.getClientRects()) : [];
}
function pn(e) {
	let t = Xt(e), n = e.ownerDocument.body, r = F(e.scrollWidth, e.clientWidth, n.scrollWidth, n.clientWidth), i = F(e.scrollHeight, e.clientHeight, n.scrollHeight, n.clientHeight), a = -t.scrollLeft + ln(e), o = -t.scrollTop;
	return V(n).direction === "rtl" && (a += F(e.clientWidth, n.clientWidth) - r), {
		width: r,
		height: i,
		x: a,
		y: o
	};
}
var mn = 25;
function hn(e, t, n) {
	n === void 0 && (n = "viewport");
	let r = n === "layoutViewport", i = R(e), a = z(e), o = i.visualViewport, s = a.clientWidth, c = a.clientHeight, l = 0, u = 0;
	if (o) {
		let e = !Jt() || t === "fixed";
		r ? e || (l = -o.offsetLeft, u = -o.offsetTop) : (s = o.width, c = o.height, e && (l = o.offsetLeft, u = o.offsetTop));
	}
	if (ln(a) <= 0) {
		let e = a.ownerDocument, t = e.body, n = getComputedStyle(t), r = e.compatMode === "CSS1Compat" && parseFloat(n.marginLeft) + parseFloat(n.marginRight) || 0, i = Math.abs(a.clientWidth - t.clientWidth - r), o = getComputedStyle(a).scrollbarGutter === "stable both-edges" ? i / 2 : i;
		o <= mn && (s -= o);
	}
	return {
		width: s,
		height: c,
		x: l,
		y: u
	};
}
function gn(e, t) {
	let n = cn(e, !0, t === "fixed"), r = n.top + e.clientTop, i = n.left + e.clientLeft, a = rn(e);
	return {
		width: e.clientWidth * a.x,
		height: e.clientHeight * a.y,
		x: i * a.x,
		y: r * a.y
	};
}
function _n(e, t, n) {
	let r;
	if (t === "viewport" || t === "layoutViewport") r = hn(e, n, t);
	else if (t === "document") r = pn(z(e));
	else if (B(t)) r = gn(t, n);
	else {
		let n = on(e);
		r = {
			x: t.x - n.x,
			y: t.y - n.y,
			width: t.width,
			height: t.height
		};
	}
	return St(r);
}
function vn(e, t) {
	let n = t.get(e);
	if (n) return n;
	let r = $t(e, [], !1).filter((e) => B(e) && Ft(e) !== "body"), i = null, a = V(e).position === "fixed", o = a ? Zt(e) : e;
	for (; B(o) && !Yt(o);) {
		let e = V(o), t = Kt(o), n = i ? i.position : a ? "fixed" : "";
		!t && (n === "fixed" || n === "absolute" && e.position === "static") ? r = r.filter((e) => e !== o) : i = e, o = Zt(o);
	}
	return t.set(e, r), r;
}
function yn(e) {
	let { element: t, boundary: n, rootBoundary: r, strategy: i } = e, a = [...n === "clippingAncestors" ? Vt(t) ? [] : vn(t, this._c) : [].concat(n), r], o = _n(t, a[0], i), s = o.top, c = o.right, l = o.bottom, u = o.left;
	for (let e = 1; e < a.length; e++) {
		let n = _n(t, a[e], i);
		s = F(n.top, s), c = P(n.right, c), l = P(n.bottom, l), u = F(n.left, u);
	}
	return {
		width: c - u,
		height: l - s,
		x: u,
		y: s
	};
}
function bn(e) {
	let { width: t, height: n } = tn(e);
	return {
		width: t,
		height: n
	};
}
function xn(e, t, n) {
	let r = Lt(t), i = z(t), a = n === "fixed", o = cn(e, !0, a, t), s = {
		scrollLeft: 0,
		scrollTop: 0
	}, c = I(0);
	if ((r || !a) && ((Ft(t) !== "body" || zt(i)) && (s = Xt(t)), r)) {
		let e = cn(t, !0, a, t);
		c.x = e.x + t.clientLeft, c.y = e.y + t.clientTop;
	}
	!r && i && (c.x = ln(i));
	let l = i && !r && !a ? un(i, s) : I(0);
	return {
		x: o.left + s.scrollLeft - c.x - l.x,
		y: o.top + s.scrollTop - c.y - l.y,
		width: o.width,
		height: o.height
	};
}
function Sn(e) {
	return V(e).position === "static";
}
function Cn(e, t) {
	if (!Lt(e) || V(e).position === "fixed") return null;
	if (t) return t(e);
	let n = e.offsetParent;
	return z(e) === n && (n = n.ownerDocument.body), n;
}
function wn(e, t) {
	let n = R(e);
	if (Vt(e)) return n;
	if (!Lt(e)) {
		let t = Zt(e);
		for (; t && !Yt(t);) {
			if (B(t) && !Sn(t)) return t;
			t = Zt(t);
		}
		return n;
	}
	let r = Cn(e, t);
	for (; r && Bt(r) && Sn(r);) r = Cn(r, t);
	return r && Yt(r) && Sn(r) && !Kt(r) ? n : r || qt(e) || n;
}
var Tn = async function(e) {
	let t = this.getOffsetParent || wn, n = this.getDimensions, r = await n(e.floating);
	return {
		reference: xn(e.reference, await t(e.floating), e.strategy),
		floating: {
			x: 0,
			y: 0,
			width: r.width,
			height: r.height
		}
	};
};
function En(e) {
	return V(e).direction === "rtl";
}
var Dn = {
	convertOffsetParentRelativeRectToViewportRelativeRect: dn,
	getDocumentElement: z,
	getClippingRect: yn,
	getOffsetParent: wn,
	getElementRects: Tn,
	getClientRects: fn,
	getDimensions: bn,
	getScale: rn,
	isElement: B,
	isRTL: En
};
function On(e, t) {
	return e.x === t.x && e.y === t.y && e.width === t.width && e.height === t.height;
}
function kn(e, t, n) {
	let r = null, i, a = z(e);
	function o() {
		var e;
		clearTimeout(i), (e = r) == null || e.disconnect(), r = null;
	}
	function s(n, c) {
		n === void 0 && (n = !1), c === void 0 && (c = 1), o();
		let l = e.getBoundingClientRect(), { left: u, top: d, width: f, height: p } = l;
		if (n || t(), !f || !p) return;
		let m = tt(d), h = tt(a.clientWidth - (u + f)), g = tt(a.clientHeight - (d + p)), _ = tt(u), v = {
			rootMargin: -m + "px " + -h + "px " + -g + "px " + -_ + "px",
			threshold: F(0, P(1, c)) || 1
		}, y = !0;
		function b(t) {
			let n = t[0].intersectionRatio;
			if (!On(l, e.getBoundingClientRect())) return s();
			if (n !== c) {
				if (!y) return s();
				n ? s(!1, n) : i = setTimeout(() => {
					s(!1, 1e-7);
				}, 1e3);
			}
			y = !1;
		}
		try {
			r = new IntersectionObserver(b, {
				...v,
				root: a.ownerDocument
			});
		} catch {
			r = new IntersectionObserver(b, v);
		}
		r.observe(e);
	}
	let c = R(e), l = () => s(n);
	return c.addEventListener("resize", l), s(!0), () => {
		c.removeEventListener("resize", l), o();
	};
}
function An(e, t, n, r) {
	r === void 0 && (r = {});
	let { ancestorScroll: i = !0, ancestorResize: a = !0, elementResize: o = typeof ResizeObserver == "function", layoutShift: s = typeof IntersectionObserver == "function", animationFrame: c = !1 } = r, l = nn(e), u = i || a ? [...l ? $t(l) : [], ...t ? $t(t) : []] : [];
	u.forEach((e) => {
		i && e.addEventListener("scroll", n), a && e.addEventListener("resize", n);
	});
	let d = l && s ? kn(l, n, a) : null, f = -1, p = null;
	o && (p = new ResizeObserver((e) => {
		let [r] = e;
		r && r.target === l && p && t && (p.unobserve(t), cancelAnimationFrame(f), f = requestAnimationFrame(() => {
			var e;
			(e = p) == null || e.observe(t);
		})), n();
	}), l && !c && p.observe(l), t && p.observe(t));
	let m, h = c ? cn(e) : null;
	c && g();
	function g() {
		let t = cn(e);
		h && !On(h, t) && n(), h = t, m = requestAnimationFrame(g);
	}
	return n(), () => {
		var e;
		u.forEach((e) => {
			i && e.removeEventListener("scroll", n), a && e.removeEventListener("resize", n);
		}), d?.(), (e = p) == null || e.disconnect(), p = null, c && cancelAnimationFrame(m);
	};
}
var jn = jt, Mn = Mt, Nn = Ot, Pn = Nt, Fn = Dt, In = (e, t, n) => {
	let r = /* @__PURE__ */ new Map(), i = n ?? {}, a = {
		...Dn,
		...i.platform,
		_c: r
	};
	return Et(e, t, {
		...i,
		platform: a
	});
};
// node_modules/composed-offset-position/dist/composed-offset-position.browser.min.mjs
function Ln(e) {
	return zn(e);
}
function Rn(e) {
	return e.assignedSlot ? e.assignedSlot : e.parentNode instanceof ShadowRoot ? e.parentNode.host : e.parentNode;
}
function zn(e) {
	for (let t = e; t; t = Rn(t)) if (t instanceof Element && getComputedStyle(t).display === "none") return null;
	for (let t = Rn(e); t; t = Rn(t)) {
		if (!(t instanceof Element)) continue;
		let e = getComputedStyle(t);
		if (e.display !== "contents" && (e.position !== "static" || Kt(e) || t.tagName === "BODY")) return t;
	}
	return null;
}
// node_modules/@verbb/plugin-kit-web/dist/chunks/pk-popup-BhAQq8-2.js
function Bn(e, t) {
	if (!t) return null;
	let n = e.getRootNode();
	if (n instanceof Document || n instanceof ShadowRoot) {
		let e = n.getElementById(t);
		if (e) return e;
	}
	return e.ownerDocument.getElementById(t);
}
var Vn = class extends Event {
	constructor() {
		super("pk-reposition", {
			bubbles: !0,
			cancelable: !1,
			composed: !0
		});
	}
}, Hn = p`
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
function Un(e) {
	return typeof e == "object" && !!e && "getBoundingClientRect" in e;
}
function Wn(e) {
	return e || ($e ? "absolute" : "fixed");
}
function Gn(e, t) {
	if ($e && !Un(e) && t === "scroll") return $t(e).filter((e) => e instanceof Element);
}
var H = class extends m {
	constructor(...e) {
		super(...e), this.anchor = "", this.active = !1, this.boundary = "viewport", this.placement = "bottom-start", this.distance = 4, this.skidding = 0, this.flip = !0, this.flipFallbackPlacements = "", this.flipFallbackStrategy = "best-fit", this.flipPadding = 8, this.shift = !0, this.shiftPadding = 8, this.arrow = !1, this.arrowPlacement = "anchor", this.arrowPadding = 10, this.autoSizePadding = 8, this.anchorTracking = !0, this.hoverBridge = !1, this.anchorElement = null, this.settlingInitialPosition = !1, this.settleGeneration = 0;
	}
	static {
		this.styles = Hn;
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
		let r = Gn(this.anchorElement, this.boundary), i = [jn({
			mainAxis: this.distance,
			crossAxis: this.skidding
		})];
		this.sync ? i.push(Pn({ apply: ({ rects: e }) => {
			let n = this.sync === "width" || this.sync === "both", r = this.sync === "height" || this.sync === "both";
			t.style.width = n ? `${e.reference.width}px` : "", t.style.height = r ? `${e.reference.height}px` : "";
		} })) : (t.style.width = "", t.style.height = ""), this.flip && i.push(Nn({
			boundary: r,
			fallbackPlacements: this.flipFallbackPlacements ? this.flipFallbackPlacements.split(" ").map((e) => e.trim()).filter(Boolean) : void 0,
			fallbackStrategy: this.flipFallbackStrategy === "best-fit" ? "bestFit" : "initialPlacement",
			padding: this.flipPadding
		})), this.shift && i.push(Mn({
			boundary: r,
			padding: this.shiftPadding
		})), this.autoSize ? i.push(Pn({
			boundary: r,
			padding: this.autoSizePadding,
			apply: ({ availableHeight: e, availableWidth: n }) => {
				let r = this.autoSize === "horizontal" || this.autoSize === "both", i = this.autoSize === "vertical" || this.autoSize === "both";
				r ? t.style.setProperty("--pk-popup-available-width", `${Math.max(0, Math.floor(n))}px`) : t.style.removeProperty("--pk-popup-available-width"), i ? t.style.setProperty("--pk-popup-available-height", `${Math.max(0, Math.floor(e))}px`) : t.style.removeProperty("--pk-popup-available-height");
			}
		})) : (t.style.removeProperty("--pk-popup-available-width"), t.style.removeProperty("--pk-popup-available-height")), this.arrow && n && i.push(Fn({
			element: n,
			padding: this.arrowPadding
		}));
		let a = Wn(this.positionMethod), o = a === "fixed";
		t.classList.toggle("popup-fixed", o);
		let s = $e ? (e) => Dn.getOffsetParent(e, Ln) : Dn.getOffsetParent, { x: c, y: l, middlewareData: u, placement: d } = await In(this.anchorElement, t, {
			placement: this.placement,
			middleware: i,
			strategy: a,
			platform: {
				...Dn,
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
			let r = Xe(d, e, n, this.distance, u.shift);
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
		return requestAnimationFrame(() => this.updateHoverBridge()), e && this.dispatchEvent(new Vn()), !0;
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
		t ? (await this.frames(2), this.active && e === this.settleGeneration && (await this.repositionAsync(!1), t.offsetHeight, await this.frames(1), this.active && e === this.settleGeneration && (await this.repositionAsync(!1), this.active && e === this.settleGeneration && (t.classList.add("positioned"), this.settlingInitialPosition = !1, requestAnimationFrame(() => this.updateHoverBridge()), this.dispatchEvent(new Vn()))))) : this.settlingInitialPosition = !1;
	}
	resolveAnchor() {
		if (typeof this.anchor == "string" && this.anchor) {
			this.anchorElement = Bn(this, this.anchor);
			return;
		}
		if (this.anchor instanceof Element || Un(this.anchor)) {
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
		return $e && this.positionMethod !== "fixed";
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
		this.anchorElement && this.active && this.isConnected && this.popupElement && (this.popupElement.classList.remove("positioned"), this.settlingInitialPosition = !0, this.usesPopoverTopLayer() && this.popupElement.showPopover?.(), this.anchorTracking && (this.cleanup = An(this.anchorElement, this.popupElement, () => {
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
		let e = !$e || this.positionMethod === "fixed", t = this.usesPopoverTopLayer();
		return x`
            <slot name="anchor" @slotchange=${() => {
			this.handleAnchorChange();
		}}></slot>
            ${this.hoverBridge ? x`
                <div
                    part="hover-bridge"
                    class=${d({
			"hover-bridge": !0,
			"hover-bridge-visible": this.active
		})}
                    aria-hidden="true"
                ></div>
            ` : _}
            <div
                popover=${t ? "manual" : _}
                part="popup"
                class=${d({
			popup: !0,
			active: this.active,
			"popup-fixed": e
		})}
            >
                ${this.arrow ? x`<div part="arrow" class="arrow"></div>` : _}
                <slot></slot>
            </div>
        `;
	}
};
t([i()], H.prototype, "anchor", void 0), t([i({
	type: Boolean,
	reflect: !0
})], H.prototype, "active", void 0), t([i({ attribute: "position-method" })], H.prototype, "positionMethod", void 0), t([i({ reflect: !0 })], H.prototype, "boundary", void 0), t([i({ reflect: !0 })], H.prototype, "placement", void 0), t([i({ type: Number })], H.prototype, "distance", void 0), t([i({ type: Number })], H.prototype, "skidding", void 0), t([i({ type: Boolean })], H.prototype, "flip", void 0), t([i({ attribute: "flip-fallback-placements" })], H.prototype, "flipFallbackPlacements", void 0), t([i({ attribute: "flip-fallback-strategy" })], H.prototype, "flipFallbackStrategy", void 0), t([i({
	attribute: "flip-padding",
	type: Number
})], H.prototype, "flipPadding", void 0), t([i({ type: Boolean })], H.prototype, "shift", void 0), t([i({
	attribute: "shift-padding",
	type: Number
})], H.prototype, "shiftPadding", void 0), t([i({ type: Boolean })], H.prototype, "arrow", void 0), t([i({ attribute: "arrow-placement" })], H.prototype, "arrowPlacement", void 0), t([i({
	attribute: "arrow-padding",
	type: Number
})], H.prototype, "arrowPadding", void 0), t([i()], H.prototype, "sync", void 0), t([i({ attribute: "auto-size" })], H.prototype, "autoSize", void 0), t([i({
	attribute: "auto-size-padding",
	type: Number
})], H.prototype, "autoSizePadding", void 0), t([i({
	attribute: "anchor-tracking",
	type: Boolean
})], H.prototype, "anchorTracking", void 0), t([i({
	attribute: "hover-bridge",
	type: Boolean
})], H.prototype, "hoverBridge", void 0), t([f(".popup")], H.prototype, "popupElement", void 0), t([f(".arrow")], H.prototype, "arrowElement", void 0), H = t([e("pk-popup")], H);
// node_modules/@verbb/plugin-kit-web/dist/chunks/popup-pointer-Bh_d205v.js
function Kn(e) {
	if (e.panel instanceof Element) {
		let t = e.panel.closest("pk-popup");
		if (t) return t;
		let n = e.panel.getRootNode();
		if (n instanceof ShadowRoot && n.host.localName === "pk-popup") return n.host;
	}
	return e.host instanceof HTMLElement ? e.host.shadowRoot?.querySelector("pk-popup") ?? e.host.querySelector(":scope > pk-popup") ?? e.host.querySelector("pk-popup") : null;
}
function qn(e, t = {}) {
	let n = e.composedPath();
	if (t.host && n.includes(t.host) || t.anchor && n.includes(t.anchor) || t.panel && n.includes(t.panel)) return !0;
	let r = Kn(t);
	return r && n.includes(r) ? !0 : n.some((e) => e instanceof HTMLElement ? r && e.classList.contains("popup") && (e === r || r.contains(e)) ? !0 : t.extraMatches?.(e) ?? !1 : !1);
}
function Jn(e, t = {}) {
	return qn(e, t);
}
// node_modules/@verbb/plugin-kit-web/dist/chunks/popup-content-animation.styles-CJ8zGJz-.js
var Yn = p`
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
`, Xn = [
	s(),
	Yn,
	p`
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
], U = class extends m {
	constructor(...e) {
		super(...e), this.open = !1, this.label = "", this.placement = "bottom", this.sideOffset = 4, this.flush = !1, this.withArrow = !1, this.for = "", this.anchor = null, this.triggerElement = null, this.closing = !1, this.panelAnimated = !1, this.triggerId = E("pk-popover-trigger"), this.dismissRegistered = !1, this.syncingOpenSideEffects = !1, this.exitAnimationPromise = null, this.handleToggleClick = (e) => {
			e.preventDefault(), e.stopPropagation(), !this.closing && (this.open = !this.open);
		}, this.onDocumentPointerDown = (e) => {
			this.isPointerInside(e) || this.closing || this.closePopover("light-dismiss");
		}, this.onDocumentKeyDown = (e) => {
			e.key === "Escape" && le(this) && !this.closing && (e.preventDefault(), e.stopPropagation(), this.closePopover("escape"));
		};
	}
	static {
		this.styles = Xn;
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
		if (!this.dispatchEvent(new de())) {
			this.syncingOpenSideEffects = !0, this.open = !1, this.syncingOpenSideEffects = !1;
			return;
		}
		this.syncingOpenSideEffects = !0, this.open = !0, this.syncingOpenSideEffects = !1, this.closing = !1, this.panelAnimated = !1, this.panelElement && (this.panelElement.hidden = !1, Ze(this.panelElement, this.placement)), this.syncExpanded(), this.registerDismissHandlers(), await this.updateComplete;
		let e = await Qe(this.popupElement, this.placement, 300, { requireEvent: !0 });
		this.panelElement && Ze(this.panelElement, e), this.panelAnimated = !0, this.dispatchEvent(new fe()), this.dispatchEvent(new CustomEvent("pk-open-change", {
			detail: { open: !0 },
			bubbles: !0,
			composed: !0
		}));
	}
	async closePopover(e = "unknown", t = !1) {
		if (this.exitAnimationPromise) return this.exitAnimationPromise;
		if (!this.dismissRegistered && !this.closing && !this.open) return;
		let n = new pe(e);
		if (!this.dispatchEvent(n)) {
			this.syncingOpenSideEffects = !0, this.open = !0, this.syncingOpenSideEffects = !1, this.closing = !1, this.panelAnimated = !0;
			return;
		}
		this.unregisterDismissHandlers(), this.closing = !0, this.panelAnimated = !1, this.open && (this.syncingOpenSideEffects = !0, this.open = !1, this.syncingOpenSideEffects = !1);
		let r = async () => {
			t || (await this.updateComplete, await this.waitForExitAnimation());
			let n = !t && e !== "light-dismiss" && this.isConnected && ([...He()].some((e) => this.contains(e) || this.panelElement?.contains(e)) || (e === "close-button" || e === "escape") && document.activeElement === document.body);
			this.closing = !1, this.panelAnimated = !1, this.panelElement && (this.panelElement.hidden = !0, this.panelElement.removeAttribute("data-side")), this.syncExpanded(), n && this.getAnchorElement()?.focus({ preventScroll: !0 }), this.dispatchEvent(new me()), this.dispatchEvent(new CustomEvent("pk-open-change", {
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
		return this.anchor ? this.anchor : this.for ? Bn(this, this.for) : this.triggerElement ? this.triggerElement : null;
	}
	registerDismissHandlers() {
		this.dismissRegistered || (se(this), this.dismissRegistered = !0, document.addEventListener("pointerdown", this.onDocumentPointerDown, !0), document.addEventListener("keydown", this.onDocumentKeyDown, !0));
	}
	unregisterDismissHandlers() {
		this.dismissRegistered &&= (ce(this), !1), document.removeEventListener("pointerdown", this.onDocumentPointerDown, !0), document.removeEventListener("keydown", this.onDocumentKeyDown, !0);
	}
	isPointerInside(e) {
		return Jn(e, {
			host: this,
			anchor: this.getAnchorElement(),
			panel: this.panelElement
		});
	}
	getAnchorElement() {
		return this.anchor instanceof HTMLElement ? this.anchor : this.triggerElement ? this.triggerElement : this.for ? Bn(this, this.for) : null;
	}
	syncExpanded() {
		this.triggerElement?.setAttribute("aria-expanded", this.open ? "true" : "false");
	}
	render() {
		let e = this.getAnchor();
		return x`
            <slot name="trigger" @slotchange=${this.onTriggerSlotChange}></slot>
            <pk-popup
                .active=${this.open || this.closing}
                .anchor=${e ?? ""}
                .placement=${this.placement}
                .distance=${this.sideOffset}
                .arrow=${this.withArrow}
                flip
                shift
            >
                <div
                    part="panel"
                    role="dialog"
                    aria-label=${this.label || this.triggerElement?.getAttribute("aria-label") || this.triggerElement?.textContent?.trim() || _}
                    class=${d({
			panel: !0,
			"pk-popup-content": !0,
			closing: this.closing
		})}
                    ?hidden=${!this.open && !this.closing}
                    data-open=${this.panelAnimated && !this.closing ? "" : _}
                    tabindex=${this.open ? "-1" : _}
                >
                    <slot></slot>
                </div>
            </pk-popup>
        `;
	}
};
t([i({
	type: Boolean,
	reflect: !0
})], U.prototype, "open", void 0), t([i()], U.prototype, "label", void 0), t([i({ reflect: !0 })], U.prototype, "placement", void 0), t([i({
	attribute: "side-offset",
	type: Number
})], U.prototype, "sideOffset", void 0), t([i({
	type: Boolean,
	reflect: !0
})], U.prototype, "flush", void 0), t([i({
	attribute: "with-arrow",
	type: Boolean,
	reflect: !0
})], U.prototype, "withArrow", void 0), t([i({ reflect: !0 })], U.prototype, "for", void 0), t([i({ attribute: !1 })], U.prototype, "anchor", void 0), t([f("pk-popup")], U.prototype, "popupElement", void 0), t([h()], U.prototype, "triggerElement", void 0), t([h()], U.prototype, "closing", void 0), t([h()], U.prototype, "panelAnimated", void 0), U = t([e("pk-popover")], U);
// node_modules/@verbb/plugin-kit-web/dist/chunks/sync-listbox-separators-saLEtPpJ.js
var Zn = /* @__PURE__ */ new Set([
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
function Qn(e) {
	return e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey;
}
function $n(e) {
	return e.filter((e) => !e.hasAttribute("disabled") && !e.hasAttribute("hidden") && e.getAttribute("aria-disabled") !== "true" && e.getAttribute("aria-hidden") !== "true");
}
function er(e, t, n) {
	if (n) {
		n(t);
		return;
	}
	let r = e[t];
	r instanceof HTMLElement && "focusControl" in r && typeof r.focusControl == "function" ? r.focusControl() : r?.focus();
}
function tr(e) {
	if (!e) return;
	let t = e.shadowRoot?.querySelector(".option");
	t instanceof HTMLButtonElement ? t.click() : e.click();
}
function nr(e, t) {
	let n = $n(t.items), r = t.loop === !0;
	if (n.length === 0) return t.currentIndex;
	let i = Math.max(0, t.currentIndex), a = n[i] ?? n[0];
	switch (i = n.indexOf(a), i < 0 && (i = 0), e.key) {
		case "ArrowDown":
		case "ArrowRight": return e.preventDefault(), i = r && i >= n.length - 1 ? 0 : Math.min(i + 1, n.length - 1), er(n, i, t.focusItem), t.onSelect(i), i;
		case "ArrowUp":
		case "ArrowLeft": return e.preventDefault(), i = r && i <= 0 ? n.length - 1 : Math.max(i - 1, 0), er(n, i, t.focusItem), t.onSelect(i), i;
		case "Home": return e.preventDefault(), i = 0, er(n, i, t.focusItem), t.onSelect(i), i;
		case "End": return e.preventDefault(), i = n.length - 1, er(n, i, t.focusItem), t.onSelect(i), i;
		case "Enter":
		case " ": return t.multiselect || (e.preventDefault(), tr(n[i])), i;
		case "Escape": return e.preventDefault(), t.onClose?.(), i;
		default: return i;
	}
}
function rr(e, t) {
	let n = "", r = 0, i = () => {
		n = "", window.clearTimeout(r);
	};
	return {
		handleKey: (a) => {
			if (a.key.length !== 1 || a.ctrlKey || a.metaKey || a.altKey) return;
			n += a.key.toLowerCase(), window.clearTimeout(r), r = window.setTimeout(i, 750);
			let o = $n(e);
			for (let e = 0; e < o.length; e += 1) if ((o[e]?.textContent ?? "").trim().toLowerCase().startsWith(n)) {
				t(e), a.preventDefault();
				return;
			}
		},
		reset: i
	};
}
var ir = (e) => e.hidden || e.hasAttribute("data-pk-filter-empty"), ar = (e) => {
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
		r.hidden = !i || !a || ir(i) || ir(a);
	}
}, or = [Yn, p`
    ${n}
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
`], sr = l(r.chevronDown), W = class extends ee {
	constructor(...e) {
		super(...e), this.assumeInteractionOn = ["blur", "input"], this.open = !1, this.multiple = !1, this.placement = "bottom-start", this.sideOffset = 4, this.clearable = !1, this.withClear = !1, this.invalid = !1, this.size = "default", this.placeholder = "", this.value = "", this.defaultValue = "", this.values = [], this.defaultValues = [], this.ariaLabel = null, this.loopFocus = !1, this.hasSlotController = new c(this, "start", "end"), this.listboxId = E("pk-select-listbox"), this.triggerId = E("pk-select-trigger"), this.options = [], this.highlightedIndex = 0, this.dismissRegistered = !1, this.panelEventTarget = null, this.typeToSelect = rr([], () => {}), this.closing = !1, this.panelAnimated = !1, this.handleOptionsMutation = (e = {}) => {
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
					if (!le(this)) return;
					e.preventDefault(), e.stopPropagation(), this.closePanel("escape");
				} else (Zn.has(e.key) || Qn(e)) && qn(e, {
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
		this.styles = or;
	}
	static get validators() {
		return [
			...super.validators,
			C(),
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
		ar(this), this.syncValueInput(), this.syncTriggerDecorations(), this.open && this.syncHighlight();
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
		n && this.panelElement && (n.highlighted = !0, n.focusIndex = 0, e && we(n, this.panelElement, "vertical", "auto"));
	}
	updateTypeToSelect() {
		this.typeToSelect = rr(this.getEnabledVisibleOptions(), (e) => {
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
		this.dispatchEvent(new de()), this.closing = !1, this.panelAnimated = !1, this.open = !0, this.popupElement.active = !0, this.applySelection(), this.syncHighlightedIndexToSelection(), this.panelElement && (this.panelElement.style.removeProperty("height"), this.panelElement.hidden = !1, Ze(this.panelElement, this.placement));
		let t = e.getBoundingClientRect().width;
		return this.style.setProperty("--pk-select-anchor-width", `${t}px`), this.registerDismissHandlers(), this.syncHighlight(), this.updateTypeToSelect(), this.updateComplete.then(async () => {
			let e = await Qe(this.popupElement, this.placement, 300, { requireEvent: !0 });
			this.panelElement && Ze(this.panelElement, e), this.fitPanelToOptions(!0), this.observeOptionSizes(), this.panelAnimated = !0, this.bindPanelEvents(), this.refreshOptions(), this.getEnabledVisibleOptions()[this.highlightedIndex]?.focusControl(), this.dispatchEvent(new fe()), this.dispatchEvent(new CustomEvent("pk-open-change", {
				detail: { open: !0 },
				bubbles: !0,
				composed: !0
			}));
		});
	}
	async closePanel(e = "unknown") {
		if (!this.open || this.closing) return;
		let t = new pe(e);
		this.dispatchEvent(t) && (this.typeToSelect.reset(), this.optionResizeObserver?.disconnect(), cancelAnimationFrame(this.optionResizeFrame ?? 0), this.optionResizeFrame = void 0, this.unbindPanelEvents(), this.unregisterDismissHandlers(), this.closing = !0, this.panelAnimated = !1, await this.waitForExitAnimation(), this.open = !1, this.closing = !1, this.panelAnimated = !1, this.panelElement && (this.panelElement.hidden = !0, this.panelElement.removeAttribute("data-side")), this.popupElement.active = !1, this.dispatchEvent(new me()), this.dispatchEvent(new CustomEvent("pk-open-change", {
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
		se(this), this.dismissRegistered = !0, document.addEventListener("pointerdown", this.onDocumentPointerDown, !0), document.addEventListener("keydown", this.onDocumentKeyDown, !0);
	}
	unregisterDismissHandlers() {
		this.dismissRegistered &&= (ce(this), !1), document.removeEventListener("pointerdown", this.onDocumentPointerDown, !0), document.removeEventListener("keydown", this.onDocumentKeyDown, !0);
	}
	isPointerInside(e) {
		return Jn(e, {
			anchor: this.getPopupAnchor(),
			panel: this.panelElement
		});
	}
	removeTag(e, t) {
		t.preventDefault(), t.stopPropagation(), this.values = this.values.filter((t) => t !== e), this.applySelection(), this.emitValueChange();
	}
	handleClear(e) {
		e.preventDefault(), e.stopPropagation(), this.multiple ? this.values = [] : this.value = "", this.applySelection(), this.dispatchEvent(new ie()), this.emitValueChange(), this.triggerButton?.focus();
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
		this.highlightedIndex = nr(e, {
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
		return this.getSelectedOptions().map((e) => x`
            <span class="tag" part="tag">
                <span class="tag-label">${e.getLabel()}</span>
                <button
                    type="button"
                    class="tag-remove"
                    part="tag-remove"
                    aria-label=${`Remove ${e.getLabel()}`}
                    @click=${(t) => this.removeTag(e.value, t)}
                >
                    ×
                </button>
            </span>
        `);
	}
	renderChevronIcon() {
		return x`
            <span class="icon" aria-hidden="true">${u(sr)}</span>
        `;
	}
	renderHostDecorationSlot(e) {
		return this.hasSlotController.test(e) ? x`
            <span part=${e} class=${e === "start" ? "control-start" : "control-end"}>
                <slot name=${e}></slot>
            </span>
        ` : x`<slot name=${e} hidden></slot>`;
	}
	render() {
		let e = this.getDisplayValue(), t = !this.hasSelection(), n = (this.clearable || this.withClear) && this.hasSelection() && !this.disabled;
		return x`
            <input
                class="value-input"
                part="value-input"
                tabindex="-1"
                aria-hidden="true"
                .value=${this.multiple ? this.values.join(",") : this.value}
                ?required=${this.required}
                @input=${() => this.updateValidity()}
            />
            ${this.multiple ? x`
                    <div
                        part="control"
                        class=${d({
			control: !0,
			"is-disabled": this.disabled
		})}
                    >
                        ${this.renderHostDecorationSlot("start")}
                        ${this.hasSelection() ? x`
                                <div class="tags" part="tags">${this.renderTags()}</div>
                                ${n ? x`
                                        <button
                                            type="button"
                                            class="clear-button"
                                            part="clear-button"
                                            aria-label="Clear selection"
                                            @click=${this.handleClear}
                                        >
                                            ×
                                        </button>
                                    ` : _}
                            ` : x`
                                <button
                                    part="trigger"
                                    type="button"
                                    class="trigger"
                                    id=${this.triggerId}
                                    ?disabled=${this.disabled}
                                    aria-label=${this.ariaLabel ?? _}
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
                ` : x`
                    <button
                        part="control"
                        type="button"
                        class=${d({
			control: !0,
			"is-disabled": this.disabled
		})}
                        id=${this.triggerId}
                        ?disabled=${this.disabled}
                        aria-label=${this.ariaLabel ?? _}
                        aria-haspopup="listbox"
                        aria-expanded=${this.open ? "true" : "false"}
                        aria-controls=${this.listboxId}
                        @click=${this.togglePanel}
                    >
                        ${this.renderHostDecorationSlot("start")}
                        <span part="trigger-start" class="trigger-start"></span>
                        <span
                            class=${d({
			value: !0,
			"is-placeholder": t
		})}
                        >${e}</span>
                        ${n ? x`
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
                            ` : _}
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
                    class=${d({
			panel: !0,
			"pk-popup-content": !0,
			closing: this.closing
		})}
                    id=${this.listboxId}
                    role="listbox"
                    aria-multiselectable=${this.multiple ? "true" : "false"}
                    tabindex="-1"
                    ?hidden=${!this.open && !this.closing}
                    data-open=${this.panelAnimated && !this.closing ? "" : _}
                    @slotchange=${this.syncOptions}
                >
                    <slot></slot>
                </div>
            </pk-popup>
        `;
	}
};
t([i({
	type: Boolean,
	reflect: !0
})], W.prototype, "open", void 0), t([i({
	type: Boolean,
	reflect: !0
})], W.prototype, "multiple", void 0), t([i({ reflect: !0 })], W.prototype, "placement", void 0), t([i({
	attribute: "side-offset",
	type: Number
})], W.prototype, "sideOffset", void 0), t([i({
	type: Boolean,
	reflect: !0
})], W.prototype, "clearable", void 0), t([i({
	attribute: "with-clear",
	type: Boolean
})], W.prototype, "withClear", void 0), t([i({
	type: Boolean,
	reflect: !0
})], W.prototype, "invalid", void 0), t([i({ reflect: !0 })], W.prototype, "size", void 0), t([i({ reflect: !0 })], W.prototype, "width", void 0), t([i()], W.prototype, "placeholder", void 0), t([i()], W.prototype, "value", void 0), t([i({ attribute: "default-value" })], W.prototype, "defaultValue", void 0), t([i({
	type: Array,
	attribute: !1
})], W.prototype, "values", void 0), t([i({ attribute: !1 })], W.prototype, "defaultValues", void 0), t([i({ attribute: "aria-label" })], W.prototype, "ariaLabel", void 0), t([i({
	attribute: "loop-focus",
	type: Boolean
})], W.prototype, "loopFocus", void 0), t([f(".trigger-start")], W.prototype, "triggerStartElement", void 0), t([f("pk-popup")], W.prototype, "popupElement", void 0), t([f(".control")], W.prototype, "controlElement", void 0), t([f("button.control, .control > button.trigger")], W.prototype, "triggerButton", void 0), t([f(".value-input")], W.prototype, "input", void 0), t([h()], W.prototype, "highlightedIndex", void 0), t([h()], W.prototype, "closing", void 0), t([h()], W.prototype, "panelAnimated", void 0), W = t([e("pk-select")], W);
// node_modules/@verbb/plugin-kit-web/dist/chunks/pk-option-Br87xU6D.js
function cr(e, t) {
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
var lr = p`
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
`, ur = l(r.check), G = class extends m {
	constructor(...e) {
		super(...e), this.value = "", this.label = "", this.disabled = !1, this.selected = !1, this.highlighted = !1, this.hidden = !1, this.focusIndex = -1, this.optionId = "", this.matchQuery = "";
	}
	static {
		this.styles = lr;
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
		let e = this.matchQuery.trim();
		if (!e || this.hasRichLabelContent()) return x`
                <span part="label" class="label">
                    <slot></slot>
                </span>
            `;
		let t = cr(this.getLabel(), e);
		return x`
            <span part="label" class="label">
                ${t.map((e) => e.match ? x`<mark class="match">${e.text}</mark>` : x`<span>${e.text}</span>`)}
            </span>
        `;
	}
	render() {
		return x`
            <button
                part="option"
                type="button"
                class="option"
                role="option"
                id=${this.optionId || _}
                ?disabled=${this.disabled}
                aria-disabled=${this.disabled ? "true" : _}
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
                <span part="check" class="check" aria-hidden="true">${u(ur)}</span>
            </button>
        `;
	}
};
t([i()], G.prototype, "value", void 0), t([i()], G.prototype, "label", void 0), t([i({
	type: Boolean,
	reflect: !0
})], G.prototype, "disabled", void 0), t([i({
	type: Boolean,
	reflect: !0
})], G.prototype, "selected", void 0), t([i({
	type: Boolean,
	reflect: !0
})], G.prototype, "highlighted", void 0), t([i({
	type: Boolean,
	reflect: !0
})], G.prototype, "hidden", void 0), t([i({
	type: Number,
	attribute: "focus-index"
})], G.prototype, "focusIndex", void 0), t([i()], G.prototype, "optionId", void 0), t([i({ attribute: !1 })], G.prototype, "matchQuery", void 0), G = t([e("pk-option")], G);
// node_modules/@verbb/plugin-kit-web/dist/chunks/pk-button-group-B17QRNSi.js
var dr = p`
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

        :host([data-has-separator]) {
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
`, fr = "data-pk-group-join", pr = "data-pk-group-divider", mr = "data-pk-group-item-first", hr = "data-pk-group-item-last", gr = "data-pk-group-internal-trail", _r = "data-pk-group-btn-last", vr = "data-pk-group-orientation", yr = /* @__PURE__ */ new Set(["PK-SEPARATOR", "PK-BUTTON-GROUP-SEPARATOR"]), br = /* @__PURE__ */ new Set(["PK-POPOVER", "PK-DROPDOWN-MENU"]), xr = {
	fromAttribute(e) {
		return e === null || e !== "false";
	},
	toAttribute(e) {
		return e ? "" : "false";
	}
};
function Sr(e) {
	return yr.has(e.tagName);
}
function Cr(e) {
	return br.has(e.tagName) ? e.querySelector("[slot=\"trigger\"]") ?? e : e;
}
function wr(e) {
	if (br.has(e.tagName)) {
		let t = Cr(e);
		return t === e ? [e] : [e, t];
	}
	return [e];
}
function Tr(e, t) {
	return e === t ? [e] : [e, t];
}
function Er(e) {
	let t = Cr(e);
	return [.../* @__PURE__ */ new Set([...wr(e), ...Tr(e, t)])];
}
function Dr(e) {
	for (let t of Er(e)) t.removeAttribute(fr), t.removeAttribute(pr), t.removeAttribute(mr), t.removeAttribute(hr), t.removeAttribute(gr), t.removeAttribute(_r), t.removeAttribute(vr);
}
function Or(e) {
	return Cr(e).hasAttribute("group-trigger") || e.hasAttribute("group-trigger");
}
function kr(e) {
	let t = [], n = [];
	for (let r of e) Sr(r) ? (n.length && t.push(n), n = []) : n.push(r);
	return n.length && t.push(n), t;
}
var Ar = class extends m {
	constructor(...e) {
		super(...e), this.orientation = "horizontal", this.separators = !0, this.exclusive = !1, this.label = "";
	}
	static {
		this.styles = dr;
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
		let e = this.defaultSlot?.assignedElements({ flatten: !0 }) ?? [], t = kr(e);
		this.toggleAttribute("data-has-separator", e.some((e) => e.localName === "pk-button-group-separator"));
		for (let t of e) Dr(t);
		for (let t of e) for (let e of Er(t)) e.setAttribute(vr, this.orientation);
		for (let e = 0; e < t.length; e++) {
			let n = t[e], r = e === 0, i = e === t.length - 1;
			for (let e = 0; e < n.length; e++) {
				let t = n[e], a = Cr(t), o = Tr(t, a), s = n.length === 1, c = e === 0, l = e === n.length - 1;
				for (let e of o) s ? (r && e.setAttribute(mr, ""), i && e.setAttribute(hr, "")) : (c && e.setAttribute(mr, ""), l && e.setAttribute(hr, ""));
				if (c) {
					if (this.separators && !l) for (let e of wr(t)) e.setAttribute(gr, "");
				} else {
					for (let e of wr(t)) e.setAttribute(fr, ""), this.separators && e.setAttribute(pr, "");
					if (this.separators && !l) for (let e of wr(t)) e.setAttribute(gr, "");
					Or(t) && a.setAttribute(_r, "");
				}
			}
		}
	}
	render() {
		return x`
            <div part="base" class="group" role="group" aria-label=${this.label || _}>
                <slot @slotchange=${this.handleSlotChange}></slot>
            </div>
        `;
	}
};
t([i({ reflect: !0 })], Ar.prototype, "orientation", void 0), t([i({
	type: Boolean,
	reflect: !0,
	converter: xr
})], Ar.prototype, "separators", void 0), t([i({
	type: Boolean,
	reflect: !0
})], Ar.prototype, "exclusive", void 0), t([i()], Ar.prototype, "label", void 0), t([f("slot:not([name])")], Ar.prototype, "defaultSlot", void 0), Ar = t([e("pk-button-group")], Ar);
// node_modules/@verbb/plugin-kit-web/dist/chunks/pk-dropdown-item-DPM5KJ--.js
var K = {
	default: p`
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
	xs: p`
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
	sm: p`
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
	lg: p`
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
	xl: p`
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
}, jr = p`
    @layer pk-component {
        :host {
            ${K.default}
        }

        :host([size='xs']) {
            ${K.xs}
        }

        :host([size='sm']) {
            ${K.sm}
        }

        :host([size='lg']) {
            ${K.lg}
        }

        :host([size='xl']) {
            ${K.xl}
        }
    }
`, Mr = p`
    @layer pk-component {
        .panel[data-size='default'],
        .submenu-panel[data-size='default'] {
            ${K.default}
        }

        .panel[data-size='xs'],
        .submenu-panel[data-size='xs'] {
            ${K.xs}
        }

        .panel[data-size='sm'],
        .submenu-panel[data-size='sm'] {
            ${K.sm}
        }

        .panel[data-size='lg'],
        .submenu-panel[data-size='lg'] {
            ${K.lg}
        }

        .panel[data-size='xl'],
        .submenu-panel[data-size='xl'] {
            ${K.xl}
        }
    }
`;
p`
    ${jr}
    ${Mr}
`;
var Nr = [
	Yn,
	Mr,
	p`
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
], Pr, Fr = l(a), Ir = l(o), q = class extends m {
	static {
		Pr = this;
	}
	constructor(...e) {
		super(...e), this.value = "", this.type = "normal", this.radioGroup = "", this.disabled = !1, this.destructive = !1, this.checked = !1, this.submenuOpen = !1, this.active = !1, this.submenuAnimated = !1, this.hasSlotController = new c(this, "submenu", "details", "start", "prefix"), this.handleMouseEnter = () => {
			this.hasSubmenu() && !this.disabled && (this.notifyParentOfOpening(), this.submenuOpen = !0);
		}, this.handleHostClick = (e) => {
			this.disabled && (e.preventDefault(), e.stopImmediatePropagation());
		};
	}
	static {
		this.styles = Nr;
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
		if (e) for (let t of e.children) t !== this && t instanceof Pr && t.getAttribute("slot") === this.getAttribute("slot") && t.submenuOpen && (t.submenuOpen = !1);
	}
	ensureSubmenuSurface() {
		this.hasSubmenu() && !this.disabled && (this.submenuAnimated = !0, this.updateComplete.then(() => {
			this.submenuOpen && this.submenuPanelElement && (this.submenuPanelElement.hidden = !1, Ze(this.submenuPanelElement, "right-start"), Qe(this.submenuPopupElement, "right-start").then((e) => {
				Ze(this.submenuPanelElement, e);
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
		let e = this.hasSubmenu(), t = this.type === "checkbox" || this.type === "radio", n = this.hasSlotController.test("start") || this.hasSlotController.test("prefix");
		return x`
            <button
                part="item"
                type="button"
                class="item"
                ?disabled=${this.disabled}
                aria-disabled=${this.disabled ? "true" : _}
                @click=${this.handleClick}
            >
                <span
                    part="prefix"
                    class=${n ? "prefix" : "prefix prefix--empty"}
                >
                    <slot name="start"></slot>
                    <slot name="prefix"></slot>
                </span>
                <span part="label" class="label"><slot></slot></span>
                <span class="details"><slot name="details"></slot></span>
                ${t ? x`<span part="check" class="check" aria-hidden="true">${u(Fr)}</span>` : _}
                ${e ? x`<span class="submenu-icon" aria-hidden="true">${u(Ir)}</span>` : _}
            </button>
            ${e ? x`
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
                        data-size=${Lr(this)}
                        ?hidden=${!this.submenuOpen}
                        data-open=${this.submenuAnimated ? "" : _}
                        aria-orientation="vertical"
                    >
                        <slot name="submenu"></slot>
                    </div>
                </pk-popup>
            ` : _}
        `;
	}
};
t([i()], q.prototype, "value", void 0), t([i({ reflect: !0 })], q.prototype, "type", void 0), t([i({ attribute: "radio-group" })], q.prototype, "radioGroup", void 0), t([i({
	type: Boolean,
	reflect: !0
})], q.prototype, "disabled", void 0), t([i({
	type: Boolean,
	reflect: !0
})], q.prototype, "destructive", void 0), t([i({
	type: Boolean,
	reflect: !0
})], q.prototype, "checked", void 0), t([i({
	attribute: "submenu-open",
	type: Boolean,
	reflect: !0
})], q.prototype, "submenuOpen", void 0), t([i({ type: Boolean })], q.prototype, "active", void 0), t([h()], q.prototype, "submenuAnimated", void 0), t([f(".submenu-panel")], q.prototype, "submenuPanelElement", void 0), t([f("pk-popup")], q.prototype, "submenuPopupElement", void 0), q = Pr = t([e("pk-dropdown-item")], q);
function Lr(e) {
	let t = e.parentElement?.getAttribute("data-size");
	if (t === "xs" || t === "sm" || t === "default" || t === "lg" || t === "xl") return t;
	let n = e.closest("pk-dropdown-menu")?.getAttribute("size");
	return n === "xs" || n === "sm" || n === "lg" || n === "xl" ? n : "default";
}
// node_modules/@verbb/plugin-kit-web/dist/chunks/pk-dropdown-menu-CxPvOe32.js
var Rr = [
	s(),
	jr,
	Mr,
	p`
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
], zr = /* @__PURE__ */ new Set(), J = class extends m {
	constructor(...e) {
		super(...e), this.open = !1, this.size = "default", this.placement = "bottom-start", this.sideOffset = 4, this.distance = 4, this.skidding = 0, this.for = "", this.userTypedQuery = "", this.userTypedTimeout = 0, this.openSubmenuStack = [], this.openedByKeyboard = !1, this.triggerElement = null, this.handleMenuClick = (e) => {
			let t = this.resolveMenuItem(e);
			t && !t.disabled && (t.hasSubmenu() ? (t.submenuOpen || (this.closeSiblingSubmenus(t), this.addToSubmenuStack(t), t.openSubmenu()), e.stopPropagation()) : this.makeSelection(t));
		}, this.handleSubmenuOpening = (e) => {
			let t = e.detail?.item;
			t instanceof q && (this.closeSiblingSubmenus(t), this.addToSubmenuStack(t));
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
			if (e.key === "Escape" && this.open && le(this)) {
				e.preventDefault(), e.stopPropagation(), this.open = !1, this.getTrigger()?.focus({ preventScroll: !0 });
				return;
			}
			if (!this.open) return;
			let n = [...He()].find((e) => e.localName === "pk-dropdown-item"), r = n?.localName === "pk-dropdown-item", i = this.getCurrentSubmenuItem(), a = !!i, o, s, c;
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
					t.focus({ preventScroll: !0 }), t.active = !0, (t.slot === "submenu" && t.parentElement instanceof q ? this.getSubmenuItems(t.parentElement) : this.getItems()).forEach((e) => {
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
		this.styles = Rr;
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
		window.clearTimeout(this.userTypedTimeout), this.removeEventListener("click", this.handleTriggerClick, !0), this.removeEventListener("keydown", this.handleTriggerKeyDown), this.unbindTrigger(this.triggerElement), this.triggerElement = null, this.closeAllSubmenus(), this.popupElement && (this.popupElement.active = !1), this.menuElement?.classList.remove("show", "hide"), document.removeEventListener("keydown", this.handleDocumentKeyDown), document.removeEventListener("pointerdown", this.handleDocumentPointerDown, !0), document.removeEventListener("mousemove", this.handleGlobalMouseMove), ce(this), zr.delete(this), super.disconnectedCallback();
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
		return this.for ? Bn(this, this.for) ?? this.triggerElement : this.querySelector("[slot=\"trigger\"]") ?? this.triggerElement;
	}
	getAnchor() {
		return this.getTrigger() ?? "";
	}
	resolveExternalTrigger() {
		this.unbindTrigger(this.triggerElement), this.triggerElement = this.for ? Bn(this, this.for) : null, this.bindTrigger(this.triggerElement), this.requestUpdate();
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
		if (t instanceof q) return t;
		if (t instanceof Element) {
			let e = t.closest("pk-dropdown-item");
			if (e instanceof q) return e;
		}
		return e.composedPath().find((e) => e instanceof q) ?? null;
	}
	whenClosed() {
		return this.open ? new Promise((e) => {
			this.addEventListener("pk-after-hide", () => {
				this.popupElement.stop().then(() => e());
			}, { once: !0 });
		}) : this.popupElement?.active ? this.popupElement.stop() : Promise.resolve();
	}
	forceDismissCleanup() {
		this.open = !1, this.popupElement.active = !1, this.menuElement?.classList.remove("show", "hide"), this.closeAllSubmenus(), document.removeEventListener("keydown", this.handleDocumentKeyDown), document.removeEventListener("pointerdown", this.handleDocumentPointerDown, !0), document.removeEventListener("mousemove", this.handleGlobalMouseMove), ce(this), zr.delete(this);
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
		(t instanceof q ? this.getSubmenuItems(t, !0) : this.getItems(!0)).forEach((t) => {
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
		let e = new de();
		if (!this.dispatchEvent(e)) {
			this.open = !1;
			return;
		}
		if (this.popupElement.active && (this.popupElement.active = !1, this.menuElement.classList.remove("show", "hide"), await this.updateComplete), zr.forEach((e) => {
			e !== this && (e.open = !1);
		}), this.popupElement.active = !0, this.open = !0, zr.add(this), se(this), document.addEventListener("keydown", this.handleDocumentKeyDown), document.addEventListener("pointerdown", this.handleDocumentPointerDown, !0), document.addEventListener("mousemove", this.handleGlobalMouseMove), await this.updateComplete, await Qe(this.popupElement, this.placement, 100, { requireEvent: !0 }), !this.open) {
			this.popupElement.active = !1, zr.delete(this), ce(this), document.removeEventListener("keydown", this.handleDocumentKeyDown), document.removeEventListener("pointerdown", this.handleDocumentPointerDown, !0), document.removeEventListener("mousemove", this.handleGlobalMouseMove);
			return;
		}
		this.menuElement.classList.remove("hide"), await Te(this.menuElement, "show");
		let t = this.getItems();
		t.length > 0 && (this.openedByKeyboard ? this.setActiveItem(t, t[0]) : (t.forEach((e) => {
			e.active = !1, e.removeAttribute("data-highlighted");
		}), this.menuElement.focus({ preventScroll: !0 }))), this.openedByKeyboard = !1, this.dispatchEvent(new fe()), this.dispatchEvent(new CustomEvent("pk-open-change", {
			detail: { open: !0 },
			bubbles: !0,
			composed: !0
		}));
	}
	async hideMenu(e) {
		if (!this.popupElement || !this.menuElement) return;
		let t = new pe(e);
		this.dispatchEvent(t) ? (this.open = !1, zr.delete(this), ce(this), document.removeEventListener("keydown", this.handleDocumentKeyDown), document.removeEventListener("pointerdown", this.handleDocumentPointerDown, !0), document.removeEventListener("mousemove", this.handleGlobalMouseMove), this.userTypedQuery = "", window.clearTimeout(this.userTypedTimeout), this.getItems(!0).forEach((e) => {
			e.active = !1, e.removeAttribute("data-highlighted");
		}), this.menuElement.classList.remove("show"), await Te(this.menuElement, "hide"), this.popupElement.active = !1, this.dispatchEvent(new me()), this.dispatchEvent(new CustomEvent("pk-open-change", {
			detail: { open: !1 },
			bubbles: !0,
			composed: !0
		}))) : this.open = !0;
	}
	render() {
		let e = this.hasUpdated ? this.popupElement?.active : this.open, t = this.for ? this.getAnchor() : "";
		return x`
            <pk-popup
                .anchor=${t}
                placement=${this.placement}
                .distance=${this.distance || this.sideOffset}
                .skidding=${this.skidding}
                ?active=${e}
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
t([i({
	type: Boolean,
	reflect: !0
})], J.prototype, "open", void 0), t([i({ reflect: !0 })], J.prototype, "size", void 0), t([i({ reflect: !0 })], J.prototype, "placement", void 0), t([i({
	attribute: "side-offset",
	type: Number
})], J.prototype, "sideOffset", void 0), t([i({ type: Number })], J.prototype, "distance", void 0), t([i({ type: Number })], J.prototype, "skidding", void 0), t([i({ reflect: !0 })], J.prototype, "for", void 0), t([f("slot:not([name])")], J.prototype, "defaultSlot", void 0), t([f("#menu")], J.prototype, "menuElement", void 0), t([f("pk-popup")], J.prototype, "popupElement", void 0), J = t([e("pk-dropdown-menu")], J);
// node_modules/@verbb/plugin-kit-web/dist/chunks/translate-DrB3jtbL.js
var Br = "plugin-kit";
function Vr(e) {
	let t = globalThis.Craft;
	if (typeof t?.t != "function") return e;
	let n = t.t(Br, e);
	return typeof n == "string" && n !== "" ? n : e;
}
// node_modules/lit-html/directives/style-map.js
var Hr = "important", Ur = " !" + Hr, Wr = S(class extends v {
	constructor(e) {
		if (super(e), e.type !== b.ATTRIBUTE || e.name !== "style" || e.strings?.length > 2) throw Error("The `styleMap` directive must be used in the `style` attribute and must be the only part in the attribute.");
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
				let t = typeof r == "string" && r.endsWith(Ur);
				e.includes("-") || t ? n.setProperty(e, t ? r.slice(0, -11) : r, t ? Hr : "") : n[e] = r;
			}
		}
		return y;
	}
});
// node_modules/@verbb/plugin-kit-web/dist/chunks/pk-data-table-BINSj5GM.js
function Gr(e) {
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
function Kr(e, t) {
	let n = Gr(e);
	return t && n.includes(t) ? t : n[0];
}
var qr = (e) => e !== "empty" && e !== "not-empty", Jr = (e) => Array.isArray(e) ? e.every((e) => !e.trim()) : !e.trim();
function Yr(e, t, n, r, i) {
	let a = Kr(e, r), o = t == null || t === "" || Array.isArray(t) && !t.length;
	if (a === "empty") return o;
	if (a === "not-empty") return !o;
	if (Jr(n)) return !0;
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
var Xr = {
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
	rowReorderCancelled: "Row move canceled.",
	moveUp: "Move {label} earlier",
	moveDown: "Move {label} later",
	sort: "Sort by {label}"
}, Zr = () => ({
	search: "",
	sort: [],
	filters: {},
	pageIndex: 0,
	pageSize: 25
}), Qr = (e, t) => `cell-${typeof e}-${encodeURIComponent(String(e))}-${encodeURIComponent(t)}`, $r = (e, t) => t.value ? t.value(e) : e[t.id];
function Y(e) {
	return e == null ? "" : Array.isArray(e) ? e.join(", ") : String(e);
}
function ei(e, t, n, r, i) {
	let a = r.search.trim().toLocaleLowerCase(i), o = e.filter((e) => a && !t.some((t) => t.searchable !== !1 && Y($r(e, t)).toLocaleLowerCase(i).includes(a)) ? !1 : n.every((t) => {
		let n = r.filters[t.id];
		return n == null || Yr(t, t.value ? t.value(e) : e[t.id], n, r.filterOperators?.[t.id], i);
	})), s = new Intl.Collator(i, {
		numeric: !0,
		sensitivity: "base"
	});
	o.sort((e, n) => {
		for (let i of r.sort) {
			let r = t.find((e) => e.id === i.id && e.sortable);
			if (!r) continue;
			let a = $r(e, r), o = $r(n, r), c = r.type === "date" ? (Date.parse(Y(a)) || 0) - (Date.parse(Y(o)) || 0) : r.type === "number" && Number.isFinite(Number(a)) && Number.isFinite(Number(o)) ? Number(a) - Number(o) : typeof a == "number" && typeof o == "number" ? a - o : s.compare(Y(a), Y(o));
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
var ti = class {
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
}, ni = {
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
}, ri = class {
	constructor(e) {
		this.host = e, this.rules = [], this.error = "", this.opened = !1, this.closing = !1, this.preserveUnknown = !0, this.signature = "", this.position = () => {
			let e = this.host.toolbarRoot.querySelector(".search") ?? this.host.toolbarRoot.querySelector(".toolbar"), t = this.dialog;
			if (!e || !t) return;
			let n = e.getBoundingClientRect(), r = this.host.ownerDocument.defaultView, i = n.width >= 450 && n.bottom >= 0 && r.innerHeight - n.bottom >= 240;
			t.toggleAttribute("data-anchored", i), t.style.setProperty("--filter-left", `${Math.max(8, Math.min(n.left + 1, r.innerWidth - n.width - 8))}px`), t.style.setProperty("--filter-top", `${n.bottom}px`), t.style.setProperty("--filter-width", `${n.width - 2}px`), t.style.setProperty("--filter-max-height", `${Math.max(200, r.innerHeight - n.bottom - 12)}px`);
		}, e.addController(this);
	}
	text(e, t = {}) {
		return (this.host.labels[e] ?? Vr(Xr[e])).replace(/\{(\w+)\}/g, (e, n) => String(t[n] ?? e));
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
			operator: Kr(e, this.host.query.filterOperators?.[e.id]),
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
			operator: Kr(t),
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
		return this.text(ni[t]);
	}
	async apply() {
		if (this.closing) return;
		let e = this.preserveUnknown ? { ...this.host.query.filters } : {}, t = this.preserveUnknown ? { ...this.host.query.filterOperators } : {};
		for (let n of this.host.filters) delete e[n.id], delete t[n.id];
		for (let n of this.rules) {
			let r = this.host.filters.find((e) => e.id === n.id);
			if (r && !(qr(n.operator) && Jr(n.value))) {
				if (qr(n.operator) && (r.type === "number-range" || r.type === "date-range")) {
					let e = (Array.isArray(n.value) ? n.value : [n.value]).map((e) => e ? r.type === "date-range" ? Date.parse(e) : Number(e) : void 0);
					if (e.some((e) => e !== void 0 && !Number.isFinite(e)) ? this.error = this.text("filterValueError", { label: r.label }) : n.operator === "between" && e[0] != null && e[1] != null && e[0] > e[1] && (this.error = this.text("filterRangeError", { label: r.label })), this.error) {
						this.host.requestUpdate(), await this.host.updateComplete, this.focusRule(n.id);
						return;
					}
				}
				e[n.id] = qr(n.operator) ? structuredClone(n.value) : "", n.operator !== Kr(r) && (t[n.id] = n.operator);
			}
		}
		let n = JSON.stringify([e, t]) !== JSON.stringify([this.host.query.filters, this.host.query.filterOperators ?? {}]);
		this.closing = !0, n && this.host.setQuery({
			filters: e,
			filterOperators: t
		}), await this.dialog?.hide();
	}
	renderValue(e, t) {
		if (!qr(t.operator)) return _;
		if (e.type === "select" || e.type === "multi-select" || e.type === "boolean") {
			let n = e.type === "boolean" ? [{
				value: "true",
				label: this.text("yes")
			}, {
				value: "false",
				label: this.text("no")
			}] : e.options ?? [];
			return x`<pk-select
                class="filter-value"
                width="full"
                aria-label=${e.label}
                .placeholder=${this.text("all")}
                .multiple=${e.type === "multi-select"}
                .value=${Array.isArray(t.value) ? "" : t.value}
                .values=${Array.isArray(t.value) ? t.value : t.value ? [t.value] : []}
                @change=${(n) => {
				let r = n.currentTarget;
				this.change(t.id, { value: e.type === "multi-select" ? [...r.values] : r.value });
			}}
                >${n.map((e) => x`<pk-option .value=${e.value}>${e.label}</pk-option>`)}</pk-select
            >`;
		}
		let n = t.operator === "between", r = n ? Array.isArray(t.value) ? t.value : [t.value, ""] : [Array.isArray(t.value) ? t.value[0] ?? "" : t.value];
		return x`<div class="filter-values">
            ${r.map((i, a) => x`<pk-input
                        class="filter-input"
                        type=${e.type === "date-range" ? "date" : e.type === "number-range" ? "number" : "text"}
                        .label=${n ? `${e.label}: ${this.text(a ? "to" : "from")}` : e.label}
                        .placeholder=${n ? this.text(a ? "to" : "from") : ""}
                        .value=${i}
                        @input=${(e) => {
			let i = e.currentTarget.value, o = [...r];
			o[a] = i, this.change(t.id, { value: n ? o : i });
		}}
                    ></pk-input>`)}
        </div>`;
	}
	renderTrigger(e) {
		if (!this.host.filters.length && !this.host.showFilters) return _;
		let t = Object.keys(this.host.query.filters).length + (this.host.query.criteria && Object.keys(this.host.query.criteria).length ? 1 : 0);
		return x`<pk-button
            class="filter-trigger"
            slot=${e ?? _}
            variant="transparent"
            size="sm"
            ?data-active=${this.opened || t > 0}
            aria-label=${`${this.text("filters")}${t ? ` (${t})` : ""}`}
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
            >${t ? x`<span>${t}</span>` : _}</pk-button
        >`;
	}
	renderDialog() {
		return x`<pk-dialog
            class="filter-dialog"
            .label=${this.text("filterResults")}
            without-header
            @pk-after-hide=${(e) => this.afterHide(e)}
            @keydown=${(e) => {
			e.key === "Enter" && e.composedPath().some((e) => e.localName === "pk-input") && !e.composedPath().some((e) => e.localName === "pk-select") && (e.preventDefault(), this.apply());
		}}
            ><div class="filter-builder" part="filter-builder">
                ${this.rules.length ? x`<p class="filter-intro">${this.text("filterMatch")}</p>` : _}
                ${oe(this.rules, (e) => e.id, (e, t) => {
			let n = this.host.filters.find((t) => t.id === e.id);
			return n ? x`<div
                            class="filter-rule"
                            part="filter-rule"
                            data-id=${e.id}
                            role="group"
                            aria-label=${n.label}
                        >
                            <pk-select
                                class="filter-type"
                                aria-label=${`${n.label}: ${this.text("filterType")}`}
                                width="full"
                                .value=${e.id}
                                @change=${(t) => {
				this.pendingType = {
					id: e.id,
					nextId: t.currentTarget.value
				};
			}}
                                @pk-after-hide=${(t) => {
				if (t.target !== t.currentTarget || this.pendingType?.id !== e.id) return;
				let n = this.pendingType.nextId;
				this.pendingType = void 0;
				let r = this.host.filters.find((e) => e.id === n);
				r && n !== e.id && (this.change(e.id, {
					id: n,
					operator: Kr(r),
					value: ""
				}), this.host.updateComplete.then(() => this.focusRule(n)));
			}}
                            >
                                ${[n, ...this.available].map((e) => x`<pk-option .value=${e.id}>${e.label}</pk-option>`)}
                            </pk-select>
                            <pk-select
                                class="filter-operator"
                                width="full"
                                .value=${e.operator}
                                aria-label=${this.text("filterOperator", { label: n.label })}
                                @change=${(t) => {
				let r = t.currentTarget.value;
				this.change(e.id, {
					operator: r,
					value: r === "between" ? Array.isArray(e.value) ? e.value : [e.value, ""] : n.type === "multi-select" ? e.value : Array.isArray(e.value) ? e.value[0] ?? "" : e.value
				});
			}}
                            >
                                ${Gr(n).map((e) => x`<pk-option .value=${e}>${this.operatorLabel(n, e)}</pk-option>`)}
                            </pk-select>
                            ${this.renderValue(n, e)}
                            <pk-button
                                class="filter-remove"
                                variant="transparent"
                                size="sm"
                                aria-label=${this.text("removeFilter", { label: n.label })}
                                @click=${() => this.remove(e.id)}
                                ><span class="icon" aria-hidden="true"
                                    >${u(l(r.xmark))}</span
                                ></pk-button
                            >
                        </div>` : _;
		})}
                ${this.host.filters.length ? x`<div class="filter-add">
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
                                  >${this.available.map((e) => x`<pk-option .value=${e.id}>${e.label}</pk-option>`)}</pk-select
                              >
                          </div>` : _}
                <slot name="filters"></slot>
                ${this.error ? x`<p class="filter-error" role="alert">${this.error}</p>` : _}
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
}, ii = class {
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
}, ai = /* @__PURE__ */ new WeakMap(), oi = (e) => typeof e == "object" && !!e && !Array.isArray(e);
function si(e, t, n) {
	let r = oi(e) ? e : n, i = t.map((e) => e.id), a = (e, t) => [...new Set((Array.isArray(e) ? e : t).filter((e) => typeof e == "string" && i.includes(e)))], o = a(r.hiddenColumns, n.hiddenColumns).filter((e) => t.find((t) => t.id === e)?.hideable !== !1);
	return i.length && o.length === i.length && o.splice(o.indexOf(i[0]), 1), {
		hiddenColumns: i.filter((e) => o.includes(e)),
		columnOrder: [.../* @__PURE__ */ new Set([...a(r.columnOrder, n.columnOrder), ...i])]
	};
}
function ci(e, t) {
	if (!Array.isArray(e)) return [];
	let n = /* @__PURE__ */ new Set();
	return e.flatMap((e) => !oi(e) || typeof e.id != "string" || n.has(e.id) || !t.some((t) => t.id === e.id && t.sortable) || e.direction !== "asc" && e.direction !== "desc" ? [] : (n.add(e.id), [{
		id: e.id,
		direction: e.direction
	}]));
}
function li(e, t) {
	ai.get(e)?.disconnect();
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
		let t = ci(e.defaultSort, e.columns), n = e.columns.find((e) => e.sortable);
		return t.length ? t : n ? [{
			id: n.id,
			direction: "asc"
		}] : [];
	}, a = () => {
		if (e.manualOrder) return [];
		let t = ci(e.query.sort, e.columns);
		return t.length ? t : i();
	};
	try {
		let i = r?.getItem(n), a = i ? JSON.parse(i) : void 0;
		if (oi(a) && (e.view = si(a.view, e.columns, e.view), t.restoreSort !== !1 && !e.manualOrder)) {
			let t = ci(a.sort, e.columns);
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
			view: si(e.view, e.columns, e.defaultView),
			sort: a()
		}, u = {
			view: si(e.defaultView, e.columns, e.defaultView),
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
			s && u(), o = !1, e.removeEventListener("pk-view-change", f), e.removeEventListener("pk-query-change", p), ai.get(e) === m && ai.delete(e);
		}
	};
	return ai.set(e, m), m;
}
var X = class extends m {
	constructor(...e) {
		super(...e), this.filterController = new ri(this), this.label = "Records", this.rows = [], this.columns = [], this.filters = [], this.rowKey = "id", this.query = Zr(), this.view = {
			hiddenColumns: [],
			columnOrder: []
		}, this.mode = "client", this.pagination = "offset", this.hasNext = !1, this.loading = !1, this.error = "", this.searchable = !0, this.showFilters = !1, this.columnControls = !0, this.hideToolbar = !1, this.hideFooter = !1, this.selectable = !1, this.manualOrder = !1, this.reorderDisabled = !1, this.selectedKeys = [], this.exportFormats = [], this.exporting = !1, this.defaultView = {
			hiddenColumns: [],
			columnOrder: []
		}, this.defaultSort = [], this.searchDelay = 250, this.size = "default", this.layout = "embedded", this.locale = "", this.timeZone = "", this.labels = {}, this.dragOffsets = {}, this.reorderAnnouncement = "", this.rowOrderAnnouncement = "", this.rowOrder = new ii(() => this.canReorderRows, (e, t) => this.reorderRows(e, t), () => {
			this.rowOrderAnnouncement = this.text("rowReorderCancelled");
		}), this.tableScrollWidth = 0, this.hasHorizontalOverflow = !1, this.sortLoading = !1, this.viewClosePending = !1, this.requests = new ti();
	}
	static {
		this.styles = We;
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
			let e = ei(this.rows, this.columns, this.filters, this.query, this.locale || void 0), t = Math.max(0, Math.ceil((e.total ?? 0) / this.query.pageSize) - 1);
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
		this.toolbarHost === e && (e.mount && g(_, e.mount, { host: this }), this.toolbarHost = void 0, this.requestUpdate());
	}
	get toolbarRoot() {
		return this.toolbarHost?.renderRoot ?? this.renderRoot;
	}
	updated(e) {
		this.toolbarHost?.mount && g(this.renderToolbar(), this.toolbarHost.mount, { host: this }), this.resizeObserver || this.observeLayout(), this.measureLayout(), this.restorePagingFocus(), (e.has("query") || e.has("dataSource") || e.has("mode")) && this.mode === "remote" && this.dataSource && this.refresh();
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
		return (this.labels[e] ?? Vr(Xr[e])).replace(/\{(\w+)\}/g, (e, n) => String(t[n] ?? e));
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
		return this.mode === "client" ? ei(this.rows, this.columns, this.filters, this.manualOrder ? {
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
	icon(e, t) {
		return x`<span
            class="icon"
            slot=${t ?? _}
            aria-hidden="true"
            >${u(l(e))}</span
        >`;
	}
	directionIcon(e) {
		return x`<svg
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
                d=${e === "asc" ? "M5 20V4m-3 3 3-3 3 3" : "M5 4v16m-3-3 3 3 3-3"}
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
		let e = this.query.sort[0], t = this.manualOrder ? [] : this.columns.filter((e) => e.sortable), n = this.columns.filter((e) => e.hideable === !1 || !this.view.hiddenColumns.includes(e.id)).length;
		return x`<pk-popover
            class="view-popover"
            .label=${this.text("view")}
            @pk-hide=${this.finishViewChildren}
            placement="bottom-end"
            flush
        >
            <pk-button slot="trigger" with-caret
                >${this.icon(r.slidersOutline, "start")}${this.text("view")}</pk-button
            >
            <div class="view-grid">
                ${t.length ? x`<div class="view-label">
                                  ${this.text("sortBy")}
                              </div>
                              <div
                                  class="view-sort"
                                  role="group"
                                  aria-label=${this.text("sortBy")}
                              >
                                  <pk-select
                                      aria-label=${this.text("sortBy")}
                                      .value=${e?.id ?? ""}
                                      @pk-change=${(t) => {
			let n = t.detail.value;
			n && this.setQuery({ sort: [{
				id: n,
				direction: e?.direction ?? "asc"
			}] });
		}}
                                      >${t.map((e) => x`<pk-option value=${e.id}>${e.label}</pk-option>`)}</pk-select
                                  >
                                  <pk-button-group
                                      .label=${this.text("sortDirection")}
                                      >${["asc", "desc"].map((t) => x`<pk-button .disabled=${!e} variant=${e?.direction === t ? "secondary" : "default"} aria-label=${this.text(t === "asc" ? "ascending" : "descending")} aria-pressed=${e?.direction === t ? "true" : "false"} @click=${() => e && this.setQuery({ sort: [{
			id: e.id,
			direction: t
		}] })}>${this.directionIcon(t)}</pk-button>`)}</pk-button-group
                                  >
                              </div>` : _}
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
                    ${oe(this.orderedColumns, (e) => e.id, (e) => x`<div
                                class="column-option"
                                data-column=${e.id}
                                ?data-dragging=${this.drag?.moved && this.drag.id === e.id}
                                style=${Wr({ transform: this.drag?.moved ? `translateY(${this.dragOffsets[e.id] ?? 0}px)` : void 0 })}
                            >
                                <button
                                    class="column-handle"
                                    type="button"
                                    aria-label=${this.text("reorder", { label: e.label })}
                                    aria-describedby="reorder-help"
                                    @pointerdown=${(t) => this.startColumnDrag(t, e.id)}
                                    @pointermove=${(e) => this.dragColumn(e)}
                                    @pointerup=${(e) => this.endColumnDrag(e, !0)}
                                    @pointercancel=${(e) => this.endColumnDrag(e, !1)}
                                    @lostpointercapture=${(e) => this.endColumnDrag(e, !1)}
                                    @keydown=${(t) => {
			this.drag && t.key === "Escape" ? (t.preventDefault(), t.stopPropagation(), this.cancelColumnDrag()) : this.drag || (t.key === "ArrowUp" || t.key === "ArrowDown") && (t.preventDefault(), this.moveColumn(e.id, t.key === "ArrowUp" ? -1 : 1));
		}}
                                >
                                    ${this.icon(r.gripMove)}
                                </button>
                                <pk-checkbox
                                    .checked=${e.hideable === !1 || !this.view.hiddenColumns.includes(e.id)}
                                    .disabled=${e.hideable === !1 || !this.view.hiddenColumns.includes(e.id) && n <= 1}
                                    @change=${() => this.toggleColumn(e.id)}
                                    >${e.label}</pk-checkbox
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
		return this.exportFormats.length ? x`<pk-dropdown-menu
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
            >${this.exportFormats.map((e) => x`<pk-dropdown-item value=${e.value} .disabled=${!!e.disabled}>${e.label}</pk-dropdown-item>`)}</pk-dropdown-menu
        >` : _;
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
		return this.hideToolbar ? _ : x`<div class="toolbar" part="toolbar">
            <slot name="toolbar-start"></slot>
            ${this.searchable ? x`<pk-input class="search" type="search" with-clear .clearLabel=${this.text("clearSearch")} .label=${this.text("search")} placeholder=${this.text("search")} .value=${this.searchDraft ?? this.query.search} @input=${this.inputSearch}>${this.icon(r.magnifyingGlass, "start")}${this.filterController.renderTrigger("end")}</pk-input>` : _}
            <div class="toolbar-end">
                ${this.searchable ? _ : this.filterController.renderTrigger()}
                <slot name="toolbar-controls"></slot>
                ${this.columnControls ? this.renderView() : _}
                <slot name="toolbar-end"></slot>
            </div>
        </div>`;
	}
	renderCell(e, t) {
		let n = $r(e, t), i = this.key(e), a = {
			row: e,
			column: t,
			value: n,
			rowKey: i
		};
		if (t.renderCell) return t.renderCell(a);
		if (t.type === "custom") return x`<slot name=${Qr(i, t.id)}
                >${Y(n)}</slot
            >`;
		if (n == null || n === "") return "—";
		if (t.type === "handle") {
			let e = Y(n);
			return x`<span class="cell-handle" part="handle">
                <code>${e}</code><pk-copy-button
                    .value=${e}
                    .ariaLabel=${this.text("copyHandle", { label: e })}
                    .copiedLabel=${this.text("copiedHandle", { label: e })}
                    >${this.icon(r.clipboard, "icon")}</pk-copy-button>
            </span>`;
		}
		if (t.type === "number" && Number.isFinite(Number(n))) return new Intl.NumberFormat(this.locale || void 0, t.numberOptions).format(Number(n));
		if (t.type === "date") {
			let e = n instanceof Date ? n : new Date(Y(n));
			return Number.isFinite(e.getTime()) ? x`<time datetime=${e.toISOString()}
                >${new Intl.DateTimeFormat(this.locale || void 0, {
				...this.timeZone ? { timeZone: this.timeZone } : {},
				...t.dateOptions
			}).format(e)}</time
            >` : "—";
		}
		if (t.type === "status") return x`<pk-status-label
                .status=${t.status?.(e) ?? Y(n)}
                >${Y(n)}</pk-status-label
            >`;
		if (t.type === "link") {
			let r = t.href?.(e) ?? Y(n);
			try {
				if (![
					"http:",
					"https:",
					"mailto:",
					"tel:"
				].includes(new URL(r, document.baseURI).protocol)) return Y(n);
			} catch {
				return Y(n);
			}
			return x`<a
                href=${r}
                class=${t.wrap === !0 ? _ : "cell-link"}
                title=${Y(n)}
                >${Y(n)}</a
            >`;
		}
		return Y(n);
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
	renderRowActions(e, t) {
		if (!t.length) return _;
		let n = (e) => (e.display ?? (e.preset ? "inline" : "menu")) === "inline", i = t.filter((e) => !n(e));
		return x`<div class="row-actions" part="row-actions">
            ${t.filter(n).map((t) => {
			let n = this.text("rowAction", {
				label: this.actionLabel(t),
				id: this.accessibleRowLabel(e)
			});
			return x`<pk-button
                    class="row-action"
                    data-preset=${t.preset ?? _}
                    size="sm"
                    variant="none"
                    aria-label=${n}
                    .disabled=${!!t.disabled}
                    @click=${() => this.activateAction(t, e)}
                    >${t.preset ? this.icon({
				edit: r.pen,
				move: r.gripMove,
				delete: r.xmark
			}[t.preset], "start") : this.actionLabel(t)}</pk-button>`;
		})}
            ${i.length ? x`<pk-dropdown-menu placement="bottom-end"
                @pk-select=${(t) => {
			t.stopPropagation();
			let n = i.find((e) => e.id === t.detail.value);
			n && this.activateAction(n, e);
		}}>
                <pk-button slot="trigger" size="sm" variant="none"
                    aria-label=${this.text("actions", { id: this.accessibleRowLabel(e) })}
                    >${this.icon(r.ellipsis, "start")}</pk-button>
                ${i.map((e) => x`<pk-dropdown-item
                    value=${e.id}
                    .disabled=${!!e.disabled}
                    >${this.actionLabel(e)}</pk-dropdown-item>`)}
            </pk-dropdown-menu>` : _}
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
		let e = this.result, t = this.orderedColumns.filter((e) => e.hideable === !1 || !this.view.hiddenColumns.includes(e.id)), n = e.rows.filter((e) => this.isRowSelectable?.(e) !== !1).map((e) => this.key(e)), i = new Set(this.selectable ? this.selectedKeys : []), a = n.length > 0 && n.every((e) => i.has(e)), o = e.rows.length ? this.query.pageIndex * this.query.pageSize + 1 : 0, s = e.rows.length ? o + e.rows.length - 1 : 0, c = this.pagination === "cursor" ? !!e.nextCursor : e.total == null ? !!e.hasNext : s < e.total, l = this.pagination === "cursor" ? this.query.pageIndex > 0 && e.previousCursor !== void 0 : this.query.pageIndex > 0, u = this.loading || !!this.error, d = this.loading && this.sortLoading, f = this.loading && !d, p = this.layout === "index" && !this.hideFooter && this.hasHorizontalOverflow, m = this.text(e.total == null ? "unknownRange" : "range", {
			start: o,
			end: s,
			total: e.total ?? 0
		});
		return x`
            ${this.toolbarHost ? _ : x`<div class="header-shell" part="header-shell">
                ${this.renderToolbar()}
            </div>`}
            ${this.filterController.renderDialog()}
            ${this.manualOrder ? x`
                <span id="row-order-help" class="sr-only">${this.text("reorderRowHelp")}</span>
                ${this.rowOrderFiltered ? x`<p class="row-order-hint">${this.text("reorderRowsFiltered")}</p>` : _}
                <span role="status" class="sr-only">${this.rowOrderAnnouncement}</span>
            ` : _}
            <div role="status" aria-live="polite" class="sr-only">
                ${this.loading ? this.text(e.rows.length ? "updating" : "loading") : i.size ? `${m}. ${this.text("selected", { count: i.size })}` : m}
            </div>
            ${this.error ? x`<pk-state-panel variant="error" .heading=${this.error}><pk-button slot="actions" @click=${() => this.refresh()}>${this.text("retry")}</pk-button></pk-state-panel>` : _}
            <div class="table-frame">
                <div
                    class="scroll"
                    part="scroll"
                    ?data-proxy=${p}
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
                                ${this.selectable ? x`<th class="small"><pk-checkbox aria-label=${this.text("selectPage")} .checked=${a} .indeterminate=${!a && n.some((e) => i.has(e))} .disabled=${u || n.length === 0} @change=${() => this.selectPage(!a)}></pk-checkbox></th>` : _}
                                ${t.map((e) => {
			let t = !this.manualOrder && this.query.sort.find((t) => t.id === e.id);
			return x`<th
                                        scope="col"
                                        style=${Wr({ width: e.width })}
                                        class=${e.align ?? (e.type === "number" ? "end" : "")}
                                        ?data-sorted=${!!t}
                                        ?data-sortable=${!(!e.sortable || this.manualOrder)}
                                        aria-sort=${t ? t.direction === "asc" ? "ascending" : "descending" : _}
                                    >
                                        ${e.sortable && !this.manualOrder ? x`<button class="sort" aria-label=${this.text("sort", { label: e.label })} @click=${() => this.sortColumn(e.id)}>${e.label}${t ? d ? x`<pk-spinner class="sort-spinner" size="xs" aria-hidden="true"></pk-spinner>` : this.icon(t.direction === "asc" ? r.chevronUp : r.chevronDown) : _}</button>` : e.label}
                                    </th>`;
		})}
                                ${this.rowActions ? x`<th class="small"><span class="sr-only">${this.text("actions", { id: "" })}</span></th>` : _}
                                ${this.manualOrder ? x`<th class="small" scope="col"><span class="sr-only">${this.text("rowOrder")}</span></th>` : _}
                            </tr>
                        </thead>
                        <tbody class=${u && !d ? "busy" : ""} ?inert=${u} ?data-loading=${f}>
                            ${oe(e.rows, (e) => this.key(e), (e) => {
			let n = this.rowActions?.(e) ?? [];
			return x`<tr
                                        ?data-selected=${this.selectable && i.has(this.key(e))}
                                        ?data-selectable=${this.canSelectRow(e)}
                                        @click=${(t) => this.clickRow(t, e)}
                                        @keydown=${(t) => this.keyRow(t, e)}
                                        @mousedown=${(t) => {
				t.button === 0 && this.canSelectRow(e) && (t.shiftKey || t.metaKey || t.ctrlKey) && !this.isRowControl(t) && t.preventDefault();
			}}
                                    >
                                        ${this.selectable ? x`<td class="small">
                                                      <pk-checkbox
                                                          aria-label=${this.text("selectRow", { id: this.accessibleRowLabel(e) })}
                                                          .checked=${ne(i.has(this.key(e)))}
                                                          .disabled=${u || this.isRowSelectable?.(e) === !1}
                                                          @mousedown=${(e) => {
				e.shiftKey && (e.preventDefault(), e.currentTarget.focus({ preventScroll: !0 }));
			}}
                                                          @click=${(t) => this.clickRowCheckbox(t, e)}
                                                          @change=${() => this.changeRowCheckbox(e)}
                                                      ></pk-checkbox>
                                                  </td>` : _}
                                        ${t.map((t) => x`<td part="cell" class=${`${t.align ?? (t.type === "number" ? "end" : "")} ${t.wrap ?? ![
				"number",
				"date",
				"status",
				"handle"
			].includes(t.type ?? "text") ? "" : "nowrap"}`}>${this.renderCell(e, t)}</td>`)}
                                        ${this.rowActions ? x`<td class="small">
                                                      ${this.renderRowActions(e, n)}
                                                  </td>` : _}
                                        ${this.manualOrder ? x`<td class="small">
                                            <button class="row-handle" part="row-handle" type="button"
                                                aria-label=${this.text("reorderRow", { label: this.accessibleRowLabel(e) })}
                                                aria-describedby="row-order-help"
                                                aria-disabled=${this.canReorderRows ? "false" : "true"}
                                                @pointerdown=${(e) => this.rowOrder.start(e)}
                                                @keydown=${(t) => this.keyRowOrder(t, e)}
                                                >${this.icon(r.gripMove)}</button>
                                        </td>` : _}
                                    </tr>`;
		})}
                        </tbody>
                    </table>
                    ${f ? x`
                        ${e.rows.length ? _ : x`<div class="loading-space" aria-hidden="true"></div>`}
                        <div class="loading-indicator" aria-hidden="true"><pk-spinner></pk-spinner></div>
                    ` : _}
                    ${!e.rows.length && !f && !this.error ? x`<slot name="empty"><pk-state-panel .heading=${this.text(this.query.search || Object.keys(this.query.filters).length ? "noResults" : "empty")}></pk-state-panel></slot>` : _}
                </div>
                ${this.hideFooter ? _ : x`<div class="footer-shell" part="footer-shell">
                              ${p ? x`<div class="scrollbar" part="scrollbar" role="region" aria-label=${this.text("scrollColumns", { label: this.label })} tabindex="0" @scroll=${(e) => this.syncHorizontalScroll(e, ".scroll")}><div style=${Wr({ width: `${this.tableScrollWidth}px` })}></div></div>` : _}
                              <div class="footer" part="footer">
                                  <nav
                                      class="pager"
                                      aria-label=${this.text("pagination", { label: this.label })}
                                  >
                                      <pk-button
                                          size="sm"
                                          variant="outline"
                                          aria-label=${this.text("previous")}
                                          .disabled=${!l || this.loading}
                                          @click=${() => this.page(-1, e)}
                                          >${this.icon(r.chevronLeft)}</pk-button
                                      >
                                      <pk-button
                                          size="sm"
                                          variant="outline"
                                          aria-label=${this.text("next")}
                                          .disabled=${!c || this.loading}
                                          @click=${() => this.page(1, e)}
                                          >${this.icon(r.chevronRight)}</pk-button
                                      >
                                      <span>${m}</span>
                                  </nav>
                                  ${this.selectable ? x`<div
                                                class="selection"
                                                part="selection"
                                                ?data-empty=${i.size === 0}
                                                ?inert=${u || i.size === 0}
                                                aria-hidden=${i.size === 0 ? "true" : _}
                                            >
                                                <span class="selection-count"
                                                    >${this.text("selected", { count: i.size })}</span
                                                >
                                                <slot
                                                    name="selection-actions"
                                                ></slot>
                                            </div>` : _}
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
t([i()], X.prototype, "label", void 0), t([i({ attribute: !1 })], X.prototype, "rows", void 0), t([i({ attribute: !1 })], X.prototype, "columns", void 0), t([i({ attribute: !1 })], X.prototype, "filters", void 0), t([i({ attribute: !1 })], X.prototype, "rowKey", void 0), t([i({ attribute: !1 })], X.prototype, "query", void 0), t([i({ attribute: !1 })], X.prototype, "view", void 0), t([i()], X.prototype, "mode", void 0), t([i()], X.prototype, "pagination", void 0), t([i({ attribute: !1 })], X.prototype, "dataSource", void 0), t([i({ type: Number })], X.prototype, "total", void 0), t([i({
	type: Boolean,
	attribute: "has-next"
})], X.prototype, "hasNext", void 0), t([i({ attribute: "next-cursor" })], X.prototype, "nextCursor", void 0), t([i({ attribute: "previous-cursor" })], X.prototype, "previousCursor", void 0), t([i({ type: Boolean })], X.prototype, "loading", void 0), t([i()], X.prototype, "error", void 0), t([i({ type: Boolean })], X.prototype, "searchable", void 0), t([i({
	type: Boolean,
	attribute: "show-filters"
})], X.prototype, "showFilters", void 0), t([i({
	type: Boolean,
	attribute: "column-controls"
})], X.prototype, "columnControls", void 0), t([i({
	type: Boolean,
	attribute: "hide-toolbar"
})], X.prototype, "hideToolbar", void 0), t([i({
	type: Boolean,
	attribute: "hide-footer"
})], X.prototype, "hideFooter", void 0), t([i({ type: Boolean })], X.prototype, "selectable", void 0), t([i({
	type: Boolean,
	attribute: "manual-order"
})], X.prototype, "manualOrder", void 0), t([i({
	type: Boolean,
	attribute: "reorder-disabled"
})], X.prototype, "reorderDisabled", void 0), t([i({ attribute: !1 })], X.prototype, "selectedKeys", void 0), t([i({ attribute: !1 })], X.prototype, "isRowSelectable", void 0), t([i({ attribute: !1 })], X.prototype, "rowActions", void 0), t([i({ attribute: !1 })], X.prototype, "exportFormats", void 0), t([i({ type: Boolean })], X.prototype, "exporting", void 0), t([i({ attribute: !1 })], X.prototype, "defaultView", void 0), t([i({ attribute: !1 })], X.prototype, "defaultSort", void 0), t([i({
	type: Number,
	attribute: "search-delay"
})], X.prototype, "searchDelay", void 0), t([i({ reflect: !0 })], X.prototype, "size", void 0), t([i({ reflect: !0 })], X.prototype, "layout", void 0), t([i({ attribute: !1 })], X.prototype, "rowLabel", void 0), t([i()], X.prototype, "locale", void 0), t([i({ attribute: "time-zone" })], X.prototype, "timeZone", void 0), t([i({ attribute: !1 })], X.prototype, "labels", void 0), t([h()], X.prototype, "loaded", void 0), t([h()], X.prototype, "searchDraft", void 0), t([h()], X.prototype, "dragOffsets", void 0), t([h()], X.prototype, "reorderAnnouncement", void 0), t([h()], X.prototype, "rowOrderAnnouncement", void 0), t([h()], X.prototype, "tableScrollWidth", void 0), t([h()], X.prototype, "hasHorizontalOverflow", void 0), X = t([e("pk-data-table")], X);
// src/web/assets/cp/src/commenters.ts
var Z = (e, t = {}) => window.Craft?.t("comments", e, t) ?? e, ui = {
	trusted: "teal",
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
function di(e) {
	if (typeof e.photo == "string" && e.photo !== "") return $("img", {
		src: e.photo,
		alt: "",
		style: Q.avatar
	});
	let t = e.guest ? Q.guestAvatar : `background-color:var(--comments-color-avatar-${Number(e.tone) || 0})`;
	return $("span", {
		style: `${Q.avatar};${t}`,
		"aria-hidden": "true"
	}, String(e.initials ?? ""));
}
function fi(e) {
	if (typeof e != "string" || e === "") return "—";
	let t = new Date(e), n = Math.round((Date.now() - t.getTime()) / 6e4);
	return n < 60 ? Z("{count} min", { count: Math.max(1, n) }) : n < 1440 ? Z("{count} h", { count: Math.round(n / 60) }) : n < 10080 ? Z("{count} d", { count: Math.round(n / 1440) }) : t.toLocaleDateString(void 0, {
		day: "numeric",
		month: "short",
		year: "numeric"
	});
}
function pi(e) {
	let t = JSON.parse(e.dataset.settings ?? "[]"), n = new X();
	n.id = "comments-commenters-table", n.label = e.dataset.label ?? Z("Commenters"), n.rowKey = "id", n.mode = "remote", n.pagination = "offset", n.searchable = !0, n.showFilters = !1, n.selectable = !0, n.layout = "index", n.rowLabel = "name", n.defaultSort = [{
		id: "lastComment",
		direction: "desc"
	}], n.query = {
		...Zr(),
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
				let t = $("a", {
					href: String(e.url),
					style: `${Q.cell};min-width:200px;text-decoration:none`
				});
				t.append(di(e));
				let n = $("div", { style: "display:grid;gap:1px;min-width:0;line-height:1.35" }), r = $("div", { style: Q.name }), i = $("span", { style: `font-weight:600;color:var(--pk-data-table-cell-color, var(--link-color));${Q.truncate}` }, String(e.name));
				return t.addEventListener("pointerenter", () => {
					i.style.textDecoration = "underline";
				}), t.addEventListener("pointerleave", () => {
					i.style.textDecoration = "";
				}), r.append(i), e.guest && r.append($("span", { style: Q.guestPill }, Z("Guest"))), n.append(r), e.email && n.append($("div", { style: `color:var(--pk-data-table-cell-muted-color, var(--light-text-color));font-size:13px;${Q.truncate}` }, String(e.email))), t.append(n), t;
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
			renderCell: ({ row: e }) => $("pk-status-label", { status: ui[String(e.reviewSetting)] ?? "disabled" }, String(e.reviewSettingLabel ?? ""))
		},
		{
			id: "lastComment",
			label: Z("Last comment"),
			type: "custom",
			sortable: !0,
			renderCell: ({ row: e }) => {
				let t = $("div", { style: "min-width:0;white-space:nowrap" });
				return t.append($("div", {}, fi(e.lastComment))), e.lastOn && t.append($("div", {
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
	}, Z("Change review setting…")), i = $("pk-dialog", {
		label: Z("Change review setting"),
		class: "comments-review-dialog"
	}), a = $("pk-radio-group", { label: Z("Review setting") });
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
	l && new ResizeObserver(() => n.style.setProperty("--pk-data-table-header-offset", `${l.offsetHeight}px`)).observe(l), li(n, {
		key: "comments:commenters",
		userId: e.dataset.userId ?? ""
	}), e.replaceChildren(n);
}
function mi(e) {
	e.addEventListener("submit", (e) => {
		let t = e.submitter;
		t?.dataset.commentsConfirm && !window.confirm(t.dataset.commentsConfirm) && e.preventDefault();
	});
}
function hi(e) {
	let t = window.jQuery, n = window.Craft.Tabs;
	if (!t || !n) return;
	let r = [...e.querySelectorAll("[role=\"tab\"]")].map((e) => document.getElementById(e.dataset.id ?? "")).filter((e) => e !== null), i = new n(t(e));
	i.on("selectTab", ({ $tab: e }) => {
		let t = (e.attr("href") ?? "").replace(/^#/, "");
		r.forEach((e) => e.classList.toggle("hidden", e.id !== t)), window.dispatchEvent(new Event("resize"));
	});
	let a = window.LOCATION_HASH || window.location.hash.slice(1), o = [...e.querySelectorAll("[role=\"tab\"]")].find((e) => a !== "" && e.getAttribute("href") === `#${a}`);
	o && i.selectTab(t(o));
}
document.addEventListener("click", async (e) => {
	let t = e.target.closest("[data-comments-commenter-note]"), n = t?.closest("[data-comments-commenter-activity]"), r = n?.querySelector("[data-comments-note-input]");
	if (t && n && r) {
		if (e.preventDefault(), !r.value.trim()) r.focus();
		else {
			t.classList.add("loading");
			try {
				let e = await window.Craft.sendActionRequest("POST", "comments/commenters/add-note", { data: {
					identityId: n.dataset.identityId,
					note: r.value
				} });
				n.outerHTML = e.data.html ?? n.outerHTML, window.Craft.cp.displaySuccess(e.data.message ?? "");
			} catch (e) {
				t.classList.remove("loading"), window.Craft.cp.displayError(e.response?.data?.message);
			}
		}
	}
}), document.querySelectorAll("[data-comments-commenters]").forEach(pi), document.querySelectorAll("[data-comments-commenter-tabs]").forEach(hi), document.querySelectorAll("[data-comments-review-form]").forEach(mi);

//# sourceMappingURL=commenters.js.map