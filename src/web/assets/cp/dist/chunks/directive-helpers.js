import { c as e } from "./directive.js";
// node_modules/lit-html/directive-helpers.js
var { I: t } = e, n = (e) => e, r = (e) => e.strings === void 0, i = () => document.createComment(""), a = (e, r, a) => {
	let o = e._$AA.parentNode, s = r === void 0 ? e._$AB : r._$AA;
	if (a === void 0) a = new t(o.insertBefore(i(), s), o.insertBefore(i(), s), e, e.options);
	else {
		let t = a._$AB.nextSibling, r = a._$AM, i = r !== e;
		if (i) {
			let t;
			a._$AQ?.(e), a._$AM = e, a._$AP !== void 0 && (t = e._$AU) !== r._$AU && a._$AP(t);
		}
		if (t !== s || i) {
			let e = a._$AA;
			for (; e !== t;) {
				let t = n(e).nextSibling;
				n(o).insertBefore(e, s), e = t;
			}
		}
	}
	return a;
}, o = (e, t, n = e) => (e._$AI(t, n), e), s = {}, c = (e, t = s) => e._$AH = t, l = (e) => e._$AH, u = (e) => {
	e._$AR(), e._$AA.remove();
};
export { o as a, r as i, u as n, a as o, c as r, l as t };

//# sourceMappingURL=directive-helpers.js.map