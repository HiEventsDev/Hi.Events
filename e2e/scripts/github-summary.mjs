#!/usr/bin/env node
import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';

const MODES = {
  'self-hosted': 'Self-hosted',
  saas: 'SaaS',
};

const reportsDir = process.argv[2] ?? 'reports';
const title = process.env.SUMMARY_TITLE ?? 'E2E';
const runUrl = process.env.RUN_URL ?? '';

const stripAnsi = (text) => text.replace(/\u001b\[[0-9;]*m/g, '');

const truncate = (text, max = 2000) =>
  text.length > max ? `${text.slice(0, max)}\n… (truncated)` : text;

const formatDuration = (ms) => `${Math.floor(ms / 60000)}m ${Math.round((ms % 60000) / 1000)}s`;

const modeLabel = (mode) => MODES[mode] ?? mode;

const readShards = () => {
  if (!existsSync(reportsDir)) {
    return [];
  }

  return readdirSync(reportsDir, { withFileTypes: true })
    .filter((entry) => entry.isDirectory())
    .map((entry) => {
      const meta = JSON.parse(readFileSync(join(reportsDir, entry.name, 'meta.json'), 'utf8'));
      const reportPath = join(reportsDir, entry.name, 'results.json');
      let report = null;
      try {
        report = JSON.parse(readFileSync(reportPath, 'utf8'));
      } catch {
        report = null;
      }
      return { ...meta, report };
    });
};

const collect = (shard) => {
  const failures = [];
  const flaky = [];
  const skipReasons = new Map();

  const walk = (suite, path) => {
    for (const spec of suite.specs ?? []) {
      const name = [...path, spec.title].filter(Boolean).join(' › ');
      for (const test of spec.tests ?? []) {
        if (test.status === 'unexpected') {
          const lastResult = (test.results ?? []).at(-1);
          const messages = (lastResult?.errors ?? []).map((error) => error.message).filter(Boolean);
          const error = (messages.length > 0 ? messages : [lastResult?.error?.message ?? '']).join('\n\n');
          failures.push({ name, file: spec.file, line: spec.line, error: truncate(stripAnsi(error)) });
        } else if (test.status === 'flaky') {
          flaky.push({ name, file: spec.file, line: spec.line });
        } else if (test.status === 'skipped') {
          const reason = (test.annotations ?? []).find((annotation) => annotation.type === 'skip')?.description || 'No reason given';
          skipReasons.set(reason, (skipReasons.get(reason) ?? 0) + 1);
        }
      }
    }
    for (const child of suite.suites ?? []) {
      walk(child, child.title && !child.title.endsWith('.ts') ? [...path, child.title] : path);
    }
  };

  for (const suite of shard.report?.suites ?? []) {
    walk(suite, []);
  }

  const stats = shard.report?.stats ?? {};
  const counts = {
    passed: stats.expected ?? 0,
    failed: stats.unexpected ?? 0,
    flaky: stats.flaky ?? 0,
    skipped: stats.skipped ?? 0,
  };
  const globalErrors = (shard.report?.errors ?? []).map((error) => truncate(stripAnsi(error.message ?? '')));
  const ranNothing = Object.values(counts).every((count) => count === 0);

  return {
    ...shard,
    counts,
    failures,
    flaky,
    skipReasons,
    globalErrors,
    duration: stats.duration ?? 0,
    broken: shard.report === null || globalErrors.length > 0 || ranNothing,
    jobFailed: shard.outcome !== 'success',
  };
};

const shards = readShards().map(collect);

if (shards.length === 0) {
  console.log(`## ⚠️ ${title}\n`);
  console.log('> No test reports were uploaded. The test jobs probably failed before Playwright ran. Open the run to check their logs.');
  process.exit(0);
}

const modes = [...new Set(shards.map((shard) => shard.mode))].sort((a, b) =>
  Object.keys(MODES).indexOf(a) - Object.keys(MODES).indexOf(b));

const byMode = modes.map((mode) => {
  const modeShards = shards.filter((shard) => shard.mode === mode);
  const sum = (key) => modeShards.reduce((total, shard) => total + shard.counts[key], 0);
  const counts = { passed: sum('passed'), failed: sum('failed'), flaky: sum('flaky'), skipped: sum('skipped') };
  const broken = modeShards.some((shard) => shard.broken || (shard.jobFailed && shard.counts.failed === 0));
  const status = counts.failed > 0 || broken ? 'fail' : counts.flaky > 0 ? 'flaky' : 'pass';
  return {
    mode,
    shards: modeShards,
    counts,
    broken,
    status,
    duration: Math.max(...modeShards.map((shard) => shard.duration)),
  };
});

const STATUS = {
  pass: { icon: '✅', text: 'Passed' },
  flaky: { icon: '⚠️', text: 'Passed (some flaky)' },
  fail: { icon: '❌', text: 'Failed' },
};

const overall = byMode.some((mode) => mode.status === 'fail')
  ? 'fail'
  : byMode.some((mode) => mode.status === 'flaky') ? 'flaky' : 'pass';

const totalFailed = byMode.reduce((total, mode) => total + mode.counts.failed, 0);
const totalFlaky = byMode.reduce((total, mode) => total + mode.counts.flaky, 0);

const plural = (count, noun) => `${count} ${noun}${count === 1 ? '' : 's'}`;
const brokenShards = shards.filter((shard) => shard.broken || (shard.jobFailed && shard.counts.failed === 0));
const failParts = [
  totalFailed > 0 ? `${plural(totalFailed, 'test')} failed` : null,
  brokenShards.length > 0 ? `${plural(brokenShards.length, 'job')} did not complete` : null,
].filter(Boolean);

const headline = {
  pass: 'Everything passed.',
  flaky: `Everything passed, but ${plural(totalFlaky, 'test')} only passed on retry.`,
  fail: `${failParts.join(' and ')}.`.replace(/^./, (first) => first.toUpperCase()),
}[overall];

console.log(`## ${STATUS[overall].icon} ${title}: ${headline}\n`);

console.log('| Mode | Result | ✅ Passed | ❌ Failed | ⚠️ Flaky | ⏭️ Skipped | ⏱️ Duration |');
console.log('|---|---|---:|---:|---:|---:|---:|');
for (const mode of byMode) {
  const { icon, text } = STATUS[mode.status];
  const result = mode.broken && mode.counts.failed === 0 ? 'Did not complete' : text;
  console.log(`| **${modeLabel(mode.mode)}** | ${icon} ${result} | ${mode.counts.passed} | ${mode.counts.failed} | ${mode.counts.flaky} | ${mode.counts.skipped} | ${formatDuration(mode.duration)} |`);
}

if (brokenShards.length > 0) {
  console.log('\n### 🚧 Jobs that did not complete\n');
  for (const shard of brokenShards) {
    const reason = shard.report === null
      ? 'no test report was produced (the stack probably failed to start)'
      : shard.globalErrors.length > 0 ? 'errors outside tests' : 'the job failed before any test finished';
    console.log(`- **${modeLabel(shard.mode)} · ${shard.shard}**: ${reason}. Check the job's "Run E2E suite" step.`);
    for (const message of shard.globalErrors) {
      console.log('\n```');
      console.log(message);
      console.log('```');
    }
  }
}

const failures = shards.flatMap((shard) => shard.failures.map((failure) => ({ ...failure, mode: shard.mode })));
if (failures.length > 0) {
  console.log(`\n### ❌ Failed tests (${failures.length})\n`);
  for (const failure of failures) {
    console.log(`<details><summary><b>${modeLabel(failure.mode)}</b> · <code>${failure.file}:${failure.line}</code> · ${failure.name}</summary>\n`);
    console.log('```');
    console.log(failure.error || 'No error message captured.');
    console.log('```');
    console.log('</details>\n');
  }
  console.log('> Traces, videos and screenshots for each failure are in the `playwright-report-*` artifacts of this run.');
}

const flaky = shards.flatMap((shard) => shard.flaky.map((test) => ({ ...test, mode: shard.mode })));
if (flaky.length > 0) {
  console.log(`\n### ⚠️ Flaky tests: failed first, then passed on retry (${flaky.length})\n`);
  for (const test of flaky) {
    console.log(`- **${modeLabel(test.mode)}** · \`${test.file}:${test.line}\` · ${test.name}`);
  }
}

const skipped = byMode.filter((mode) => mode.counts.skipped > 0);
if (skipped.length > 0) {
  console.log('\n<details><summary>⏭️ Why tests were skipped</summary>\n');
  for (const mode of skipped) {
    const reasons = new Map();
    for (const shard of mode.shards) {
      for (const [reason, count] of shard.skipReasons) {
        reasons.set(reason, (reasons.get(reason) ?? 0) + count);
      }
    }
    console.log(`**${modeLabel(mode.mode)}**\n`);
    for (const [reason, count] of [...reasons].sort((a, b) => b[1] - a[1])) {
      console.log(`- ${count} × ${reason}`);
    }
    console.log('');
  }
  console.log('</details>');
}

if (runUrl) {
  console.log(`\n[Open the run](${runUrl})`);
}
