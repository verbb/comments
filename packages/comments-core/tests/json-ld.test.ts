import { describe, expect, it } from 'vitest';
import { jsonLdScript } from '../src/index';

describe('jsonLdScript()', () => {
    it('keeps a comment’s </script> from ending the script, and reads back as the same data', () => {
        const data = { '@type': 'Article', comment: [{ '@type': 'Comment', text: 'Nice</script><script>alert(1)</script> & <b>bold</b> \u2028\u2029' }] };
        const text = jsonLdScript(data);

        expect(text).not.toMatch(/[<>&\u2028\u2029]/);
        expect(text).toContain('\\u003c/script\\u003e');
        expect(text).toContain('\\u0026');
        expect(text).toContain('\\u2028\\u2029');
        expect(JSON.parse(text)).toEqual(data);
    });

    it('stays safe in a document parsed as HTML', () => {
        const html = `<script type="application/ld+json">${jsonLdScript({ text: '</script><img src=x onerror=alert(1)>' })}</script>`;

        expect(html.match(/<\/script>/gi)).toHaveLength(1);
        expect(html).not.toContain('<img');
    });

    it('writes null for what JSON can’t hold', () => {
        expect(jsonLdScript(undefined)).toBe('null');
    });
});
