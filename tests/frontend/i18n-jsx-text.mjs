// Lint guard for i18n plan item 7: fails on NEW hardcoded JSX text literals
// (text nodes containing letters) in `resources/js/pages`. Existing debt is
// ratcheted in `i18n-jsx-text-baseline.txt`; entries are keyed by file + text
// (not line) so unrelated edits do not retrigger them. After translating a
// slice or intentionally accepting new strings, regenerate the baseline with:
//   sail npm run test:i18n -- --update
import ts from 'typescript';
import { readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { join, relative } from 'node:path';

const pagesDir = 'resources/js/pages';
const baselinePath = join(import.meta.dirname, 'i18n-jsx-text-baseline.txt');

function listTsxFiles(dir, out = []) {
    for (const entry of readdirSync(dir)) {
        const path = join(dir, entry);
        if (statSync(path).isDirectory()) listTsxFiles(path, out);
        else if (entry.endsWith('.tsx')) out.push(path);
    }
    return out;
}

function scan() {
    const found = new Map();
    for (const path of listTsxFiles(pagesDir)) {
        const source = readFileSync(path, 'utf8');
        const sourceFile = ts.createSourceFile(
            path,
            source,
            ts.ScriptTarget.Latest,
            true,
            ts.ScriptKind.TSX,
        );
        const visit = (node) => {
            if (ts.isJsxText(node)) {
                const raw = node.getText(sourceFile).trim();
                if (raw && /\p{L}/u.test(raw)) {
                    const text = raw.replace(/\s+/g, ' ');
                    const key = `${relative('.', path)}\t${text}`;
                    if (!found.has(key)) {
                        const { line } =
                            sourceFile.getLineAndCharacterOfPosition(
                                node.getStart(sourceFile),
                            );
                        found.set(key, {
                            file: relative('.', path),
                            line: line + 1,
                            text,
                        });
                    }
                }
            }
            ts.forEachChild(node, visit);
        };
        visit(sourceFile);
    }
    return found;
}

const violations = scan();
const currentKeys = [...violations.keys()];

if (process.argv.includes('--update')) {
    const sorted = currentKeys.sort((a, b) => (a < b ? -1 : a > b ? 1 : 0));
    writeFileSync(baselinePath, `${sorted.join('\n')}\n`);
    console.log(
        `i18n-jsx-text: baseline updated with ${currentKeys.length} literal(s).`,
    );
    process.exit(0);
}

const baseline = new Set(
    readFileSync(baselinePath, 'utf8')
        .split('\n')
        .filter((line) => line !== ''),
);
const newViolations = currentKeys.filter((key) => !baseline.has(key));
const staleCount = [...baseline].filter((key) => !violations.has(key)).length;

if (newViolations.length > 0) {
    console.error(
        `i18n-jsx-text: ${newViolations.length} new hardcoded JSX text literal(s) in ${pagesDir}.\n` +
            'Translate them with t() / lang files, or after review regenerate the baseline:\n' +
            '  sail npm run test:i18n -- --update\n',
    );
    for (const key of newViolations) {
        const { file, line, text } = violations.get(key);
        console.error(`  ${file}:${line}  ${JSON.stringify(text)}`);
    }
    process.exit(1);
}

console.log(
    `i18n-jsx-text: no new hardcoded JSX text literals in ${pagesDir} ` +
        `(${currentKeys.length} known).` +
        (staleCount > 0
            ? ` ${staleCount} baseline entr${staleCount === 1 ? 'y is' : 'ies are'} stale; prune with: sail npm run test:i18n -- --update`
            : ''),
);
