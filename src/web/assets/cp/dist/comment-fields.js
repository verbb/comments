// packages/comments-browser/dist/css/comments.css?inline
var e = globalThis, t = (e) => e, n = e.trustedTypes, r = n ? n.createPolicy("lit-html", { createHTML: (e) => e }) : void 0, i = "$lit$", a = `lit$${Math.random().toFixed(9).slice(2)}$`, o = "?" + a, s = `<${o}>`, c = document, l = () => c.createComment(""), u = (e) => e === null || typeof e != "object" && typeof e != "function", d = Array.isArray, f = (e) => d(e) || typeof e?.[Symbol.iterator] == "function", p = "[ 	\n\f\r]", m = /<(?:(!--|\/[^a-zA-Z])|(\/?[a-zA-Z][^>\s]*)|(\/?$))/g, h = /-->/g, g = />/g, _ = RegExp(`>|${p}(?:([^\\s"'>=/]+)(${p}*=${p}*(?:[^ \t\n\f\r"'\`<>=]|("|')|))|$)`, "g"), v = /'/g, y = /"/g, b = /^(?:script|style|textarea|title)$/i, x = (e) => (t, ...n) => ({
	_$litType$: e,
	strings: t,
	values: n
}), S = x(1), C = x(2), w = Symbol.for("lit-noChange"), T = Symbol.for("lit-nothing"), ee = /* @__PURE__ */ new WeakMap(), E = c.createTreeWalker(c, 129);
function te(e, t) {
	if (!d(e) || !e.hasOwnProperty("raw")) throw Error("invalid template strings array");
	return r === void 0 ? t : r.createHTML(t);
}
var ne = (e, t) => {
	let n = e.length - 1, r = [], o, c = t === 2 ? "<svg>" : t === 3 ? "<math>" : "", l = m;
	for (let t = 0; t < n; t++) {
		let n = e[t], u, d, f = -1, p = 0;
		for (; p < n.length && (l.lastIndex = p, d = l.exec(n), d !== null);) p = l.lastIndex, l === m ? d[1] === "!--" ? l = h : d[1] === void 0 ? d[2] === void 0 ? d[3] !== void 0 && (l = _) : (b.test(d[2]) && (o = RegExp("</" + d[2], "g")), l = _) : l = g : l === _ ? d[0] === ">" ? (l = o ?? m, f = -1) : d[1] === void 0 ? f = -2 : (f = l.lastIndex - d[2].length, u = d[1], l = d[3] === void 0 ? _ : d[3] === "\"" ? y : v) : l === y || l === v ? l = _ : l === h || l === g ? l = m : (l = _, o = void 0);
		let x = l === _ && e[t + 1].startsWith("/>") ? " " : "";
		c += l === m ? n + s : f >= 0 ? (r.push(u), n.slice(0, f) + i + n.slice(f) + a + x) : n + a + (f === -2 ? t : x);
	}
	return [te(e, c + (e[n] || "<?>") + (t === 2 ? "</svg>" : t === 3 ? "</math>" : "")), r];
}, D = class e {
	constructor({ strings: t, _$litType$: r }, s) {
		let c;
		this.parts = [];
		let u = 0, d = 0, f = t.length - 1, p = this.parts, [m, h] = ne(t, r);
		if (this.el = e.createElement(m, s), E.currentNode = this.el.content, r === 2 || r === 3) {
			let e = this.el.content.firstChild;
			e.replaceWith(...e.childNodes);
		}
		for (; (c = E.nextNode()) !== null && p.length < f;) {
			if (c.nodeType === 1) {
				if (c.hasAttributes()) for (let e of c.getAttributeNames()) if (e.endsWith(i)) {
					let t = h[d++], n = c.getAttribute(e).split(a), r = /([.?@])?(.*)/.exec(t);
					p.push({
						type: 1,
						index: u,
						name: r[2],
						strings: n,
						ctor: r[1] === "." ? ie : r[1] === "?" ? ae : r[1] === "@" ? oe : A
					}), c.removeAttribute(e);
				} else e.startsWith(a) && (p.push({
					type: 6,
					index: u
				}), c.removeAttribute(e));
				if (b.test(c.tagName)) {
					let e = c.textContent.split(a), t = e.length - 1;
					if (t > 0) {
						c.textContent = n ? n.emptyScript : "";
						for (let n = 0; n < t; n++) c.append(e[n], l()), E.nextNode(), p.push({
							type: 2,
							index: ++u
						});
						c.append(e[t], l());
					}
				}
			} else if (c.nodeType === 8) {
				if (c.data === o) p.push({
					type: 2,
					index: u
				});
				else {
					let e = -1;
					for (; (e = c.data.indexOf(a, e + 1)) !== -1;) p.push({
						type: 7,
						index: u
					}), e += a.length - 1;
				}
			}
			u++;
		}
	}
	static createElement(e, t) {
		let n = c.createElement("template");
		return n.innerHTML = e, n;
	}
};
function O(e, t, n = e, r) {
	if (t === w) return t;
	let i = r === void 0 ? n._$Cl : n._$Co?.[r], a = u(t) ? void 0 : t._$litDirective$;
	return i?.constructor !== a && (i?._$AO?.(!1), a === void 0 ? i = void 0 : (i = new a(e), i._$AT(e, n, r)), r === void 0 ? n._$Cl = i : (n._$Co ??= [])[r] = i), i !== void 0 && (t = O(e, i._$AS(e, t.values), i, r)), t;
}
var re = class {
	constructor(e, t) {
		this._$AV = [], this._$AN = void 0, this._$AD = e, this._$AM = t;
	}
	get parentNode() {
		return this._$AM.parentNode;
	}
	get _$AU() {
		return this._$AM._$AU;
	}
	u(e) {
		let { el: { content: t }, parts: n } = this._$AD, r = (e?.creationScope ?? c).importNode(t, !0);
		E.currentNode = r;
		let i = E.nextNode(), a = 0, o = 0, s = n[0];
		for (; s !== void 0;) {
			if (a === s.index) {
				let t;
				s.type === 2 ? t = new k(i, i.nextSibling, this, e) : s.type === 1 ? t = new s.ctor(i, s.name, s.strings, this, e) : s.type === 6 && (t = new se(i, this, e)), this._$AV.push(t), s = n[++o];
			}
			a !== s?.index && (i = E.nextNode(), a++);
		}
		return E.currentNode = c, r;
	}
	p(e) {
		let t = 0;
		for (let n of this._$AV) n !== void 0 && (n.strings === void 0 ? n._$AI(e[t]) : (n._$AI(e, n, t), t += n.strings.length - 2)), t++;
	}
}, k = class e {
	get _$AU() {
		return this._$AM?._$AU ?? this._$Cv;
	}
	constructor(e, t, n, r) {
		this.type = 2, this._$AH = T, this._$AN = void 0, this._$AA = e, this._$AB = t, this._$AM = n, this.options = r, this._$Cv = r?.isConnected ?? !0;
	}
	get parentNode() {
		let e = this._$AA.parentNode, t = this._$AM;
		return t !== void 0 && e?.nodeType === 11 && (e = t.parentNode), e;
	}
	get startNode() {
		return this._$AA;
	}
	get endNode() {
		return this._$AB;
	}
	_$AI(e, t = this) {
		e = O(this, e, t), u(e) ? e === T || e == null || e === "" ? (this._$AH !== T && this._$AR(), this._$AH = T) : e !== this._$AH && e !== w && this._(e) : e._$litType$ === void 0 ? e.nodeType === void 0 ? f(e) ? this.k(e) : this._(e) : this.T(e) : this.$(e);
	}
	O(e) {
		return this._$AA.parentNode.insertBefore(e, this._$AB);
	}
	T(e) {
		this._$AH !== e && (this._$AR(), this._$AH = this.O(e));
	}
	_(e) {
		this._$AH !== T && u(this._$AH) ? this._$AA.nextSibling.data = e : this.T(c.createTextNode(e)), this._$AH = e;
	}
	$(e) {
		let { values: t, _$litType$: n } = e, r = typeof n == "number" ? this._$AC(e) : (n.el === void 0 && (n.el = D.createElement(te(n.h, n.h[0]), this.options)), n);
		if (this._$AH?._$AD === r) this._$AH.p(t);
		else {
			let e = new re(r, this), n = e.u(this.options);
			e.p(t), this.T(n), this._$AH = e;
		}
	}
	_$AC(e) {
		let t = ee.get(e.strings);
		return t === void 0 && ee.set(e.strings, t = new D(e)), t;
	}
	k(t) {
		d(this._$AH) || (this._$AH = [], this._$AR());
		let n = this._$AH, r, i = 0;
		for (let a of t) i === n.length ? n.push(r = new e(this.O(l()), this.O(l()), this, this.options)) : r = n[i], r._$AI(a), i++;
		i < n.length && (this._$AR(r && r._$AB.nextSibling, i), n.length = i);
	}
	_$AR(e = this._$AA.nextSibling, n) {
		for (this._$AP?.(!1, !0, n); e !== this._$AB;) {
			let n = t(e).nextSibling;
			t(e).remove(), e = n;
		}
	}
	setConnected(e) {
		this._$AM === void 0 && (this._$Cv = e, this._$AP?.(e));
	}
}, A = class {
	get tagName() {
		return this.element.tagName;
	}
	get _$AU() {
		return this._$AM._$AU;
	}
	constructor(e, t, n, r, i) {
		this.type = 1, this._$AH = T, this._$AN = void 0, this.element = e, this.name = t, this._$AM = r, this.options = i, n.length > 2 || n[0] !== "" || n[1] !== "" ? (this._$AH = Array(n.length - 1).fill(/* @__PURE__ */ new String()), this.strings = n) : this._$AH = T;
	}
	_$AI(e, t = this, n, r) {
		let i = this.strings, a = !1;
		if (i === void 0) e = O(this, e, t, 0), a = !u(e) || e !== this._$AH && e !== w, a && (this._$AH = e);
		else {
			let r = e, o, s;
			for (e = i[0], o = 0; o < i.length - 1; o++) s = O(this, r[n + o], t, o), s === w && (s = this._$AH[o]), a ||= !u(s) || s !== this._$AH[o], s === T ? e = T : e !== T && (e += (s ?? "") + i[o + 1]), this._$AH[o] = s;
		}
		a && !r && this.j(e);
	}
	j(e) {
		e === T ? this.element.removeAttribute(this.name) : this.element.setAttribute(this.name, e ?? "");
	}
}, ie = class extends A {
	constructor() {
		super(...arguments), this.type = 3;
	}
	j(e) {
		this.element[this.name] = e === T ? void 0 : e;
	}
}, ae = class extends A {
	constructor() {
		super(...arguments), this.type = 4;
	}
	j(e) {
		this.element.toggleAttribute(this.name, !!e && e !== T);
	}
}, oe = class extends A {
	constructor(e, t, n, r, i) {
		super(e, t, n, r, i), this.type = 5;
	}
	_$AI(e, t = this) {
		if ((e = O(this, e, t, 0) ?? T) === w) return;
		let n = this._$AH, r = e === T && n !== T || e.capture !== n.capture || e.once !== n.once || e.passive !== n.passive, i = e !== T && (n === T || r);
		r && this.element.removeEventListener(this.name, this, n), i && this.element.addEventListener(this.name, this, e), this._$AH = e;
	}
	handleEvent(e) {
		typeof this._$AH == "function" ? this._$AH.call(this.options?.host ?? this.element, e) : this._$AH.handleEvent(e);
	}
}, se = class {
	constructor(e, t, n) {
		this.element = e, this.type = 6, this._$AN = void 0, this._$AM = t, this.options = n;
	}
	get _$AU() {
		return this._$AM._$AU;
	}
	_$AI(e) {
		O(this, e);
	}
}, ce = {
	M: i,
	P: a,
	A: o,
	C: 1,
	L: ne,
	R: re,
	D: f,
	V: O,
	I: k,
	H: A,
	N: ae,
	U: oe,
	B: ie,
	F: se
}, le = e.litHtmlPolyfillSupport;
le?.(D, k), (e.litHtmlVersions ??= []).push("3.3.3");
var ue = (e, t, n) => {
	let r = n?.renderBefore ?? t, i = r._$litPart$;
	if (i === void 0) {
		let e = n?.renderBefore ?? null;
		r._$litPart$ = i = new k(t.insertBefore(l(), e), e, void 0, n ?? {});
	}
	return i._$AI(e), i;
}, j = {
	ATTRIBUTE: 1,
	CHILD: 2,
	PROPERTY: 3,
	BOOLEAN_ATTRIBUTE: 4,
	EVENT: 5,
	ELEMENT: 6
}, M = (e) => (...t) => ({
	_$litDirective$: e,
	values: t
}), N = class {
	constructor(e) {}
	get _$AU() {
		return this._$AM._$AU;
	}
	_$AT(e, t, n) {
		this._$Ct = e, this._$AM = t, this._$Ci = n;
	}
	_$AS(e, t) {
		return this.update(e, t);
	}
	update(e, t) {
		return this.render(...t);
	}
}, { I: de } = ce, fe = (e) => e, pe = () => document.createComment(""), P = (e, t, n) => {
	let r = e._$AA.parentNode, i = t === void 0 ? e._$AB : t._$AA;
	if (n === void 0) n = new de(r.insertBefore(pe(), i), r.insertBefore(pe(), i), e, e.options);
	else {
		let t = n._$AB.nextSibling, a = n._$AM, o = a !== e;
		if (o) {
			let t;
			n._$AQ?.(e), n._$AM = e, n._$AP !== void 0 && (t = e._$AU) !== a._$AU && n._$AP(t);
		}
		if (t !== i || o) {
			let e = n._$AA;
			for (; e !== t;) {
				let t = fe(e).nextSibling;
				fe(r).insertBefore(e, i), e = t;
			}
		}
	}
	return n;
}, F = (e, t, n = e) => (e._$AI(t, n), e), me = {}, he = (e, t = me) => e._$AH = t, ge = (e) => e._$AH, I = (e) => {
	e._$AR(), e._$AA.remove();
}, _e = (e, t, n) => {
	let r = /* @__PURE__ */ new Map();
	for (let i = t; i <= n; i++) r.set(e[i], i);
	return r;
}, ve = M(class extends N {
	constructor(e) {
		if (super(e), e.type !== j.CHILD) throw Error("repeat() can only be used in text expressions");
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
		let i = ge(e), { values: a, keys: o } = this.dt(t, n, r);
		if (!Array.isArray(i)) return this.ut = o, a;
		let s = this.ut ??= [], c = [], l, u, d = 0, f = i.length - 1, p = 0, m = a.length - 1;
		for (; d <= f && p <= m;) if (i[d] === null) d++;
		else if (i[f] === null) f--;
		else if (s[d] === o[p]) c[p] = F(i[d], a[p]), d++, p++;
		else if (s[f] === o[m]) c[m] = F(i[f], a[m]), f--, m--;
		else if (s[d] === o[m]) c[m] = F(i[d], a[m]), P(e, c[m + 1], i[d]), d++, m--;
		else if (s[f] === o[p]) c[p] = F(i[f], a[p]), P(e, i[d], i[f]), f--, p++;
		else if (l === void 0 && (l = _e(o, p, m), u = _e(s, d, f)), l.has(s[d])) {
			if (l.has(s[f])) {
				let t = u.get(o[p]), n = t === void 0 ? null : i[t];
				if (n === null) {
					let t = P(e, i[d]);
					F(t, a[p]), c[p] = t;
				} else c[p] = F(n, a[p]), P(e, i[d], n), i[t] = null;
				p++;
			} else I(i[f]), f--;
		} else I(i[d]), d++;
		for (; p <= m;) {
			let t = P(e, c[m + 1]);
			F(t, a[p]), c[p++] = t;
		}
		for (; d <= f;) {
			let e = i[d++];
			e !== null && I(e);
		}
		return this.ut = o, he(e, c), w;
	}
}), ye = "verbb-comments", L, be = /* @__PURE__ */ new Set();
function xe(e) {
	try {
		let t = new URL(e, globalThis.location?.href);
		return (t.protocol === "https:" || t.protocol === "http:") && be.has(t.origin);
	} catch {
		return !1;
	}
}
function Se() {
	let e = globalThis;
	if (!e.trustedTypes) return null;
	if (L === void 0) try {
		L = e.trustedTypes.createPolicy(ye, {
			createHTML: (e) => e,
			createScriptURL(e) {
				if (!xe(e)) throw TypeError("Comments only loads Editor from the data API’s origin or its own.");
				return e;
			}
		});
	} catch {
		L = null;
	}
	return L;
}
function Ce(e) {
	let t = Se();
	return t ? t.createHTML(e) : e;
}
// packages/comments-browser/src/directives.ts
var R = /* @__PURE__ */ new Map(), we = /* @__PURE__ */ new Set(), Te = class extends N {
	constructor(e) {
		if (super(e), this.applied = /* @__PURE__ */ new Map(), e.type !== j.ELEMENT) throw Error("Comments: attributes() only works on an element.");
	}
	render(e) {
		return T;
	}
	update(e, [t]) {
		let n = e.element, r = /* @__PURE__ */ new Map();
		for (let [e, n] of Object.entries(t)) (we.has(e) || /^[a-zA-Z_:][-a-zA-Z0-9_:.]*$/.test(e) && we.add(e)) && r.set(e, n === !0 ? "" : n);
		for (let e of this.applied.keys()) r.has(e) || n.removeAttribute(e);
		for (let [e, t] of r) (this.applied.get(e) !== t || n.getAttribute(e) !== t) && n.setAttribute(e, t);
		return this.applied = r, w;
	}
}, z = M(Te);
R.set(Te, { attributes: ([e]) => e });
var Ee = class extends N {
	constructor(e) {
		if (super(e), e.type !== j.ELEMENT) throw Error("Comments: richHtml() only works on an element.");
	}
	render(e) {
		return T;
	}
	update(e, [t]) {
		return t !== this.last && (this.last = t, e.element.innerHTML = Ce(t)), w;
	}
}, De = M(Ee);
R.set(Ee, { inner: ([e]) => String(e ?? "") });
var Oe = class extends N {
	constructor(e) {
		if (super(e), this.element = null, this.tag = "", e.type !== j.CHILD) throw Error("Comments: a custom element part goes where content goes.");
	}
	render(e, t) {
		return e;
	}
	update(e, [t, n]) {
		return this.element && this.tag === t || (this.tag = t, this.element = document.createElement(t)), Object.assign(this.element, n), this.element;
	}
};
R.set(Oe, { child: ([e]) => {
	let t = String(e);
	return /^[a-z][a-z0-9._-]*-[a-z0-9._-]*$/.test(t) ? {
		_$litType$: 1,
		strings: [`<${t}></${t}>`],
		values: []
	} : T;
} });
var ke = ve([], () => "")._$litDirective$;
R.set(ke, { child: (e) => {
	let [t, n, r] = e, i = r ?? n;
	return Array.from(t, (e, t) => i(e, t));
} });
// packages/comments-browser/src/labels.ts
var Ae = {
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
function je(...e) {
	let t = { ...Ae };
	for (let n of e) for (let [e, r] of Object.entries(n ?? {})) typeof r == "string" && r !== "" && (t[e] = r);
	return t;
}
function B(e, t) {
	return e.replace(/\{(\w+)\}/g, (e, n) => n in t ? String(t[n]) : e);
}
// packages/comments-core/dist/store.js
function Me(e, t) {
	return (e.replies[t]?.uids ?? []).flatMap((t) => e.comments[t] ?? []);
}
// packages/comments-browser/src/view.ts
var Ne = () => ({
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
function V(e) {
	let t = e.permalink ? e.permalink.slice(e.permalink.indexOf("#") + 1) : "";
	return /^comment-[\w-]+$/.test(t) ? t : `comment-${e.uid}`;
}
var H = "ugc nofollow noopener";
function Pe(e) {
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
function Fe(e) {
	return Array.isArray(e.value) ? e.value.filter((e) => !!e && typeof e == "object") : [];
}
function Ie(e) {
	let t = Pe(e.url), n = String(e.filename ?? "");
	return t && e.kind === "image" ? S`<a class="comments__field-file comments__field-file--image" href=${t} rel=${H}><img class="comments__field-thumb" src=${t} alt=${n} loading="lazy" width=${e.width ?? T} height=${e.height ?? T}></a>` : t ? S`<a class="comments__field-file" href=${t} rel=${H}>${n}</a>` : S`<span class="comments__field-file">${n}</span>`;
}
function Le(e, t = 0) {
	let n = t && e.text.length > t ? `${e.text.slice(0, t)}…` : e.text;
	if (e.type === "url") {
		let t = Pe(e.value);
		return t ? S`<a class="comments__field-link" href=${t} rel=${H}>${n}</a>` : n;
	}
	if (e.type === "file" && !t) {
		let t = Fe(e);
		return t.length ? S`<span class="comments__field-files">${t.map(Ie)}</span>` : n;
	}
	return n;
}
function Re(e) {
	return !!e.text || e.type === "file" && Fe(e).length > 0;
}
function ze(e, t, n, r = 0) {
	return e.field("display", n.handle, {
		field: n,
		comment: t,
		value: n.value,
		text: n.text
	}, () => Le(n, r));
}
function Be(e, t) {
	return t.fields.filter((e) => e.placement === "inline" && Re(e)).map((n) => S`<span class=${`comments__meta-item comments__field-value--${n.handle}`}>${ze(e, t, n, 40)}</span>`);
}
function Ve(e, t, n) {
	let r = t.fields.filter((e) => e.placement === "details" && Re(e));
	return r.length ? e.part("fields", {
		...n,
		fields: r
	}, () => S`<dl class="comments__fields">${r.map((n) => S`<div class=${`comments__field-value comments__field-value--${n.handle}`}><dt class="comments__field-name">${n.label}</dt><dd class="comments__field-text">${ze(e, t, n)}</dd></div>`)}</dl>`) : T;
}
// packages/comments-browser/src/parts/icons.ts
var He = {
	heart: C`<path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7z"/>`,
	up: C`<path d="M12 19V5M5 12l7-7 7 7"/>`,
	down: C`<path d="M12 5v14M19 12l-7 7-7-7"/>`,
	reply: C`<path d="m9 17-5-5 5-5"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/>`,
	more: C`<circle cx="5" cy="12" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="19" cy="12" r="1.3"/>`,
	bell: C`<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>`,
	user: C`<circle cx="12" cy="8" r="4.5"/><path d="M20 21a8 8 0 0 0-16 0"/>`,
	clock: C`<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>`,
	spam: C`<path d="M7.9 2h8.2L22 7.9v8.2L16.1 22H7.9L2 16.1V7.9z"/><path d="M12 8v4M12 16h.01"/>`,
	flag: C`<path d="M4 22V4c3-2 6-1 8 0s5 2 8 0v10c-3 2-6 1-8 0s-5-2-8 0"/>`,
	removed: C`<path d="M10.7 5.1A10 10 0 0 1 12 5c7 0 10 7 10 7a13 13 0 0 1-1.7 2.7M6.6 6.6A13.5 13.5 0 0 0 2 12s3 7 10 7a9.7 9.7 0 0 0 5.4-1.6M2 2l20 20M9.9 9.9a3 3 0 0 0 4.2 4.2"/>`,
	close: C`<path d="M18 6 6 18M6 6l12 12"/>`,
	check: C`<path d="M20 6 9 17l-5-5"/>`,
	alert: C`<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>`,
	info: C`<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>`,
	file: C`<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>`,
	attach: C`<path d="m21 11-8.6 8.6a5 5 0 0 1-7-7l8.6-8.6a3.3 3.3 0 0 1 4.7 4.7l-8.6 8.6a1.7 1.7 0 0 1-2.4-2.4L15.6 7"/>`,
	retry: C`<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>`
};
function U(e) {
	return S`<svg class=${`comments__icon comments__icon--${e}`} width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${He[e]}</svg>`;
}
function W(e, t = "") {
	return S`<svg class=${`comments__frame-icon${t ? ` ${t}` : ""}`} width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${He[e]}</svg>`;
}
var Ue = {
	approve: "approve",
	keep: "keep",
	remove: "remove",
	restore: "restore",
	spam: "spam",
	"not-spam": "notSpam",
	hold: "hold"
}, We = /* @__PURE__ */ new Set([
	"held",
	"spam",
	"pending",
	"removed",
	"reported"
]);
function G(e, t) {
	return e.state.permissions[t] ?? null;
}
function Ge(e, t) {
	return t.moderation ?? G(e, t.uid)?.moderation ?? null;
}
var Ke = /* @__PURE__ */ new Set([
	"held",
	"spam",
	"removed"
]);
function qe(e, t) {
	if (e.ui.hideHeld && Ke.has(t.state)) return T;
	let n = e.budget;
	if (n && !n.drawn.has(t.uid)) {
		if (n.left <= 0) return n.more = !0, T;
		n.left--, n.drawn.add(t.uid);
	} else if (n?.adding && Je(e, t, n.drawn)) return w;
	return e.part("comment", K(e, t), () => Xe(e, t), {
		around: (n) => $e(e, t, S`${n}${et(e, t)}`),
		inside: () => q(e, t)
	});
}
function Je(e, t, n) {
	return Me(e.state, t.uid).every((t) => e.ui.hideHeld && Ke.has(t.state) || n.has(t.uid) && Je(e, t, n));
}
var Ye = /* @__PURE__ */ new WeakMap();
function K(e, t) {
	let n = Ye.get(e);
	n || (n = /* @__PURE__ */ new Map(), Ye.set(e, n));
	let r = n.get(t.uid);
	return r && r.comment === t || (r = {
		comment: t,
		permissions: G(e, t.uid),
		viewer: e.state.viewer,
		conversation: e.state.conversation,
		actions: e.actions(t.uid),
		form: () => tt(e, t),
		replies: () => ht(e, t)
	}, n.set(t.uid, r)), r;
}
function Xe(e, t) {
	return $e(e, t, t.state === "placeholder" ? q(e, t) : S`${q(e, t)}${et(e, t)}`);
}
function q(e, t) {
	return t.state === "placeholder" ? e.part("placeholder", K(e, t), () => S`<p class="comments__placeholder">${e.labels.unavailable}</p>`) : Ze(e, t) ? S`${rt(e, t)}${Qe(e, t)}${ut(e, t)}${pt(e, t)}` : S`${rt(e, t)}${e.part("content", K(e, t), () => ct(e, t))}${lt(e, t)}${ut(e, t)}${pt(e, t)}`;
}
function Ze(e, t) {
	return !!t.folded && !e.ui.unfolded.has(t.uid);
}
function Qe(e, t) {
	let { labels: n } = e, r = t.folded === "downvotes" ? n.foldedDownvotes : n.foldedReports;
	return S`<div class="comments__folded" data-comments-folded=${t.folded ?? T}><p class="comments__folded-label">${r}</p><button ${z(e.control("quietButton", {
		identify: "comments__folded-show",
		attributes: {
			type: "button",
			"data-comments-action": "unfold",
			"data-comments-comment-uid": t.uid
		}
	}))}>${n.showFolded}</button></div>`;
}
function $e(e, t, n) {
	let { labels: r } = e, i = t.state === "placeholder", a = t.state, o = [
		"comments__comment",
		a === "held" || a === "spam" || a === "removed" ? "comments__comment--held" : "",
		a === "pending" ? "comments__comment--pending" : "",
		a === "reported" || a === "approved" && G(e, t.uid)?.moderation?.openReportCount ? "comments__comment--reported" : "",
		e.ui.highlight === t.uid ? "comments__comment--highlight" : ""
	].filter(Boolean).join(" "), s = t.author?.name ?? r.guest;
	return S`<li class=${o} data-comments-comment data-comments-comment-uid=${t.uid} data-comments-depth=${t.depth} data-comments-visual-depth=${Math.min(t.depth, 4)} data-comments-state=${a}><article id=${V(t)} tabindex="-1" aria-label=${i ? T : B(r.commentBy, { name: s })}>${n}${ht(e, t)}</article></li>`;
}
function et(e, t) {
	return S`<div class="comments__contextual-editor" data-comments-region="contextual-editor" data-comments-comment-uid=${t.uid}>${tt(e, t)}</div>`;
}
function tt(e, t) {
	let n = e.ui.openForm;
	return n && n.uid === t.uid ? e.form(`${n.mode}:${t.uid}`) : T;
}
function nt(e, t, n = 32) {
	return t?.avatarUrl ? S`<img ${z(e.control("avatar", { attributes: {
		src: t.avatarUrl,
		alt: "",
		width: n,
		height: n,
		loading: "lazy"
	} }))}>` : S`<span ${z(e.control("avatar", {
		style: "comments__avatar--initials",
		attributes: {
			"data-tone": t?.avatarTone ?? 0,
			"aria-hidden": "true"
		}
	}))}>${t?.initials ?? ""}</span>`;
}
function rt(e, t) {
	let n = K(e, t), r = t.author?.badges ?? [];
	return S`<div class="comments__author">${e.state.conversation?.showAvatars === !1 ? T : e.part("avatar", n, () => nt(e, t.author))}<div class="comments__author-meta">${e.part("authorName", n, () => t.author?.name ? S`<span class="comments__author-name">${t.author.name}</span>` : T)}${it(e, t)}${r.map((t) => e.part("badge", {
		...n,
		badge: t
	}, () => at(t)))}${e.part("meta", n, () => ot(e, t))}</div></div>`;
}
function it(e, t) {
	let n = t.author?.isGuest ? null : t.author?.score;
	return typeof n == "number" ? S`<span class=${`comments__author-score${n > 0 ? " comments__author-score--positive" : n < 0 ? " comments__author-score--negative" : ""}`} title=${e.labels.authorScore}><span class="comments__visually-hidden">${e.labels.authorScore}: </span>${n > 0 ? `+${n}` : String(n)}</span>` : T;
}
function at(e) {
	return S`<span class=${`comments__badge comments__badge--${e.type.replace(/[^\w-]/g, "")}`}>${e.label}</span>`;
}
function ot(e, t) {
	let n = t.display, r = e.ui.fullDates.has(t.uid), i = Ze(e, t) ? T : Be(e, t);
	return S`${n && t.createdAt ? S`<a ${z(e.control("link", {
		identify: "comments__permalink",
		attributes: {
			href: t.permalink ?? `#${V(t)}`,
			"data-comments-action": "toggle-date",
			"data-comments-comment-uid": t.uid
		}
	}))}><time class="comments__date" datetime=${t.createdAt} title=${n.fullDate}>${r ? n.fullDate : n.timeAgo || n.fullDate}</time></a>` : T}${i}${t.editedAt ? S`<span class="comments__edited" title=${t.editedAt}>${e.labels.edited}</span>` : T}`;
}
function st(e, t) {
	return e && t && t > e ? "comments__gif comments__gif--portrait" : "comments__gif";
}
function ct(e, t) {
	let n = t.content;
	if (!n) return S`<div class="comments__content" dir="auto"></div>`;
	let r = n.text.split("\n");
	return S`<div class="comments__content" dir="auto">${n.format === "rich" && n.html ? S`<div class="comments__content-rich" ${De(n.html)}></div>` : n.text ? S`<p class="comments__content-plain">${r.map((e, t) => t ? S`<br>${e}` : e)}</p>` : T}${n.gifUrl ? S`<img class=${st(n.gifWidth, n.gifHeight)} src=${n.gifUrl} alt=${e.labels.gif} loading="lazy" width=${n.gifWidth ?? T} height=${n.gifHeight ?? T}>` : T}</div>`;
}
function lt(e, t) {
	return Ve(e, t, K(e, t));
}
function ut(e, t) {
	return t.state !== "approved" && t.state !== "reported" ? T : e.part("actions", K(e, t), () => dt(e, t));
}
function dt(e, t) {
	let { labels: n } = e, r = G(e, t.uid), i = e.state.conversation?.interactionMode ?? "like", a = i === "none" ? null : t.interaction, o = e.ui.pendingVotes.has(t.uid) ? e.ui.pendingVotes.get(t.uid) : void 0, s = o === void 0 ? r?.vote ?? null : o, c = r?.vote ?? null, l = (e) => o === void 0 ? 0 : (s === e) - +(c === e), u = (a?.upvotes ?? 0) + l("upvote"), d = (a?.downvotes ?? 0) + l("downvote"), f = i === "updown", p = Ge(e, t), m = t.state === "approved" && !p?.openReportCount ? p?.actions ?? [] : [], h = !!t.permalink, g = !(!r?.canReport || r.reported), _ = h || r?.canEdit || r?.canDelete || g || m.length > 0, v = V(t);
	if (!a && !r?.canReply && !_) return T;
	let y = a ? S`<div class="comments__interactions" data-comments-interaction-mode=${i}>${r?.canVote ? S`<button ${z(e.control("quietButton", {
		identify: "comments__vote comments__vote--up",
		attributes: {
			type: "button",
			"data-comments-action": "vote",
			"data-comments-vote": "upvote",
			"data-comments-comment-uid": t.uid,
			"aria-pressed": s === "upvote" ? "true" : "false"
		}
	}))}>${U(f ? "up" : "heart")}<span class=${f || u > 0 ? "comments__visually-hidden" : "comments__vote-label"}>${f ? n.upvote : n.like}</span>${!f && u > 0 ? S`<span class="comments__vote-count">${u}</span>` : T}</button>` : !f && u > 0 ? S`<span class="comments__vote-total">${U("heart")}<span class="comments__visually-hidden">${n.likes}: </span><span class="comments__vote-count">${u}</span></span>` : T}${f ? S`<span class="comments__score"><span class="comments__visually-hidden">${n.score}: </span>${u - d}</span>` : T}${f && r?.canDownvote ? S`<button ${z(e.control("quietButton", {
		identify: "comments__vote comments__vote--down",
		attributes: {
			type: "button",
			"data-comments-action": "vote",
			"data-comments-vote": "downvote",
			"data-comments-comment-uid": t.uid,
			"aria-pressed": s === "downvote" ? "true" : "false"
		}
	}))}>${U("down")}<span class="comments__visually-hidden">${n.downvote}</span></button>` : T}</div>` : T, b = r?.canReply ? S`<button ${z(e.control("quietButton", {
		identify: "comments__action",
		attributes: {
			type: "button",
			"data-comments-action": "reply",
			"data-comments-comment-uid": t.uid,
			"aria-busy": e.ui.busy.has(`reply:${t.uid}`) ? "true" : null
		}
	}))}>${U("reply")}${n.reply}</button>` : T, x = (e, n, r = {}, i = !1) => S`<button type="button" class=${`comments__menu-item${i ? " comments__menu-item--danger" : ""}`} data-comments-action=${e} data-comments-comment-uid=${t.uid} data-comments-moderation-action=${r.moderation ?? T}>${n}</button>`, C = e.mode === "string" || e.ui.menus.has(t.uid);
	return S`<div class="comments__actions">${y}${b}${_ ? S`<details class="comments__menu"><summary ${z(e.control("quietButton", {
		identify: "comments__action comments__menu-toggle",
		attributes: {
			"data-comments-comment-uid": t.uid,
			"aria-busy": e.ui.busy.has(`menu:${t.uid}`) ? "true" : null
		}
	}))}>${U("more")}<span class="comments__visually-hidden">${n.moreActions}</span></summary>${C ? S`<div class="comments__menu-items">${h ? x("copy-link", n.copyLink) : T}${r?.canEdit ? x("edit", n.edit) : T}${g ? x("report", n.report) : T}${r?.canDelete ? x("delete", n.delete, {}, !0) : T}${m.length ? S`<div class="comments__menu-group" role="group" aria-labelledby=${`${v}-moderator-actions`}><span class="comments__menu-heading" id=${`${v}-moderator-actions`}>${n.moderator}</span>${m.map((t) => x("moderate", ft(e, t, !0), { moderation: t }, !0))}</div>` : T}</div>` : T}</details>` : T}</div>`;
}
function ft(e, t, n = !1) {
	let { labels: r } = e;
	return n && t === "spam" ? r.markSpam : r[Ue[t] ?? t] ?? t;
}
function pt(e, t) {
	let n = Ge(e, t), r = t.state === "reported" || !!(t.state === "approved" && n?.openReportCount);
	return !We.has(t.state) && !r ? T : e.part("review", {
		...K(e, t),
		moderation: n
	}, () => mt(e, t, n, r));
}
function mt(e, t, n, r) {
	let { labels: i } = e, a = r ? "flag" : t.state === "spam" ? "spam" : t.state === "removed" ? "removed" : "clock", o = n?.reviewLabel ?? (t.state === "removed" ? i.removedNote : t.state === "pending" ? i.awaitingApproval : ""), s = n?.actions ?? [];
	return S`<div class="comments__review" data-comments-state-badge=${t.state}><p class="comments__review-label">${U(a)}${o}</p>${s.length ? S`<div class="comments__moderation-actions" role="group" aria-label=${i.moderation}>${s.map((n, r) => S`<button ${z(e.control(r === 0 ? "primaryButton" : "secondaryButton", { attributes: {
		type: "button",
		"data-comments-action": "moderate",
		"data-comments-moderation-action": n,
		"data-comments-comment-uid": t.uid,
		"aria-busy": e.ui.busy.has(`moderate:${t.uid}:${n}`) ? "true" : null
	} }))}>${ft(e, n)}</button>`)}</div>` : T}</div>`;
}
function ht(e, t) {
	let n = e.state.replies[t.uid], r = Me(e.state, t.uid), i = n?.count ?? t.replies.count, a = !!(n ? n.next : t.replies.next) && i > r.filter((e) => e.state === "approved" || e.state === "reported").length;
	if (!r.length && !a) return T;
	let o = `${V(t)}-replies`, s = e.ui.collapsed.has(t.uid), c = Math.max(0, i - r.filter((e) => e.state === "approved" || e.state === "reported").length);
	return S`<div class="comments__replies" data-comments-replies data-comments-root-uid=${t.uid}>${r.length ? S`<button ${z(e.control("quietButton", {
		identify: "comments__toggle-replies",
		attributes: {
			type: "button",
			"data-comments-action": "toggle-replies",
			"data-comments-comment-uid": t.uid,
			"aria-expanded": s ? "false" : "true",
			"aria-controls": o
		}
	}))}>${s ? e.labels.showReplies : e.labels.hideReplies}</button><ol class="comments__replies-list" id=${o} role="list" ?hidden=${s}>${ve(r, (e) => e.uid, (t) => qe(e, t))}</ol>` : T}${a ? S`<div class="comments__load-more" ?hidden=${s}><button ${z(e.control("secondaryButton", {
		identify: "comments__load-more-button",
		attributes: {
			type: "button",
			"data-comments-action": "load-replies",
			"data-comments-comment-uid": t.uid,
			"aria-controls": r.length ? o : null,
			"aria-busy": e.ui.busy.has(`replies:${t.uid}`) || n?.loading ? "true" : null
		}
	}))}>${e.labels.loadMoreReplies}${c ? ` (${c})` : ""}</button></div>` : T}</div>`;
}
// packages/comments-browser/src/parts/form.ts
function gt(e) {
	return `comments__editor comments__editor--${e}`;
}
var J = (e, t) => `${e.idPrefix}-${t.replace(/[^\w-]/g, "-")}-error`, _t = /* @__PURE__ */ new Set();
function Y(e, t) {
	let n = e.errors[t];
	return n ? S`<p class="comments__field-error" id=${J(e, t)}>${n}</p>` : T;
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
function vt(e) {
	let { limit: t, length: n, labels: r } = e;
	if (!(t > 0) || n < t * .8) return S`<span class="comments__frame-count"></span>`;
	let i = t - n, a = 2 * Math.PI * 8, o = Math.min(n / t, 1);
	return S`<span class=${`comments__frame-count${i < 0 ? " comments__frame-count--bad" : i <= t * .1 ? " comments__frame-count--warn" : ""}`} title=${B(r.characterCount, {
		count: n.toLocaleString(),
		limit: t.toLocaleString()
	})}><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><circle class="comments__ring-bg" cx="10" cy="10" r=${8}></circle><circle class="comments__ring-fg" cx="10" cy="10" r=${8} stroke-dasharray=${a} stroke-dashoffset=${a * (1 - o)}></circle></svg><span>${i}</span><span class="comments__visually-hidden">${i < 0 ? B(r.tooLong, { count: -i }) : B(r.charactersLeft, { count: i })}</span></span>`;
}
function yt(e) {
	let t = e.limit > 0 ? e.length - e.limit : 0;
	return t > 0 ? S`<span class="comments__frame-status comments__frame-status--bad">${W("alert")}<span class="comments__frame-status-text">${B(e.labels.tooLong, { count: t })}</span></span>` : e.draftState ? S`<span class=${`comments__frame-status${e.draftState === "faded" ? " comments__frame-status--faded" : ""}`}>${W("check")}<span class="comments__frame-status-text">${e.labels.draftSaved}</span></span>` : S`<span class="comments__frame-status"></span>`;
}
function bt(e) {
	let { labels: t, viewer: n } = e, r = n && !n.isGuest ? n : null, i = `${e.idPrefix}-comment-body`, a = `${e.idPrefix}-comment-label`, o = !!(e.replyTo || r || e.guest.show), s = e.fields.some((e) => Z(e) === "above"), c = e.fields.some((e) => Z(e) === "below");
	return S`${e.errors.form ? S`<p class="comments__form-error" tabindex="-1">${e.errors.form}</p>` : T}<div class="comments__editor-row">${e.avatars === !1 ? T : r ? xt(e, r) : S`<span ${z(e.control("avatar", {
		style: "comments__avatar--guest",
		attributes: { "aria-hidden": "true" }
	}))}>${U("user")}</span>`}<div class=${`comments__frame${e.fields.length ? " comments__frame--fields" : ""}`} ?data-comments-collapsed=${e.collapsed}>${o ? S`<div class="comments__frame-head">${e.replyTo ? St(e) : T}${r ? e.slots.postingAs : T}${e.guest.show ? e.slots.guestFields : T}</div>` : T}${s ? e.slots.fieldsAbove : T}<div class="comments__frame-box"><label class="comments__visually-hidden" id=${a} for=${i}>${t.yourComment}</label><textarea ${z(e.control("textarea", {
		identify: "comments__textarea-fallback",
		attributes: {
			id: i,
			name: "content",
			rows: 1,
			placeholder: e.placeholder,
			required: !0,
			"data-comments-body": !0,
			...X(e, "content")
		}
	}))}></textarea>${e.notice ? S`<div class="comments__frame-notice" role="status">${W("info")}<span class="comments__frame-notice-text">${e.notice.message}</span>${e.notice.action ? S`<button type="button" class="comments__frame-link" data-comments-action="discard-draft">${e.notice.action}</button>` : T}</div>` : S`<div class="comments__frame-notice" role="status" hidden></div>`}</div>${e.slots.editor}${e.gifs ? e.slots.gifArea : T}${c ? e.slots.formFields : T}<div class="comments__frame-foot"><span class="comments__frame-meta">${yt(e)}${vt(e)}</span>${e.gifs ? e.slots.gifButton : T}${e.slots.cancel}<button ${z(e.control("primaryButton", {
		identify: "comments__submit",
		attributes: {
			type: "submit",
			"aria-label": e.submitLabel,
			"aria-disabled": e.posting || e.blocker ? "true" : "false",
			"aria-description": !e.posting && e.blocker ? e.blocker : null,
			"data-comments-posting": e.posting ? !0 : null
		}
	}))}><span class="comments__submit-label">${e.posting ? t.posting : e.submitLabel}</span><kbd class="comments__submit-keys">${e.shortcut}</kbd>${W("up", "comments__submit-icon")}</button></div></div>${Y(e, "content")}</div>`;
}
function xt(e, t) {
	return nt(e, t, 36);
}
function St(e) {
	let [t, ...n] = e.labels.replyingTo.split("{name}");
	return S`<p class="comments__frame-reply">${W("reply")}<span class="comments__frame-reply-text">${t}<b>${e.replyTo}</b>${n.join("{name}")}</span><button type="button" class="comments__frame-reply-cancel" data-comments-action="cancel-editor" aria-label=${e.labels.cancelReply}>${W("close")}</button></p>`;
}
function Ct(e) {
	let t = e.viewer;
	return !t || t.isGuest ? T : e.part("postingAs", { viewer: t }, () => {
		let [n, ...r] = e.labels.postingAs.split("{name}");
		return S`<span>${n}<b>${t.name}</b>${r.join("{name}")}</span>${t.badges.map(at)}`;
	});
}
function wt(e) {
	let { labels: t, guest: n } = e, r = (t) => `${e.idPrefix}-guest-${t}`, i = n.required ? S` <span class="comments__field-required" aria-hidden="true">*</span>` : T;
	return S`<legend class="comments__visually-hidden">${t.yourDetails}</legend><label class="comments__field" for=${r("name")}><span class="comments__field-label">${t.yourName}${i}</span><input ${z(e.control("input", { attributes: {
		id: r("name"),
		name: "guest[name]",
		type: "text",
		autocomplete: "name",
		value: n.name,
		required: n.required,
		...X(e, "guest.name")
	} }))}>${Y(e, "guest.name")}</label><label class="comments__field" for=${r("email")}><span class="comments__field-label">${t.yourEmail}${i}<span class="comments__field-hint">${t.notShownPublicly}</span></span><input ${z(e.control("input", { attributes: {
		id: r("email"),
		name: "guest[email]",
		type: "email",
		autocomplete: "email",
		value: n.email,
		required: n.required,
		...X(e, "guest.email")
	} }))}>${Y(e, "guest.email")}</label>${n.offerReplyEmails ? S`<label class="comments__field--checkbox" for=${r("notify")}><input ${z(e.control("checkbox", { attributes: {
		id: r("notify"),
		name: "guest[notifyReplies]",
		type: "checkbox",
		value: "1"
	} }))}><span>${t.replyEmails}</span></label>` : T}`;
}
function Z(e) {
	return e.formPosition === "above" ? "above" : "below";
}
function Tt(e, t = "below") {
	let n = e.fields.filter((e) => Z(e) === t);
	return e.part("formFields", {
		fields: n,
		position: t
	}, () => n.map((t) => S`<div class="comments__field-cell" data-comments-width=${t.width ?? 100}>${Et(e, t)}</div>`));
}
function Et(e, t) {
	let n = `${e.idPrefix}-${t.handle}`, r = `fields[${t.handle}]`, i = `fields.${t.handle}`, a = t.instructions ? `${n}-instructions` : null, o = [a, e.errors[i] ? J(e, i) : null].filter(Boolean).join(" ") || null, s = t.required ? S` <span class="comments__field-required" aria-hidden="true">*</span>` : T, c = t.instructions ? S`<p class="comments__field-instructions" id=${a}>${t.instructions}</p>` : T, l = `${n}-label`, u = {
		field: t,
		value: e.values[t.handle] ?? null,
		setValue: (n) => e.setValue(t.handle, n),
		error: e.errors[i] ?? null,
		id: n,
		name: r,
		labelId: l,
		describedBy: o,
		mode: e.mode,
		...t.type === "file" ? {
			files: e.fieldFiles?.(t.handle) ?? [],
			upload: (n) => e.upload?.(t.handle, n) ?? Promise.reject(/* @__PURE__ */ Error("Comments: uploads aren’t available here."))
		} : {}
	};
	return e.overridden(t.handle) ? S`<div class="comments__field comments__field-custom" data-comments-field=${t.handle} data-comments-field-override><span class="comments__field-label" id=${l}>${t.label}${s}</span>${c}${e.field("input", t.handle, u, () => Dt(e, t, n, o, s, c))}${Y(e, i)}</div>` : Dt(e, t, n, o, s, c);
}
function Dt(e, t, n, r, i, a) {
	let o = `fields[${t.handle}]`, s = `fields.${t.handle}`, c = `${n}-label`;
	if (t.type === "file") {
		let o = {
			inputId: n,
			labelId: c,
			describedBy: r,
			star: i,
			instructions: a
		};
		return e.fileTray ? e.fileTray(e, t, o) : S`<div class="comments__field comments__field-custom comments__field--files" data-comments-field-pending=${t.handle}><span class="comments__field-label" id=${c}>${t.label}${i}</span>${a}${e.trayFailed ? S`<p class="comments__field-error" role="alert">${e.labels.uploadsUnavailable ?? "Files can’t be added right now."}</p><button ${z(e.control("secondaryButton", {
			identify: "comments__upload-add",
			attributes: {
				type: "button",
				"data-comments-upload-action": "reload",
				"data-comments-upload-field": t.handle
			}
		}))}>${e.labels.tryAgain}</button>` : T}</div>`;
	}
	let l = {
		"aria-describedby": r,
		"aria-invalid": e.errors[s] ? "true" : null,
		"data-comments-field": t.handle
	}, u = Object.fromEntries(Object.entries(t.attributes ?? {}).filter(([e]) => /^(min|max|step|maxlength|minlength|pattern|placeholder)$/.test(e))), d = {
		"data-1p-ignore": !0,
		"data-lpignore": "true",
		"data-bwignore": !0,
		"data-form-type": "other"
	};
	if (t.type === "checkboxes" || t.type === "radio") {
		let c = t.type === "radio" ? "radio" : "checkbox";
		return S`<fieldset class="comments__field comments__field-custom" aria-describedby=${r ?? T}><legend class="comments__field-label">${t.label}${i}</legend>${a}${t.options.map((r, i) => S`<label class="comments__field--checkbox" for=${`${n}-${i + 1}`}><input ${z(e.control(c, { attributes: {
			id: `${n}-${i + 1}`,
			type: c,
			name: c === "checkbox" ? `${o}[]` : o,
			value: r.value,
			required: c === "radio" && t.required,
			"aria-invalid": i === 0 && e.errors[s] ? "true" : null,
			"aria-describedby": i === 0 && e.errors[s] ? J(e, s) : null,
			"data-comments-field": t.handle
		} }))}><span>${r.label}</span></label>`)}${Y(e, s)}</fieldset>`;
	}
	if (t.type === "toggle") return S`<div class="comments__field comments__field-custom"><label class="comments__field-label" for=${n}>${t.label}</label>${a}<input ${z(e.control("checkbox", {
		identify: "comments__switch",
		attributes: {
			id: n,
			type: "checkbox",
			role: "switch",
			name: o,
			value: "1",
			...l
		}
	}))}>${Y(e, s)}</div>`;
	if (t.type === "custom") return _t.has(t.handle) || (_t.add(t.handle), console.warn(`Comments: the "${t.handle}" field needs an input override to be drawn: Comments.configure({ fields: { ${t.handle}: { input } } }).`)), T;
	let f;
	return f = t.type === "select" ? S`<select ${z(e.control("select", { attributes: {
		id: n,
		name: t.multiple ? `${o}[]` : o,
		multiple: t.multiple,
		required: t.required,
		...l
	} }))}>${t.multiple ? T : S`<option value="">${e.labels.chooseOption}</option>`}${t.options.map((e) => S`<option value=${e.value}>${e.label}</option>`)}</select>` : t.type === "textarea" ? S`<textarea ${z(e.control("textarea", { attributes: {
		id: n,
		name: o,
		required: t.required,
		...u,
		...l,
		...d
	} }))}></textarea>` : S`<input ${z(e.control("input", { attributes: {
		id: n,
		name: o,
		type: t.type,
		required: t.required,
		...u,
		...l,
		...d
	} }))}>`, S`<div class="comments__field comments__field-custom"><label class="comments__field-label" for=${n}>${t.label}${i}</label>${a}${f}${Y(e, s)}</div>`;
}
// packages/comments-browser/src/parts/report.ts
var Ot = 200;
function kt(e) {
	let { labels: t, state: n, instanceId: r } = e, i = e.ui.reportUid ? n.comments[e.ui.reportUid] : void 0, a = (i?.content?.text ?? "").replace(/\s+/g, " ").trim(), o = a.length > Ot ? `${a.slice(0, Ot)}…` : a, s = n.conversation?.reportReasons ?? [];
	return S`<dialog class="comments__report-dialog" data-comments-report-dialog aria-labelledby=${`${r}-report-title`}><form class="comments__report-form" data-comments-form="report" novalidate>${e.ui.reportError ? S`<p class="comments__form-error" tabindex="-1">${e.ui.reportError}</p>` : T}<div class="comments__report-header"><h3 class="comments__report-title" id=${`${r}-report-title`}>${t.reportTitle}</h3><button type="button" class="comments__report-close" data-comments-action="cancel-report" aria-label=${t.close}>×</button></div><blockquote class="comments__report-quote" ?hidden=${!i}>${i?.author?.name ? S`<strong>${i.author.name}</strong>: ` : T}${o}</blockquote>${s.length ? S`<fieldset class="comments__report-fields"><legend class="comments__label">${t.reportReason}</legend>${s.map((t, n) => S`<label class="comments__report-reason" for=${`${r}-report-reason-${n + 1}`}><input ${z(e.control("radio", { attributes: {
		id: `${r}-report-reason-${n + 1}`,
		type: "radio",
		name: "reason",
		value: t.value,
		required: !0
	} }))}><span><span class="comments__report-reason-label">${t.label}</span>${t.description ? S`<span class="comments__report-reason-description">${t.description}</span>` : T}</span></label>`)}</fieldset>` : T}<label class="comments__field" for=${`${r}-report-details`}><span class="comments__label">${t.reportDetails}</span><span class="comments__field-instructions">${t.reportDetailsHint}</span><textarea ${z(e.control("textarea", { attributes: {
		id: `${r}-report-details`,
		name: "details",
		rows: 3,
		maxlength: 2e3
	} }))}></textarea></label><div class="comments__report-actions"><button ${z(e.control("secondaryButton", { attributes: {
		type: "button",
		"data-comments-action": "cancel-report"
	} }))}>${t.cancel}</button><button ${z(e.control("primaryButton", {
		identify: "comments__submit",
		attributes: {
			type: "submit",
			"aria-busy": e.ui.busy.has("report") ? "true" : null
		}
	}))}>${t.sendReport}</button></div></form></dialog>`;
}
// packages/comments-browser/src/parts/tray.ts
var At = {
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
	return e.labels[t] ?? At[t] ?? t;
}
function jt(e) {
	return e >= 1048576 ? `${Number((e / 1048576).toFixed(1))} MB` : `${Math.max(1, Math.round(e / 1024))} KB`;
}
function Mt(e, t, n) {
	let r = `fields.${t.handle}`, i = e.uploads[t.handle] ?? [], a = t.maxFiles ?? null, o = a !== null && i.filter((e) => e.state !== "failed").length >= a, s = t.multiple && a !== 1, c = `${n.inputId}-limits`, l = t.maxSize ? jt(t.maxSize) : null, u = l ? s && a ? B(Q(e, "uploadLimits"), {
		count: a,
		size: l
	}) : B(Q(e, "uploadLimit"), { size: l }) : "", d = [n.describedBy, u ? c : null].filter(Boolean).join(" ") || null;
	return S`<div class="comments__field comments__field-custom comments__field--files" role="group" aria-labelledby=${n.labelId} aria-describedby=${d ?? T} data-comments-field=${t.handle}><span class="comments__field-label" id=${n.labelId}>${t.label}${n.star}</span>${n.instructions}${i.length ? S`<ul class="comments__uploads" role="list">${i.map((n) => Nt(e, t, n))}</ul>` : T}<input class="comments__visually-hidden" id=${n.inputId} type="file" tabindex="-1" aria-hidden="true" ?multiple=${s} accept=${t.accept?.join(",") || T} data-comments-upload-input=${t.handle}><div class="comments__upload-row"><button ${z(e.control("secondaryButton", {
		identify: "comments__upload-add",
		attributes: {
			type: "button",
			"data-comments-upload-action": "add",
			"data-comments-upload-field": t.handle,
			"aria-describedby": d,
			"aria-invalid": e.errors[r] ? "true" : null,
			hidden: o
		}
	}))}>${U("attach")}${Q(e, s ? "addFiles" : "addFile")}</button>${u ? S`<span class="comments__upload-limit" id=${c}>${u}</span>` : T}</div>${Y(e, r)}</div>`;
}
function Nt(e, t, n) {
	let r = n.kind === "image" && n.preview, i = n.state === "uploading" ? Q(e, "uploading") : n.state === "failed" ? n.error ?? Q(e, "uploadFailed") : n.size ? jt(n.size) : "", a = {
		"data-comments-upload-field": t.handle,
		"data-comments-upload": n.key
	};
	return S`<li class=${`comments__upload comments__upload--${r ? "image" : "file"} comments__upload--${n.state}`} aria-busy=${n.state === "uploading" ? "true" : T}>${r ? S`<img class="comments__upload-thumb" src=${n.preview} alt="">` : S`<span class="comments__upload-icon">${U("file")}</span>`}<span class="comments__upload-text"><span class="comments__upload-name">${n.filename}</span><span class="comments__upload-meta">${i}</span></span>${n.state === "failed" ? S`<button type="button" class="comments__upload-retry" data-comments-upload-action="retry" ${z(a)} aria-label=${B(Q(e, "retryUpload"), { name: n.filename })}>${U("retry")}</button>` : T}<button type="button" class="comments__upload-remove" data-comments-upload-action="remove" ${z(a)} aria-label=${B(Q(e, "removeFile"), { name: n.filename })}>${U("close")}</button></li>`;
}
// packages/comments-browser/src/parts/gif.ts
function Pt(e) {
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
function Ft(e) {
	return S`<span aria-hidden="true">${e.labels.gif}</span>`;
}
function It(e) {
	let t = e.gif;
	return S`${t ? S`<figure class="comments__gif-preview"><img class="comments__gif-preview-image" src=${t.url} alt=${e.labels.gif} width=${t.width ?? T} height=${t.height ?? T}><button ${z(e.control("quietButton", {
		identify: "comments__gif-remove",
		attributes: {
			type: "button",
			"data-comments-gif-action": "remove"
		}
	}))}>${e.labels.gifRemove}</button></figure>` : T}${e.gifOpen ? e.gifPanel ?? S`<div class="comments__gif-picker" id=${`${e.idPrefix}-gif-picker`} aria-busy=${e.gifFailed ? "false" : "true"}><p class="comments__gif-status" role="status">${e.gifFailed ? e.labels.gifUnavailable : T}</p><p class="comments__gif-attribution">${e.labels.gifPoweredBy}</p></div>` : T}`;
}
// packages/comments-browser/src/theme-config.ts
var Lt = [
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
], Rt = {
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
}, zt = /^(class|title|aria-label|data-(?!comments-)[a-z0-9]+(-[a-z0-9]+)*)$/, $ = (e) => (typeof e == "string" ? e.split(/\s+/) : [...e ?? []]).filter(Boolean);
function Bt(e) {
	return Lt.includes(e);
}
function Vt(e, t = Ut) {
	let n = {};
	for (let r of e) if (r && typeof r == "object") for (let [e, i] of Object.entries(r)) {
		if (!Bt(e)) {
			t(`Comments: themeConfig has no control called "${e}". It covers ${Lt.join(", ")}.`);
			continue;
		}
		if (!i || typeof i != "object" || Array.isArray(i)) {
			t(`Comments: themeConfig's ${e} must be an object, such as { attributes: { class: 'btn' } }.`);
			continue;
		}
		let r = i, a = n[e] ?? {}, o = { ...a.attributes }, s = r.attributes ?? {};
		if (typeof s != "object" || Array.isArray(s)) t(`Comments: themeConfig's ${e}.attributes must be an object.`);
		else {
			for (let [n, r] of Object.entries(s)) zt.test(n) ? !((e) => typeof e == "string" || typeof e == "number" || typeof e == "boolean" || e === null)(r) && !(Array.isArray(r) && r.every((e) => typeof e == "string")) ? t(`Comments: themeConfig's ${e} "${n}" must be text, a number, true or false, or a list of classes.`) : o[n] = r : t(`Comments: themeConfig can't set "${n}" on ${e}. It sets class, title, aria-label and data-* attributes.`);
			n[e] = {
				resetClass: r.resetClass ?? a.resetClass,
				attributes: o
			};
		}
	}
	return n;
}
var Ht = /* @__PURE__ */ new Set();
function Ut(e) {
	!Ht.has(e) && typeof console < "u" && (Ht.add(e), console.warn(e));
}
function Wt(e, t, n = {}) {
	let r = e[t] ?? {}, i = [...$(n.identify), ...r.resetClass ? [] : [...Rt[t], ...$(n.style)]], a = {};
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
function Gt(e) {
	let { state: t } = e, n = je(t.labels, e.labels), r = Vt([e.themeConfig]), i = e.instanceId ?? `comments-${t.conversation?.ownerId ?? 0}-${t.conversation?.siteId ?? 1}`, a = {
		...Ne(),
		...e.ui
	}, o = (e, t) => Wt(r, e, t), s = (e, t, r, i, a) => {
		if (!e) return i();
		let s = {
			labels: n,
			actions: {},
			...r,
			controlAttributes: o,
			html: S,
			defaultRender: i
		};
		if (typeof e == "string") return /^[a-z][a-z0-9._-]*-[a-z0-9._-]*$/.test(e) ? {
			_$litType$: 1,
			strings: [`<${e}></${e}>`],
			values: []
		} : T;
		if (typeof e == "object") {
			let e = {
				_$litType$: 1,
				strings: ["<verbb-comments-host></verbb-comments-host>"],
				values: []
			};
			return a ? a.around(e) : e;
		}
		try {
			return e(s);
		} catch (e) {
			return console.error(`Comments: the ${t} override failed, so the default was drawn.`, e), i();
		}
	}, c = {
		mode: "string",
		instanceId: i,
		phase: e.phase ?? (t.status === "ready" ? "ready" : "waiting"),
		count: e.count ?? t.conversation?.commentCount ?? 0,
		state: t,
		ui: a,
		labels: n,
		control: o,
		part: (t, n, r, i) => s(e.parts?.[t], `"${t}" part`, n, r, i),
		field: (t, n, r, i) => s(e.fields?.[n]?.[t], `"${n}" field's ${t}`, r, i),
		fieldInputs: new Set(Object.keys(e.fields ?? {}).filter((t) => e.fields?.[t]?.input)),
		reportDialog: kt,
		form: (e) => {
			let [t, n] = e.split(":"), r = Kt(c, t, n ?? null);
			return S`<form class=${gt(t)} data-comments-form=${t} ?data-comments-collapsible=${r.collapsible} novalidate>${bt(r)}</form>`;
		},
		actions: () => ({}),
		formFields: (e) => (e === "reply" ? t.viewerEditor?.fields.reply : t.viewerEditor?.fields.comment) ?? []
	};
	return c;
}
function Kt(e, t, n) {
	let { state: r, labels: i } = e, a = r.viewer, o = r.viewerEditor?.guest, s = (n ? r.comments[n]?.author?.name : null) ?? i.guest, c = !(!a || a.isGuest), l = {
		mode: t,
		idPrefix: `${e.instanceId}-${t}-${n ?? "new"}`,
		labels: i,
		control: e.control,
		part: (t, n, r) => e.part(t, n, r),
		field: (t, n, r, i) => e.field(t, n, r, i),
		overridden: (t) => !!e.fieldInputs?.has(t),
		viewer: a,
		guest: {
			show: !!(a?.isGuest && o?.show !== !1 && t !== "edit"),
			required: o?.required ?? !0,
			name: o?.name ?? null,
			email: o?.email ?? null,
			offerReplyEmails: o?.offerReplyEmails ?? !1
		},
		fields: e.formFields(t),
		values: {},
		setValue: () => void 0,
		uploads: {},
		fileTray: Mt,
		avatars: r.conversation?.showAvatars !== !1,
		collapsible: t === "create" && c,
		collapsed: t === "create" && c,
		replyTo: t === "reply" ? s : null,
		placeholder: t === "edit" ? i.editPlaceholder : t === "reply" ? B(i.replyTo, { name: s.split(/\s+/)[0] ?? s }) : i.editorPlaceholder,
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
	return l.slots = {
		postingAs: S`<p class="comments__posting-as" data-comments-slot="header">${Ct(l)}</p>`,
		guestFields: S`<fieldset class="comments__guest-fields" data-comments-slot="header">${wt(l)}</fieldset>`,
		fieldsAbove: S`<div class="comments__editor-fields comments__editor-fields--above" data-comments-slot="above">${Tt(l, "above")}</div>`,
		formFields: S`<div class="comments__editor-fields" data-comments-slot="footer">${Tt(l, "below")}</div>`,
		cancel: S`<button ${qt(l)}>${i.cancel}</button>`,
		editor: "",
		gifButton: S`<button ${z(Pt(l))}>${Ft(l)}</button>`,
		gifArea: S`<div class="comments__gif-area" data-comments-slot="footer">${It(l)}</div>`
	}, l;
}
function qt(e) {
	return z(e.control("secondaryButton", {
		identify: "comments__cancel verbb-editor__button",
		attributes: {
			type: "button",
			"data-comments-action": "cancel-editor",
			"data-comments-slot": "actions"
		}
	}));
}
// src/web/assets/cp/src/comment-fields.ts
var Jt = "Has anyone styled the thread with Tailwind? The web starter does it with the stable class names.";
function Yt(e, t) {
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
function Xt(e, t, n) {
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
function Zt(e) {
	let t = e.attachShadow({ mode: "open" }), n = new CSSStyleSheet();
	return n.replaceSync("[data-comments-region=live],[data-comments-root] .comments__visually-hidden{clip-path:inset(50%);white-space:nowrap;border:0;block-size:1px;inline-size:1px;margin:-1px;padding:0;position:absolute;overflow:hidden}[data-comments-root] .protect-honeypot{clip-path:inset(50%)!important;white-space:nowrap!important;width:1px!important;height:1px!important;position:absolute!important;overflow:hidden!important}:where([data-comments-root]){display:block}[data-comments-root] verbb-comments-host{display:contents}[data-comments-root] [hidden]{display:none!important}[data-comments-root]:not([data-comments-phase=ready]){box-sizing:border-box;min-block-size:var(--comments-reserve,10rem);align-content:start}[data-comments-root].comments--reserve-1:not([data-comments-phase=ready]){min-block-size:var(--comments-reserve,16rem)}[data-comments-root].comments--reserve-2:not([data-comments-phase=ready]){min-block-size:var(--comments-reserve,24rem)}[data-comments-root].comments--reserve-3:not([data-comments-phase=ready]){min-block-size:var(--comments-reserve,32rem)}[data-comments-root].comments--reserve-4:not([data-comments-phase=ready]){min-block-size:var(--comments-reserve,40rem)}[data-comments-root] [data-comments-placeholder][aria-hidden=true]{visibility:hidden}[data-comments-root] :where(button,summary,a)[aria-busy=true]:not([data-editor-root] *){cursor:progress;-webkit-text-fill-color:transparent;position:relative}[data-comments-root] :where(button,summary,a)[aria-busy=true]:not([data-editor-root] *)>*{visibility:hidden}[data-comments-root] :where(button,summary,a)[aria-busy=true]:not([data-editor-root] *):after{content:\"\";box-sizing:border-box;border:1.5px solid;-webkit-text-fill-color:currentColor;border-inline-end-color:#0000;border-radius:50%;block-size:1em;inline-size:1em;margin:auto;animation:.7s linear infinite comments-spin;position:absolute;inset:0}[data-comments-root] ol[aria-busy=true]{opacity:.55;transition:opacity .15s}@keyframes comments-spin{to{transform:rotate(360deg)}}[data-comments-root] [data-comments-control][aria-disabled=true]{cursor:not-allowed}[data-comments-root] svg[aria-hidden=true]{flex:none}[data-comments-collapsed]>:not(.comments__frame-box,verbb-editor),[data-comments-collapsed]>.comments__frame-box>:not(textarea,label){display:none!important}[data-comments-collapsed]>.comments__frame-box>textarea{resize:none}form[data-comments-editor-mounted] :is(.comments__frame-head,.comments__frame-box,.comments__frame-foot){display:none!important}[data-comments-root] verbb-editor{display:block}[data-comments-root] form:not([data-comments-editor-mounted]) verbb-editor{display:none!important}[data-comments-root] verbb-editor:not(:defined){display:none!important}[data-comments-root] details.comments__menu{position:relative}[data-comments-root] .comments__menu>summary{list-style:none}[data-comments-root] .comments__menu>summary::-webkit-details-marker{display:none}[data-comments-root] .comments__menu-items{z-index:10;display:grid;position:absolute;inset-inline-start:0}[data-comments-theme=none] .comments__menu-items{color:canvastext;background:canvas;border:1px solid graytext;min-inline-size:12em;padding:.25em}[data-comments-theme=none] .comments__author-meta{flex-wrap:wrap;align-items:baseline;gap:.25em .5em;display:flex}[data-comments-report-dialog]{box-sizing:border-box;inline-size:min(28rem,100vw - 2rem)}[data-comments-report-dialog]::backdrop{background:#00000073}[data-comments-region=toast]{z-index:20;pointer-events:none;justify-content:center;display:flex;position:sticky;inset-block-end:1rem}[data-comments-region=toast]>:empty{display:none!important}[data-comments-region=toast]>*{pointer-events:auto}@media (prefers-reduced-motion:reduce){[data-comments-root] :where(button,summary,a)[aria-busy=true]:after{animation-duration:1.6s}[data-comments-root] *{scroll-behavior:auto!important;transition:none!important;animation:none!important}}.comments__gif-area:empty{display:none}.comments__gif-preview{flex-wrap:wrap;align-items:flex-start;margin:0;display:flex}.comments__gif-preview-image{block-size:auto;max-block-size:12rem;inline-size:auto;max-inline-size:min(100%,12rem);display:block}.comments__gif-results{overscroll-behavior:contain;grid-template-columns:repeat(auto-fill,minmax(6.5rem,1fr));max-block-size:16rem;display:grid;overflow-y:auto}.comments__gif-result{cursor:pointer;background:0 0;border:0;inline-size:100%;padding:0;display:block}.comments__gif-result-image{object-fit:cover;block-size:6rem;inline-size:100%;display:block}.comments__field-thumb,.comments__upload-thumb{object-fit:cover;flex:none;max-block-size:4rem;max-inline-size:4rem;display:block}.comments__upload-thumb{border-radius:.375rem;block-size:3.25rem;inline-size:3.25rem}@layer comments-tokens{[data-comments-root]{--comments-font-family:inherit;--comments-font-size:1rem;--comments-line-height:1.5;--comments-color-text:#1f2328;--comments-color-muted:#5a6068;--comments-color-link:#0b57d0;--comments-color-border:#d6d9dd;--comments-color-control-border:#8b9198;--comments-color-surface:#fff;--comments-color-surface-muted:#f5f6f7;--comments-color-accent:#0b57d0;--comments-color-on-accent:#fff;--comments-color-danger:#b3261e;--comments-color-success:#146c2e;--comments-color-warning:#a85d00;--comments-color-focus:#0b57d0;--comments-color-scrim:#00000073;--comments-color-toast:#1f2328;--comments-color-on-toast:#fff;--comments-color-toast-success:#7dd99b;--comments-color-toast-danger:#ff9a93;--comments-color-held-surface:#fff8eb;--comments-color-held-border:#f2d39b;--comments-color-held-text:#8a5300;--comments-color-reported-surface:#fff1f0;--comments-color-reported-border:#f5c2bd;--comments-color-reported-text:#a8322a;--comments-color-like:#c4314b;--comments-color-avatar-0:#3b6ea5;--comments-color-avatar-1:#8a5a2b;--comments-color-avatar-2:#2f7d4f;--comments-color-avatar-3:#8a3f7d;--comments-color-avatar-4:#b0532a;--comments-color-avatar-5:#237a7a;--comments-color-avatar-6:#5a4fa3;--comments-color-avatar-7:#6b7a2a;--comments-color-on-avatar:#fff;--comments-space-1:.25rem;--comments-space-2:.5rem;--comments-space-3:.75rem;--comments-space-4:1rem;--comments-space-5:1.5rem;--comments-radius:.5rem;--comments-avatar-size:2.25rem;--comments-gif-max-size:15rem;--comments-thread-indent:.875rem;--comments-control-size:2.25rem}[data-comments-root][data-comments-theme=dark]{--comments-color-text:#eceef0;--comments-color-muted:#b4b9c0;--comments-color-link:#8ab4f8;--comments-color-border:#43484f;--comments-color-control-border:#70767e;--comments-color-surface:#1f2226;--comments-color-surface-muted:#2a2e33;--comments-color-panel:#17191c;--comments-color-accent:#8ab4f8;--comments-color-on-accent:#10223f;--comments-color-danger:#f2b8b5;--comments-color-success:#7dd99b;--comments-color-focus:#8ab4f8;--comments-color-toast:#eceef0;--comments-color-on-toast:#17191c;--comments-color-toast-success:#146c2e;--comments-color-toast-danger:#b3261e;--comments-color-held-surface:#33291a;--comments-color-held-border:#6b5428;--comments-color-held-text:#f3c77a;--comments-color-reported-surface:#3a2422;--comments-color-reported-border:#74403b;--comments-color-reported-text:#f2a8a1;--comments-color-like:#ff8a9e}@media (prefers-color-scheme:dark){[data-comments-root][data-comments-theme=auto]{--comments-color-text:#eceef0;--comments-color-muted:#b4b9c0;--comments-color-link:#8ab4f8;--comments-color-border:#43484f;--comments-color-control-border:#70767e;--comments-color-surface:#1f2226;--comments-color-surface-muted:#2a2e33;--comments-color-panel:#17191c;--comments-color-accent:#8ab4f8;--comments-color-on-accent:#10223f;--comments-color-danger:#f2b8b5;--comments-color-success:#7dd99b;--comments-color-focus:#8ab4f8;--comments-color-toast:#eceef0;--comments-color-on-toast:#17191c;--comments-color-toast-success:#146c2e;--comments-color-toast-danger:#b3261e;--comments-color-held-surface:#33291a;--comments-color-held-border:#6b5428;--comments-color-held-text:#f3c77a;--comments-color-reported-surface:#3a2422;--comments-color-reported-border:#74403b;--comments-color-reported-text:#f2a8a1;--comments-color-like:#ff8a9e}}@media (pointer:coarse){[data-comments-root]{--comments-control-size:2.75rem}}@media (max-width:40rem){[data-comments-root]{--comments-thread-indent:.75rem}}}[data-comments-root]{font-family:var(--comments-font-family);font-size:var(--comments-font-size);line-height:var(--comments-line-height);color:var(--comments-color-text);--lightningcss-light:initial;--lightningcss-dark: ;color-scheme:light}[data-comments-root][data-comments-theme=dark]{padding:var(--comments-space-4);border-radius:var(--comments-radius);background:var(--comments-color-panel);--lightningcss-light: ;--lightningcss-dark:initial;color-scheme:dark}@media (prefers-color-scheme:dark){[data-comments-root][data-comments-theme=auto]{padding:var(--comments-space-4);border-radius:var(--comments-radius);background:var(--comments-color-panel);--lightningcss-light: ;--lightningcss-dark:initial;color-scheme:dark}}.comments{gap:var(--comments-space-4);grid-template-columns:minmax(0,1fr);display:grid}.comments :where(a:not([data-editor-root] *)){color:var(--comments-color-link)}.comments :where(input,select,textarea,button):where(:not([data-editor-root] *)){font:inherit}.comments :where(button:not([data-editor-root] *)){color:inherit;cursor:pointer}.comments :where(a,button,input,select,textarea,summary,article):focus-visible{outline:2px solid var(--comments-color-focus);outline-offset:2px}.comments article:focus:not(:focus-visible),.comments__heading:focus:not(:focus-visible){outline:none}.comments__header{align-items:flex-end;gap:var(--comments-space-3);flex-wrap:wrap;display:flex}.comments__header-main{align-items:baseline;gap:var(--comments-space-2) var(--comments-space-3);flex-wrap:wrap;margin-inline-end:auto;display:flex}.comments__heading{margin:0;font-size:1.25em;line-height:1.3}.comments__count{color:var(--comments-color-muted);margin:0}.comments__follow-form{align-items:flex-end;gap:var(--comments-space-2);flex-wrap:wrap;max-inline-size:100%;margin:0;display:flex}.comments__list,.comments__replies-list{gap:var(--comments-space-4);grid-template-columns:minmax(0,1fr);margin:0;padding:0;list-style:none;display:grid}.comments__comment{padding:var(--comments-space-4);border:1px solid var(--comments-color-border);border-radius:var(--comments-radius);background:var(--comments-color-surface)}.comments__replies .comments__comment{background:0 0;border:0;border-radius:0;padding:0}.comments__replies{gap:var(--comments-space-4);border-inline-start:2px solid color-mix(in srgb, var(--comments-color-border) 65%, transparent);grid-template-columns:minmax(0,1fr);margin-block-start:.875rem;padding-inline-start:var(--comments-thread-indent);display:grid}.comments__comment[data-comments-visual-depth=\"4\"]>article>.comments__replies{border-inline-start:0;grid-column:1/-1;padding-inline-start:0}.comments__comment>article{grid-template-columns:var(--comments-avatar-size) minmax(0, 1fr);column-gap:var(--comments-space-3);border-radius:var(--comments-radius);align-items:start;display:grid}.comments__comment>article>*{grid-column:2}[data-comments-avatars=off] .comments__comment>article{grid-template-columns:0 minmax(0,1fr);column-gap:0}[data-comments-avatars=off] .comments__editor-row{grid-template-columns:minmax(0,1fr)}[data-comments-avatars=off] .comments__editor-row>.comments__field-error{grid-column:1}.comments__comment>article>.comments__placeholder{grid-column:1/-1}.comments__comment>article:target,.comments__comment--highlight>article{outline:2px solid var(--comments-color-accent);outline-offset:4px}.comments__author{display:contents}.comments__author>[data-comments-control=avatar]{grid-area:1/1/span 2}.comments__avatar{inline-size:var(--comments-avatar-size);block-size:var(--comments-avatar-size);object-fit:cover;border-radius:50%;flex:none}.comments__author-meta{align-items:baseline;gap:var(--comments-space-1) var(--comments-space-2);flex-wrap:wrap;grid-area:1/2;min-inline-size:0;margin-block-end:.125rem;font-size:.875em;display:flex}.comments__author-name{overflow-wrap:anywhere;font-weight:650}.comments__date,.comments__edited{color:var(--comments-color-muted);font-size:.875em}.comments__permalink{color:inherit;text-decoration:none}.comments__permalink:hover .comments__date,.comments__permalink:focus-visible .comments__date{color:var(--comments-color-link);text-decoration:underline}.comments__author-score{color:var(--comments-color-muted);font-variant-numeric:tabular-nums;font-size:.8em;font-weight:650}.comments__author-score--positive{color:var(--comments-color-success)}.comments__author-score--negative{color:var(--comments-color-danger)}.comments__badge{background:color-mix(in srgb, var(--comments-color-accent) 12%, transparent);color:var(--comments-color-accent);border-radius:.25rem;padding:.0625rem .375rem;font-size:.8em;font-weight:700;line-height:1.5}.comments__state-badge{margin:0 0 var(--comments-space-2);border:1px dashed var(--comments-color-muted);border-radius:var(--comments-radius);padding:.1rem .45rem;font-size:.8125em;display:inline-block}.comments__content{overflow-wrap:anywhere;font-size:.9375em;line-height:1.6}.comments__content-plain{margin:0}.comments__content-muted,.comments__placeholder{color:var(--comments-color-muted);margin:0;font-style:italic}.comments__folded{align-items:center;gap:var(--comments-space-1) var(--comments-space-2);flex-wrap:wrap;font-size:.9375em;display:flex}.comments__folded-label{color:var(--comments-color-muted);margin:0;font-style:italic}.comments__content-rich :where(p,ul,ol,blockquote,pre){margin-block:var(--comments-space-2)}.comments__content-rich :where(:first-child){margin-block-start:0}.comments__content-rich :where(:last-child){margin-block-end:0}.comments__content-rich :where(img,video){block-size:auto;max-inline-size:100%}.comments__content-rich :where(pre){overflow-x:auto}.comments__gif{max-inline-size:min(100%, var(--comments-gif-max-size));max-block-size:var(--comments-gif-max-size);border-radius:var(--comments-space-1);margin-block-start:var(--comments-space-2);display:block}.comments__gif:not(:where(.comments__gif--portrait)){block-size:auto}.comments__gif--portrait{object-fit:contain;inline-size:auto}.comments__button{justify-content:center;align-items:center;gap:var(--comments-space-1);min-block-size:var(--comments-control-size);padding:var(--comments-space-1) var(--comments-space-3);border:1px solid var(--comments-color-border);border-radius:var(--comments-radius);color:inherit;font:inherit;cursor:pointer;background:0 0;font-size:.875em;text-decoration:none;display:inline-flex}.comments__button:hover{background:var(--comments-color-surface-muted)}.comments__button--primary{border-color:var(--comments-color-accent);background:var(--comments-color-accent);color:var(--comments-color-on-accent);font-weight:600}.comments__button--primary:hover{background:var(--comments-color-accent);filter:brightness(.92)}.comments__button--secondary{background:0 0}.comments__button--quiet{min-block-size:1.75rem;padding:var(--comments-space-1) var(--comments-space-2);color:var(--comments-color-muted);border-color:#0000;border-radius:.375rem;gap:.3125rem;font-size:.8125rem;font-weight:600}.comments__button--quiet:hover{background:var(--comments-color-surface-muted);color:var(--comments-color-text)}@media (pointer:coarse){.comments__button--quiet{min-block-size:var(--comments-control-size)}}.comments__moderation-actions>.comments__button,.comments__moderator-bar-actions>.comments__button,.comments__join-actions>.comments__button,.comments__join-guest>.comments__button,.comments__report-actions>.comments__button--secondary{border-radius:999px}.comments__actions{align-items:center;gap:var(--comments-space-1);flex-wrap:wrap;margin-block-start:var(--comments-space-1);margin-inline-start:calc(var(--comments-space-2) * -1);display:flex}.comments__interactions{align-items:center;gap:var(--comments-space-1);flex-wrap:wrap;display:flex}.comments__follow[aria-pressed=true]:where(.comments__button){background:var(--comments-color-surface-muted);border-color:currentColor;font-weight:600}.comments__vote-count,.comments__score{font-variant-numeric:tabular-nums}.comments__score{min-inline-size:1.25em;color:var(--comments-color-muted);text-align:center;font-size:.8125rem;font-weight:600}.comments__moderation-actions{gap:var(--comments-space-2);flex-wrap:wrap;margin-inline-start:auto;display:flex}.comments__toggle-replies{justify-self:start}.comments__toggle-replies:where(.comments__button){min-block-size:0;color:var(--comments-color-muted);background:0 0;border:0;border-radius:0;padding:0;font-size:.8125em;font-weight:600}.comments__toggle-replies:where(.comments__button):hover{color:var(--comments-color-text);background:0 0;text-decoration:underline}.comments__load-more,.comments__show-comments,.comments__load-error{margin-block-start:var(--comments-space-1)}.comments__editor{margin:0;display:block}.comments__editor-row{grid-template-columns:var(--comments-avatar-size) minmax(0, 1fr);gap:var(--comments-space-3);align-items:start;display:grid}.comments__editor-row>.comments__avatar{margin-block-start:.2rem}.comments__avatar--guest:where(.comments__avatar){box-sizing:border-box;border:1.5px solid var(--comments-color-border);color:var(--comments-color-muted);justify-content:center;align-items:center;display:inline-flex}.comments__avatar--guest:where(.comments__avatar) .comments__icon{block-size:60%;inline-size:60%}.comments__frame{border:1px solid var(--comments-color-control-border);border-radius:var(--comments-radius);background:var(--comments-color-surface);flex-direction:column;min-inline-size:0;font-size:.9375rem;line-height:1.55;transition:border-color .15s,box-shadow .15s;display:flex;position:relative;container:comments-frame/inline-size}.comments__frame:focus-within{border-color:color-mix(in srgb, var(--comments-color-accent) 70%, var(--comments-color-control-border));box-shadow:0 0 0 3px color-mix(in srgb, var(--comments-color-accent) 22%, transparent)}.comments__frame--fields:not([data-comments-collapsed]),.comments__frame--fields:not([data-comments-collapsed]):focus-within,.comments verbb-editor .verbb-editor.has-fields:not(.is-collapsed){border-color:var(--comments-color-border);box-shadow:none}.comments__frame--fields:not([data-comments-collapsed])>.comments__frame-box{transition:border-color .15s,box-shadow .15s}.comments__frame--fields:not([data-comments-collapsed])>.comments__frame-box:focus-within,.comments :is(.comments__input,.comments__select,.comments__textarea:not(.comments__textarea-fallback)):focus-visible{border-color:color-mix(in srgb, var(--comments-color-accent) 70%, var(--comments-color-control-border));box-shadow:0 0 0 3px color-mix(in srgb, var(--comments-color-accent) 22%, transparent);outline:none}.comments__frame:has(>.comments__frame-box>[aria-invalid=true]){border-color:var(--comments-color-danger)}.comments__frame :focus-visible{outline-offset:1px}.comments__frame-icon{fill:none;stroke:currentColor;stroke-width:1.9px;stroke-linecap:round;stroke-linejoin:round;flex:none;block-size:18px;inline-size:18px}.comments__frame-head{min-inline-size:0;color:var(--comments-color-muted);flex-wrap:wrap;align-items:center;gap:8px;padding:9px 12px 0;font-size:.8125rem;display:flex}.comments__frame-head>.comments__guest-fields{flex:1 0 100%}.comments__frame-head>.comments__posting-as{flex:1 0 auto}.comments__frame-reply{background:color-mix(in srgb, var(--comments-color-accent) 10%, var(--comments-color-surface));block-size:26px;min-inline-size:0;max-inline-size:100%;color:var(--comments-color-text);border-radius:13px;align-items:center;gap:6px;margin:0;padding:0 4px 0 9px;font-size:.78125rem;display:inline-flex}.comments__frame-reply>.comments__frame-icon{block-size:14px;inline-size:14px}.comments__frame-reply-text{text-overflow:ellipsis;white-space:nowrap;min-inline-size:0;overflow:hidden}.comments__frame-reply b,.comments__posting-as b{font-weight:600}.comments__frame-reply-cancel{block-size:20px;min-block-size:0;inline-size:20px;color:color-mix(in srgb, var(--comments-color-text) 72%, transparent);cursor:pointer;background:0 0;border:0;border-radius:50%;flex:none;place-items:center;padding:0;display:grid}.comments__frame-reply-cancel:hover{color:var(--comments-color-text)}.comments__frame-reply-cancel .comments__frame-icon{block-size:14px;inline-size:14px}.comments__frame .comments__textarea-fallback:where(.comments__textarea){box-sizing:border-box;block-size:calc(3.75em + 16px);min-block-size:calc(3.75em + 16px);max-block-size:436px;font-size:inherit;line-height:inherit;overflow-wrap:anywhere;resize:vertical;background:0 0;border:0;border-radius:0;padding:10px 14px 6px;display:block}.comments__frame .comments__textarea-fallback:where(.comments__textarea)::placeholder{color:color-mix(in srgb, var(--comments-color-muted) 85%, transparent);opacity:1}.comments__frame .comments__textarea-fallback:where(.comments__textarea):focus-visible{outline:none}.comments__frame .comments__textarea-fallback:where(.comments__textarea){resize:none}.comments__frame-notice{background:color-mix(in srgb, var(--comments-color-accent) 10%, var(--comments-color-surface));border-radius:7px;align-items:center;gap:8px;margin:0 10px 8px;padding:7px 10px;font-size:.8125rem;display:flex}.comments__frame-notice[hidden]{display:none}.comments__frame-notice>.comments__frame-icon{block-size:16px;inline-size:16px}.comments__frame-notice-text{flex:1;min-inline-size:0}.comments__frame-link{min-block-size:0;color:var(--comments-color-accent);font:inherit;cursor:pointer;background:0 0;border:0;padding:0 2px;font-weight:600;text-decoration:underline;display:inline}.comments__frame>.comments__editor-fields{padding:0 12px 8px}.comments__frame>.comments__editor-fields--above{padding:10px 12px 0}.comments__frame--fields:not([data-comments-collapsed])>.comments__frame-box{border:1px solid var(--comments-color-control-border);border-radius:calc(var(--comments-radius) - 2px);margin:10px 12px}.comments__frame--fields:not([data-comments-collapsed])>:is(.comments__frame-head,.comments__editor-fields--above)+.comments__frame-box{margin-block-start:8px}.comments__frame--fields:not([data-comments-collapsed])>.comments__frame-box~.comments__editor-fields{padding-block-start:2px}.comments__frame-foot{flex-wrap:wrap;justify-content:flex-end;align-items:center;gap:6px;min-inline-size:0;padding:6px 8px 8px;display:flex}.comments__frame-meta{min-inline-size:0;color:var(--comments-color-muted);flex:none;align-items:center;gap:8px;margin-inline-end:auto;padding-inline-start:6px;font-size:.8125rem;display:flex}.comments__frame-status{white-space:nowrap;text-overflow:ellipsis;align-items:center;gap:5px;transition:opacity .3s;display:inline-flex;overflow:hidden}.comments__frame-status .comments__frame-icon{block-size:14px;inline-size:14px}.comments__frame-status--bad{color:var(--comments-color-danger);font-weight:600}.comments__frame-status--faded{opacity:.55}.comments__frame-count{font-variant-numeric:tabular-nums;flex:none;align-items:center;gap:4px;font-size:.75rem;font-weight:600;display:inline-flex}.comments__frame-count svg{block-size:20px;inline-size:20px;transform:rotate(-90deg)}.comments__frame-count circle{fill:none;stroke-width:2.6px}.comments__frame-count .comments__ring-bg{stroke:color-mix(in srgb, var(--comments-color-text) 14%, transparent)}.comments__frame-count .comments__ring-fg{stroke:var(--comments-color-accent);stroke-linecap:round}.comments__frame-count--warn{color:var(--comments-color-warning)}.comments__frame-count--warn .comments__ring-fg{stroke:var(--comments-color-warning)}.comments__frame-count--bad{color:var(--comments-color-danger)}.comments__frame-count--bad .comments__ring-fg{stroke:var(--comments-color-danger)}.comments__frame-foot .comments__submit:where(.comments__button),.comments__cancel:where(.comments__button),.comments__gif-button:where(.comments__button){border-radius:calc(var(--comments-radius) * .7);white-space:nowrap;cursor:pointer;border:0;justify-content:center;align-items:center;gap:8px;block-size:34px;min-block-size:0;padding:0 14px;font-size:.875rem;font-weight:600;line-height:normal;text-decoration:none;display:inline-flex}.comments__cancel:where(.comments__button),.comments__gif-button:where(.comments__button){color:var(--comments-color-text);background:0 0}.comments__gif-button:where(.comments__button){letter-spacing:.04em;padding:0 10px;font-size:.75rem}.comments__gif-button:where(.comments__button)[aria-expanded=true]{background:var(--comments-color-surface-muted)}.comments__frame-foot .comments__submit:where(.comments__button):hover{background:color-mix(in srgb, var(--comments-color-accent) 88%, #000);filter:none}.comments__cancel:where(.comments__button):hover,.comments__gif-button:where(.comments__button):hover{background:var(--comments-color-surface-muted)}.comments__gif-area{gap:8px;padding:0 12px 8px;display:grid}.comments__gif-preview{gap:8px}.comments__gif-preview-image{border-radius:calc(var(--comments-radius) * .5)}.comments__gif-picker{border:1px solid var(--comments-color-control-border);border-radius:calc(var(--comments-radius) - 2px);background:var(--comments-color-surface);gap:8px;padding:8px;display:grid}.comments__gif-results{gap:6px}.comments__gif-result{border-radius:calc(var(--comments-radius) * .5);background:var(--comments-color-surface-muted);overflow:hidden}.comments__gif-result:hover .comments__gif-result-image{opacity:.85}.comments__gif-status{color:var(--comments-color-muted);margin:0;font-size:.8125rem}.comments__gif-status:empty{display:none}.comments__gif-attribution{color:var(--comments-color-text);text-align:end;margin:0;font-size:.75rem;font-weight:700}.comments__frame-foot .comments__submit:where(.comments__button)[aria-disabled=true]{opacity:.45;cursor:not-allowed}.comments__frame-foot .comments__submit:where(.comments__button)[aria-disabled=true]:hover{background:var(--comments-color-accent)}.comments__submit-keys{letter-spacing:.06em;opacity:.7;align-items:center;font-family:inherit;font-size:12px;font-weight:500;line-height:1;display:inline-flex}.comments__submit-icon{display:none}.comments__submit .comments__frame-icon{stroke-width:2.2px;block-size:18px;inline-size:18px}@container comments-frame (width<=520px){.comments__submit-keys,.comments__frame-status-text{display:none}.comments__frame-meta{gap:4px;padding-inline-start:2px}}@container comments-frame (width<=400px){.comments__frame-foot .comments__submit:where(.comments__button){border-radius:50%;inline-size:36px;padding:0}.comments__submit-label{display:none}.comments__submit-icon{display:block}}.comments__frame[data-comments-collapsed]{border-radius:999px}.comments__frame[data-comments-collapsed] .comments__textarea-fallback:where(.comments__textarea){block-size:auto;min-block-size:0;padding-block:9px;padding-inline:16px}form[data-comments-editor-mounted] .comments__frame{box-shadow:none;background:0 0;border:0;border-radius:0}.comments__editor-row>.comments__field-error{grid-column:2}.comments__contextual-editor:not(:empty){margin-block-start:var(--comments-space-3)}.comments__guest-fields{gap:var(--comments-space-2) var(--comments-space-3);min-inline-size:0;color:var(--comments-color-text);border:0;flex:1 0 100%;grid-template-columns:repeat(2,minmax(0,1fr));align-items:start;margin:0;padding:0;font-size:.875rem;display:grid}.comments__guest-fields>.comments__field--checkbox{grid-column:1/-1}@container (width<=30rem){.comments__guest-fields{grid-template-columns:minmax(0,1fr)}}.comments__guest-fields .comments__input,.comments__editor-fields :is(.comments__input,.comments__select),.comments__editor-fields .comments__textarea{border-color:var(--comments-color-control-border);background:var(--comments-color-surface)}.comments__field-hint{color:var(--comments-color-muted);margin-inline-start:var(--comments-space-1);font-weight:400}.comments__editor-fields{gap:var(--comments-space-3);color:var(--comments-color-text);grid-template-columns:repeat(4,minmax(0,1fr));align-items:start;font-size:.875rem;display:grid}.comments__field-cell{grid-column:span 4;min-inline-size:0}.comments__field-cell[data-comments-width=\"25\"]{grid-column:span 1}.comments__field-cell[data-comments-width=\"50\"]{grid-column:span 2}.comments__field-cell[data-comments-width=\"75\"]{grid-column:span 3}@container (width<=30rem){.comments__field-cell{grid-column:span 4}}.comments__report-fields,.comments__field-custom:where(fieldset){gap:var(--comments-space-3);border:0;min-inline-size:0;margin:0;padding:0;display:grid}.comments__report-fields legend,.comments__field-custom legend{padding:0;font-weight:600}.comments__field{gap:var(--comments-space-1);grid-template-columns:minmax(0,1fr);display:grid}.comments__field-label,.comments__label{font-size:.875em;font-weight:600;line-height:1.35}.comments__field-instructions{color:var(--comments-color-muted);margin:0;font-size:.875em;line-height:1.4}.comments__field-label+.comments__field-instructions{margin-block-start:calc(var(--comments-space-1) * -.5)}.comments__field--checkbox{align-items:flex-start;gap:var(--comments-space-2);font-size:.875em;display:flex}.comments__field--checkbox input{flex:none;margin:.2em 0 0}.comments__field-custom:where(fieldset)>legend{margin-block-end:var(--comments-space-1)}.comments__field-custom:where(fieldset){gap:var(--comments-space-2)}.comments__switch:where(.comments__checkbox){appearance:none;background:var(--comments-color-control-border);cursor:pointer;border:1px solid #0000;border-radius:999px;flex:none;block-size:1.25rem;inline-size:2.25rem;margin:0;transition:background-color .15s;position:relative}.comments__switch:where(.comments__checkbox):before{content:\"\";background:var(--comments-color-surface);border-radius:50%;block-size:calc(1.25rem - 4px);inline-size:calc(1.25rem - 4px);transition:inset-inline-start .15s;position:absolute;inset-block-start:1px;inset-inline-start:1px;box-shadow:0 1px 2px #00000040}.comments__switch:where(.comments__checkbox):checked{background:var(--comments-color-accent)}.comments__switch:where(.comments__checkbox):checked:before{inset-inline-start:calc(100% - 1.25rem + 3px)}.comments__switch:where(.comments__checkbox)[aria-invalid=true]{border-color:var(--comments-color-danger)}@media (forced-colors:active){.comments__switch:where(.comments__checkbox){background:buttonface;border-color:buttontext}.comments__switch:where(.comments__checkbox):before{background:buttontext}.comments__switch:where(.comments__checkbox):checked{background:highlight}.comments__switch:where(.comments__checkbox):checked:before{background:highlighttext}}@media (prefers-reduced-motion:reduce){.comments__switch:where(.comments__checkbox),.comments__switch:where(.comments__checkbox):before{transition:none}}.comments__input,.comments__textarea,.comments__select{box-sizing:border-box;inline-size:100%;max-inline-size:100%;min-block-size:var(--comments-control-size);padding:var(--comments-space-2) var(--comments-space-3);border:1px solid var(--comments-color-control-border);border-radius:var(--comments-radius);background:var(--comments-color-surface);color:inherit;font:inherit;transition:border-color .15s,box-shadow .15s}.comments__select:not([multiple]){appearance:none;background-image:linear-gradient(45deg,#0000 calc(50% - .75px),currentColor calc(50% - .75px) calc(50% + .75px),#0000 calc(50% + .75px)),linear-gradient(-45deg,#0000 calc(50% - .75px),currentColor calc(50% - .75px) calc(50% + .75px),#0000 calc(50% + .75px));background-position:calc(100% - var(--comments-space-3) - 5px) 50%, calc(100% - var(--comments-space-3)) 50%;background-repeat:no-repeat;background-size:5px 5px;padding-inline-end:calc(var(--comments-space-3) + 18px)}.comments__select:not([multiple]):is(:lang(ae),:lang(ar),:lang(arc),:lang(bcc),:lang(bqi),:lang(ckb),:lang(dv),:lang(fa),:lang(glk),:lang(he),:lang(ku),:lang(mzn),:lang(nqo),:lang(pnb),:lang(ps),:lang(sd),:lang(ug),:lang(ur),:lang(yi)){background-position:var(--comments-space-3) 50%, calc(var(--comments-space-3) + 5px) 50%}.comments__field-required{color:var(--comments-color-danger)}.comments__textarea{resize:vertical;min-block-size:6rem}.comments__report-form .comments__textarea{min-block-size:var(--comments-control-size)}.comments [aria-invalid=true]{border-color:var(--comments-color-danger)}.comments__field-error,.comments__form-error{color:var(--comments-color-danger);margin:0;font-size:.875em}.comments__report-actions{gap:var(--comments-space-2);flex-wrap:wrap;margin-block-start:var(--comments-space-4);display:flex}.comments__report-dialog{padding:var(--comments-space-4);border:1px solid var(--comments-color-border);border-radius:var(--comments-radius);background:var(--comments-color-surface);color:var(--comments-color-text)}.comments__report-dialog::backdrop{background:var(--comments-color-scrim,#00000073)}.comments__empty p,.comments__guest-notice,.comments__closed-notice,.comments__loading{color:var(--comments-color-muted);margin:0}.comments__empty p+p{margin-block-start:var(--comments-space-1)}[data-comments-region=toast]{inset-block-end:var(--comments-space-4)}.comments__toast{align-items:center;gap:var(--comments-space-2);max-inline-size:min(100%,32rem);padding:var(--comments-space-2) var(--comments-space-3);background:var(--comments-color-toast);color:var(--comments-color-on-toast);border-radius:.5rem;font-size:.875rem;font-weight:500;line-height:1.4;animation:.18s ease-out comments-toast-in;display:inline-flex;box-shadow:0 8px 24px -8px #00000073}.comments__toast[data-comments-state]:before{content:\"\";block-size:1rem;inline-size:1rem;-webkit-mask:var(--_comments-toast-icon) center / contain no-repeat;-webkit-mask:var(--_comments-toast-icon) center / contain no-repeat;mask:var(--_comments-toast-icon) center / contain no-repeat;background:currentColor;flex:none}.comments__toast[data-comments-state=success]{--_comments-toast-icon:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M20 6 9 17l-5-5'/%3E%3C/svg%3E\")}.comments__toast[data-comments-state=success]:before{color:var(--comments-color-toast-success)}.comments__toast[data-comments-state=error]{--_comments-toast-icon:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='10'/%3E%3Cpath d='M12 8v4M12 16h.01'/%3E%3C/svg%3E\")}.comments__toast[data-comments-state=error]:before{color:var(--comments-color-toast-danger)}.comments__toast button{min-block-size:0;color:inherit;font-size:inherit;text-underline-offset:2px;background:0 0;border:0;margin-inline-start:var(--comments-space-1);padding:0;font-weight:600;text-decoration:underline}.comments__toast button:hover{opacity:.85;background:0 0}@keyframes comments-toast-in{0%{opacity:0;transform:translateY(6px)}}@media (max-width:40rem){.comments__moderation-actions{margin-inline-start:0}}.comments__icon{fill:none;stroke:currentColor;stroke-width:1.8px;stroke-linecap:round;stroke-linejoin:round;flex:none;block-size:1.1em;inline-size:1.1em}.comments__icon--more circle,.comments__vote[aria-pressed=true] .comments__icon--heart{fill:currentColor}.comments__vote[aria-pressed=true] :is(.comments__icon--up,.comments__icon--down){stroke-width:2.4px}.comments__heading .comments__count{margin-inline-start:var(--comments-space-1);font-size:.7em;font-weight:400}.comments__sort{background:var(--comments-color-surface-muted);border-radius:999px;padding:2px;display:inline-flex}.comments__sort-option:where(.comments__button){min-block-size:0;padding:var(--comments-space-1) var(--comments-space-3);color:color-mix(in srgb, var(--comments-color-text) 60%, var(--comments-color-muted));font-size:.875em;font-weight:400;line-height:inherit;background:0 0;border:0;border-radius:999px;display:inline}.comments__sort-option:where(.comments__button):hover{color:var(--comments-color-text);background:0 0}.comments__sort-option:where(.comments__button)[aria-pressed=true]{background:var(--comments-color-surface);color:var(--comments-color-text);font-weight:600}.comments__actions .comments__icon{block-size:.9375rem;inline-size:.9375rem}.comments .comments__vote-total{min-block-size:1.75rem;padding:var(--comments-space-1) var(--comments-space-2);color:var(--comments-color-muted);border:1px solid #0000;align-items:center;gap:.3125rem;font-size:.8125rem;font-weight:600;display:inline-flex}.comments [data-comments-interaction-mode=updown]{gap:0}.comments__vote[aria-pressed=true]{color:var(--comments-color-like)}[data-comments-interaction-mode=updown] .comments__vote--up[aria-pressed=true]{color:var(--comments-color-accent)}.comments__follow:where(.comments__button){gap:var(--comments-space-2)}.comments__menu-items{min-inline-size:12rem;padding:var(--comments-space-1);border:1px solid var(--comments-color-border);border-radius:var(--comments-radius);background:var(--comments-color-surface);margin-block-start:var(--comments-space-1);box-shadow:0 8px 24px #0000001f}.comments__menu-item{justify-content:flex-start;align-items:center;gap:var(--comments-space-1);box-sizing:border-box;inline-size:100%;min-block-size:var(--comments-control-size);padding:var(--comments-space-1) var(--comments-space-3);border-radius:calc(var(--comments-radius) - var(--comments-space-1));text-align:start;white-space:nowrap;background:0 0;border:1px solid #0000;font-size:.875em;display:inline-flex}.comments__menu-item:hover{background:var(--comments-color-surface-muted)}.comments .comments__menu-item:focus-visible{outline-offset:-2px}.comments__menu-group{border-block-start:1px solid var(--comments-color-border);margin-block-start:var(--comments-space-1);padding-block-start:var(--comments-space-1);display:grid}.comments__menu-group:first-child{border-block-start:0;margin-block-start:0;padding-block-start:0}.comments__menu-heading{padding:var(--comments-space-1) var(--comments-space-3);color:var(--comments-color-muted);letter-spacing:.04em;text-transform:uppercase;font-size:.75em;font-weight:600}.comments__menu-item--danger{color:var(--comments-color-danger)}.comments__avatar--initials:where(.comments__avatar){color:var(--comments-color-on-avatar);background:var(--comments-color-avatar-0);justify-content:center;align-items:center;font-size:.8125rem;font-weight:700;display:inline-flex}.comments__avatar--initials:where(.comments__avatar)[data-tone=\"1\"]{background:var(--comments-color-avatar-1)}.comments__avatar--initials:where(.comments__avatar)[data-tone=\"2\"]{background:var(--comments-color-avatar-2)}.comments__avatar--initials:where(.comments__avatar)[data-tone=\"3\"]{background:var(--comments-color-avatar-3)}.comments__avatar--initials:where(.comments__avatar)[data-tone=\"4\"]{background:var(--comments-color-avatar-4)}.comments__avatar--initials:where(.comments__avatar)[data-tone=\"5\"]{background:var(--comments-color-avatar-5)}.comments__avatar--initials:where(.comments__avatar)[data-tone=\"6\"]{background:var(--comments-color-avatar-6)}.comments__avatar--initials:where(.comments__avatar)[data-tone=\"7\"]{background:var(--comments-color-avatar-7)}.comments__avatar--small{block-size:1.5rem;inline-size:1.5rem;font-size:.625em}.comments__comment--held>article,.comments__comment--pending>article,.comments__comment--reported>article{padding:var(--comments-space-3);border:1px solid var(--comments-color-held-border);border-radius:var(--comments-radius);background:var(--comments-color-held-surface)}.comments__comment--reported>article{border-color:var(--comments-color-reported-border);background:var(--comments-color-reported-surface)}.comments__review{align-items:center;gap:var(--comments-space-2);border-block-start:1px solid var(--comments-color-held-border);flex-wrap:wrap;margin-block-start:var(--comments-space-3);padding-block-start:var(--comments-space-2);display:flex}.comments__review-label{align-items:center;gap:var(--comments-space-1);color:var(--comments-color-held-text);margin:0;font-size:.875em;display:inline-flex}.comments__comment--reported>article>.comments__review{border-block-start-color:var(--comments-color-reported-border)}.comments__comment--reported>article>.comments__review .comments__review-label{color:var(--comments-color-reported-text)}.comments :is(.comments__moderator-bar,.comments__comment--held>article,.comments__comment--pending>article,.comments__comment--reported>article){--_comments-tint-border:var(--comments-color-held-border)}.comments .comments__comment--reported>article{--_comments-tint-border:var(--comments-color-reported-border)}.comments :is(.comments__moderator-bar,.comments__comment--held>article,.comments__comment--reported>article) .comments__button--secondary{border-color:var(--_comments-tint-border)}.comments :is(.comments__moderator-bar,.comments__comment--held>article,.comments__comment--reported>article) .comments__button--secondary:hover{background:color-mix(in srgb, var(--_comments-tint-border) 35%, transparent)}.comments__moderator-bar{justify-content:space-between;align-items:center;gap:var(--comments-space-2);inline-size:100%;padding:var(--comments-space-2) var(--comments-space-3);border:1px solid var(--comments-color-held-border);border-radius:var(--comments-radius);background:var(--comments-color-held-surface);color:var(--comments-color-held-text);flex-wrap:wrap;display:flex}.comments__moderator-bar p{margin:0}.comments__moderator-bar-actions{align-items:center;gap:var(--comments-space-2);flex-wrap:wrap;display:flex}.comments__join{padding:var(--comments-space-4);border:1px solid var(--comments-color-border);border-radius:var(--comments-radius);background:var(--comments-color-surface)}.comments__join-title{margin:0;font-size:1em}.comments__join-text{margin:var(--comments-space-1) 0 var(--comments-space-3);color:var(--comments-color-muted)}.comments__join-actions{align-items:flex-start;gap:var(--comments-space-2);flex-wrap:wrap;display:flex}.comments__join-guest{flex:0 auto}.comments__join-guest[open]{flex-basis:100%}.comments__join-guest>summary{list-style:none}.comments__join-guest>summary:where(.comments__button){padding-inline:var(--comments-space-4)}.comments__join-guest>summary::-webkit-details-marker{display:none}.comments__join:has(.comments__join-guest[open]){background:0 0;border:0;padding:0}.comments__join-guest[open]>summary{display:none!important}.comments__join:has(.comments__join-guest[open])>:is(.comments__join-title,.comments__join-text){display:none!important}.comments__join-actions:has(.comments__join-guest[open])>:not(.comments__join-guest[open]){display:none!important}.comments__join-actions>.comments__button{min-block-size:var(--comments-control-size);padding:var(--comments-space-1) var(--comments-space-4);align-items:center;text-decoration:none;display:inline-flex}.comments__join-actions:has(.comments__join-guest[open]){display:block}.comments__posting-as{align-items:center;gap:var(--comments-space-2);color:var(--comments-color-muted);flex-wrap:wrap;margin:0;font-size:.8125rem;display:flex}.comments__posting-as b{color:var(--comments-color-text)}.comments__report-header{justify-content:space-between;align-items:center;gap:var(--comments-space-2);margin-block-end:var(--comments-space-3);display:flex}.comments__report-title{margin:0;font-size:1.05em}.comments__report-close{min-block-size:var(--comments-control-size);padding:var(--comments-space-1) var(--comments-space-3);border-radius:var(--comments-radius);background:0 0;border:1px solid #0000;justify-content:center;align-items:center;font-size:1.25em;display:inline-flex}.comments__report-close:hover{background:var(--comments-color-surface-muted)}.comments__report-quote{margin:0 0 var(--comments-space-3);padding:var(--comments-space-2) var(--comments-space-3);border-inline-start:3px solid var(--comments-color-border);background:var(--comments-color-surface-muted);font-size:.875em}.comments__report-reason{align-items:flex-start;gap:var(--comments-space-2);padding:var(--comments-space-2) var(--comments-space-3);border:1px solid var(--comments-color-border);border-radius:var(--comments-radius);cursor:pointer;margin-block-end:var(--comments-space-2);display:flex}.comments__report-reason:has(input:checked){border-color:var(--comments-color-accent)}.comments__report-reason input{margin-block-start:.25em}.comments__report-reason-label{font-weight:600;display:block}.comments__report-reason-description{color:var(--comments-color-muted);font-size:.875em;display:block}.comments__comment{background:0 0;border:0;border-radius:0;padding:0}.comments__list{gap:1.25rem}.comments verbb-editor{--verbb-editor-accent:var(--comments-color-accent);--verbb-editor-accent-fg:var(--comments-color-on-accent);--verbb-editor-bg:var(--comments-color-surface);--verbb-editor-fg:var(--comments-color-text);--verbb-editor-muted:var(--comments-color-muted);--verbb-editor-border:var(--comments-color-control-border);--verbb-editor-hover:var(--comments-color-surface-muted);--verbb-editor-danger:var(--comments-color-danger);--verbb-editor-radius:var(--comments-radius);--verbb-editor-font-family:inherit}.comments verbb-editor .verbb-editor.is-collapsed{border-radius:999px}.comments verbb-editor .verbb-editor.is-collapsed .verbb-editor__body{padding-inline:16px}.comments verbb-editor .verbb-editor__head .comments__posting-as,.comments verbb-editor .verbb-editor__head .comments__guest-fields{flex:1 0 100%}.comments verbb-editor .verbb-editor__head .comments__posting-as{flex-basis:auto}.comments__meta-item{color:var(--comments-color-muted);font-size:.875em}.comments__meta-item:before{content:\"· \"}.comments__fields{gap:var(--comments-space-1);margin:var(--comments-space-2) 0 0;font-size:.875em;display:grid}.comments__field-value{gap:var(--comments-space-2);flex-wrap:wrap;display:flex}.comments__field-name{color:var(--comments-color-muted);font-weight:600}.comments__field-text{margin:0}.comments__field-link,.comments__field-file{color:var(--comments-color-link);overflow-wrap:anywhere}.comments__field-files{align-items:center;gap:var(--comments-space-2);flex-wrap:wrap;display:flex}span.comments__field-file{color:inherit}.comments__field-thumb{border-radius:var(--comments-radius);block-size:4rem;inline-size:4rem;box-shadow:inset 0 0 0 1px var(--comments-color-border);display:block}.comments__field--files{justify-items:start}.comments__field--files>:is(.comments__uploads,.comments__upload-row){margin-block-start:var(--comments-space-1)}.comments__upload-row{align-items:center;gap:var(--comments-space-1) var(--comments-space-3);flex-wrap:wrap;display:flex}.comments__upload-limit{color:var(--comments-color-muted);font-size:.8125rem}.comments__uploads{gap:var(--comments-space-2);flex-wrap:wrap;margin:0;padding:0;list-style:none;display:flex}.comments__upload{border-radius:var(--comments-radius);background:var(--comments-color-surface-muted);block-size:4rem;min-inline-size:0;max-inline-size:15rem;box-shadow:inset 0 0 0 1px var(--comments-color-border);align-items:center;gap:.5625rem;padding-block:0;padding-inline:.375rem 2rem;display:flex;position:relative}.comments__upload--failed{box-shadow:inset 0 0 0 1px var(--comments-color-danger)}.comments__upload--uploading{opacity:.75}.comments__upload-icon{background:var(--comments-color-surface);block-size:2.5rem;inline-size:2.125rem;box-shadow:0 0 0 1px var(--comments-color-border);color:var(--comments-color-accent);border-radius:.3125rem;flex:none;place-items:center;display:grid}.comments__upload-text{gap:.1875rem;min-inline-size:0;font-size:.8125rem;display:grid}.comments__upload-name{text-overflow:ellipsis;white-space:nowrap;font-weight:600;overflow:hidden}.comments__upload-meta{color:var(--comments-color-muted)}.comments__upload--failed .comments__upload-meta{color:var(--comments-color-danger)}.comments__upload-remove,.comments__upload-retry{background:var(--comments-color-toast);block-size:1.375rem;inline-size:1.375rem;color:var(--comments-color-on-toast);cursor:pointer;border:0;border-radius:50%;place-items:center;padding:0;display:grid;position:absolute;inset-block-start:.25rem;inset-inline-end:.25rem}.comments__upload-retry{inset-block:auto .25rem}.comments__upload-remove .comments__icon,.comments__upload-retry .comments__icon{stroke-width:2.4px;block-size:.75rem;inline-size:.75rem}@media (pointer:coarse){.comments__upload-remove:after,.comments__upload-retry:after{content:\"\";block-size:2.75rem;inline-size:2.75rem;position:absolute}}.comments__upload-add{align-items:center;gap:var(--comments-space-1);display:inline-flex}.comments__loading[aria-busy=true]{animation:1.6s ease-in-out infinite comments-pulse}@keyframes comments-pulse{50%{opacity:.55}}[dir=rtl] :is(.comments__icon--reply,.comments__frame-reply>.comments__frame-icon){transform:scaleX(-1)}.comments__load-error>.comments__loading{margin-block-end:var(--comments-space-2)}\n:host { display: block; }\n.preview { pointer-events: none; }"), t.adoptedStyleSheets = [n], t;
}
function Qt(e) {
	let t = JSON.parse(e.querySelector("[data-comments-field-rows]")?.textContent ?? "[]"), n = JSON.parse(e.querySelector("[data-comments-field-labels]")?.textContent ?? "{}"), r = e.dataset.photoUrl ?? "", i = e.querySelector("[data-comments-form-preview]"), a = e.querySelector("[data-comments-card-preview]");
	if (!i || !a) return;
	let o = Zt(i), s = Zt(a), c = e.closest("form")?.querySelector("input[name=\"settings[fieldLayout]\"]"), l = () => {
		let e = [], t = {}, n = /* @__PURE__ */ new Set(), r = !1;
		try {
			let i = JSON.parse(c?.value ?? "{}");
			for (let a of (i.tabs ?? []).flatMap((e) => e.elements ?? [])) a.type === "verbb\\comments\\fieldlayoutelements\\CommentsField" ? r = !0 : a.uid && (e.push(a.uid), t[a.uid] = a.width ?? 100, r || n.add(a.uid));
		} catch {}
		return r || n.clear(), {
			order: e,
			width: t,
			above: n
		};
	}, u = (t) => e.querySelector(`[data-comments-field-ask="${t}"]`)?.classList.contains("on") ?? !1, d = (t) => e.querySelector(`[data-comments-field-show="${t}"]`)?.value ?? "none", f = () => {
		let i = l(), a = (e) => i.order.includes(e) ? i.order.indexOf(e) : 2 ** 53 - 1, c = t.filter((e) => e.description && u(e.uid) && (i.order.length === 0 || i.order.includes(e.uid))).sort((e, t) => a(e.uid) - a(t.uid)).map((e) => ({
			...e.description,
			...i.order.length ? {
				width: [
					25,
					50,
					75
				].includes(i.width[e.uid] ?? 100) ? i.width[e.uid] : 100,
				formPosition: i.above.has(e.uid) ? "above" : "below"
			} : {}
		})), f = new Map(c.map((e) => [e, t.find((t) => t.description?.handle === e.handle).uid])), p = Kt(Gt({
			state: Xt(c, null, n),
			instanceId: "preview"
		}), "create", null);
		p.collapsed = !1, ue(S`<section class="comments preview" data-comments-root data-comments-theme="light"><form class=${gt("create")} novalidate>${bt(p)}</form></section>`, o);
		let m = [];
		for (let e of c) {
			let t = d(f.get(e));
			(t === "inline" || t === "details") && m.push({
				handle: e.handle,
				label: e.label,
				type: e.type,
				placement: t,
				...Yt(e, r)
			});
		}
		let h = {
			uid: "preview",
			parentUid: null,
			depth: 0,
			state: "approved",
			content: {
				format: "plain",
				html: null,
				text: Jt,
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
			fields: m,
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
		};
		ue(S`<section class="comments preview" data-comments-root data-comments-theme="light"><ol class="comments__list">${qe(Gt({
			state: Xt(c, h, n),
			instanceId: "preview"
		}), h)}</ol></section>`, s);
		let g = [];
		for (let n of t) {
			let t = e.querySelector(`[data-comments-shown-row="${n.uid}"]`), r = u(n.uid);
			t && (t.hidden = !r), r && n.description && d(n.uid) === "none" && g.push(n.label), t?.querySelector("[data-comments-email-warning]")?.toggleAttribute("hidden", d(n.uid) === "none");
		}
		let _ = e.querySelector("[data-comments-card-hidden]");
		_ && (_.textContent = g.length ? Craft.t("comments", "Not shown: {fields}.", { fields: g.join(", ") }) : ""), e.querySelector("[data-comments-shown-empty]")?.toggleAttribute("hidden", t.some((e) => e.description && u(e.uid)));
	};
	window.jQuery?.(e).on("change", f), e.addEventListener("change", f);
	let p = e.closest("form")?.querySelector(".layoutdesigner"), m = 0;
	p && new MutationObserver(() => {
		cancelAnimationFrame(m), m = requestAnimationFrame(f);
	}).observe(p, {
		subtree: !0,
		childList: !0,
		attributes: !0
	}), f();
}
document.querySelectorAll("[data-comments-field-settings]").forEach(Qt);

//# sourceMappingURL=comment-fields.js.map