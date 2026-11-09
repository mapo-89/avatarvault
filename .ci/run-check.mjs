import { readFileSync } from 'node:fs'
import { resolve, join } from 'node:path'
import { spawnSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'

export function isFork(environment, event) {
    const owner = event?.pull_request?.head?.repo?.full_name
    return environment.GITHUB_EVENT_NAME === 'pull_request'
        && typeof owner === 'string' && owner !== environment.GITHUB_REPOSITORY
}

export function forkCommand(configuration, snapshot, name) {
    if (snapshot.ref !== configuration.source?.ref) throw new Error('Stale fork CI snapshot')
    if (!Object.hasOwn(snapshot.checks || {}, name)) throw new Error('Unknown fork CI check')
    const command = configuration.overrides?.[name] ?? snapshot.checks?.[name]
    if (typeof command !== 'string' || !command.trim()) throw new Error('Unknown fork CI check')
    return command
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
    try {
        const project = process.env.GITHUB_WORKSPACE || process.cwd()
        const name = process.argv[2]
        const shared = process.env.CI_STANDARDS_DIR
        let result
        if (shared) {
            result = spawnSync(process.execPath, [join(shared, 'bin/run-check.mjs'), name], { stdio: 'inherit' })
        } else {
            const event = JSON.parse(readFileSync(process.env.GITHUB_EVENT_PATH, 'utf8'))
            if (!isFork(process.env, event)) throw new Error('Shared CI standards are unavailable')
            const configuration = JSON.parse(readFileSync(resolve(project, '.ci/standards.json'), 'utf8'))
            const snapshot = JSON.parse(readFileSync(resolve(project, '.ci/fork-checks.json'), 'utf8'))
            result = spawnSync('bash', ['-euo', 'pipefail', '-c', forkCommand(configuration, snapshot, name)], { stdio: 'inherit' })
        }
        if (result.error || result.signal) process.exitCode = 1
        else process.exitCode = result.status ?? 1
    } catch {
        console.error('CI check could not run. Verify the pinned standards and generated fork snapshot.')
        process.exitCode = 1
    }
}
