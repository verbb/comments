import { describe, expect, it } from 'vitest';
import { characterCount } from '../src/form-controller';

/**
 * The plain editor's limit counts characters as the server's `mb_strlen()` does, so text the server
 * accepts is never refused as too long in the browser.
 */
describe('characterCount()', () => {
    it('counts an emoji as one character, as the server does', () => {
        expect(characterCount('😀😀😀😀😀😀')).toBe(6);
        expect(characterCount('Hi 👋🏽')).toBe(5);
    });

    it('counts plain and accented text as before', () => {
        expect(characterCount('')).toBe(0);
        expect(characterCount('Café crème')).toBe(10);
        expect(characterCount('line one\nline two')).toBe(17);
    });
});
