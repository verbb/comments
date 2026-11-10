// src/resources/src/js/events.ts
function e(e, t, n, r = {}) {
	let i = () => new CustomEvent(t, {
		bubbles: !0,
		composed: !0,
		cancelable: r.cancelable ?? !1,
		detail: {
			contractVersion: 1,
			root: e,
			instanceId: e.dataset.commentsInstance ?? "",
			conversationUid: e.dataset.commentsConversationUid ?? "",
			controller: r.controller,
			source: r.source ?? "api",
			...n
		}
	}), a = e.dispatchEvent(i());
	return !e.isConnected && !r.cancelable && document.dispatchEvent(i()), a;
}
// src/resources/src/js/editor-frame.ts
var t = {
	writeFirst: "Write something first.",
	tooLong: "Too long by {count} characters",
	charactersLeft: "{count} characters left",
	charactersOf: "{count} of {limit} characters",
	draftSaved: "Draft saved",
	draftRestored: "Restored your draft from {time}.",
	discard: "Discard",
	posting: "Posting…"
}, n = "comments-draft:", r = 1, i = {
	check: "<path d=\"M20 6 9 17l-5-5\"/>",
	alert: "<circle cx=\"12\" cy=\"12\" r=\"10\"/><path d=\"M12 8v4M12 16h.01\"/>",
	info: "<circle cx=\"12\" cy=\"12\" r=\"10\"/><path d=\"M12 16v-4M12 8h.01\"/>"
}, a = /* @__PURE__ */ new WeakMap();
function o(e, t) {
	return e.replace(/\{(\w+)\}/g, (e, n) => n in t ? String(t[n]) : e);
}
function s(e) {
	return `<svg class="comments__frame-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${i[e]}</svg>`;
}
function c(e, t, n) {
	let r = document.createElement(e);
	return r.className = t, n !== void 0 && (r.textContent = n), r;
}
function l() {
	let e = navigator.userAgentData?.platform ?? navigator.platform ?? "";
	return /mac|iphone|ipad|ipod/i.test(e);
}
function u(e) {
	let t = Math.min(-1, Math.round((e - Date.now()) / 6e4)), n;
	try {
		n = new Intl.RelativeTimeFormat(document.documentElement.lang || void 0, { numeric: "auto" });
	} catch {
		n = new Intl.RelativeTimeFormat(void 0, { numeric: "auto" });
	}
	return Math.abs(t) < 60 ? n.format(t, "minute") : Math.abs(t) < 1440 ? n.format(Math.round(t / 60), "hour") : n.format(Math.round(t / 1440), "day");
}
function d(e) {
	try {
		let t = JSON.parse(localStorage.getItem(n + e) ?? "null");
		if (!t || typeof t != "object") return null;
		let { version: i, savedAt: a, text: o } = t;
		return i === r && typeof a == "number" && typeof o == "string" ? {
			savedAt: a,
			text: o
		} : null;
	} catch {
		return null;
	}
}
function f(e, t) {
	try {
		t === null ? localStorage.removeItem(n + e) : localStorage.setItem(n + e, JSON.stringify({
			version: r,
			savedAt: Date.now(),
			text: t
		}));
	} catch {}
}
function p(e) {
	let t = e.dataset.commentsDraftKey;
	try {
		return !!(t && localStorage.getItem(`verbb-editor-draft:${t}`));
	} catch {
		return !1;
	}
}
function m(e) {
	return e.querySelector("textarea[name$=\"[comment]\"]");
}
function h(e) {
	return e.form.querySelector("template[data-comments-editor]") ? null : e.form.dataset.commentsDraftKey ?? null;
}
function g(e) {
	return Number(e.form.dataset.commentsLimit) || 0;
}
function _(e) {
	let t = a.get(e);
	if (!t || e.dataset.commentsEditorMounted) return "";
	let n = t.box.value, r = g(t);
	return n.trim() ? r > 0 && n.length > r ? o(t.labels.tooLong, { count: n.length - r }) : "" : t.labels.writeFirst;
}
function v(e) {
	let t = _(e), n = a.get(e);
	return !t || !n ? !1 : (n.announce(t), n.submit?.animate?.([
		{ transform: "translateX(0)" },
		{ transform: "translateX(-3px)" },
		{ transform: "translateX(3px)" },
		{ transform: "translateX(0)" }
	], { duration: 220 }), n.box.focus(), !0);
}
function y(e) {
	let { count: t, labels: n } = e, r = g(e), i = e.box.value.length;
	if (t.replaceChildren(), t.className = "comments__frame-count", t.removeAttribute("title"), !(r > 0) || i < r * .8) return;
	let a = r - i, s = 2 * Math.PI * 8, l = Math.min(i / r, 1);
	t.classList.toggle("is-bad", a < 0), t.classList.toggle("is-warn", a >= 0 && a <= r * .1), t.title = o(n.charactersOf, {
		count: i.toLocaleString(),
		limit: r.toLocaleString()
	}), t.innerHTML = `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><circle class="comments__ring-bg" cx="10" cy="10" r="8"/><circle class="comments__ring-fg" cx="10" cy="10" r="8" stroke-dasharray="${s}" stroke-dashoffset="${s * (1 - l)}"/></svg>`, t.append(c("span", "", String(a)), c("span", "comments__visually-hidden", a < 0 ? o(n.tooLong, { count: -a }) : o(n.charactersLeft, { count: a })));
}
function b(e) {
	let { box: t, status: n, labels: r, submit: i } = e, a = g(e), l = a > 0 ? t.value.length - a : 0;
	if (y(e), n.className = "comments__frame-status", n.replaceChildren(), l > 0 ? (n.classList.add("is-bad"), n.innerHTML = s("alert"), n.append(c("span", "comments__frame-status-text", o(r.tooLong, { count: l })))) : e.draftState && (n.classList.toggle("is-faded", e.draftState === "faded"), n.innerHTML = s("check"), n.append(c("span", "comments__frame-status-text", r.draftSaved))), i && !i.hasAttribute("aria-busy")) {
		let t = _(e.form);
		i.setAttribute("aria-disabled", String(!!t)), t ? i.setAttribute("aria-description", t) : i.removeAttribute("aria-description");
	}
	t.style.height = "", t.value && t.scrollHeight > t.clientHeight && (t.style.height = `${Math.min(t.scrollHeight + t.offsetHeight - t.clientHeight, 436)}px`);
}
function x(e) {
	if (!e.form.hasAttribute("data-comments-collapsible")) return;
	let t = !e.frame.contains(document.activeElement) && !e.box.value;
	e.frame.toggleAttribute("data-comments-collapsed", t);
}
function S(e, t, n) {
	let { notice: r } = e;
	if (r.hidden = !1, r.innerHTML = s("info"), r.append(c("span", "comments__frame-notice-text", t)), n) {
		let e = c("button", "comments__frame-link", n.label);
		e.type = "button", e.addEventListener("click", n.run), r.append(e);
	}
}
function C(e) {
	e.notice.hidden = !0, e.notice.replaceChildren();
}
function ee(e) {
	let t = h(e);
	t && (window.clearTimeout(e.draftTimer), e.draftTimer = window.setTimeout(() => {
		let { box: n } = e;
		n.value === n.defaultValue || !n.value.trim() ? (f(t, null), e.draftState && (e.draftState = null, b(e))) : d(t)?.text !== n.value && (f(t, n.value), e.draftState = "saved", b(e), window.clearTimeout(e.fadeTimer), e.fadeTimer = window.setTimeout(() => {
			e.draftState = "faded", b(e);
		}, 2600));
	}, 800));
}
function w(e) {
	let t = h(e), n = t ? d(t) : null;
	t && n && n.text !== e.box.value && (e.box.value = n.text, S(e, o(e.labels.draftRestored, { time: u(n.savedAt) }), {
		label: e.labels.discard,
		run: () => {
			oe(e.form), e.box.value = e.box.defaultValue, b(e), e.box.focus();
		}
	}));
}
function te(e) {
	C(e), b(e), x(e), ee(e);
}
function ne(e, n, r) {
	let i = m(e), o = i?.closest(".comments__frame") ?? null;
	if (a.has(e) || !i || !o) return;
	i.rows = 1;
	let s = e.querySelector(".comments__submit"), u = e.querySelector(".comments__frame-foot"), d = c("span", "comments__frame-meta"), f = c("span", "comments__frame-status"), p = c("span", "comments__frame-count"), h = c("div", "comments__frame-notice");
	if (d.append(f, p), u?.prepend(d), h.setAttribute("role", "status"), h.hidden = !0, i.after(h), s && !s.querySelector("kbd")) {
		let e = c("kbd", "comments__submit-keys", l() ? "⌘↵" : "Ctrl+Enter");
		s.querySelector(".comments__submit-label")?.after(e);
	}
	let g = {
		form: e,
		box: i,
		frame: o,
		submit: s,
		label: s?.querySelector(".comments__submit-label") ?? null,
		status: f,
		count: p,
		notice: h,
		labels: {
			...t,
			...n
		},
		announce: r,
		draftState: null
	};
	a.set(e, g), e.dataset.commentsEditorFrame = "1", w(g), b(g), x(g);
}
function re(e) {
	let t = e.target;
	if (!(t instanceof Element)) return;
	let n = t.closest("form[data-comments-editor-frame]"), r = n ? a.get(n) : void 0;
	if (n && r && !n.dataset.commentsEditorMounted) switch (e.type) {
		case "input":
			t === r.box && te(r);
			break;
		case "keydown": {
			let i = e;
			t === r.box && i.key === "Enter" && (i.metaKey || i.ctrlKey) && (e.preventDefault(), v(n) || n.requestSubmit(r.submit ?? void 0));
			break;
		}
		case "click":
			r.submit && t.closest(".comments__submit") === r.submit && v(n) && e.preventDefault();
			break;
		case "focusin":
			window.clearTimeout(r.collapseTimer), x(r);
			break;
		case "focusout": window.clearTimeout(r.collapseTimer), r.collapseTimer = window.setTimeout(() => x(r), 140);
	}
}
function ie(e, t) {
	let n = a.get(e);
	if (!n?.submit || !n.label) return;
	let r = n.submit.getAttribute("aria-label") ?? n.label.textContent ?? "";
	n.label.textContent = t ? n.labels.posting : r, n.submit.toggleAttribute("data-comments-posting", t), t ? n.submit.setAttribute("aria-disabled", "true") : b(n);
}
function ae(e) {
	let t = a.get(e);
	t && (C(t), b(t), x(t));
}
function oe(e) {
	let t = a.get(e), n = t ? h(t) : null;
	t && (window.clearTimeout(t.draftTimer), window.clearTimeout(t.fadeTimer), t.draftState = null, n && f(n, null), C(t), b(t));
}
function se(e) {
	(e instanceof HTMLFormElement ? [e] : Array.from(e.querySelectorAll("form[data-comments-editor-frame]"))).forEach((e) => {
		let t = a.get(e);
		t && (window.clearTimeout(t.draftTimer), window.clearTimeout(t.fadeTimer), window.clearTimeout(t.collapseTimer));
	});
}
// src/resources/src/js/editor-bridge.ts
var T = /* @__PURE__ */ new WeakSet(), E = [
	"header",
	"actions",
	"footer"
];
function D(e) {
	return e === "dark" || e === "none" ? e : e === "auto" && typeof window < "u" && window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
}
function O(e) {
	let t = e.querySelector("verbb-editor[data-editor-root]");
	return t && customElements.get("verbb-editor") ? t : null;
}
function k(e) {
	return O(e)?.editor ?? null;
}
function A(e) {
	return !!e.querySelector("template[data-comments-editor], verbb-editor[data-editor-root]");
}
function ce(e, t) {
	let n = m(e), r = e.querySelector("verbb-editor[data-editor-root]");
	if (!r && customElements.get("verbb-editor")) {
		let t = e.querySelector("template[data-comments-editor]"), n = t?.content.querySelector("verbb-editor")?.cloneNode(!0);
		n instanceof HTMLElement && (r = n, t?.after(r));
	}
	if (!r || !customElements.get("verbb-editor")) return null;
	let i = r, a = n !== null && document.activeElement === n;
	return i.setAttribute("theme", D(t.theme)), e.querySelectorAll("[data-comments-slot]").forEach((e) => {
		let t = e.dataset.commentsSlot ?? "";
		E.includes(t) && !i.contains(e) && (e.setAttribute("slot", t), i.append(e));
	}), n && !T.has(i) && (n.value !== n.defaultValue && i.editor && i.editor.setContent(n.value), a && i.focus(), n.required = !1), T.has(i) || (i.addEventListener("editor-update", () => le(e)), T.add(i)), le(e), i;
}
function le(e) {
	let t = k(e), n = e.querySelector("input[name=\"contentFormat\"], input[name$=\"[contentFormat]\"]"), r = e.querySelector("input[name$=\"[editorContent]\"]"), i = m(e);
	t ? (i && (i.value = t.getText()), r && (r.value = JSON.stringify(t.getJSON()), n && (n.value = "editor"))) : n && (n.value = "plain");
}
function ue(e) {
	return !!k(e)?.isUploading();
}
function de(e, t, n) {
	let r = O(e);
	r && (r.toggleAttribute("busy", t), n === void 0 ? t && r.clearPostError() : r.showPostError(n || void 0));
}
function j(e) {
	let t = e ? e.querySelector("verbb-editor[data-editor-root]") : null;
	t && T.delete(t);
}
function M(e) {
	return O(e);
}
// src/resources/src/js/api-url.ts
function fe(e, t) {
	let n = new URL(e);
	return n.searchParams.has("p") ? n.searchParams.set("p", `${n.searchParams.get("p").replace(/\/$/, "")}/${t}`) : n.pathname = `${n.pathname.replace(/\/$/, "")}/${t}`, n;
}
// ../../../protect/craft5/plugin/packages/protect/dist/scripts.js
var N = /* @__PURE__ */ new Map();
function P(e, t) {
	let n = N.get(e);
	if (n) return n;
	let r = new Promise((n, r) => {
		let i = document.createElement("script");
		i.src = e, i.async = !0, t && (i.nonce = t);
		let a = setTimeout(() => o(), 15e3), o = () => {
			clearTimeout(a), i.remove(), N.delete(e), r(/* @__PURE__ */ Error("Challenge script unavailable."));
		};
		i.addEventListener("load", () => {
			clearTimeout(a), n();
		}, { once: !0 }), i.addEventListener("error", o, { once: !0 }), document.head.append(i);
	});
	return N.set(e, r), r;
}
function F(e, t, n = 15e3) {
	return new Promise((r, i) => {
		let a = () => {
			s(), i(/* @__PURE__ */ Error("Challenge unavailable."));
		}, o = setTimeout(a, n), s = () => {
			clearTimeout(o), t.removeEventListener("abort", a);
		};
		t.addEventListener("abort", a, { once: !0 }), t.aborted ? a() : e.then((e) => {
			s(), r(e);
		}, () => a());
	});
}
// ../../../protect/craft5/plugin/packages/protect/dist/providers.js
var pe = (e) => /^(100|110[1-5]|200|400)/.test(String(e ?? "")), me = /* @__PURE__ */ new Map([["turnstile", async (e) => {
	await F(P("https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit", e.nonce), e.signal);
	let t = window.turnstile;
	if (!t || e.signal.aborted) throw Error("Challenge unavailable.");
	let n = null, r = !1, i = !1, a = t.render(e.element, {
		sitekey: e.manifest.siteKey,
		action: e.manifest.action,
		execution: "execute",
		appearance: "interaction-only",
		"refresh-expired": "never",
		retry: "never",
		"response-field": !1,
		callback: (t) => {
			e.onToken(t), n?.resolve(t), n = null;
		},
		"expired-callback": () => {
			e.onToken("");
		},
		"error-callback": (t) => (i = pe(t), e.onToken(""), e.onError(t === void 0 ? void 0 : String(t)), n?.reject(), n = null, !0)
	});
	return {
		execute() {
			return i ? Promise.reject(/* @__PURE__ */ Error("Challenge unavailable.")) : F(new Promise((e, i) => {
				n = {
					resolve: e,
					reject: () => i(/* @__PURE__ */ Error("Challenge unavailable."))
				}, r && t.reset(a), r = !0, t.execute(a);
			}), e.signal, 12e4);
		},
		reset() {
			i = !1, e.onToken(""), r && t.reset(a), r = !1;
		},
		destroy() {
			n?.reject(), n = null, t.remove(a);
		}
	};
}], ["recaptcha-v3", async (e) => {
	await F(P("https://www.google.com/recaptcha/api.js?render=explicit", e.nonce), e.signal);
	let t = window.grecaptcha;
	if (!t || e.signal.aborted) throw Error("Challenge unavailable.");
	await F(new Promise((e) => t.ready(e)), e.signal);
	let n = t.render(e.element, {
		sitekey: e.manifest.siteKey,
		size: "invisible",
		badge: "inline"
	});
	return e.element.querySelectorAll("[name=\"g-recaptcha-response\"]").forEach((e) => e.removeAttribute("name")), {
		async execute() {
			try {
				let r = await F(t.execute(n, { action: e.manifest.action }), e.signal);
				return e.onToken(r), r;
			} catch (t) {
				throw e.onError(), t;
			}
		},
		reset() {
			e.onToken(""), t.reset(n);
		},
		destroy() {
			e.onToken(""), t.reset(n), e.element.replaceChildren();
		}
	};
}]]), I = /* @__PURE__ */ new WeakMap(), L = /* @__PURE__ */ new WeakMap();
function R(e) {
	if (!e || typeof e != "object") throw Error("Invalid protection manifest.");
	let t = e;
	if (t.version !== 1 || typeof t.instanceId != "string" || typeof t.preparation != "string" || typeof t.expiresAt != "number" || typeof t.javascript != "boolean" || t.honeypot !== null && (typeof t.honeypot != "string" || !/^[a-f0-9]+$/.test(t.honeypot)) || !Array.isArray(t.challenges)) throw Error("Invalid protection manifest.");
	if (typeof t.issuedAt != "number" || !Number.isFinite(t.issuedAt) || t.notBefore !== void 0 && (typeof t.notBefore != "number" || !Number.isFinite(t.notBefore))) throw Error("Invalid protection timing.");
	let n = /* @__PURE__ */ new Set();
	for (let e of t.challenges) {
		if (!e || typeof e.provider != "string" || typeof e.siteKey != "string" || !e.siteKey || typeof e.action != "string" || n.has(e.provider)) throw Error("Invalid challenge manifest.");
		n.add(e.provider);
	}
	return L.has(t) || L.set(t, t.issuedAt * 1e3 - Date.now()), t;
}
function z(e, t, n = {}) {
	I.get(e)?.destroy();
	let r = R(t), i = L.get(r) ?? 0, a = () => Date.now() + i, o = new AbortController(), s = [], c = !1, l = null, u = (t, n = {}) => {
		c || e.dispatchEvent(new CustomEvent(`protect:${t}`, {
			bubbles: !0,
			detail: {
				instanceId: r.instanceId,
				...n
			}
		}));
	}, d = (t, n, r = !1) => {
		let i = Array.from(e.querySelectorAll("input[name]")).find((e) => e.name === t), a = !i;
		if (!i) {
			if (i = document.createElement("input"), i.type = r ? "text" : "hidden", i.name = t, r) {
				let t = document.createElement("div");
				t.className = "protect-honeypot", t.hidden = !0, t.setAttribute("aria-hidden", "true"), i.tabIndex = -1, i.autocomplete = "off", i.setAttribute("aria-label", "Leave this field blank"), t.append(i), e.append(t), s.push(t);
			} else e.append(i), s.push(i);
		}
		return (!r || a) && (i.value = n), i;
	};
	d("__protect[version]", "1"), d("__protect[preparation]", r.preparation), r.javascript && d("__protect[javascript]", "1");
	let f = r.honeypot ? d(`__protect[honeypot][${r.honeypot}]`, "", !0) : null, p = new Map(r.challenges.map((e) => [e.provider, d(`__protect[challenges][${e.provider}]`, "")])), m = r.challenges.map((e) => ({
		challenge: e,
		element: null,
		driver: null,
		starting: null
	})), h = () => m.flatMap((e) => e.driver ?? []), g = (e) => {
		try {
			e.destroy();
		} catch {}
	}, _ = (t) => t.driver ? Promise.resolve() : (t.starting ?? (t.starting = (async () => {
		let r = t.challenge.provider, i = me.get(r);
		if (!i) throw Error("Unsupported challenge provider.");
		t.element?.remove(), t.element = document.createElement("div"), e.append(t.element), s.push(t.element);
		let a = await i({
			element: t.element,
			manifest: t.challenge,
			signal: o.signal,
			nonce: n.nonce,
			onToken(e) {
				c || (p.get(r).value = e, u("token", { provider: r }));
			},
			onError(e) {
				u("error", {
					provider: r,
					code: e
				});
			}
		});
		c ? g(a) : t.driver = a;
	})().finally(() => {
		t.starting = null;
	})), t.starting), v = !1, y = () => Promise.all(m.map(_)).then(() => {
		!v && !c && (v = !0, u("ready"));
	}, () => {
		throw u("error"), Error("Protection could not become ready.");
	}), b = y();
	b.catch(() => void 0);
	let x = {
		ready: b,
		prepare() {
			return l || (l = (async () => {
				if (await y(), c || x.expiresIn() === 0) throw Error("Refresh protection before submitting.");
				let e = x.remainingWait();
				if (e && (u("waiting", { wait: e }), await F(new Promise((t) => {
					let n = setTimeout(t, e);
					o.signal.addEventListener("abort", () => clearTimeout(n), { once: !0 });
				}), o.signal, e + 1e3)), await Promise.all(h().map((e) => e.execute())), c) throw Error("Protection was removed.");
				return {
					version: 1,
					preparation: r.preparation,
					honeypot: f && r.honeypot ? { [r.honeypot]: f.value } : {},
					javascript: r.javascript ? "1" : "",
					challenges: Object.fromEntries(Array.from(p, ([e, t]) => [e, t.value]))
				};
			})().finally(() => {
				l = null;
			}), l);
		},
		reset() {
			c || (h().forEach((e) => e.reset()), p.forEach((e) => {
				e.value = "";
			}), r.javascript && d("__protect[javascript]", "1"), u("reset"));
		},
		destroy() {
			c || (c = !0, o.abort(), h().forEach(g), p.forEach((e) => {
				e.value = "";
			}), s.forEach((e) => e.remove()), e.removeEventListener("reset", S), I.delete(e));
		},
		expiresIn() {
			return Math.max(0, r.expiresAt * 1e3 - a());
		},
		remainingWait() {
			return Math.max(0, (r.notBefore ?? 0) * 1e3 - a());
		}
	}, S = () => queueMicrotask(() => x.reset());
	return e.addEventListener("reset", S), I.set(e, x), x;
}
// src/resources/src/js/protection.ts
var B = /* @__PURE__ */ new Map(), V = /* @__PURE__ */ new WeakSet();
function he(e) {
	for (let [e, t] of B) e.isConnected || (t.destroy(), B.delete(e));
	e.querySelectorAll("[data-comments-protection]").forEach((e) => {
		let t = e.closest("form");
		if (t && !B.has(t)) try {
			let n = R(JSON.parse(e.dataset.commentsProtection ?? "null"));
			B.set(t, z(t, n));
		} catch {
			t.dispatchEvent(new CustomEvent("protect:error", { bubbles: !0 }));
		}
	});
}
function H(e) {
	for (let [t, n] of B) (e === t || e.contains(t)) && (n.destroy(), B.delete(t));
}
async function ge(e, t) {
	let n = new FormData(e), r = fe(t, "protection");
	r.searchParams.set("ownerId", String(n.get("elementId") ?? n.get("ownerId") ?? "")), r.searchParams.set("siteId", String(n.get("siteId") ?? "")), r.searchParams.set("commentId", String(n.get("commentId") ?? "")), r.searchParams.set("channel", "browser");
	let i = await fetch(r, {
		credentials: "same-origin",
		cache: "no-store",
		headers: { Accept: "application/json" },
		signal: AbortSignal.timeout(15e3)
	});
	if (!i.ok) throw Error("Could not refresh verification. Please try again.");
	let a = R((await i.json()).manifest);
	if (!e.isConnected) throw Error("The comment form was removed.");
	B.get(e)?.destroy(), e.querySelectorAll("input[name^=\"__protect[\"]").forEach((e) => e.remove()), e.querySelectorAll(".protect-honeypot").forEach((e) => e.remove());
	let o = e.querySelector("[data-comments-protection]");
	o && (o.dataset.commentsProtection = JSON.stringify(a)), B.set(e, z(e, a)), V.delete(e);
}
async function _e(e, t) {
	if (!e.querySelector("[data-comments-protection]")) throw Error("Refresh the page before posting your comment.");
	let n = B.get(e);
	(V.has(e) || !n || n.expiresIn() === 0) && await ge(e, t);
	let r = B.get(e);
	if (!r) throw Error("Comment verification is unavailable.");
	await r.prepare();
}
function ve(e) {
	V.add(e);
}
// src/resources/src/js/fragments.ts
function U(e, t) {
	return e.querySelector(`[data-comments-region="${t}"]`);
}
function ye(e, t) {
	return e.querySelector(`[data-comments-region="contextual-editor"][data-comments-comment-uid="${t}"]`);
}
function be(e) {
	if (!e) return !1;
	let t = e.querySelector("textarea[name$=\"[comment]\"]");
	if (t && t.value !== t.defaultValue) return !0;
	let n = e.querySelector("[data-editor-root]"), r = n?.editor?.getText() ?? "";
	return !!r.trim() && r !== n?.dataset.commentsInitialText;
}
function xe(e) {
	return new DOMParser().parseFromString(e, "text/html");
}
function Se(e) {
	return e.querySelector("[data-comments-action=\"load-roots\"]")?.closest(".comments__load-more") ?? null;
}
function Ce(e, t) {
	let n = U(e, "list");
	if (!n) return;
	let r = xe(t), i = n.querySelector(".comments__list"), a = r.querySelector(".comments__list");
	i && a && a.querySelectorAll(":scope > li").forEach((e) => {
		let t = e.dataset.commentsCommentUid;
		t && i.querySelector(`:scope > [data-comments-comment-uid="${t}"]`) || i.appendChild(document.importNode(e, !0));
	});
	let o = Se(n), s = Se(r);
	o ? s ? o.replaceWith(document.importNode(s, !0)) : o.remove() : s && n.appendChild(document.importNode(s, !0));
}
function we(e) {
	let t = e.querySelector("[data-comments-action=\"sort\"][aria-current=\"true\"], select[data-comments-action=\"sort\"]");
	return t instanceof HTMLSelectElement ? t.value : t?.dataset.commentsSort ?? e.dataset.commentsSort;
}
function Te(e, t, n) {
	let r = e.querySelector(`[data-comments-comment][data-comments-comment-uid="${t}"]`);
	if (r) {
		r.outerHTML = n;
		return;
	}
	let i = xe(n), a = i.querySelector(`[data-comments-comment-uid="${t}"]`) ?? i.body.firstElementChild;
	if (!a) return;
	let o = document.importNode(a, !0), s = e.querySelector(".comments__list");
	if (!s) {
		let t = U(e, "list");
		if (!t) return;
		s = document.createElement("ol"), s.className = "comments__list", s.setAttribute("role", "list"), s.id = `${e.dataset.commentsInstance ?? "comments"}-list`, t.replaceChildren(s);
	}
	we(e) === "oldest" ? s.appendChild(o) : s.prepend(o);
}
function Ee(e, t, n) {
	let r = e.querySelector(".comments__list"), i = xe(n).querySelector(`[data-comments-comment-uid="${t}"]`);
	r && i && (r.querySelector(`:scope > [data-comments-comment-uid="${t}"]`)?.remove(), r.prepend(document.importNode(i, !0)));
}
function De(e, t, n) {
	let r = ye(e, t);
	r ? (j(r), r.innerHTML = n) : t === e.dataset.commentsConversationUid && !be(U(e, "editor")?.querySelector("form")) && Ae(e, "editor", n);
}
function Oe(e, t) {
	for (let n of t) ke(e, n);
}
function ke(e, t) {
	switch (t.type) {
		case "conversationHeader": {
			let n = U(e, "header");
			n && (n.outerHTML = t.html);
			break;
		}
		case "rootList":
			t.mode === "append" ? Ce(e, t.html) : Ae(e, "list", t.html);
			break;
		case "editor":
		case "viewerControls":
			De(e, t.resourceUid, t.html);
			break;
		case "reportDialog":
			Ae(e, "report-dialog", t.html);
			break;
		case "comment":
			if (t.mode === "remove") {
				e.querySelector(`[data-comments-comment][data-comments-comment-uid="${t.resourceUid}"]`)?.remove();
				break;
			}
			if (t.mode === "insert") {
				Te(e, t.resourceUid, t.html);
				break;
			}
			{
				let n = e.querySelector(`[data-comments-comment][data-comments-comment-uid="${t.resourceUid}"]`);
				n && (n.outerHTML = t.html);
			}
			break;
		case "thread":
			Ee(e, t.resourceUid, t.html);
			break;
		case "replies": {
			let n = e.querySelector(`[data-comments-replies][data-comments-root-uid="${t.resourceUid}"]`);
			n && (n.outerHTML = t.html);
			break;
		}
	}
}
function Ae(e, t, n) {
	let r = U(e, t);
	r && (t === "editor" && j(r), r.innerHTML = n);
}
// src/resources/src/js/envelope.ts
function je(e) {
	if (!e || typeof e != "object") throw Error("Invalid response envelope.");
	let t = e;
	if (t.contractVersion !== 1) throw Error(`Unsupported contract version: ${String(t.contractVersion)}`);
	return t;
}
function Me(e) {
	return e.status === "error" || e.status === "conflict" || !!e.errors?.length;
}
function Ne(e) {
	return e.announcement ? e.announcement : e.errors?.[0]?.message ?? "Request failed.";
}
// src/resources/src/js/request.ts
var Pe = 3e4;
function Fe(e) {
	let t = e instanceof HTMLElement ? e.dataset.commentsCsrfParam : void 0;
	if (!t) return null;
	let n = Array.from(e.querySelectorAll("input[type=\"hidden\"][name]")).find((e) => e.name === t);
	return n?.value ? {
		name: n.name,
		value: n.value
	} : null;
}
async function W(e, t, n = {}) {
	let r = new AbortController(), i = window.setTimeout(() => r.abort(), n.timeoutMs ?? Pe), a = n.signal ? AbortSignal.any([n.signal, r.signal]) : r.signal, o = {
		Accept: "application/json",
		"X-Requested-With": "XMLHttpRequest",
		...n.headers ?? {}
	}, s = n.body;
	if (s instanceof FormData) {
		s.delete("action");
		let e = Fe(t);
		e && !s.has(e.name) && s.set(e.name, e.value);
	}
	try {
		let r = await fetch(e, {
			method: n.method ?? "GET",
			body: s,
			headers: o,
			credentials: "same-origin",
			signal: a
		}), i;
		try {
			i = je(await r.json());
		} catch (e) {
			throw a.aborted ? e : Error(t.dataset?.commentsFailedMessage ?? "Something went wrong. Please try again.");
		}
		return !r.ok && i.status === "ok" && (i.status = "error", i.errors = i.errors ?? [{
			category: "http",
			code: "http_error",
			message: `HTTP ${r.status}`,
			httpStatus: r.status,
			recoverable: r.status >= 500
		}]), i;
	} finally {
		window.clearTimeout(i);
	}
}
// src/resources/src/js/instance-controller.ts
function G(t, n, r) {
	e(t, n, r, { controller: Ie.get(t) });
}
function K(e, t) {
	let n = e.get(t);
	return typeof n == "string" ? n : null;
}
var Ie = /* @__PURE__ */ new WeakMap(), Le = /* @__PURE__ */ new Map(), Re = 8e3;
function q(e, t, n = {}) {
	let r = fe(e, t);
	for (let [e, t] of Object.entries(n)) t !== void 0 && t !== "" && r.searchParams.set(e, String(t));
	return r.toString();
}
function ze(e) {
	let t = e.closest("[data-comments-comment-id]");
	return t instanceof HTMLElement && t.dataset.commentsCommentId ? t.dataset.commentsCommentId : e.closest("form")?.querySelector("input[name=\"commentId\"]")?.value ?? null;
}
function Be(e) {
	let t = e.querySelector("[data-comments-report-dialog]");
	return t instanceof HTMLDialogElement ? t : null;
}
function Ve() {
	let e = window.location.hash;
	if (!e.startsWith("#comment-")) return null;
	let t = e.slice(9);
	return /^\d+$/.test(t) ? t : null;
}
function J(e, t) {
	let n = t ? e.querySelector(`[data-comments-comment][data-comments-comment-uid="${t}"] > article`) : null;
	return n?.focus(), !!n;
}
function Y(e) {
	if (!e) return;
	let t = Array.from(e.querySelectorAll("[contenteditable=\"true\"], input:not([type=\"hidden\"]), textarea, select")).filter((e) => e.getClientRects().length > 0 && !e.closest("[data-comments-protection], .protect-honeypot")), n = t.find((e) => e instanceof HTMLInputElement && e.required && !e.value), r = e.querySelector("form[data-comments-editor-mounted]") ?? (e instanceof HTMLFormElement && e.dataset.commentsEditorMounted ? e : null), i = r ? M(r) : null;
	(n ?? i ?? t[0])?.focus();
}
function He(e) {
	let t = e?.closest("[data-comments-action]");
	if (!t) return null;
	let { commentsAction: n, commentsCommentUid: r, commentsRootUid: i, commentsInteractionType: a, commentsModerationAction: o } = t.dataset;
	return [
		["data-comments-action", n],
		["data-comments-comment-uid", r],
		["data-comments-root-uid", i],
		["data-comments-interaction-type", a],
		["data-comments-moderation-action", o]
	].filter(([, e]) => e).map(([e, t]) => `[${e}="${t}"]`).join("");
}
function X(e, t) {
	e && [e, e.closest(".comments__menu")?.querySelector(".comments__menu-toggle")].forEach((e) => {
		e && (t ? e.setAttribute("aria-busy", "true") : e.removeAttribute("aria-busy"));
	});
}
function Z(e) {
	e.querySelectorAll(".comments__field-error, .comments__form-error").forEach((e) => e.remove()), e.querySelectorAll("[aria-invalid]").forEach((e) => {
		e.removeAttribute("aria-invalid");
		let t = (e.getAttribute("aria-describedby") ?? "").split(" ").filter((e) => !e.endsWith("-error")).join(" ");
		t ? e.setAttribute("aria-describedby", t) : e.removeAttribute("aria-describedby");
	});
}
function Q(e, t, n = {}) {
	Z(e);
	let r = null, i = e.dataset.commentsOperationId ?? "comment";
	if (Object.entries(n).forEach(([n, a], o) => {
		let s = e.querySelector(`[name="${n}"]:not([type="hidden"]), [name="${n}[]"]:not([type="hidden"])`), c = n === "fields[comment]" || n === "fields[editorContent]" ? e.querySelector("[data-editor-root]") : null, l = e.dataset.commentsEditorMounted && c ? c : s;
		if (!l) return;
		let u = l === c ? c.querySelector("[contenteditable]") ?? l : l, d = `${i}-${o}-error`, f = document.createElement("p");
		f.className = "comments__field-error", f.id = d, f.textContent = a[0] ?? t, l.after(f), u.setAttribute("aria-invalid", "true"), u.setAttribute("aria-describedby", [u.getAttribute("aria-describedby"), d].filter(Boolean).join(" ")), r ?? (r = l);
	}), r) {
		Y(r.parentElement ?? e);
		return;
	}
	if (!t) return;
	let a = document.createElement("p");
	a.className = "comments__form-error", a.textContent = t, a.tabIndex = -1, e.prepend(a), a.focus();
}
function Ue(e, t) {
	let n = e.querySelector(`[data-comments-comment-id="${t}"]`);
	return n ? (e.querySelectorAll(".comments__comment--highlight").forEach((e) => {
		e.classList.remove("comments__comment--highlight");
	}), n.classList.add("comments__comment--highlight"), n.scrollIntoView({
		behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth",
		block: "center"
	}), n.querySelector(":scope > article")?.focus({ preventScroll: !0 }), window.setTimeout(() => {
		n.classList.remove("comments__comment--highlight");
	}, 4e3), !0) : !1;
}
function We(t, n) {
	let r = new AbortController(), i = t.dataset.commentsOwnerId ?? "", a = t.dataset.commentsSiteId ?? "", o = /* @__PURE__ */ new Set(), s = t.dataset.commentsSort, c = t.dataset.commentsLimit, l = null, u, d = {
		root: t,
		conversationUid: t.dataset.commentsConversationUid ?? "",
		refresh(e) {
			b(e?.reason ?? "refresh", e?.sort);
		},
		openEditor(e) {
			ee(e.mode, e.commentUid);
		},
		closeEditor(e) {
			w(e?.reason ?? "user-cancel");
		},
		showFormErrors(e, t) {
			let n = t.fieldErrors ?? {}, r = t.message ?? "";
			Q(e, r, n), N(Object.values(n)[0]?.[0] ?? r);
		},
		clearFormErrors(e) {
			Z(e);
		},
		destroy() {
			r.abort(), window.clearTimeout(u), delete t.dataset.commentsJs, H(t), t.removeEventListener("click", F), t.removeEventListener("change", R), t.removeEventListener("submit", z), t.removeEventListener("editor-reply-cancel", pe), se(t), G(t, "comments:destroyed", {});
		}
	};
	async function f() {
		if (t.dataset.commentsSharedCache === "1" && i) {
			delete t.dataset.commentsSharedCache, delete t.dataset.commentsLazy, G(t, "comments:request-start", {
				operationId: null,
				reason: "viewer-hydrate"
			});
			try {
				await y(await W(q(n.apiBase, "viewer-controls", {
					ownerId: i,
					siteId: a,
					sort: s,
					limit: c
				}), t, { signal: r.signal }), "viewer-hydrate") && G(t, "comments:viewer-hydrated", {});
			} catch (e) {
				let n = e instanceof Error ? e.message : "Could not load viewer controls.";
				P(t, n, !0, !0), G(t, "comments:request-error", {
					reason: "viewer-hydrate",
					message: n
				});
			}
		}
	}
	async function h() {
		let e = Ve();
		if (e && !Ue(t, e) && !o.has(e) && i) {
			o.add(e);
			try {
				await y(await W(q(n.apiBase, "thread", {
					ownerId: i,
					siteId: a,
					commentId: e
				}), t, { signal: r.signal }), "deep-link") && Ue(t, e);
			} catch (e) {
				G(t, "comments:request-error", {
					reason: "deep-link",
					message: e instanceof Error ? e.message : "Could not load the linked comment."
				});
			}
		}
	}
	function g(e) {
		let n = Be(t), r = n?.querySelector("[data-comments-form=\"report\"]"), i = ze(e);
		if (!(n instanceof HTMLDialogElement) || !(r instanceof HTMLFormElement) || !i) return;
		r.reset(), Z(r);
		let a = r.querySelector("input[name=\"commentId\"]");
		a && (a.value = i);
		let o = n.querySelector("[data-comments-report-quote]"), s = e.closest("[data-comments-comment]")?.querySelector(":scope > article");
		if (o && s) {
			let e = s.querySelector(".comments__author-name")?.textContent?.trim() ?? "", t = (s.querySelector(":scope > .comments__content")?.textContent ?? "").replace(/\s+/g, " ").trim();
			if (o.replaceChildren(), e) {
				let t = document.createElement("strong");
				t.textContent = e, o.append(t, document.createTextNode(": "));
			}
			o.append(document.createTextNode(t.length > 200 ? `${t.slice(0, 200)}…` : t)), o.hidden = !1;
		}
		e.closest("details")?.removeAttribute("open"), n.showModal();
	}
	function _() {
		Be(t)?.close();
	}
	async function y(e, n, r) {
		if (Me(e)) {
			let i = Ne(e), a = e.errors?.[0];
			return r ? (Q(r, i, a?.fieldErrors), N(i)) : P(t, i, !0), G(t, "comments:request-error", {
				reason: n,
				message: i,
				category: a?.category,
				code: a?.code,
				fieldErrors: a?.fieldErrors,
				recoverable: a?.recoverable
			}), !1;
		}
		if (e.fragments) {
			let n = document.activeElement, r = t.contains(n) ? He(n) : null, i = n?.closest("[data-comments-comment]")?.dataset.commentsCommentUid;
			if (H(t), Oe(t, e.fragments), M(t), he(t), r !== null && (!document.activeElement || document.activeElement === document.body)) {
				let e = r ? t.querySelector(r) : null;
				e ? e.focus() : J(t, i);
			}
		}
		return e.announcement && P(t, e.announcement, !1), G(t, "comments:request-end", { reason: n }), !0;
	}
	async function b(e, o, l) {
		if (!i) return;
		o && (s = o), G(t, "comments:request-start", {
			operationId: null,
			reason: e
		});
		let u = t.querySelector(".comments__list");
		X(l, !0), X(u, !0);
		try {
			await y(await W(q(n.apiBase, "conversation", {
				ownerId: i,
				siteId: a,
				sort: s,
				limit: c
			}), t, { signal: r.signal }), e);
		} catch (n) {
			let r = n instanceof Error ? n.message : "Refresh failed.";
			P(t, r, !0, !0), G(t, "comments:request-error", {
				reason: e,
				message: r
			});
		} finally {
			X(l, !1), X(u, !1);
		}
	}
	async function x(e, o) {
		G(t, "comments:request-start", {
			operationId: null,
			reason: "load-roots"
		}), X(o, !0);
		let l = t.querySelectorAll(".comments__list > li").length;
		try {
			await y(await W(q(n.apiBase, "roots", {
				ownerId: i,
				siteId: a,
				cursor: e,
				sort: s,
				limit: c
			}), t, { signal: r.signal }), "load-roots"), t.querySelectorAll(".comments__list > li > article")[l]?.focus(), G(t, "comments:roots-loaded", {});
		} catch (e) {
			let n = e instanceof Error ? e.message : "Load more failed.";
			P(t, n, !0), G(t, "comments:request-error", {
				reason: "load-roots",
				message: n
			}), X(o, !1);
		}
	}
	async function S(e, i, o) {
		G(t, "comments:request-start", {
			operationId: null,
			reason: "load-replies"
		}), X(o, !0);
		let s = `[data-comments-replies][data-comments-root-uid="${e}"]`, c = t.querySelectorAll(`${s} > ol > li`).length;
		try {
			await y(await W(q(n.apiBase, "replies", {
				commentUid: e,
				siteId: a,
				...i ? { limit: i } : {}
			}), t, { signal: r.signal }), "load-replies"), t.querySelectorAll(`${s} > ol > li > article`)[c]?.focus(), G(t, "comments:replies-loaded", { commentUid: e });
		} catch (e) {
			let n = e instanceof Error ? e.message : "Load replies failed.";
			P(t, n, !0), G(t, "comments:request-error", {
				reason: "load-replies",
				message: n
			}), X(o, !1);
		}
	}
	async function C(e, i, o, s, c) {
		G(t, "comments:request-start", {
			operationId: null,
			reason: o
		}), X(s, !0), a && !i.has("siteId") && i.set("siteId", a);
		try {
			return await y(await W(q(n.apiBase, e), t, {
				method: "POST",
				body: i,
				signal: r.signal
			}), o, c);
		} catch (e) {
			let n = e instanceof Error ? e.message : "Request failed.";
			return P(t, n, !0), G(t, "comments:request-error", {
				reason: o,
				message: n
			}), !1;
		} finally {
			X(s, !1);
		}
	}
	async function ee(e, o, s) {
		if (!be(t.querySelector("[data-comments-form=\"reply\"], [data-comments-form=\"edit\"]")) || window.confirm(t.dataset.commentsDiscardMessage ?? "Discard your changes?")) {
			w("replaced", !1), l = s ? He(s) : null, D(), X(s, !0), G(t, "comments:request-start", {
				operationId: null,
				reason: "editor-open"
			});
			try {
				if (!await y(await W(q(n.apiBase, "editor", {
					ownerId: i,
					siteId: a,
					mode: e,
					commentUid: o
				}), t, { signal: r.signal }), "editor-open")) return;
				let s = e === "create" ? t.querySelector("[data-comments-region=\"editor\"]") : t.querySelector(`[data-comments-region="contextual-editor"][data-comments-comment-uid="${o}"]`), c = s?.querySelector("form[data-comments-form]");
				if (!s || !c) return;
				M(s), A(c) && (customElements.get("verbb-editor") || T(c)) ? await O(c) : A(c) && O(c), Y(c), G(t, "comments:editor-opened", {
					mode: e,
					commentUid: o ?? null
				});
			} catch (e) {
				let n = e instanceof Error ? e.message : "Could not open the editor.";
				P(t, n, !0), G(t, "comments:request-error", {
					reason: "editor-open",
					message: n
				});
			} finally {
				X(s, !1);
			}
		}
	}
	function w(e, n = !0) {
		let r = t.querySelector("[data-comments-form=\"reply\"], [data-comments-form=\"edit\"]")?.closest("[data-comments-region=\"contextual-editor\"]");
		r instanceof HTMLElement && (j(r), se(r), H(r), r.innerHTML = "", n && l && t.querySelector(l)?.focus(), G(t, "comments:editor-closed", { reason: e }));
	}
	function te(e) {
		if (be(e) && !window.confirm(t.dataset.commentsDiscardMessage ?? "Discard your changes?")) return;
		e.reset(), oe(e), ae(e), Z(e);
		let n = e.closest("details.comments__join-guest");
		n ? (n.open = !1, n.querySelector(":scope > summary")?.focus()) : t.querySelector(".comments__heading")?.focus();
	}
	function T(e) {
		return e?.dataset.commentsEditorModule || t.dataset.commentsEditorModule || void 0;
	}
	async function E(e) {
		if (customElements.get("verbb-editor")) return;
		let n = T(e), r = e?.dataset.commentsEditorCss || t.dataset.commentsEditorCss;
		if (!n) {
			await Promise.race([customElements.whenDefined("verbb-editor"), new Promise((e, t) => window.setTimeout(() => t(/* @__PURE__ */ Error("Editor did not load.")), Re))]);
			return;
		}
		if (r && !document.querySelector(`link[href="${r}"]`)) {
			let e = document.createElement("link");
			e.rel = "stylesheet", e.href = r, document.head.append(e);
		}
		let i = Le.get(n);
		i || (i = new Promise((e, t) => {
			let r = document.createElement("script");
			r.src = n, r.type = "module", r.onload = () => e(), r.onerror = () => {
				Le.delete(n), r.remove(), t(/* @__PURE__ */ Error("Editor could not be loaded."));
			}, document.head.append(r);
		}), Le.set(n, i)), await i, await customElements.whenDefined("verbb-editor");
	}
	function D(e) {
		!customElements.get("verbb-editor") && T(e) && E(e).catch(() => void 0);
	}
	async function O(e) {
		if (e.dataset.commentsEditorMounted || !A(e)) return;
		try {
			await E(e);
		} catch {
			return;
		}
		if (r.signal.aborted || !e.isConnected || e.dataset.commentsEditorMounted || e.dataset.commentsSubmitting) return;
		let n = ce(e, { theme: t.dataset.commentsTheme });
		n && (e.dataset.commentsEditorMounted = "1", e.querySelector(".comments__frame")?.removeAttribute("data-comments-collapsed"), n.dataset.commentsInitialText === void 0 && (n.dataset.commentsInitialText = n.editor?.getText() ?? ""));
	}
	function k() {
		try {
			let e = JSON.parse(t.dataset.commentsEditorLabels ?? "{}");
			return e && typeof e == "object" ? e : {};
		} catch {
			return {};
		}
	}
	function M(e) {
		e.querySelectorAll("form[data-comments-form]").forEach((e) => {
			[
				"create",
				"reply",
				"edit"
			].includes(e.dataset.commentsForm ?? "") && (ne(e, k(), N), (e.querySelector("verbb-editor[data-editor-root]") || A(e) && p(e)) && O(e));
		});
	}
	function fe(e) {
		let t = e.target;
		if (!(t instanceof Element)) return;
		let n = t.closest("form[data-comments-editor-frame]");
		n && A(n) && !n.dataset.commentsEditorMounted && t.closest(".comments__frame") ? e.type === "focusin" && t === m(n) ? O(n) : D(n) : e.type !== "focusin" && t.closest("[data-comments-action=\"reply\"], [data-comments-action=\"edit\"], .comments__join-guest > summary") && D();
	}
	function N(e) {
		let n = t.querySelector("[data-comments-region=\"live\"]");
		n && (n.textContent = e);
	}
	function P(e, n, r = !1, i = !1) {
		let a = e.querySelector("[data-comments-region=\"feedback\"] .comments__feedback");
		if (a instanceof HTMLElement) {
			if (window.clearTimeout(u), a.textContent = n, a.dataset.commentsState = r ? "error" : "success", N(n), i) {
				let e = document.createElement("button");
				e.type = "button", e.dataset.commentsAction = "retry", e.textContent = t.dataset.commentsRetryLabel ?? "Try again", a.append(e);
			}
			r || (u = window.setTimeout(() => {
				a.textContent = "", delete a.dataset.commentsState;
			}, 6e3));
		}
	}
	function F(n) {
		let r = n.target;
		if (!(r instanceof Element)) return;
		let i = r.closest("[data-comments-action]");
		if (!(i instanceof HTMLElement)) return;
		let a = i.dataset.commentsAction;
		if (a && e(t, "comments:action-before", { action: a }, {
			controller: d,
			source: "user",
			cancelable: !0
		})) {
			if (i.closest(".comments__menu")?.removeAttribute("open"), a === "refresh" || a === "retry") n.preventDefault(), b(a);
			else if (a === "load-roots") {
				n.preventDefault();
				let e = i.dataset.commentsNext;
				e && x(e, i);
			} else if (a === "load-replies") {
				n.preventDefault();
				let e = i.dataset.commentsRootUid ?? i.dataset.commentsCommentUid;
				e && S(e, i.dataset.commentsReplyLimit, i);
			} else if (a === "reply" || a === "edit") n.preventDefault(), ee(a, i.dataset.commentsCommentUid, i);
			else if (a === "cancel-editor") {
				n.preventDefault();
				let e = i.closest("form[data-comments-form]");
				e?.dataset.commentsForm === "create" ? te(e) : w("user-cancel");
			} else if (a === "toggle-replies") {
				n.preventDefault();
				let e = document.getElementById(i.getAttribute("aria-controls") ?? "");
				if (e) {
					let t = i.getAttribute("aria-expanded") === "true";
					e.hidden = t, i.closest("[data-comments-replies]")?.querySelectorAll(":scope > .comments__load-more").forEach((e) => {
						e.hidden = t;
					}), i.setAttribute("aria-expanded", t ? "false" : "true"), i.textContent = (t ? i.dataset.commentsLabelShow : i.dataset.commentsLabelHide) ?? "";
				}
			} else if (a === "remove" && t.dataset.commentsEnhanced === "1") {
				if (n.preventDefault(), i.dataset.commentsConfirm && !window.confirm(i.dataset.commentsConfirm)) return;
				let e = ze(i), r = i.dataset.commentsCommentUid;
				if (!e) return;
				let a = i.closest("[data-comments-comment]"), o = a?.parentElement?.closest("[data-comments-comment]"), s = a?.nextElementSibling ?? a?.previousElementSibling ?? o, c = new FormData();
				c.set("commentId", e), C("remove", c, "remove", i).then((e) => {
					e && (J(t, s?.dataset.commentsCommentUid) || t.querySelector(".comments__heading")?.focus(), G(t, "comments:comment-removed", { commentUid: r ?? null }));
				});
			} else if (a === "report" && t.dataset.commentsEnhanced === "1") n.preventDefault(), g(i);
			else if (a === "cancel-report") n.preventDefault(), _();
			else if (a === "sort" && t.dataset.commentsEnhanced === "1") {
				n.preventDefault();
				let e = i.dataset.commentsSort ?? "";
				b("sort", e, i).then(() => {
					G(t, "comments:sorted", { sort: e });
				});
			} else if (a === "toggle-date") {
				let e = i.querySelector("time"), t = i.dataset.commentsFullDate;
				if (e && t) {
					n.preventDefault();
					let r = e.dataset.commentsRelative ?? e.textContent ?? "";
					e.dataset.commentsRelative = r, e.textContent = e.textContent === t ? r : t;
				}
			} else if (a === "copy-link") {
				n.preventDefault();
				let e = new URL(i.dataset.commentsLink ?? "", window.location.href).toString();
				i.closest("details")?.removeAttribute("open"), navigator.clipboard?.writeText(e).then(() => {
					P(t, t.dataset.commentsCopiedMessage ?? "Link copied.");
				});
			} else if (a === "toggle-held") {
				n.preventDefault();
				let e = i.getAttribute("aria-pressed") !== "true";
				t.classList.toggle("comments--hide-held", e), i.setAttribute("aria-pressed", e ? "true" : "false"), i.textContent = (e ? i.dataset.commentsLabelShow : i.dataset.commentsLabelHide) ?? "";
			}
		}
	}
	function pe(e) {
		e.target instanceof Element && e.target.closest("[data-comments-form=\"reply\"]") && (e.preventDefault(), w("user-cancel"));
	}
	function me(e) {
		return Array.from(e.querySelectorAll(".comments__menu-item")).filter((e) => e.getClientRects().length > 0);
	}
	function I(e) {
		let t = e.target instanceof HTMLElement ? e.target : null, n = t?.closest(".comments__menu"), r = n?.querySelector(".comments__menu-toggle");
		if (!t || !n || !r) return;
		if (t === r) {
			if (![
				"Enter",
				" ",
				"ArrowDown",
				"ArrowUp"
			].includes(e.key)) return;
			if (e.preventDefault(), n.open && (e.key === "Enter" || e.key === " ")) {
				n.open = !1;
				return;
			}
			L(n), n.open = !0;
			let t = me(n);
			(e.key === "ArrowUp" ? t[t.length - 1] : t[0])?.focus();
			return;
		}
		let i = me(n), a = i.indexOf(t);
		if (a === -1) return;
		let o = {
			ArrowDown: i[(a + 1) % i.length],
			ArrowUp: i[(a - 1 + i.length) % i.length],
			Home: i[0],
			End: i[i.length - 1]
		};
		o[e.key] ? (e.preventDefault(), o[e.key]?.focus()) : e.key === "Escape" ? (e.preventDefault(), n.open = !1, r.focus()) : e.key === "Tab" && (n.open = !1);
	}
	function L(e) {
		t.querySelectorAll(".comments__menu[open]").forEach((t) => {
			t !== e && t.removeAttribute("open");
		});
	}
	function R(e) {
		let n = e.target;
		n instanceof HTMLSelectElement && n.dataset.commentsAction === "sort" && t.dataset.commentsEnhanced === "1" && (e.preventDefault(), b("sort", n.value).then(() => {
			G(t, "comments:sorted", { sort: n.value });
		}));
	}
	async function z(i) {
		let a = i.target;
		if (a instanceof HTMLFormElement && a.matches("[data-comments-form], .comments__vote-form, .comments__moderate-form") && t.dataset.commentsEnhanced && t.dataset.commentsEnhanced === "1" && !i.defaultPrevented) {
			if (i.preventDefault(), a.matches("[data-comments-form=\"create\"], [data-comments-form=\"reply\"], [data-comments-form=\"edit\"]")) {
				if (a.dataset.commentsSubmitting === "1" || v(a)) return;
				if (ue(a)) {
					Q(a, t.dataset.commentsUploadingMessage ?? "");
					return;
				}
				le(a);
				let i = a.dataset.commentsOperationId ?? null, o = a.dataset.commentsForm ?? "create", c = a.querySelector("[type=\"submit\"]"), l = a.closest("[data-comments-comment]")?.dataset.commentsCommentUid;
				if (!e(t, "comments:submit-before", {
					form: a,
					mode: o,
					commentUid: l ?? null,
					formData: new FormData(a)
				}, {
					controller: d,
					source: "user",
					cancelable: !0
				})) return;
				a.dataset.commentsSubmitting = "1", G(t, "comments:request-start", { operationId: i }), X(c, !0), de(a, !0), ie(a, !0), Z(a);
				try {
					await _e(a, n.apiBase), ve(a);
					let e = new FormData(a);
					s && e.set("sort", s);
					let i = await W(q(n.apiBase, "create"), t, {
						method: a.method || "POST",
						body: e,
						signal: r.signal
					});
					Me(i) || (oe(a), a.reset(), j(a)), await y(i, o, a) && (!J(t, i.resource) && !J(t, l) && Y(t.querySelector("[data-comments-region=\"editor\"]")), o === "edit" ? G(t, "comments:comment-updated", {
						operationId: i.operationId ?? null,
						resource: i.resource ?? null
					}) : G(t, "comments:comment-created", {
						operationId: i.operationId ?? null,
						resource: i.resource ?? null
					}));
				} catch (e) {
					let n = e instanceof Error ? e.message : "Submit failed.";
					Q(a, n), de(a, !1, n), N(n), G(t, "comments:request-error", { message: n });
				} finally {
					delete a.dataset.commentsSubmitting, X(c, !1), de(a, !1), ie(a, !1);
				}
			} else if (a.matches("[data-comments-form=\"report\"]")) C("report", new FormData(a), "report", a.querySelector("[type=\"submit\"]"), a).then((e) => {
				e && (_(), G(t, "comments:report-submitted", { commentId: K(new FormData(a), "commentId") }));
			});
			else if (a.matches("[data-comments-form=\"subscribe\"]")) {
				let e = new FormData(a), n = a.querySelector("[data-comments-action=\"subscribe\"]")?.getAttribute("aria-pressed") !== "true";
				e.set("subscribed", n ? "1" : "0"), s && e.set("sort", s), C("subscription", e, "subscribe", a.querySelector("[data-comments-action=\"subscribe\"]")).then((e) => {
					e && G(t, "comments:subscription-changed", { subscribed: n });
				});
			} else if (a.matches(".comments__vote-form")) {
				let e = new FormData(a);
				C("interaction", e, "interact", a.querySelector("button")).then((n) => {
					n && G(t, "comments:interaction-changed", {
						commentId: K(e, "commentId"),
						type: K(e, "type")
					});
				});
			} else if (a.matches(".comments__moderate-form")) {
				let e = new FormData(a);
				C("moderate", e, "moderate", a.querySelector("button")).then((n) => {
					n && G(t, "comments:comment-moderated", {
						commentId: K(e, "commentId"),
						moderationAction: K(e, "moderationAction")
					});
				});
			}
		}
	}
	Ie.set(t, d), document.addEventListener("click", (e) => {
		L(e.target instanceof Element ? e.target.closest(".comments__menu") : null);
	}, { signal: r.signal }), document.addEventListener("keydown", (e) => {
		e.key === "Escape" && L();
	}, { signal: r.signal }), t.addEventListener("click", F), t.addEventListener("keydown", I, { signal: r.signal }), t.addEventListener("change", R), t.addEventListener("submit", z), t.addEventListener("editor-reply-cancel", pe);
	for (let e of [
		"input",
		"keydown",
		"click",
		"focusin",
		"focusout"
	]) t.addEventListener(e, re, { signal: r.signal });
	for (let e of [
		"focusin",
		"pointerdown",
		"pointerover"
	]) t.addEventListener(e, fe, { signal: r.signal });
	let B = () => f().then(h);
	if (window.addEventListener("hashchange", () => {
		B();
	}, { signal: r.signal }), M(t), he(t), t.dataset.commentsJs = "1", t.dataset.commentsLazy === "1" && !Ve() && typeof IntersectionObserver < "u") {
		let e = new IntersectionObserver((t) => {
			t.some((e) => e.isIntersecting) && (e.disconnect(), B());
		}, { rootMargin: "600px 0px" });
		e.observe(t), r.signal.addEventListener("abort", () => e.disconnect());
	} else B();
	return d;
}
// src/resources/src/js/comments-frontend.ts
var Ge = "[data-comments-root][data-comments-contract]", Ke = Symbol.for("verbb.comments.runtime");
function qe(e) {
	return e.dataset.commentsApiBase || new URL("/actions/comments/frontend-api", window.location.href).toString();
}
function Je(e = document) {
	let t = Array.from(e.querySelectorAll(Ge));
	return e instanceof HTMLElement && e.matches(Ge) && t.unshift(e), t;
}
function Ye(e) {
	return e.closest("[data-comments-auto-mount]")?.getAttribute("data-comments-auto-mount") !== "false";
}
function Xe() {
	let t = /* @__PURE__ */ new WeakMap(), n = null;
	function r(n) {
		if (!(n instanceof HTMLElement) || n.dataset.commentsContract !== "1") return null;
		let r = t.get(n);
		if (r) return r;
		let i = We(n, { apiBase: qe(n) });
		return t.set(n, i), e(n, "comments:mounted", {}, { controller: i }), i;
	}
	let i = {
		mount(e) {
			let t = [];
			for (let n of Je(e ?? document)) {
				let e = r(n);
				e && t.push(e);
			}
			return t;
		},
		destroy(e) {
			for (let n of Je(e ?? document)) t.get(n)?.destroy(), t.delete(n);
		},
		get(e) {
			return e instanceof HTMLElement ? t.get(e) : void 0;
		}
	};
	function a(e = document) {
		for (let t of Je(e)) Ye(t) && r(t);
	}
	function o() {
		n || typeof MutationObserver > "u" || (n = new MutationObserver((e) => {
			for (let t of e) {
				for (let e of t.addedNodes) e instanceof HTMLElement && a(e);
				for (let e of t.removedNodes) e instanceof HTMLElement && i.destroy(e);
			}
		}), n.observe(document.documentElement, {
			childList: !0,
			subtree: !0
		}));
	}
	function s() {
		a(), o();
	}
	return document.readyState === "loading" ? document.addEventListener("DOMContentLoaded", s) : s(), document.addEventListener("turbo:before-cache", () => {
		i.destroy();
	}), document.addEventListener("turbo:load", () => {
		a();
	}), Object.freeze(i);
}
var Ze = window, Qe = Ze[Ke];
Qe && console.warn("Comments: the comments script has loaded twice on this page, so this copy won't start any threads. If your site bundles comments-frontend.js itself, turn off Load Bundled JavaScript (outputDefaultJs) in the Comments settings.");
var $ = Qe ?? Xe();
Qe || (Ze[Ke] = $, window.Comments = $);
export { $ as Comments, $ as default };

//# sourceMappingURL=comments-frontend.js.map