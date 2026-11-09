// src/resources/src/js/editor-bridge.ts
var e = /* @__PURE__ */ new WeakSet();
function t(e) {
	return e === "dark" || e === "none" ? e : e === "auto" && typeof window < "u" && window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
}
function n(e) {
	let t = e.querySelector("verbb-editor[data-editor-root]");
	return t && customElements.get("verbb-editor") ? t : null;
}
function r(e) {
	return n(e)?.editor ?? null;
}
function i(r, i) {
	let o = n(r);
	return o ? (o.setAttribute("theme", t(i.theme)), e.has(o) || (o.addEventListener("editor-update", () => a(r)), e.add(o)), a(r), o) : null;
}
function a(e) {
	let t = r(e), n = e.querySelector("input[name=\"contentFormat\"], input[name$=\"[contentFormat]\"]"), i = e.querySelector("input[name$=\"[editorContent]\"]"), a = e.querySelector("textarea[name$=\"[comment]\"]");
	!t || !i ? n && (n.value = "plain") : (i.value = JSON.stringify(t.getJSON()), n && (n.value = "editor"), a && (a.value = t.getText()));
}
function o(e) {
	return !!r(e)?.isUploading();
}
function s(e, t, r) {
	let i = n(e);
	i && (i.toggleAttribute("busy", t), r === void 0 ? t && i.clearPostError() : i.showPostError(r || void 0));
}
function c(t) {
	let n = t ? t.querySelector("verbb-editor[data-editor-root]") : null;
	n && e.delete(n);
}
// src/resources/src/js/api-url.ts
function l(e, t) {
	let n = new URL(e);
	return n.searchParams.has("p") ? n.searchParams.set("p", `${n.searchParams.get("p").replace(/\/$/, "")}/${t}`) : n.pathname = `${n.pathname.replace(/\/$/, "")}/${t}`, n;
}
// ../../../protect/craft5/plugin/packages/protect/dist/scripts.js
var u = /* @__PURE__ */ new Map();
function d(e, t) {
	let n = u.get(e);
	if (n) return n;
	let r = new Promise((n, r) => {
		let i = document.createElement("script");
		i.src = e, i.async = !0, t && (i.nonce = t);
		let a = setTimeout(() => o(), 15e3), o = () => {
			clearTimeout(a), i.remove(), u.delete(e), r(/* @__PURE__ */ Error("Challenge script unavailable."));
		};
		i.addEventListener("load", () => {
			clearTimeout(a), n();
		}, { once: !0 }), i.addEventListener("error", o, { once: !0 }), document.head.append(i);
	});
	return u.set(e, r), r;
}
function f(e, t, n = 15e3) {
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
var p = (e) => /^(100|110[1-5]|200|400)/.test(String(e ?? "")), m = /* @__PURE__ */ new Map([["turnstile", async (e) => {
	await f(d("https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit", e.nonce), e.signal);
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
		"error-callback": (t) => (i = p(t), e.onToken(""), e.onError(t === void 0 ? void 0 : String(t)), n?.reject(), n = null, !0)
	});
	return {
		execute() {
			return i ? Promise.reject(/* @__PURE__ */ Error("Challenge unavailable.")) : f(new Promise((e, i) => {
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
	await f(d("https://www.google.com/recaptcha/api.js?render=explicit", e.nonce), e.signal);
	let t = window.grecaptcha;
	if (!t || e.signal.aborted) throw Error("Challenge unavailable.");
	await f(new Promise((e) => t.ready(e)), e.signal);
	let n = t.render(e.element, {
		sitekey: e.manifest.siteKey,
		size: "invisible",
		badge: "inline"
	});
	return e.element.querySelectorAll("[name=\"g-recaptcha-response\"]").forEach((e) => e.removeAttribute("name")), {
		async execute() {
			try {
				let r = await f(t.execute(n, { action: e.manifest.action }), e.signal);
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
}]]), h = /* @__PURE__ */ new WeakMap(), g = /* @__PURE__ */ new WeakMap();
function _(e) {
	if (!e || typeof e != "object") throw Error("Invalid protection manifest.");
	let t = e;
	if (t.version !== 1 || typeof t.instanceId != "string" || typeof t.preparation != "string" || typeof t.expiresAt != "number" || typeof t.javascript != "boolean" || t.honeypot !== null && (typeof t.honeypot != "string" || !/^[a-f0-9]+$/.test(t.honeypot)) || !Array.isArray(t.challenges)) throw Error("Invalid protection manifest.");
	if (typeof t.issuedAt != "number" || !Number.isFinite(t.issuedAt) || t.notBefore !== void 0 && (typeof t.notBefore != "number" || !Number.isFinite(t.notBefore))) throw Error("Invalid protection timing.");
	let n = /* @__PURE__ */ new Set();
	for (let e of t.challenges) {
		if (!e || typeof e.provider != "string" || typeof e.siteKey != "string" || !e.siteKey || typeof e.action != "string" || n.has(e.provider)) throw Error("Invalid challenge manifest.");
		n.add(e.provider);
	}
	return g.has(t) || g.set(t, t.issuedAt * 1e3 - Date.now()), t;
}
function v(e, t, n = {}) {
	h.get(e)?.destroy();
	let r = _(t), i = g.get(r) ?? 0, a = () => Date.now() + i, o = new AbortController(), s = [], c = !1, l = null, u = (t, n = {}) => {
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
	let p = r.honeypot ? d(`__protect[honeypot][${r.honeypot}]`, "", !0) : null, v = new Map(r.challenges.map((e) => [e.provider, d(`__protect[challenges][${e.provider}]`, "")])), y = r.challenges.map((e) => ({
		challenge: e,
		element: null,
		driver: null,
		starting: null
	})), b = () => y.flatMap((e) => e.driver ?? []), x = (e) => {
		try {
			e.destroy();
		} catch {}
	}, S = (t) => t.driver ? Promise.resolve() : (t.starting ?? (t.starting = (async () => {
		let r = t.challenge.provider, i = m.get(r);
		if (!i) throw Error("Unsupported challenge provider.");
		t.element?.remove(), t.element = document.createElement("div"), e.append(t.element), s.push(t.element);
		let a = await i({
			element: t.element,
			manifest: t.challenge,
			signal: o.signal,
			nonce: n.nonce,
			onToken(e) {
				c || (v.get(r).value = e, u("token", { provider: r }));
			},
			onError(e) {
				u("error", {
					provider: r,
					code: e
				});
			}
		});
		c ? x(a) : t.driver = a;
	})().finally(() => {
		t.starting = null;
	})), t.starting), C = !1, w = () => Promise.all(y.map(S)).then(() => {
		!C && !c && (C = !0, u("ready"));
	}, () => {
		throw u("error"), Error("Protection could not become ready.");
	}), T = w();
	T.catch(() => void 0);
	let E = {
		ready: T,
		prepare() {
			return l || (l = (async () => {
				if (await w(), c || E.expiresIn() === 0) throw Error("Refresh protection before submitting.");
				let e = E.remainingWait();
				if (e && (u("waiting", { wait: e }), await f(new Promise((t) => {
					let n = setTimeout(t, e);
					o.signal.addEventListener("abort", () => clearTimeout(n), { once: !0 });
				}), o.signal, e + 1e3)), await Promise.all(b().map((e) => e.execute())), c) throw Error("Protection was removed.");
				return {
					version: 1,
					preparation: r.preparation,
					honeypot: p && r.honeypot ? { [r.honeypot]: p.value } : {},
					javascript: r.javascript ? "1" : "",
					challenges: Object.fromEntries(Array.from(v, ([e, t]) => [e, t.value]))
				};
			})().finally(() => {
				l = null;
			}), l);
		},
		reset() {
			c || (b().forEach((e) => e.reset()), v.forEach((e) => {
				e.value = "";
			}), r.javascript && d("__protect[javascript]", "1"), u("reset"));
		},
		destroy() {
			c || (c = !0, o.abort(), b().forEach(x), v.forEach((e) => {
				e.value = "";
			}), s.forEach((e) => e.remove()), e.removeEventListener("reset", D), h.delete(e));
		},
		expiresIn() {
			return Math.max(0, r.expiresAt * 1e3 - a());
		},
		remainingWait() {
			return Math.max(0, (r.notBefore ?? 0) * 1e3 - a());
		}
	}, D = () => queueMicrotask(() => E.reset());
	return e.addEventListener("reset", D), h.set(e, E), E;
}
// src/resources/src/js/protection.ts
var y = /* @__PURE__ */ new Map(), b = /* @__PURE__ */ new WeakSet();
function x(e) {
	for (let [e, t] of y) e.isConnected || (t.destroy(), y.delete(e));
	e.querySelectorAll("[data-comments-protection]").forEach((e) => {
		let t = e.closest("form");
		if (t && !y.has(t)) try {
			let n = _(JSON.parse(e.dataset.commentsProtection ?? "null"));
			y.set(t, v(t, n));
		} catch {
			t.dispatchEvent(new CustomEvent("protect:error", { bubbles: !0 }));
		}
	});
}
function S(e) {
	for (let [t, n] of y) (e === t || e.contains(t)) && (n.destroy(), y.delete(t));
}
async function C(e, t) {
	let n = new FormData(e), r = l(t, "protection");
	r.searchParams.set("ownerId", String(n.get("elementId") ?? n.get("ownerId") ?? "")), r.searchParams.set("siteId", String(n.get("siteId") ?? "")), r.searchParams.set("commentId", String(n.get("commentId") ?? "")), r.searchParams.set("channel", "browser");
	let i = await fetch(r, {
		credentials: "same-origin",
		cache: "no-store",
		headers: { Accept: "application/json" },
		signal: AbortSignal.timeout(15e3)
	});
	if (!i.ok) throw Error("Could not refresh verification. Please try again.");
	let a = _((await i.json()).manifest);
	if (!e.isConnected) throw Error("The comment form was removed.");
	y.get(e)?.destroy(), e.querySelectorAll("input[name^=\"__protect[\"]").forEach((e) => e.remove()), e.querySelectorAll(".protect-honeypot").forEach((e) => e.remove());
	let o = e.querySelector("[data-comments-protection]");
	o && (o.dataset.commentsProtection = JSON.stringify(a)), y.set(e, v(e, a)), b.delete(e);
}
async function w(e, t) {
	if (!e.querySelector("[data-comments-protection]")) throw Error("Refresh the page before posting your comment.");
	let n = y.get(e);
	(b.has(e) || !n || n.expiresIn() === 0) && await C(e, t);
	let r = y.get(e);
	if (!r) throw Error("Comment verification is unavailable.");
	await r.prepare();
}
function T(e) {
	b.add(e);
}
// src/resources/src/js/fragments.ts
function E(e, t) {
	return e.querySelector(`[data-comments-region="${t}"]`);
}
function D(e, t) {
	return e.querySelector(`[data-comments-region="contextual-composer"][data-comments-comment-uid="${t}"]`);
}
function O(e) {
	if (!e) return !1;
	let t = e.querySelector("textarea[name$=\"[comment]\"]");
	if (t && t.value !== t.defaultValue) return !0;
	let n = e.querySelector("[data-editor-root]"), r = n?.editor?.getText() ?? "";
	return !!r.trim() && r !== n?.dataset.commentsInitialText;
}
function k(e) {
	return new DOMParser().parseFromString(e, "text/html");
}
function A(e) {
	return e.querySelector("[data-comments-action=\"load-roots\"]")?.closest(".comments__load-more") ?? null;
}
function j(e, t) {
	let n = E(e, "list");
	if (!n) return;
	let r = k(t), i = n.querySelector(".comments__list"), a = r.querySelector(".comments__list");
	i && a && a.querySelectorAll(":scope > li").forEach((e) => {
		let t = e.dataset.commentsCommentUid;
		t && i.querySelector(`:scope > [data-comments-comment-uid="${t}"]`) || i.appendChild(document.importNode(e, !0));
	});
	let o = A(n), s = A(r);
	o ? s ? o.replaceWith(document.importNode(s, !0)) : o.remove() : s && n.appendChild(document.importNode(s, !0));
}
function ee(e) {
	let t = e.querySelector("[data-comments-action=\"sort\"][aria-current=\"true\"], select[data-comments-action=\"sort\"]");
	return t instanceof HTMLSelectElement ? t.value : t?.dataset.commentsSort ?? e.dataset.commentsSort;
}
function M(e, t, n) {
	let r = e.querySelector(`[data-comments-comment][data-comments-comment-uid="${t}"]`);
	if (r) {
		r.outerHTML = n;
		return;
	}
	let i = k(n), a = i.querySelector(`[data-comments-comment-uid="${t}"]`) ?? i.body.firstElementChild;
	if (!a) return;
	let o = document.importNode(a, !0), s = e.querySelector(".comments__list");
	if (!s) {
		let t = E(e, "list");
		if (!t) return;
		s = document.createElement("ol"), s.className = "comments__list", s.setAttribute("role", "list"), s.id = `${e.dataset.commentsInstance ?? "comments"}-list`, t.replaceChildren(s);
	}
	ee(e) === "oldest" ? s.appendChild(o) : s.prepend(o);
}
function N(e, t, n) {
	let r = e.querySelector(".comments__list"), i = k(n).querySelector(`[data-comments-comment-uid="${t}"]`);
	r && i && (r.querySelector(`:scope > [data-comments-comment-uid="${t}"]`)?.remove(), r.prepend(document.importNode(i, !0)));
}
function P(e, t, n) {
	let r = D(e, t);
	r ? (c(r), r.innerHTML = n) : t === e.dataset.commentsConversationUid && !O(E(e, "composer")?.querySelector("form")) && I(e, "composer", n);
}
function te(e, t) {
	for (let n of t) F(e, n);
}
function F(e, t) {
	switch (t.type) {
		case "conversationHeader": {
			let n = E(e, "header");
			n && (n.outerHTML = t.html);
			break;
		}
		case "rootList":
			t.mode === "append" ? j(e, t.html) : I(e, "list", t.html);
			break;
		case "composer":
		case "viewerControls":
			P(e, t.resourceUid, t.html);
			break;
		case "reportDialog":
			I(e, "report-dialog", t.html);
			break;
		case "comment":
			if (t.mode === "remove") {
				e.querySelector(`[data-comments-comment][data-comments-comment-uid="${t.resourceUid}"]`)?.remove();
				break;
			}
			if (t.mode === "insert") {
				M(e, t.resourceUid, t.html);
				break;
			}
			{
				let n = e.querySelector(`[data-comments-comment][data-comments-comment-uid="${t.resourceUid}"]`);
				n && (n.outerHTML = t.html);
			}
			break;
		case "thread":
			N(e, t.resourceUid, t.html);
			break;
		case "replies": {
			let n = e.querySelector(`[data-comments-replies][data-comments-root-uid="${t.resourceUid}"]`);
			n && (n.outerHTML = t.html);
			break;
		}
	}
}
function I(e, t, n) {
	let r = E(e, t);
	r && (t === "composer" && c(r), r.innerHTML = n);
}
// src/resources/src/js/envelope.ts
function L(e) {
	if (!e || typeof e != "object") throw Error("Invalid response envelope.");
	let t = e;
	if (t.contractVersion !== 1) throw Error(`Unsupported contract version: ${String(t.contractVersion)}`);
	return t;
}
function ne(e) {
	return e.status === "error" || e.status === "conflict" || !!e.errors?.length;
}
function re(e) {
	return e.announcement ? e.announcement : e.errors?.[0]?.message ?? "Request failed.";
}
// src/resources/src/js/request.ts
var R = 3e4;
function z(e) {
	let t = e instanceof HTMLElement ? e.dataset.commentsCsrfParam : void 0;
	if (!t) return null;
	let n = Array.from(e.querySelectorAll("input[type=\"hidden\"][name]")).find((e) => e.name === t);
	return n?.value ? {
		name: n.name,
		value: n.value
	} : null;
}
async function B(e, t, n = {}) {
	let r = new AbortController(), i = window.setTimeout(() => r.abort(), n.timeoutMs ?? R), a = n.signal ? AbortSignal.any([n.signal, r.signal]) : r.signal, o = {
		Accept: "application/json",
		"X-Requested-With": "XMLHttpRequest",
		...n.headers ?? {}
	}, s = n.body;
	if (s instanceof FormData) {
		s.delete("action");
		let e = z(t);
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
			i = L(await r.json());
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
function V(e, t, n) {
	e.dispatchEvent(new CustomEvent(t, {
		bubbles: !0,
		composed: !0,
		detail: {
			contractVersion: 1,
			root: e,
			instanceId: e.dataset.commentsInstance ?? "",
			conversationUid: e.dataset.commentsConversationUid ?? "",
			controller: H.get(e),
			source: "api",
			...n
		}
	}));
}
var H = /* @__PURE__ */ new WeakMap(), U = /* @__PURE__ */ new Map();
function W(e, t, n = {}) {
	let r = l(e, t);
	for (let [e, t] of Object.entries(n)) t !== void 0 && t !== "" && r.searchParams.set(e, String(t));
	return r.toString();
}
function G(e) {
	let t = e.closest("[data-comments-comment-id]");
	return t instanceof HTMLElement && t.dataset.commentsCommentId ? t.dataset.commentsCommentId : e.closest("form")?.querySelector("input[name=\"commentId\"]")?.value ?? null;
}
function ie(e) {
	let t = e.querySelector("[data-comments-report-dialog]");
	return t instanceof HTMLDialogElement ? t : null;
}
function ae() {
	let e = window.location.hash;
	if (!e.startsWith("#comment-")) return null;
	let t = e.slice(9);
	return /^\d+$/.test(t) ? t : null;
}
function K(e, t) {
	let n = t ? e.querySelector(`[data-comments-comment][data-comments-comment-uid="${t}"] > article`) : null;
	return n?.focus(), !!n;
}
function q(e) {
	e?.querySelector("[contenteditable=\"true\"], input:not([type=\"hidden\"]), textarea:not([hidden]), select")?.focus();
}
function oe(e) {
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
function J(e, t) {
	e && (t ? e.setAttribute("aria-busy", "true") : e.removeAttribute("aria-busy"));
}
function Y(e) {
	e.querySelectorAll(".comments__field-error, .comments__form-error").forEach((e) => e.remove()), e.querySelectorAll("[aria-invalid]").forEach((e) => {
		e.removeAttribute("aria-invalid");
		let t = (e.getAttribute("aria-describedby") ?? "").split(" ").filter((e) => !e.endsWith("-error")).join(" ");
		t ? e.setAttribute("aria-describedby", t) : e.removeAttribute("aria-describedby");
	});
}
function X(e, t, n = {}) {
	Y(e);
	let r = null, i = e.dataset.commentsOperationId ?? "comment";
	if (Object.entries(n).forEach(([n, a], o) => {
		let s = e.querySelector(`[name="${n}"]:not([type="hidden"]), [name="${n}[]"]:not([type="hidden"])`), c = n === "fields[comment]" || n === "fields[editorContent]" ? e.querySelector("[data-editor-root]") : null, l = e.dataset.commentsEditorMounted && c ? c : s;
		if (!l) return;
		let u = l === c ? c.querySelector("[contenteditable]") ?? l : l, d = `${i}-${o}-error`, f = document.createElement("p");
		f.className = "comments__field-error", f.id = d, f.textContent = a[0] ?? t, l.after(f), u.setAttribute("aria-invalid", "true"), u.setAttribute("aria-describedby", [u.getAttribute("aria-describedby"), d].filter(Boolean).join(" ")), r ?? (r = l);
	}), r) {
		q(r.parentElement ?? e);
		return;
	}
	let a = document.createElement("p");
	a.className = "comments__form-error", a.textContent = t, a.tabIndex = -1, e.prepend(a), a.focus();
}
function se(e, t) {
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
function ce(e, t) {
	let n = new AbortController(), r = e.dataset.commentsOwnerId ?? "", l = e.dataset.commentsSiteId ?? "", u = /* @__PURE__ */ new Set(), d = e.dataset.commentsSort, f = e.dataset.commentsLimit, p = null, m, h = {
		root: e,
		conversationUid: e.dataset.commentsConversationUid ?? "",
		refresh(e) {
			C(e?.reason ?? "refresh", e?.sort);
		},
		openComposer(e) {
			A(e.mode, e.commentUid);
		},
		closeComposer(e) {
			j(e?.reason ?? "user-cancel");
		},
		destroy() {
			n.abort(), window.clearTimeout(m), delete e.dataset.commentsJs, S(e), e.removeEventListener("click", F), e.removeEventListener("change", L), e.removeEventListener("submit", R), V(e, "comments:destroyed", {});
		}
	};
	async function g() {
		if (e.dataset.commentsSharedCache === "1" && r) {
			delete e.dataset.commentsSharedCache, delete e.dataset.commentsLazy, V(e, "comments:request-start", {
				operationId: null,
				reason: "viewer-hydrate"
			});
			try {
				await b(await B(W(t.apiBase, "viewer-controls", {
					ownerId: r,
					siteId: l,
					sort: d,
					limit: f
				}), e, { signal: n.signal }), "viewer-hydrate") && V(e, "comments:viewer-hydrated", {});
			} catch (t) {
				let n = t instanceof Error ? t.message : "Could not load viewer controls.";
				P(e, n, !0, !0), V(e, "comments:request-error", {
					reason: "viewer-hydrate",
					message: n
				});
			}
		}
	}
	async function _() {
		let i = ae();
		if (i && !se(e, i) && !u.has(i) && r) {
			u.add(i);
			try {
				await b(await B(W(t.apiBase, "thread", {
					ownerId: r,
					siteId: l,
					commentId: i
				}), e, { signal: n.signal }), "deep-link") && se(e, i);
			} catch (t) {
				V(e, "comments:request-error", {
					reason: "deep-link",
					message: t instanceof Error ? t.message : "Could not load the linked comment."
				});
			}
		}
	}
	function v(t) {
		let n = ie(e), r = n?.querySelector("[data-comments-form=\"report\"]"), i = G(t);
		if (!(n instanceof HTMLDialogElement) || !(r instanceof HTMLFormElement) || !i) return;
		r.reset(), Y(r);
		let a = r.querySelector("input[name=\"commentId\"]");
		a && (a.value = i);
		let o = n.querySelector("[data-comments-report-quote]"), s = t.closest("[data-comments-comment]")?.querySelector(":scope > article");
		if (o && s) {
			let e = s.querySelector(".comments__author-name")?.textContent?.trim() ?? "", t = (s.querySelector(":scope > .comments__content")?.textContent ?? "").replace(/\s+/g, " ").trim();
			if (o.replaceChildren(), e) {
				let t = document.createElement("strong");
				t.textContent = e, o.append(t, document.createTextNode(": "));
			}
			o.append(document.createTextNode(t.length > 200 ? `${t.slice(0, 200)}…` : t)), o.hidden = !1;
		}
		t.closest("details")?.removeAttribute("open"), n.showModal();
	}
	function y() {
		ie(e)?.close();
	}
	async function b(t, n, r) {
		if (ne(t)) {
			let i = re(t), a = t.errors?.[0];
			return r ? (X(r, i, a?.fieldErrors), N(i)) : P(e, i, !0), V(e, "comments:request-error", {
				reason: n,
				message: i,
				category: a?.category,
				code: a?.code,
				fieldErrors: a?.fieldErrors,
				recoverable: a?.recoverable
			}), !1;
		}
		if (t.fragments) {
			let n = document.activeElement, r = e.contains(n) ? oe(n) : null, i = n?.closest("[data-comments-comment]")?.dataset.commentsCommentUid;
			if (S(e), te(e, t.fragments), M(e), x(e), r !== null && (!document.activeElement || document.activeElement === document.body)) {
				let t = r ? e.querySelector(r) : null;
				t ? t.focus() : K(e, i);
			}
		}
		return t.announcement && P(e, t.announcement, !1), V(e, "comments:request-end", { reason: n }), !0;
	}
	async function C(i, a) {
		if (r) {
			a && (d = a), V(e, "comments:request-start", {
				operationId: null,
				reason: i
			});
			try {
				await b(await B(W(t.apiBase, "conversation", {
					ownerId: r,
					siteId: l,
					sort: d,
					limit: f
				}), e, { signal: n.signal }), i);
			} catch (t) {
				let n = t instanceof Error ? t.message : "Refresh failed.";
				P(e, n, !0, !0), V(e, "comments:request-error", {
					reason: i,
					message: n
				});
			}
		}
	}
	async function E(i, a) {
		V(e, "comments:request-start", {
			operationId: null,
			reason: "load-roots"
		}), J(a, !0);
		let o = e.querySelectorAll(".comments__list > li").length;
		try {
			await b(await B(W(t.apiBase, "roots", {
				ownerId: r,
				siteId: l,
				cursor: i,
				sort: d,
				limit: f
			}), e, { signal: n.signal }), "load-roots"), e.querySelectorAll(".comments__list > li > article")[o]?.focus(), V(e, "comments:roots-loaded", {});
		} catch (t) {
			let n = t instanceof Error ? t.message : "Load more failed.";
			P(e, n, !0), V(e, "comments:request-error", {
				reason: "load-roots",
				message: n
			}), J(a, !1);
		}
	}
	async function D(r, i, a) {
		V(e, "comments:request-start", {
			operationId: null,
			reason: "load-replies"
		}), J(a, !0);
		let o = `[data-comments-replies][data-comments-root-uid="${r}"]`, s = e.querySelectorAll(`${o} > ol > li`).length;
		try {
			await b(await B(W(t.apiBase, "replies", {
				commentUid: r,
				...i ? { limit: i } : {}
			}), e, { signal: n.signal }), "load-replies"), e.querySelectorAll(`${o} > ol > li > article`)[s]?.focus(), V(e, "comments:replies-loaded", { commentUid: r });
		} catch (t) {
			let n = t instanceof Error ? t.message : "Load replies failed.";
			P(e, n, !0), V(e, "comments:request-error", {
				reason: "load-replies",
				message: n
			}), J(a, !1);
		}
	}
	async function k(r, i, a, o, s) {
		V(e, "comments:request-start", {
			operationId: null,
			reason: a
		}), J(o, !0);
		try {
			return await b(await B(W(t.apiBase, r), e, {
				method: "POST",
				body: i,
				signal: n.signal
			}), a, s);
		} catch (t) {
			let n = t instanceof Error ? t.message : "Request failed.";
			return P(e, n, !0), V(e, "comments:request-error", {
				reason: a,
				message: n
			}), !1;
		} finally {
			J(o, !1);
		}
	}
	async function A(i, a, o) {
		if (!O(e.querySelector("[data-comments-form=\"reply\"], [data-comments-form=\"edit\"]")) || window.confirm(e.dataset.commentsDiscardMessage ?? "Discard your changes?")) {
			j("replaced", !1), p = o ? oe(o) : null, J(o, !0), V(e, "comments:composer-opened", {
				mode: i,
				commentUid: a ?? null
			}), V(e, "comments:request-start", {
				operationId: null,
				reason: "composer-open"
			});
			try {
				await b(await B(W(t.apiBase, "composer", {
					ownerId: r,
					siteId: l,
					mode: i,
					commentUid: a
				}), e, { signal: n.signal }), "composer-open"), q(e.querySelector(`[data-comments-region="contextual-composer"][data-comments-comment-uid="${a}"]`));
			} catch (t) {
				let n = t instanceof Error ? t.message : "Could not open composer.";
				P(e, n, !0), V(e, "comments:request-error", {
					reason: "composer-open",
					message: n
				});
			} finally {
				J(o, !1);
			}
		}
	}
	function j(t, n = !0) {
		let r = e.querySelector("[data-comments-form=\"reply\"], [data-comments-form=\"edit\"]")?.closest("[data-comments-region=\"contextual-composer\"]");
		r instanceof HTMLElement && (c(r), S(r), r.innerHTML = "", n && p && e.querySelector(p)?.focus(), V(e, "comments:composer-closed", { reason: t }));
	}
	async function ee() {
		let { commentsEditorModule: t, commentsEditorCss: n } = e.dataset;
		if (t) {
			if (n && !document.querySelector(`link[href="${n}"]`)) {
				let e = document.createElement("link");
				e.rel = "stylesheet", e.href = n, document.head.append(e);
			}
			let e = U.get(t);
			e || (e = new Promise((e, n) => {
				let r = document.createElement("script");
				r.src = t, r.type = "module", r.onload = () => e(), r.onerror = () => n(/* @__PURE__ */ Error("Rich authoring could not be loaded.")), document.head.append(r);
			}), U.set(t, e)), await e;
		}
		await customElements.whenDefined("verbb-editor");
	}
	function M(t) {
		t.querySelector("[data-editor-root]") && (window.VerbbEditor ? t.querySelectorAll("[data-comments-form]").forEach((t) => {
			let n = i(t, { theme: e.dataset.commentsTheme });
			n && (t.dataset.commentsEditorMounted = "1", n.dataset.commentsInitialText === void 0 && (n.dataset.commentsInitialText = n.editor?.getText() ?? ""));
		}) : ee().then(() => M(t)).catch(() => void 0));
	}
	function N(t) {
		let n = e.querySelector("[data-comments-region=\"live\"]");
		n && (n.textContent = t);
	}
	function P(t, n, r = !1, i = !1) {
		let a = t.querySelector("[data-comments-region=\"feedback\"] .comments__feedback");
		if (a instanceof HTMLElement) {
			if (window.clearTimeout(m), a.textContent = n, a.dataset.commentsState = r ? "error" : "success", N(n), i) {
				let t = document.createElement("button");
				t.type = "button", t.dataset.commentsAction = "retry", t.textContent = e.dataset.commentsRetryLabel ?? "Try again", a.append(t);
			}
			r || (m = window.setTimeout(() => {
				a.textContent = "", delete a.dataset.commentsState;
			}, 6e3));
		}
	}
	function F(t) {
		let n = t.target;
		if (!(n instanceof Element)) return;
		let r = n.closest("[data-comments-action]");
		if (!(r instanceof HTMLElement)) return;
		let i = r.dataset.commentsAction;
		if (!i) return;
		let a = new CustomEvent("comments:action-before", {
			bubbles: !0,
			composed: !0,
			cancelable: !0,
			detail: {
				contractVersion: 1,
				controller: h,
				root: e,
				instanceId: e.dataset.commentsInstance ?? "",
				conversationUid: h.conversationUid,
				action: i,
				source: "user"
			}
		});
		if (e.dispatchEvent(a)) {
			if (r.closest(".comments__menu")?.removeAttribute("open"), i === "refresh" || i === "retry") t.preventDefault(), C(i);
			else if (i === "load-roots") {
				t.preventDefault();
				let e = r.dataset.commentsNext;
				e && E(e, r);
			} else if (i === "load-replies") {
				t.preventDefault();
				let e = r.dataset.commentsRootUid ?? r.dataset.commentsCommentUid;
				e && D(e, r.dataset.commentsReplyLimit, r);
			} else if (i === "reply" || i === "edit") t.preventDefault(), A(i, r.dataset.commentsCommentUid, r);
			else if (i === "cancel-composer") t.preventDefault(), j("user-cancel");
			else if (i === "toggle-replies") {
				t.preventDefault();
				let e = document.getElementById(r.getAttribute("aria-controls") ?? "");
				if (e) {
					let t = r.getAttribute("aria-expanded") === "true";
					e.hidden = t, r.closest("[data-comments-replies]")?.querySelectorAll(":scope > .comments__load-more").forEach((e) => {
						e.hidden = t;
					}), r.setAttribute("aria-expanded", t ? "false" : "true"), r.textContent = (t ? r.dataset.commentsLabelShow : r.dataset.commentsLabelHide) ?? "";
				}
			} else if (i === "remove" && e.dataset.commentsEnhanced === "1") {
				if (t.preventDefault(), r.dataset.commentsConfirm && !window.confirm(r.dataset.commentsConfirm)) return;
				let n = G(r), i = r.dataset.commentsCommentUid;
				if (!n) return;
				let a = r.closest("[data-comments-comment]"), o = a?.parentElement?.closest("[data-comments-comment]"), s = a?.nextElementSibling ?? a?.previousElementSibling ?? o, c = new FormData();
				c.set("commentId", n), k("remove", c, "remove", r).then((t) => {
					t && (K(e, s?.dataset.commentsCommentUid) || e.querySelector(".comments__heading")?.focus(), V(e, "comments:comment-removed", { commentUid: i ?? null }));
				});
			} else if (i === "report" && e.dataset.commentsEnhanced === "1") t.preventDefault(), v(r);
			else if (i === "cancel-report") t.preventDefault(), y();
			else if (i === "sort" && e.dataset.commentsEnhanced === "1") {
				t.preventDefault();
				let n = r.dataset.commentsSort ?? "";
				C("sort", n).then(() => {
					V(e, "comments:sorted", { sort: n });
				});
			} else if (i === "toggle-date") {
				let e = r.querySelector("time"), n = r.dataset.commentsFullDate;
				if (e && n) {
					t.preventDefault();
					let r = e.dataset.commentsRelative ?? e.textContent ?? "";
					e.dataset.commentsRelative = r, e.textContent = e.textContent === n ? r : n;
				}
			} else if (i === "copy-link") {
				t.preventDefault();
				let n = new URL(r.dataset.commentsLink ?? "", window.location.href).toString();
				r.closest("details")?.removeAttribute("open"), navigator.clipboard?.writeText(n).then(() => {
					P(e, e.dataset.commentsCopiedMessage ?? "Link copied.");
				});
			} else if (i === "toggle-held") {
				t.preventDefault();
				let n = r.getAttribute("aria-pressed") !== "true";
				e.classList.toggle("comments--hide-held", n), r.setAttribute("aria-pressed", n ? "true" : "false"), r.textContent = (n ? r.dataset.commentsLabelShow : r.dataset.commentsLabelHide) ?? "";
			}
		}
	}
	function I(t) {
		e.querySelectorAll(".comments__menu[open]").forEach((e) => {
			e !== t && e.removeAttribute("open");
		});
	}
	function L(t) {
		let n = t.target;
		n instanceof HTMLSelectElement && n.dataset.commentsAction === "sort" && e.dataset.commentsEnhanced === "1" && (t.preventDefault(), C("sort", n.value).then(() => {
			V(e, "comments:sorted", { sort: n.value });
		}));
	}
	async function R(r) {
		let i = r.target;
		if (i instanceof HTMLFormElement && i.matches("[data-comments-form], .comments__vote-form, .comments__moderate-form") && e.dataset.commentsEnhanced && e.dataset.commentsEnhanced === "1") {
			if (r.preventDefault(), i.matches("[data-comments-form=\"create\"], [data-comments-form=\"reply\"], [data-comments-form=\"edit\"]")) {
				if (i.dataset.commentsSubmitting === "1") return;
				if (o(i)) {
					X(i, e.dataset.commentsUploadingMessage ?? "");
					return;
				}
				i.dataset.commentsSubmitting = "1", a(i);
				let r = i.dataset.commentsOperationId ?? null, l = i.dataset.commentsForm ?? "create", u = i.querySelector("[type=\"submit\"]"), f = i.closest("[data-comments-comment]")?.dataset.commentsCommentUid;
				V(e, "comments:request-start", { operationId: r }), J(u, !0), s(i, !0), Y(i);
				try {
					await w(i, t.apiBase), T(i);
					let r = new FormData(i);
					d && r.set("sort", d);
					let a = await B(W(t.apiBase, "create"), e, {
						method: i.method || "POST",
						body: r,
						signal: n.signal
					});
					ne(a) || (i.reset(), c(i)), await b(a, l, i) && (!K(e, a.resource) && !K(e, f) && q(e.querySelector("[data-comments-region=\"composer\"]")), l === "edit" ? V(e, "comments:comment-updated", {
						operationId: a.operationId ?? null,
						resource: a.resource ?? null
					}) : V(e, "comments:comment-created", {
						operationId: a.operationId ?? null,
						resource: a.resource ?? null
					}));
				} catch (t) {
					let n = t instanceof Error ? t.message : "Submit failed.";
					X(i, n), s(i, !1, n), N(n), V(e, "comments:request-error", { message: n });
				} finally {
					delete i.dataset.commentsSubmitting, J(u, !1), s(i, !1);
				}
			} else if (i.matches("[data-comments-form=\"report\"]")) k("report", new FormData(i), "report", i.querySelector("[type=\"submit\"]"), i).then((t) => {
				t && (y(), V(e, "comments:report-submitted", { commentId: new FormData(i).get("commentId") }));
			});
			else if (i.matches("[data-comments-form=\"subscribe\"]")) {
				let t = new FormData(i), n = i.querySelector("[data-comments-action=\"subscribe\"]")?.getAttribute("aria-pressed") !== "true";
				t.set("subscribed", n ? "1" : "0"), d && t.set("sort", d), k("subscription", t, "subscribe", i.querySelector("[data-comments-action=\"subscribe\"]")).then((t) => {
					t && V(e, "comments:subscription-changed", { subscribed: n });
				});
			} else if (i.matches(".comments__vote-form")) {
				let t = new FormData(i);
				k("interaction", t, "interact", i.querySelector("button")).then((n) => {
					n && V(e, "comments:interaction-changed", {
						commentId: t.get("commentId"),
						type: t.get("type")
					});
				});
			} else if (i.matches(".comments__moderate-form")) {
				let t = new FormData(i);
				k("moderate", t, "moderate", i.querySelector("button")).then((n) => {
					n && V(e, "comments:comment-moderated", {
						commentId: t.get("commentId"),
						moderationAction: t.get("moderationAction")
					});
				});
			}
		}
	}
	H.set(e, h), document.addEventListener("click", (e) => {
		I(e.target instanceof Element ? e.target.closest(".comments__menu") : null);
	}, { signal: n.signal }), document.addEventListener("keydown", (e) => {
		e.key === "Escape" && I();
	}, { signal: n.signal }), e.addEventListener("click", F), e.addEventListener("change", L), e.addEventListener("submit", R);
	let z = () => g().then(_);
	if (window.addEventListener("hashchange", () => {
		z();
	}, { signal: n.signal }), M(e), x(e), e.dataset.commentsJs = "1", e.dataset.commentsLazy === "1" && !ae() && typeof IntersectionObserver < "u") {
		let t = new IntersectionObserver((e) => {
			e.some((e) => e.isIntersecting) && (t.disconnect(), z());
		}, { rootMargin: "600px 0px" });
		t.observe(e), n.signal.addEventListener("abort", () => t.disconnect());
	} else z();
	return h;
}
// src/resources/src/js/comments-frontend.ts
var Z = /* @__PURE__ */ new WeakMap(), Q = null;
function le(e, t, n) {
	e.dispatchEvent(new CustomEvent(t, {
		bubbles: !0,
		composed: !0,
		detail: {
			contractVersion: 1,
			root: e,
			instanceId: e.dataset.commentsInstance ?? "",
			conversationUid: e.dataset.commentsConversationUid ?? "",
			source: "api",
			...n
		}
	}));
}
function ue(e) {
	return e.dataset.commentsApiBase || "/comments/api/v1/";
}
function de(e) {
	let t = ce(e, { apiBase: ue(e) });
	return Z.set(e, t), le(e, "comments:mounted", { controller: t }), t;
}
function fe(e) {
	return !(e instanceof HTMLElement) || e.dataset.commentsContract !== "1" ? null : Z.has(e) ? Z.get(e) ?? null : de(e);
}
function pe(e = document) {
	let t = Array.from(e.querySelectorAll("[data-comments-root][data-comments-contract]"));
	return e instanceof HTMLElement && e.matches("[data-comments-root][data-comments-contract]") && t.unshift(e), t;
}
var $ = {
	mount(e) {
		let t = [];
		for (let n of pe(e ?? document)) {
			let e = fe(n);
			e && t.push(e);
		}
		return t;
	},
	destroy(e) {
		let t = pe(e || document);
		for (let e of t) Z.get(e)?.destroy(), Z.delete(e);
	},
	get(e) {
		return e instanceof HTMLElement ? Z.get(e) : void 0;
	}
};
function me() {
	Q || typeof MutationObserver > "u" || (Q = new MutationObserver((e) => {
		for (let t of e) {
			for (let e of t.addedNodes) e instanceof HTMLElement && (e.matches("[data-comments-root][data-comments-contract]") ? fe(e) : $.mount(e));
			for (let e of t.removedNodes) e instanceof HTMLElement && $.destroy(e);
		}
	}), Q.observe(document.documentElement, {
		childList: !0,
		subtree: !0
	}));
}
function he() {
	$.mount(), me();
}
document.readyState === "loading" ? document.addEventListener("DOMContentLoaded", he) : he(), document.addEventListener("turbo:before-cache", () => {
	$.destroy();
}), document.addEventListener("turbo:load", () => {
	$.mount();
}), Object.freeze($), window.Comments = $;
export { $ as Comments, $ as default };

//# sourceMappingURL=comments-frontend.js.map