#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

max_bytes=14336
failures=0

fail() {
  echo "::error::$*"
  failures=$((failures + 1))
}

frontmatter_value() {
  awk -v key="$2" 'NR == 1 && $0 != "---" { exit } NR > 1 && $0 == "---" { exit } index($0, key ": ") == 1 { print substr($0, length(key) + 3); exit }' "$1"
}

size=$(wc -c <AGENTS.md | tr -d ' ')
if [ "$size" -gt "$max_bytes" ]; then
  fail "AGENTS.md is $size bytes (limit $max_bytes). Move feature-specific rules into .claude/rules/."
fi

if [ "$(head -n 1 CLAUDE.md)" != "@AGENTS.md" ]; then
  fail "CLAUDE.md must start with @AGENTS.md."
fi

while IFS= read -r rule; do
  if [ "$(head -n 1 "$rule")" != "---" ] || ! awk 'NR > 1 && $0 == "---" { exit } $0 == "paths:" { found = 1 } END { exit !found }' "$rule"; then
    fail "$rule needs paths: frontmatter."
  fi
  if ! grep -Fq "\`$rule\`" AGENTS.md; then
    fail "$rule is not listed in the AGENTS.md rules table."
  fi
done < <(find .claude/rules -name '*.md' | sort)

while IFS= read -r listed; do
  [ -f "$listed" ] || fail "AGENTS.md lists $listed, which does not exist."
done < <(grep -o '`\.claude/rules/[^`]*\.md`' AGENTS.md | tr -d '`')

for skill in .agents/skills/*/SKILL.md; do
  name=$(basename "$(dirname "$skill")")
  stub=".claude/skills/$name/SKILL.md"
  if [ ! -f "$stub" ]; then
    fail "$stub is missing; Claude Code only discovers skills under .claude/skills."
    continue
  fi
  for key in name description; do
    if [ "$(frontmatter_value "$skill" "$key")" != "$(frontmatter_value "$stub" "$key")" ]; then
      fail "$stub $key differs from $skill."
    fi
  done
done

if [ "$failures" -gt 0 ]; then
  exit 1
fi

echo "Agent docs OK (AGENTS.md: $size/$max_bytes bytes)."
