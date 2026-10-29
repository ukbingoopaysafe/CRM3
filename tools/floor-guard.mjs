#!/usr/bin/env node
// floor-guard.mjs — diff-scoped enforcement of the CONSTRAINTS.md floor.
// Adapts the reference from the constraint-driven-development skill to PHP.
// Usage: node tools/floor-guard.mjs [--base <ref>]   (default base: origin/main)
//
// Exit codes: 0 clean, 1 at least one floor violation, 2 the guard could not run.
import { execFileSync } from 'node:child_process';
import { readFileSync, statSync } from 'node:fs';

const baseArg = process.argv.indexOf('--base');
const base = baseArg > -1 ? process.argv[baseArg + 1] : 'origin/main';

const git = (args) => {
  try {
    // A diff that carries rebuilt assets is far past the 1 MB default, and a
    // truncated read would look like a clean one.
    return execFileSync('git', args, {
      encoding: 'utf8',
      maxBuffer: 64 * 1024 * 1024,
      stdio: ['ignore', 'pipe', 'pipe'],
    });
  } catch {
    return null;
  }
};
const bail = (message) => {
  console.error('floor-guard: ' + message);
  process.exit(2);
};

const top = git(['rev-parse', '--show-toplevel'])?.trim();
if (!top) bail('not inside a git work tree');
process.chdir(top);

const mergeBase = git(['merge-base', base, 'HEAD'])?.trim();
if (!mergeBase) bail('no merge base against ' + base + ' (fetch it, or pass --base <ref>)');

const tracked = git(['diff', '--unified=0', mergeBase, '--']);
if (tracked === null) bail('could not diff against ' + mergeBase);

const untrackedList = git(['ls-files', '--others', '--exclude-standard']);
if (untrackedList === null) bail('could not list untracked files');

