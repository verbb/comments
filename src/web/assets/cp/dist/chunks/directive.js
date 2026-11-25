// node_modules/lit-html/lit-html.js
var e = globalThis, t = (e) => e, n = e.trustedTypes, r = n ? n.createPolicy("lit-html", { createHTML: (e) => e }) : void 0, i = "$lit$", a = `lit$${Math.random().toFixed(9).slice(2)}$`, o = "?" + a, s = `<${o}>`, c = document, l = () => c.createComment(""), u = (e) => e === null || typeof e != "object" && typeof e != "function", d = Array.isArray, f = (e) => d(e) || typeof e?.[Symbol.iterator] == "function", p = "[ 	\n\f\r]", m = /<(?:(!--|\/[^a-zA-Z])|(\/?[a-zA-Z][^>\s]*)|(\/?$))/g, h = /-->/g, g = />/g, _ = RegExp(`>|${p}(?:([^\\s"'>=/]+)(${p}*=${p}*(?:[^ \t\n\f\r"'\`<>=]|("|')|))|$)`, "g"), v = /'/g, y = /"/g, b = /^(?:script|style|textarea|title)$/i, x = (e) => (t, ...n) => ({
	_$litType$: e,
	strings: t,
	values: n
}), S = x(1), C = x(2), w = Symbol.for("lit-noChange"), T = Symbol.for("lit-nothing"), E = /* @__PURE__ */ new WeakMap(), D = c.createTreeWalker(c, 129);
function O(e, t) {
	if (!d(e) || !e.hasOwnProperty("raw")) throw Error("invalid template strings array");
	return r === void 0 ? t : r.createHTML(t);
}
var k = (e, t) => {
	let n = e.length - 1, r = [], o, c = t === 2 ? "<svg>" : t === 3 ? "<math>" : "", l = m;
	for (let t = 0; t < n; t++) {
		let n = e[t], u, d, f = -1, p = 0;
		for (; p < n.length && (l.lastIndex = p, d = l.exec(n), d !== null);) p = l.lastIndex, l === m ? d[1] === "!--" ? l = h : d[1] === void 0 ? d[2] === void 0 ? d[3] !== void 0 && (l = _) : (b.test(d[2]) && (o = RegExp("</" + d[2], "g")), l = _) : l = g : l === _ ? d[0] === ">" ? (l = o ?? m, f = -1) : d[1] === void 0 ? f = -2 : (f = l.lastIndex - d[2].length, u = d[1], l = d[3] === void 0 ? _ : d[3] === "\"" ? y : v) : l === y || l === v ? l = _ : l === h || l === g ? l = m : (l = _, o = void 0);
		let x = l === _ && e[t + 1].startsWith("/>") ? " " : "";
		c += l === m ? n + s : f >= 0 ? (r.push(u), n.slice(0, f) + i + n.slice(f) + a + x) : n + a + (f === -2 ? t : x);
	}
	return [O(e, c + (e[n] || "<?>") + (t === 2 ? "</svg>" : t === 3 ? "</math>" : "")), r];
}, A = class e {
	constructor({ strings: t, _$litType$: r }, s) {
		let c;
		this.parts = [];
		let u = 0, d = 0, f = t.length - 1, p = this.parts, [m, h] = k(t, r);
		if (this.el = e.createElement(m, s), D.currentNode = this.el.content, r === 2 || r === 3) {
			let e = this.el.content.firstChild;
			e.replaceWith(...e.childNodes);
		}
		for (; (c = D.nextNode()) !== null && p.length < f;) {
			if (c.nodeType === 1) {
				if (c.hasAttributes()) for (let e of c.getAttributeNames()) if (e.endsWith(i)) {
					let t = h[d++], n = c.getAttribute(e).split(a), r = /([.?@])?(.*)/.exec(t);
					p.push({
						type: 1,
						index: u,
						name: r[2],
						strings: n,
						ctor: r[1] === "." ? F : r[1] === "?" ? I : r[1] === "@" ? L : P
					}), c.removeAttribute(e);
				} else e.startsWith(a) && (p.push({
					type: 6,
					index: u
				}), c.removeAttribute(e));
				if (b.test(c.tagName)) {
					let e = c.textContent.split(a), t = e.length - 1;
					if (t > 0) {
						c.textContent = n ? n.emptyScript : "";
						for (let n = 0; n < t; n++) c.append(e[n], l()), D.nextNode(), p.push({
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
function j(e, t, n = e, r) {
	if (t === w) return t;
	let i = r === void 0 ? n._$Cl : n._$Co?.[r], a = u(t) ? void 0 : t._$litDirective$;
	return i?.constructor !== a && (i?._$AO?.(!1), a === void 0 ? i = void 0 : (i = new a(e), i._$AT(e, n, r)), r === void 0 ? n._$Cl = i : (n._$Co ??= [])[r] = i), i !== void 0 && (t = j(e, i._$AS(e, t.values), i, r)), t;
}
var M = class {
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
		D.currentNode = r;
		let i = D.nextNode(), a = 0, o = 0, s = n[0];
		for (; s !== void 0;) {
			if (a === s.index) {
				let t;
				s.type === 2 ? t = new N(i, i.nextSibling, this, e) : s.type === 1 ? t = new s.ctor(i, s.name, s.strings, this, e) : s.type === 6 && (t = new R(i, this, e)), this._$AV.push(t), s = n[++o];
			}
			a !== s?.index && (i = D.nextNode(), a++);
		}
		return D.currentNode = c, r;
	}
	p(e) {
		let t = 0;
		for (let n of this._$AV) n !== void 0 && (n.strings === void 0 ? n._$AI(e[t]) : (n._$AI(e, n, t), t += n.strings.length - 2)), t++;
	}
}, N = class e {
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
		e = j(this, e, t), u(e) ? e === T || e == null || e === "" ? (this._$AH !== T && this._$AR(), this._$AH = T) : e !== this._$AH && e !== w && this._(e) : e._$litType$ === void 0 ? e.nodeType === void 0 ? f(e) ? this.k(e) : this._(e) : this.T(e) : this.$(e);
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
		let { values: t, _$litType$: n } = e, r = typeof n == "number" ? this._$AC(e) : (n.el === void 0 && (n.el = A.createElement(O(n.h, n.h[0]), this.options)), n);
		if (this._$AH?._$AD === r) this._$AH.p(t);
		else {
			let e = new M(r, this), n = e.u(this.options);
			e.p(t), this.T(n), this._$AH = e;
		}
	}
	_$AC(e) {
		let t = E.get(e.strings);
		return t === void 0 && E.set(e.strings, t = new A(e)), t;
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
}, P = class {
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
		if (i === void 0) e = j(this, e, t, 0), a = !u(e) || e !== this._$AH && e !== w, a && (this._$AH = e);
		else {
			let r = e, o, s;
			for (e = i[0], o = 0; o < i.length - 1; o++) s = j(this, r[n + o], t, o), s === w && (s = this._$AH[o]), a ||= !u(s) || s !== this._$AH[o], s === T ? e = T : e !== T && (e += (s ?? "") + i[o + 1]), this._$AH[o] = s;
		}
		a && !r && this.j(e);
	}
	j(e) {
		e === T ? this.element.removeAttribute(this.name) : this.element.setAttribute(this.name, e ?? "");
	}
}, F = class extends P {
	constructor() {
		super(...arguments), this.type = 3;
	}
	j(e) {
		this.element[this.name] = e === T ? void 0 : e;
	}
}, I = class extends P {
	constructor() {
		super(...arguments), this.type = 4;
	}
	j(e) {
		this.element.toggleAttribute(this.name, !!e && e !== T);
	}
}, L = class extends P {
	constructor(e, t, n, r, i) {
		super(e, t, n, r, i), this.type = 5;
	}
	_$AI(e, t = this) {
		if ((e = j(this, e, t, 0) ?? T) === w) return;
		let n = this._$AH, r = e === T && n !== T || e.capture !== n.capture || e.once !== n.once || e.passive !== n.passive, i = e !== T && (n === T || r);
		r && this.element.removeEventListener(this.name, this, n), i && this.element.addEventListener(this.name, this, e), this._$AH = e;
	}
	handleEvent(e) {
		typeof this._$AH == "function" ? this._$AH.call(this.options?.host ?? this.element, e) : this._$AH.handleEvent(e);
	}
}, R = class {
	constructor(e, t, n) {
		this.element = e, this.type = 6, this._$AN = void 0, this._$AM = t, this.options = n;
	}
	get _$AU() {
		return this._$AM._$AU;
	}
	_$AI(e) {
		j(this, e);
	}
}, z = {
	M: i,
	P: a,
	A: o,
	C: 1,
	L: k,
	R: M,
	D: f,
	V: j,
	I: N,
	H: P,
	N: I,
	U: L,
	B: F,
	F: R
}, B = e.litHtmlPolyfillSupport;
B?.(A, N), (e.litHtmlVersions ??= []).push("3.3.3");
var V = (e, t, n) => {
	let r = n?.renderBefore ?? t, i = r._$litPart$;
	if (i === void 0) {
		let e = n?.renderBefore ?? null;
		r._$litPart$ = i = new N(t.insertBefore(l(), e), e, void 0, n ?? {});
	}
	return i._$AI(e), i;
}, H = {
	ATTRIBUTE: 1,
	CHILD: 2,
	PROPERTY: 3,
	BOOLEAN_ATTRIBUTE: 4,
	EVENT: 5,
	ELEMENT: 6
}, U = (e) => (...t) => ({
	_$litDirective$: e,
	values: t
}), W = class {
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
};
export { V as a, z as c, T as i, C as l, W as n, w as o, H as r, S as s, U as t };

//# sourceMappingURL=directive.js.map