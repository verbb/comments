/**
 * Structured data as text to put inside `<script type="application/ld+json">`, such as from
 * `fetchStructuredData()` in a server component. `JSON.stringify()` alone isn't safe there: a
 * comment containing `</script>` would end the script and the rest of its text would run as HTML.
 * Here `<`, `>` and `&` are written as `\u003c`, `\u003e` and `\u0026`, which JSON reads back as
 * the same characters, as are U+2028 and U+2029 for any reader that treats the text as script.
 */
export declare function jsonLdScript(data: unknown): string;