const added = [];
const removed = [];
const deleted = [];
const pathOf = (value) => value.replace(/^[ab]\//, '').trim();

let file = '';
let oldFile = '';
let inHeader = false;

for (const line of tracked.split('\n')) {
  if (line.startsWith('diff ')) inHeader = true;
  else if (line.startsWith('@@')) inHeader = false;
  else if (inHeader) {
    if (line.startsWith('--- ')) oldFile = pathOf(line.slice(4));
    else if (line.startsWith('+++ ')) {
      const newFile = pathOf(line.slice(4));
      file = newFile === '/dev/null' ? oldFile : newFile;
      if (newFile === '/dev/null') deleted.push(file);
    }
  } else if (line.startsWith('+')) added.push({ file, text: line.slice(1) });
  else if (line.startsWith('-')) removed.push({ file, text: line.slice(1) });
}

// New files are not in `git diff`, so every line counts as added.
for (const path of untrackedList.split('\n').filter(Boolean)) {
  if (/\.(png|jpe?g|gif|webp|ico|woff2?|ttf|otf|pdf|zip|xlsx?)$/i.test(path)) continue;
  try {
    if (statSync(path).size > 512 * 1024) continue;
    for (const line of readFileSync(path, 'utf8').split('\n')) added.push({ file: path, text: line });
  } catch {
    console.warn('floor-guard: could not read ' + path);
  }
}

const findings = [];
const flag = (rule, path, text) => findings.push({ rule, file: path, text: text.trim().slice(0, 120) });

// The guard's own source spells out the patterns it looks for, so it names
// every one of them. That is the checker, not a suppression.
const IGNORED = new Set(['tools/floor-guard.mjs']);

const isTest = (path) => path.startsWith('tests/');
// Published Filament assets are third-party build output, not hand-written
// code, and their minified bodies trip every pattern below.
const GENERATED = /^public\/(js|css|build|fonts)\//;
const isCode = (path) => /\.(php|js|mjs|ts)$/.test(path) && !IGNORED.has(path) && !GENERATED.test(path);
const isConstraints = (path) => /CONSTRAINTS\.md$/.test(path);

// 1. Silenced checker.
const SUPPRESSIONS = /@phpstan-ignore|@psalm-suppress|phpcs:ignore|phpcs:disable|@codeCoverageIgnore|nosemgrep|gitleaks:allow/;
// 2. A test made easier.
const SKIPS = /markTestSkipped|markTestIncomplete|->skip\(/;
// 4. Unfinished work.
const STUBS = /\bdd\(|\bvar_dump\(|\bprint_r\(|\bTODO\b|\bFIXME\b|not implemented|catch\s*\([^)]*\)\s*\{\s*\}/;

for (const { file: path, text } of added) {
  if (isCode(path)) {
    if (SUPPRESSIONS.test(text)) flag('silenced-checker', path, text);
    if (STUBS.test(text)) flag('unfinished-work', path, text);
  }
  if (isTest(path) && SKIPS.test(text)) flag('test-made-easier', path, text);
  if (isConstraints(path) && /^\| *(W|E)\d+ *\|/.test(text)) flag('new-exception', path, text);
}

// 2b. A test file deleted, or an assertion dropped from a test that stayed.
for (const path of deleted) if (isTest(path)) flag('test-deleted', path, 'file deleted');
for (const { file: path, text } of removed) {
  if (isTest(path) && !deleted.includes(path) && /->assert|assertSame\(|assertTrue\(|assertNull\(|assertCount\(/.test(text)) {
    flag('assertion-removed', path, text);
  }
}

// 1b/2c. A rule in CONSTRAINTS.md weakened or removed. A rule is a floor bullet
// or a table row, keyed by the bullet's text before its first colon or the row's
// first cell. A minimum (>=, at least, must not fall) is loosened by going down,
// a maximum (<=, at most, under, must not grow) by going up. A number whose
// direction cannot be read is reported whenever it changes.
const ruleKey = (text) => {
  const trimmed = text.trim();
  if (trimmed.startsWith('|')) return trimmed.split('|').map((cell) => cell.trim()).filter(Boolean)[0] ?? '';
  if (/^[-*] /.test(trimmed)) return trimmed.slice(2).split(':')[0].trim();
  return null;
};
const isException = (text) => /^\| *(W|E)\d+ *\|/.test(text.trim());
const MIN_BEFORE = /(>=|>|≥|at least|minimum|\bmin\b|no less than|not fall|not drop)\s*$/;
const MAX_BEFORE = /(<=|<|≤|at most|maximum|\bmax\b|no more than|under|below|not grow|not exceed)\s*$/;
const MIN_AFTER = /^\s*\S*\s*(or more|or higher|must not fall|must not drop)/;
const MAX_AFTER = /^\s*\S*\s*(or less|or lower|must not grow|must not exceed)/;
const thresholds = (text) => {
  const out = [];
  const pattern = /\d+(?:\.\d+)?/g;
  let match;
  while ((match = pattern.exec(text))) {
    const before = text.slice(Math.max(0, match.index - 24), match.index).toLowerCase();
    const after = text.slice(match.index + match[0].length, match.index + match[0].length + 40).toLowerCase();
    const direction = MIN_BEFORE.test(before) || MIN_AFTER.test(after) ? 'min'
      : MAX_BEFORE.test(before) || MAX_AFTER.test(after) ? 'max' : null;
    out.push({ value: Number(match[0]), direction });
  }
  return out;
};

const removedRules = removed.filter((line) => isConstraints(line.file) && ruleKey(line.text) !== null);
const addedRules = added.filter((line) => isConstraints(line.file) && ruleKey(line.text) !== null);

for (const rule of removedRules) {
  const replacement = addedRules.find((line) => ruleKey(line.text) === ruleKey(rule.text));
  if (!replacement) {
    if (!isException(rule.text)) flag('rule-removed', rule.file, rule.text);
    continue;
  }

  const before = thresholds(rule.text);
  const after = thresholds(replacement.text);
  let verdict = null;

  for (const direction of ['min', 'max', null]) {
    const was = before.filter((entry) => entry.direction === direction);
    const now = after.filter((entry) => entry.direction === direction);
    was.forEach((entry, index) => {
      if (verdict) return;
      const current = now[index];
      if (!current) verdict = 'threshold-removed';
      else if (current.value === entry.value) return;
      else if (direction === 'min' ? current.value < entry.value
        : direction === 'max' ? current.value > entry.value : true) {
        verdict = direction ? 'threshold-loosened' : 'threshold-changed';
      }
    });
  }

  if (verdict) flag(verdict, rule.file, rule.text + '  ->  ' + replacement.text);
}

if (findings.length === 0) {
  console.log('floor-guard: clean (' + added.length + ' added lines checked)');
  process.exit(0);
}

console.error('floor-guard: ' + findings.length + ' floor violation(s):');
for (const item of findings) console.error(`  [${item.rule}] ${item.file}: ${item.text}`);
console.error('\nEach is a move that lowers the bar. Fix the code, or route it through a tracked exception in CONSTRAINTS.md.');
process.exit(1);
