#!/usr/bin/env node
import { readFileSync } from 'node:fs';

const reportPath = process.argv[2] ?? 'test-results/results.json';
const shardName = process.env.SHARD_NAME ?? 'e2e';

const stripAnsi = (text) => text.replace(/\u001b\[[0-9;]*m/g, '');

const truncate = (text, max = 2000) =>
  text.length > max ? `${text.slice(0, max)}\n… (truncated)` : text;

let report;
try {
  report = JSON.parse(readFileSync(reportPath, 'utf8'));
} catch {
  console.log(`## 🎭 E2E · ${shardName}\n`);
  console.log('> ⚠️ No test report was produced — the stack likely failed to start before Playwright ran. Check the "Run E2E suite" step logs.');
  process.exit(0);
}

const failures = [];
const flaky = [];

const walk = (suite, path) => {
  for (const spec of suite.specs ?? []) {
    const title = [...path, spec.title].filter(Boolean).join(' › ');
    const statuses = (spec.tests ?? []).map((t) => t.status);
    if (statuses.includes('unexpected')) {
      const results = (spec.tests ?? []).flatMap((t) => t.results ?? []);
      const error = results.map((r) => r.error?.message).filter(Boolean).at(-1) ?? '';
      failures.push({ title, file: spec.file, line: spec.line, error: truncate(stripAnsi(error)) });
    } else if (statuses.includes('flaky')) {
      flaky.push({ title, file: spec.file });
    }
  }
  for (const child of suite.suites ?? []) {
    walk(child, [...path, child.title]);
  }
};

for (const suite of report.suites ?? []) {
  walk(suite, []);
}

const stats = report.stats ?? {};
const passed = stats.expected ?? 0;
const failed = stats.unexpected ?? 0;
const flakyCount = stats.flaky ?? 0;
const skipped = stats.skipped ?? 0;
const minutes = Math.floor((stats.duration ?? 0) / 60000);
const seconds = Math.round(((stats.duration ?? 0) % 60000) / 1000);

const globalErrors = (report.errors ?? []).map((e) => truncate(stripAnsi(e.message ?? '')));
const ranNothing = passed + failed + flakyCount + skipped === 0;
const broken = globalErrors.length > 0 || ranNothing;

const icon = failed > 0 || broken ? '❌' : flakyCount > 0 ? '⚠️' : '✅';

console.log(`## ${icon} E2E · ${shardName}\n`);
console.log('| ✅ Passed | ❌ Failed | ⚠️ Flaky | ⏭️ Skipped | ⏱️ Duration |');
console.log('|---:|---:|---:|---:|---:|');
console.log(`| ${passed} | ${failed} | ${flakyCount} | ${skipped} | ${minutes}m ${seconds}s |`);

if (ranNothing) {
  console.log('\n> ⚠️ The run ended before any test finished — check the "Run E2E suite" step logs.');
}

if (globalErrors.length > 0) {
  console.log('\n### Errors outside tests\n');
  for (const message of globalErrors) {
    console.log('```');
    console.log(message);
    console.log('```');
  }
}

if (failures.length > 0) {
  console.log('\n### Failures\n');
  for (const failure of failures) {
    console.log(`<details><summary>❌ <code>${failure.file}:${failure.line}</code> — ${failure.title}</summary>\n`);
    console.log('```');
    console.log(failure.error || 'No error message captured.');
    console.log('```');
    console.log('</details>\n');
  }
  console.log('> Full traces, videos and screenshots are in the `playwright-report` artifact below.');
}

if (flaky.length > 0) {
  console.log('\n### Flaky (passed on retry)\n');
  for (const test of flaky) {
    console.log(`- ⚠️ \`${test.file}\` — ${test.title}`);
  }
}
