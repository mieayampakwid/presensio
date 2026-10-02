export type Translations = Record<string, unknown>;

export type Replacements = Record<string, string | number>;

/**
 * Resolve a dotted key (e.g. `users.form.roles`) against the shared bag and
 * fill `:name` placeholders. An unknown key is returned as-is so gaps are
 * visible in the UI.
 */
export function translate(
    translations: Translations,
    key: string,
    replacements: Replacements = {},
): string {
    const line = key
        .split('.')
        .reduce<unknown>(
            (node, segment) =>
                node !== null && typeof node === 'object'
                    ? (node as Translations)[segment]
                    : undefined,
            translations,
        );

    if (typeof line !== 'string') {
        return key;
    }

    return Object.entries(replacements).reduce(
        (text, [name, value]) => text.replaceAll(`:${name}`, String(value)),
        line,
    );
}
