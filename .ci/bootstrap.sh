#!/usr/bin/env bash
set -euo pipefail

: "$RUNNER_TEMP" "$GITHUB_ENV" "$GITHUB_WORKSPACE" "$CI_STANDARDS_TOKEN"
cd "$GITHUB_WORKSPACE"
if [ "$(printenv CI_STANDARDS_FORK || true)" = true ]; then
    echo 'Fork PR: run full CI using the pinned generated check snapshot.'
    exit 0
fi
test -n "$CI_STANDARDS_TOKEN" || { echo 'Configure the repository-only CI_STANDARDS_TOKEN secret.'; exit 1; }
configuration=".ci/standards.json"
source_values="$(node - "$configuration" <<'NODE'
const fs = require('node:fs')
const config = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'))
if (config.schema_version !== 1
    || config.source?.url !== 'https://git.postler.de/mapo-89/ci-standards.git'
    || !/^[a-f0-9]{40}$/.test(config.source?.ref ?? '')) {
    throw new Error('Configure the approved standards repository and an immutable full commit SHA.')
}
process.stdout.write(config.source.url + '\n' + config.source.ref)
NODE
)"
repository="$(printf '%s\n' "$source_values" | sed -n '1p')"
revision="$(printf '%s\n' "$source_values" | sed -n '2p')"
standards_directory="$(mktemp -d "$RUNNER_TEMP/ci-standards.XXXXXX")"
askpass="$(mktemp "$RUNNER_TEMP/ci-standards-askpass.XXXXXX")"
trap 'rm -f "$askpass"' EXIT

cat > "$askpass" <<'ASKPASS'
#!/bin/sh
case "$1" in
    *Username*) printf 'token' ;;
    *) printf '%s' "$CI_STANDARDS_TOKEN" ;;
esac
ASKPASS
chmod 700 "$askpass"

git -c core.hooksPath=/dev/null init --quiet "$standards_directory"
git -C "$standards_directory" remote add origin "$repository"
GIT_ASKPASS="$askpass" GIT_TERMINAL_PROMPT=0 \
    git -c credential.helper= -c core.hooksPath=/dev/null -C "$standards_directory" \
    fetch --quiet --depth=1 origin "$revision"
git -c core.hooksPath=/dev/null -C "$standards_directory" checkout --quiet --detach FETCH_HEAD

test "$(git -C "$standards_directory" rev-parse HEAD)" = "$revision"
if [ -f "$GITHUB_WORKSPACE/.ci/fork-checks.json" ]; then
    node "$standards_directory/bin/fork-snapshot.mjs" verify "$GITHUB_WORKSPACE"
fi
printf 'CI_STANDARDS_DIR=%s\n' "$standards_directory" >> "$GITHUB_ENV"
printf 'Loaded CI standards %s\n' "$revision"
