import { A as e, C as t, D as n, E as r, M as i, S as a, T as o, _ as s, a as c, c as l, g as u, h as ee, i as d, j as f, k as p, l as m, m as te, n as ne, o as re, p as h, r as g, t as _, u as v, v as y, w as b, x, y as S } from "./pk-copy-button-Dfji6-PV.js";
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/form-control.styles-CQ93gnVf.js
var C = i`
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
`, w = [
	"aria-labelledby",
	"aria-describedby",
	"aria-invalid",
	"aria-errormessage",
	"aria-required",
	"aria-label"
];
function T(e) {
	return e.hasAttribute("aria-labelledby") || e.hasAttribute("aria-describedby") || e.hasAttribute("aria-errormessage");
}
function E(e, t) {
	for (let n of w) {
		let r = e.getAttribute(n);
		r === null ? t.removeAttribute(n) : t.setAttribute(n, r);
	}
}
function D({ control: e, labelId: t, instructionsId: n, hasLabel: r, hasInstructions: i, required: a = !1, invalid: o = !1 }) {
	r && t ? e.setAttribute("aria-labelledby", t) : e.removeAttribute("aria-labelledby"), i && n ? e.setAttribute("aria-describedby", n) : e.removeAttribute("aria-describedby"), a ? e.setAttribute("aria-required", "true") : e.removeAttribute("aria-required"), o ? e.setAttribute("aria-invalid", "true") : e.removeAttribute("aria-invalid"), e.removeAttribute("aria-errormessage");
}
var O = class {
	constructor(e, t, n) {
		this.host = e, this.getTarget = t, this.onSync = n;
	}
	connect() {
		this.sync(), this.observer = new MutationObserver(() => {
			this.sync();
		}), this.observer.observe(this.host, {
			attributes: !0,
			attributeFilter: [...w]
		});
	}
	disconnect() {
		this.observer?.disconnect(), this.observer = void 0;
	}
	sync() {
		if (!T(this.host)) {
			this.onSync?.();
			return;
		}
		let e = this.getTarget();
		e && E(this.host, e);
	}
}, k = class extends Event {
	constructor() {
		super("pk-invalid", {
			bubbles: !0,
			cancelable: !1,
			composed: !0
		});
	}
};
function ie() {
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
var A = class extends b {
	static {
		this.formAssociated = !0;
	}
	static get validators() {
		return [ie()];
	}
	static get observedAttributes() {
		let e = new Set(super.observedAttributes ?? []);
		for (let t of this.validators) for (let n of t.observedAttributes ?? []) e.add(n);
		return [...e];
	}
	constructor() {
		super(), this.internals = this.attachInternals(), this.assumeInteractionOn = ["input"], this.validators = [], this.name = null, this.disabled = !1, this.required = !1, this.customError = null, this.valueHasChanged = !1, this.hasInteracted = !1, this.emittedEvents = [], this.emitInvalid = (e) => {
			e.target === this && (this.hasInteracted = !0, this.dispatchEvent(new k()));
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
		this.hostAriaMirror?.disconnect(), this.hostAriaMirror = new O(this, () => this.getAriaMirrorTarget(), () => this.syncStandaloneAria()), this.hostAriaMirror.connect();
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
o([t({ reflect: !0 })], A.prototype, "name", void 0), o([t({
	type: Boolean,
	reflect: !0
})], A.prototype, "disabled", void 0), o([t({
	type: Boolean,
	reflect: !0
})], A.prototype, "required", void 0), o([t({
	attribute: "custom-error",
	reflect: !0
})], A.prototype, "customError", void 0), o([t({
	attribute: !1,
	state: !0
})], A.prototype, "valueHasChanged", void 0), o([t({
	attribute: !1,
	state: !0
})], A.prototype, "hasInteracted", void 0);
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/required-validator-I58oJGZ3.js
function j(e = {}) {
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
function M(e, t) {
	return t || (e.getAttribute("hint") ?? "");
}
function N(e, t, n = !1) {
	return !!t || e("instructions", n) || e("hint");
}
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/mirror-validator-BkZlEv1Z.js
function P() {
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
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/focus-DcVUPVqn.js
var F = 0;
function I(e = "pk") {
	return F += 1, `${e}-${F}`;
}
// ../../../plugin-kit/plugin-kit/node_modules/lit-html/directive-helpers.js
var { I: L } = f, R = (e) => e, z = (e) => e.strings === void 0, B = () => document.createComment(""), V = (e, t, n) => {
	let r = e._$AA.parentNode, i = t === void 0 ? e._$AB : t._$AA;
	if (n === void 0) n = new L(r.insertBefore(B(), i), r.insertBefore(B(), i), e, e.options);
	else {
		let t = n._$AB.nextSibling, a = n._$AM, o = a !== e;
		if (o) {
			let t;
			n._$AQ?.(e), n._$AM = e, n._$AP !== void 0 && (t = e._$AU) !== a._$AU && n._$AP(t);
		}
		if (t !== i || o) {
			let e = n._$AA;
			for (; e !== t;) {
				let t = R(e).nextSibling;
				R(r).insertBefore(e, i), e = t;
			}
		}
	}
	return n;
}, H = (e, t, n = e) => (e._$AI(t, n), e), U = {}, W = (e, t = U) => e._$AH = t, G = (e) => e._$AH, K = (e) => {
	e._$AR(), e._$AA.remove();
}, q = te(class extends ee {
	constructor(e) {
		if (super(e), e.type !== u.PROPERTY && e.type !== u.ATTRIBUTE && e.type !== u.BOOLEAN_ATTRIBUTE) throw Error("The `live` directive is not allowed on child or event bindings");
		if (!z(e)) throw Error("`live` bindings can only contain a single expression");
	}
	render(e) {
		return e;
	}
	update(e, [t]) {
		if (t === p || t === n) return t;
		let r = e.element, i = e.name;
		if (e.type === u.PROPERTY) {
			if (t === r[i]) return p;
		} else if (e.type === u.BOOLEAN_ATTRIBUTE) {
			if (!!t === r.hasAttribute(i)) return p;
		} else if (e.type === u.ATTRIBUTE && r.getAttribute(i) === t + "") return p;
		return W(e), t;
	}
}), J = (e) => e ?? n, Y = class extends Event {
	constructor() {
		super("pk-clear", {
			bubbles: !0,
			cancelable: !1,
			composed: !0
		});
	}
}, ae = /* @__PURE__ */ new Set([
	"button",
	"submit",
	"reset",
	"checkbox",
	"radio",
	"file",
	"image",
	"hidden"
]), oe = "pk-implicit-submit", se = (e, t) => {
	if (e.key !== "Enter" || e.defaultPrevented || e.isComposing || e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) return !1;
	let n = (t || "text").toLowerCase();
	return !ae.has(n);
}, ce = (e) => {
	let t = e.closest?.("pk-dialog");
	if (t) {
		let e = t.querySelector("form");
		if (e) return e;
	}
	let n = e.form;
	return n && n.id === "main" ? e.closest?.("form") === n ? null : e.closest("form") : n;
}, le = (e, t, n) => {
	if (e.disabled || e.readonly || !se(t, n)) return !1;
	let r = ce(e);
	return !r || r.id === "main" ? !1 : (t.preventDefault(), t.stopPropagation(), r.dispatchEvent(new CustomEvent(oe, {
		bubbles: !1,
		cancelable: !0
	})), !0);
}, ue = i`
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
        .form-control__end.has-action {
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
`, X = class extends A {
	constructor(...e) {
		super(...e), this.assumeInteractionOn = ["blur", "input"], this.hasSlotController = new d(this, "instructions", "hint", "label", "start", "end"), this.inputId = I("pk-input"), this.type = "text", this._value = null, this.defaultValue = null, this.size = "default", this.label = "", this.instructions = "", this.withClear = !1, this.clearLabel = "Clear", this.placeholder = "", this.readonly = !1, this.invalid = !1, this.fitCell = !1, this.mono = !1, this.autofocus = !1, this.withLabel = !1, this.withInstructions = !1;
	}
	static {
		this.styles = [
			C,
			S(),
			y(".input", "var(--pk-input-border-radius, var(--pk-radius-sm))"),
			s(".input"),
			ue
		];
	}
	static get validators() {
		return [
			...super.validators,
			P(),
			j()
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
		this.instructions = M(this, this.instructions), this.hasAttribute("with-hint") && (this.withInstructions = !0), super.connectedCallback();
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
		let e = !!this.label || this.hasSlotController.test("label", this.withLabel), t = N((e, t) => this.hasSlotController.test(e, t), this.instructions, this.withInstructions);
		D({
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
		return N((e, t) => this.hasSlotController.test(e, t), this.instructions, this.withInstructions);
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
		le(this, e, this.type);
	}
	handleClearClick(e) {
		e.preventDefault(), this.value !== "" && (this.value = "", this.dispatchEvent(new Y()), this.dispatchEvent(new Event("input", {
			bubbles: !0,
			composed: !0
		})), this.dispatchEvent(new Event("change", {
			bubbles: !0,
			composed: !0
		})), this.input.focus());
	}
	endHasAction() {
		return [...this.children].some((e) => e.getAttribute("slot") === "end" && e.localName === "pk-copy-button");
	}
	handleEndSlotChange() {
		this.requestUpdate();
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
                        .value=${q(this.value)}
                        placeholder=${this.placeholder || n}
                        pattern=${J(this.pattern)}
                        minlength=${J(this.minlength)}
                        maxlength=${J(this.maxlength)}
                        min=${J(this.min)}
                        max=${J(this.max)}
                        step=${J(this.step)}
                        autocomplete=${J(this.autocomplete)}
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
                            <span part="end" class=${this.endHasAction() ? "form-control__end has-action" : "form-control__end"}>
                                <slot name="end" @slotchange=${this.handleEndSlotChange}></slot>
                            </span>
                        ` : e`<slot name="end" hidden></slot>`}
                </div>
            </div>
        `;
	}
};
o([x("input")], X.prototype, "input", void 0), o([t({ reflect: !0 })], X.prototype, "type", void 0), o([a()], X.prototype, "value", null), o([t({
	attribute: "value",
	reflect: !0
})], X.prototype, "defaultValue", void 0), o([t({ reflect: !0 })], X.prototype, "size", void 0), o([t()], X.prototype, "label", void 0), o([t()], X.prototype, "instructions", void 0), o([t({
	attribute: "with-clear",
	type: Boolean
})], X.prototype, "withClear", void 0), o([t({ attribute: "clear-label" })], X.prototype, "clearLabel", void 0), o([t()], X.prototype, "placeholder", void 0), o([t({
	type: Boolean,
	reflect: !0
})], X.prototype, "readonly", void 0), o([t({
	type: Boolean,
	reflect: !0
})], X.prototype, "invalid", void 0), o([t({
	type: Boolean,
	reflect: !0,
	attribute: "fit-cell"
})], X.prototype, "fitCell", void 0), o([t({
	type: Boolean,
	reflect: !0
})], X.prototype, "mono", void 0), o([t()], X.prototype, "pattern", void 0), o([t({ type: Number })], X.prototype, "minlength", void 0), o([t({ type: Number })], X.prototype, "maxlength", void 0), o([t()], X.prototype, "min", void 0), o([t()], X.prototype, "max", void 0), o([t()], X.prototype, "step", void 0), o([t()], X.prototype, "autocomplete", void 0), o([t({
	type: Boolean,
	reflect: !0
})], X.prototype, "autofocus", void 0), o([t({
	attribute: "with-label",
	type: Boolean
})], X.prototype, "withLabel", void 0), o([t({
	attribute: "with-instructions",
	type: Boolean
})], X.prototype, "withInstructions", void 0), X = o([r("pk-input")], X);
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/components/icon/pk-icon.js
var Z = class extends b {
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
		super.connectedCallback(), this.unsubscribeRegistry = v(() => {
			this.requestUpdate();
		});
	}
	disconnectedCallback() {
		this.unsubscribeRegistry?.(), this.unsubscribeRegistry = null, super.disconnectedCallback();
	}
	render() {
		let t = m(this.icon || this.name);
		return t ? e`${h(l(t, { title: this.label }))}` : n;
	}
};
o([t()], Z.prototype, "icon", void 0), o([t()], Z.prototype, "name", void 0), o([t()], Z.prototype, "label", void 0), Z = o([r("pk-icon")], Z);
// ../../../plugin-kit/plugin-kit/plugin-kit-web/dist/chunks/pk-state-panel-BjL_ch5N.js
var de = i`
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

        /* A page-level state has nothing around it, so it gets more presence: a larger icon, or a plugin's own spot
           illustration in the icon slot, centred in concentric rings that fade outward. The icon sits straight on
           the page, without a shell. After the size and variant rules, so its scale wins. */
        :host([layout='page']) {
            overflow: hidden;
            --pk-state-panel-icon-background: transparent;
            --_pk-state-panel-font-size: var(--pk-font-size-base);
            --_pk-state-panel-title-size: 1.125rem;
            --_pk-state-panel-content-width: 30rem;
            --_pk-state-panel-min-height: 22rem;
            /* The top leaves room for the filled rings, three gaps out from the icon's centre, so none is cut off. */
            --_pk-state-panel-padding: calc(var(--_pk-state-panel-ring-gap) * 3 - 2rem) 1rem 4rem;
            --_pk-state-panel-icon-shell-size: 4rem;
            --_pk-state-panel-icon-size: 2rem;
            --_pk-state-panel-icon-radius: 0;
            --_pk-state-panel-icon-margin-bottom: 1.75rem;
            --_pk-state-panel-body-margin-top: 0.5rem;
            --_pk-state-panel-ring-gap: 3rem;
        }

        /* Slotted art fills the whole icon area, keeping its own proportions. */
        :host([layout='page']) .icon:has(slot[name='icon']) {
            width: 100%;
            height: 100%;
        }

        /* The panel holds the rings' stacking, so they paint under the shell's background and over nothing outside. */
        :host([layout='page']) .panel {
            position: relative;
            z-index: 0;
        }

        :host([layout='page']) .icon-shell {
            position: relative;
        }

        /* Absolutely positioned, so the rings never change the panel's size. The first ring clears the icon. Each
           ring's band is filled, whitest at the centre and stepping down outward, under the ring lines. */
        :host([layout='page']) .icon-shell::before {
            --_pk-state-panel-ring-fill: var(--pk-state-panel-ring-fill, var(--pk-color-white));
            content: '';
            position: absolute;
            z-index: -1;
            top: 50%;
            left: 50%;
            width: 24rem;
            height: 24rem;
            border-radius: 50%;
            transform: translate(-50%, -50%);
            background:
                repeating-radial-gradient(
                    circle,
                    transparent 0 calc(var(--_pk-state-panel-ring-gap) - 1px),
                    var(--pk-state-panel-ring-color, var(--pk-color-gray-200))
                        calc(var(--_pk-state-panel-ring-gap) - 1px) var(--_pk-state-panel-ring-gap)
                ),
                radial-gradient(
                    circle,
                    color-mix(in srgb, var(--_pk-state-panel-ring-fill) 90%, transparent) 0
                        var(--_pk-state-panel-ring-gap),
                    color-mix(in srgb, var(--_pk-state-panel-ring-fill) 50%, transparent)
                        var(--_pk-state-panel-ring-gap) calc(var(--_pk-state-panel-ring-gap) * 2),
                    color-mix(in srgb, var(--_pk-state-panel-ring-fill) 25%, transparent)
                        calc(var(--_pk-state-panel-ring-gap) * 2) calc(var(--_pk-state-panel-ring-gap) * 3),
                    transparent calc(var(--_pk-state-panel-ring-gap) * 3)
                );
            mask-image: radial-gradient(circle closest-side, #000 15%, transparent 100%);
            pointer-events: none;
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
`, fe = 3e3, Q = {
	empty: c.emptySet,
	info: c.circleInfo,
	success: c.circleCheck,
	warning: c.triangleExclamation,
	error: c.triangleExclamation
}, $ = class extends b {
	constructor(...e) {
		super(...e), this.hasSlotController = new d(this, "title", "icon", "details", "actions"), this.variant = "empty", this.size = "default", this.layout = "region", this.heading = "", this.headingLevel = 2, this.icon = "", this.hideIcon = !1, this.announce = "off", this.detailsLabel = "Details", this.detailsOpen = !1, this.copyable = !1, this.copyLabel = "Copy details", this.copiedLabel = "Details copied.", this.copyErrorLabel = "Copy failed. Select the details and copy them manually.", this.copyStatus = "", this.copyFailed = !1;
	}
	static {
		this.styles = de;
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
		}, fe);
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
		if (!e) this.showCopyError(), this.dispatchEvent(new _());
		else if (this.copyButton) this.copyButton.value = e, await this.copyButton.copy();
		else try {
			await g(e), this.showCopySuccess();
			let t = new ne(e);
			this.dispatchEvent(t);
		} catch {
			this.showCopyError(), this.selectDetails(), this.dispatchEvent(new _());
		}
	}
	handleDetailsToggle(e) {
		this.detailsOpen = e.currentTarget.open;
	}
	renderIcon() {
		if (this.hideIcon) return n;
		let t;
		return t = this.hasSlotController.test("icon") ? e`<slot name="icon"></slot>` : this.icon ? e`<pk-icon .icon=${this.icon}></pk-icon>` : h(re(Q[this.variant] ?? Q.empty)), e`
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
o([t({ reflect: !0 })], $.prototype, "variant", void 0), o([t({ reflect: !0 })], $.prototype, "size", void 0), o([t({ reflect: !0 })], $.prototype, "layout", void 0), o([t()], $.prototype, "heading", void 0), o([t({
	type: Number,
	attribute: "heading-level"
})], $.prototype, "headingLevel", void 0), o([t()], $.prototype, "icon", void 0), o([t({
	type: Boolean,
	attribute: "hide-icon",
	reflect: !0
})], $.prototype, "hideIcon", void 0), o([t({ reflect: !0 })], $.prototype, "announce", void 0), o([t({ attribute: "details-label" })], $.prototype, "detailsLabel", void 0), o([t({
	type: Boolean,
	attribute: "details-open",
	reflect: !0
})], $.prototype, "detailsOpen", void 0), o([t({
	type: Boolean,
	reflect: !0
})], $.prototype, "copyable", void 0), o([t({ attribute: "copy-label" })], $.prototype, "copyLabel", void 0), o([t({ attribute: "copied-label" })], $.prototype, "copiedLabel", void 0), o([t({ attribute: "copy-error-label" })], $.prototype, "copyErrorLabel", void 0), o([x("slot[name=\"details\"]")], $.prototype, "detailsSlot", void 0), o([x("pk-copy-button.copy")], $.prototype, "copyButton", void 0), o([a()], $.prototype, "copyStatus", void 0), o([a()], $.prototype, "copyFailed", void 0), $ = o([r("pk-state-panel")], $);
export { K as a, V as c, N as d, M as f, C as g, D as h, G as i, I as l, A as m, J as n, W as o, j as p, q as r, H as s, Y as t, P as u };

//# sourceMappingURL=pk-state-panel-BjL_ch5N.js.map