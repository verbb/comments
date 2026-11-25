import { n as e, o as t, r as n, t as r } from "./directive.js";
import { a as i, n as a, o, r as s, t as c } from "./directive-helpers.js";
// node_modules/lit-html/directives/repeat.js
var l = (e, t, n) => {
	let r = /* @__PURE__ */ new Map();
	for (let i = t; i <= n; i++) r.set(e[i], i);
	return r;
}, u = r(class extends e {
	constructor(e) {
		if (super(e), e.type !== n.CHILD) throw Error("repeat() can only be used in text expressions");
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
	update(e, [n, r, u]) {
		let d = c(e), { values: f, keys: p } = this.dt(n, r, u);
		if (!Array.isArray(d)) return this.ut = p, f;
		let m = this.ut ??= [], h = [], g, _, v = 0, y = d.length - 1, b = 0, x = f.length - 1;
		for (; v <= y && b <= x;) if (d[v] === null) v++;
		else if (d[y] === null) y--;
		else if (m[v] === p[b]) h[b] = i(d[v], f[b]), v++, b++;
		else if (m[y] === p[x]) h[x] = i(d[y], f[x]), y--, x--;
		else if (m[v] === p[x]) h[x] = i(d[v], f[x]), o(e, h[x + 1], d[v]), v++, x--;
		else if (m[y] === p[b]) h[b] = i(d[y], f[b]), o(e, d[v], d[y]), y--, b++;
		else if (g === void 0 && (g = l(p, b, x), _ = l(m, v, y)), g.has(m[v])) {
			if (g.has(m[y])) {
				let t = _.get(p[b]), n = t === void 0 ? null : d[t];
				if (n === null) {
					let t = o(e, d[v]);
					i(t, f[b]), h[b] = t;
				} else h[b] = i(n, f[b]), o(e, d[v], n), d[t] = null;
				b++;
			} else a(d[y]), y--;
		} else a(d[v]), v++;
		for (; b <= x;) {
			let t = o(e, h[x + 1]);
			i(t, f[b]), h[b++] = t;
		}
		for (; v <= y;) {
			let e = d[v++];
			e !== null && a(e);
		}
		return this.ut = p, s(e, h), t;
	}
});
export { u as t };

//# sourceMappingURL=repeat.js.map