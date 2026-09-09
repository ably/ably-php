<?php
/**
 * Release pre-flight for ably/pubsub-server.
 *
 * One implementation, three callers:
 *   - .github/workflows/release.yml   php scripts/release-preflight.php --version 2.0.0
 *   - .github/workflows/check.yml     php scripts/release-preflight.php --dry-run
 *   - a maintainer, locally           php scripts/release-preflight.php --version 2.0.0 --skip-remote
 *
 * It runs every check it can and reports *all* failures, not the first one, so a
 * botched release bump takes one round trip to fix rather than four. It writes
 * nothing and pushes nothing: the release workflow only starts publishing after
 * this exits 0.
 *
 * Deliberately dependency-free plain PHP (no Composer autoload, no vendor/), so it
 * runs before `composer install` and against a tree whose autoloader is broken.
 *
 * Usage:
 *   --version X.Y.Z[-suffix]  the version being released; compared against every
 *                             version site. Required unless --dry-run.
 *   --dry-run                 no authoritative version: only assert that the version
 *                             sites agree with each other. Remote checks are skipped.
 *   --skip-remote             skip checks that need network access (origin tags, the
 *                             mirror's tags). Local git tags are still checked.
 *   --mirror <owner/repo>     distribution mirror to check for an existing tag.
 *                             Default: ably/ably-pubsub-php-dist.
 *
 * Exit codes: 0 all checks passed; 1 one or more checks failed; 2 bad invocation.
 */

const EXPECTED_PACKAGE_NAME = 'ably/pubsub-server';
const DEFAULT_MIRROR = 'ably/ably-pubsub-php-dist';

// The version at and above which a Composer-valid tag in *this* repository is a
// release-blocking emergency: the legacy `ably/ably-php` Packagist package indexes
// every Composer-valid tag here, so a plain `2.0.0` tag would make it serve the new
// package's code as its own latest version. See plan.md step 1 ("Why a mirror").
const FIRST_NEW_MAJOR = 2;

const VERSION_PATTERN = '/^\d+\.\d+\.\d+(-[0-9A-Za-z.]+)?$/';
const COMPOSER_VALID_TAG_PATTERN = '/^v?(\d+)\.(\d+)\.(\d+)/';

$root = dirname(__DIR__);

$options = parseArgv($argv);
$version = $options['version'];
$dryRun = $options['dry-run'];
$skipRemote = $options['skip-remote'] || $dryRun;
$mirror = $options['mirror'] ?? DEFAULT_MIRROR;

if ($dryRun && $version !== null) {
    fail_invocation('--dry-run and --version are mutually exclusive: a dry run has no authoritative version.');
}
if (!$dryRun && $version === null) {
    fail_invocation('--version is required unless --dry-run is given.');
}

$errors = [];
$notices = [];

echo $dryRun
    ? "Release pre-flight (dry run: version sites must agree with each other)\n\n"
    : "Release pre-flight for {$version}\n\n";

// ---------------------------------------------------------------------------
// 1. The version input is a shape Composer will accept as a version.
// ---------------------------------------------------------------------------
if ($version !== null && !preg_match(VERSION_PATTERN, $version)) {
    $errors[] = "version input '{$version}' is not X.Y.Z or X.Y.Z-suffix";
}

// ---------------------------------------------------------------------------
// 2. Defaults::LIB_VERSION — the only version site in the source tree.
// ---------------------------------------------------------------------------
$libVersion = readLibVersion($root . '/src/Defaults.php', $errors);

// ---------------------------------------------------------------------------
// 3. The top `## [x.y.z]` heading in CHANGELOG.md.
// ---------------------------------------------------------------------------
$changelogVersion = readChangelogVersion($root . '/CHANGELOG.md', $errors);

// ---------------------------------------------------------------------------
// 4. Every version site agrees. With --version that is the authority; in a dry run
//    there is none, so the sites only have to agree with each other.
// ---------------------------------------------------------------------------
if ($version !== null) {
    if ($libVersion !== null && $libVersion !== $version) {
        $errors[] = "Defaults::LIB_VERSION is '{$libVersion}', expected '{$version}'";
    }
    if ($changelogVersion !== null && $changelogVersion !== $version) {
        $errors[] = "the top CHANGELOG.md heading is '{$changelogVersion}', expected '{$version}'";
    }
} elseif ($libVersion !== null && $changelogVersion !== null && $libVersion !== $changelogVersion) {
    $errors[] = "Defaults::LIB_VERSION is '{$libVersion}' but the top CHANGELOG.md heading is "
        . "'{$changelogVersion}' — bump both in the same PR";
} elseif ($libVersion !== null) {
    $notices[] = "version sites agree at {$libVersion}";
}

// ---------------------------------------------------------------------------
// 5. composer.json declares the new package. This is the check that makes the copy
//    of release.yml on `main` inert while the split still lives on integration/v2:
//    dispatched against the legacy layout it refuses before anything is pushed.
// ---------------------------------------------------------------------------
$composerName = readComposerName($root . '/composer.json', $errors);
if ($composerName !== null && $composerName !== EXPECTED_PACKAGE_NAME) {
    $errors[] = "composer.json name is '{$composerName}', expected '" . EXPECTED_PACKAGE_NAME . "' — "
        . 'this ref does not carry the ably/pubsub-server package, so there is nothing to release from it';
}

// ---------------------------------------------------------------------------
// 6. composer validate --strict. Packagist rejects nothing, so an invalid manifest
//    ships silently; --strict is also what CI runs on every PR.
// ---------------------------------------------------------------------------
validateComposerManifest($root, $errors, $notices);

// ---------------------------------------------------------------------------
// 7. No Composer-valid 2.x-or-later tag exists in this repository.
//    This is the invariant the whole mirror arrangement exists to protect.
// ---------------------------------------------------------------------------
$localTags = gitTags($root, $errors);
$remoteTags = $skipRemote ? [] : (gitLsRemoteTags($root, 'origin', $errors) ?? []);
$repoTags = array_values(array_unique(array_merge($localTags, array_keys($remoteTags))));

$offendingTags = array_values(array_filter($repoTags, static function (string $tag): bool {
    if (!preg_match(COMPOSER_VALID_TAG_PATTERN, $tag, $m)) {
        return false;
    }
    return (int) $m[1] >= FIRST_NEW_MAJOR;
}));
sort($offendingTags);

if ($offendingTags !== []) {
    $errors[] = sprintf(
        "this repository carries Composer-valid tag(s) at or above %d.0.0: %s\n"
        . "        These are indexed by the legacy ably/ably-php Packagist package, which is bound to\n"
        . "        this repository and ignores every tag's composer.json name. Any such tag makes\n"
        . "        `composer require ably/ably-php` resolve to ably/pubsub-server code. Delete the tag\n"
        . "        locally and on origin, then delete the version on Packagist. Releases here are\n"
        . "        tagged pubsub-server/<version>; the plain tag exists only on the mirror.",
        FIRST_NEW_MAJOR,
        implode(', ', $offendingTags)
    );
} else {
    $notices[] = sprintf(
        'no Composer-valid tag at or above %d.0.0 in this repository (%d tag(s) inspected%s)',
        FIRST_NEW_MAJOR,
        count($repoTags),
        $skipRemote ? ', local only' : ''
    );
}

// ---------------------------------------------------------------------------
// 8. The release's own tags do not already exist — unless they point at HEAD, which
//    is a re-run of the same commit and is expected to complete a partial release.
// ---------------------------------------------------------------------------
if ($version !== null) {
    $head = gitHead($root, $errors);
    $namespacedTag = 'pubsub-server/' . $version;

    $localTarget = in_array($namespacedTag, $localTags, true)
        ? gitRevParse($root, $namespacedTag . '^{commit}', $errors)
        : null;
    $remoteTarget = $remoteTags[$namespacedTag] ?? null;

    checkExistingTag(
        "tag {$namespacedTag} in this repository",
        $localTarget ?? $remoteTarget,
        $head,
        $errors,
        $notices
    );

    if (!$skipRemote) {
        $mirrorTags = gitLsRemoteTags($root, 'https://github.com/' . $mirror . '.git', $errors, $mirror);
        if ($mirrorTags !== null) {
            checkExistingTag(
                "tag {$version} on the mirror {$mirror}",
                $mirrorTags[$version] ?? null,
                $head,
                $errors,
                $notices
            );
        }
    } else {
        $notices[] = "skipped the mirror tag check for {$mirror} (--skip-remote)";
    }
}

// ---------------------------------------------------------------------------
// Report.
// ---------------------------------------------------------------------------
foreach ($notices as $notice) {
    echo "  ok   {$notice}\n";
}
if ($errors === []) {
    echo "\nPre-flight OK.\n";
    exit(0);
}

echo "\n" . count($errors) . " pre-flight failure(s):\n";
foreach ($errors as $error) {
    echo "  FAIL {$error}\n";
}
echo "\nNothing has been pushed. Fix all of the above and dispatch again.\n";
exit(1);

// ---------------------------------------------------------------------------
// Helpers.
// ---------------------------------------------------------------------------

/**
 * @param list<string> $argv
 * @return array{version: ?string, dry-run: bool, skip-remote: bool, mirror: ?string}
 */
function parseArgv(array $argv): array
{
    $parsed = ['version' => null, 'dry-run' => false, 'skip-remote' => false, 'mirror' => null];

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if ($arg === '--dry-run' || $arg === '--skip-remote') {
            $parsed[substr($arg, 2)] = true;
            continue;
        }
        if ($arg === '--version' || $arg === '--mirror') {
            $key = substr($arg, 2);
            if (!isset($argv[$i + 1])) {
                fail_invocation("{$arg} needs a value");
            }
            $parsed[$key] = $argv[++$i];
            continue;
        }
        if (preg_match('/^--(version|mirror)=(.*)$/', $arg, $m)) {
            $parsed[$m[1]] = $m[2];
            continue;
        }
        fail_invocation("unknown argument '{$arg}'");
    }

    return $parsed;
}

function fail_invocation(string $message): void
{
    fwrite(STDERR, "release-preflight: {$message}\n");
    fwrite(STDERR, "Usage: php scripts/release-preflight.php (--version X.Y.Z | --dry-run) [--skip-remote] [--mirror owner/repo]\n");
    exit(2);
}

/** @param list<string> $errors */
function readLibVersion(string $path, array &$errors): ?string
{
    $source = @file_get_contents($path);
    if ($source === false) {
        $errors[] = "cannot read {$path}";
        return null;
    }
    if (!preg_match("/const\s+LIB_VERSION\s*=\s*'([^']+)'/", $source, $m)) {
        $errors[] = "cannot find Defaults::LIB_VERSION in {$path}";
        return null;
    }
    return $m[1];
}

/** @param list<string> $errors */
function readChangelogVersion(string $path, array &$errors): ?string
{
    $source = @file_get_contents($path);
    if ($source === false) {
        $errors[] = "cannot read {$path}";
        return null;
    }
    // The first `## [x.y.z]` heading in the file is the version being released.
    if (!preg_match('/^##\s*\[([^\]]+)\]/m', $source, $m)) {
        $errors[] = "cannot find a '## [x.y.z]' heading in {$path}";
        return null;
    }
    return $m[1];
}

/** @param list<string> $errors */
function readComposerName(string $path, array &$errors): ?string
{
    $source = @file_get_contents($path);
    if ($source === false) {
        $errors[] = "cannot read {$path}";
        return null;
    }
    $manifest = json_decode($source, true);
    if (!is_array($manifest)) {
        $errors[] = "{$path} is not valid JSON: " . json_last_error_msg();
        return null;
    }
    if (!isset($manifest['name']) || !is_string($manifest['name'])) {
        $errors[] = "{$path} has no 'name'";
        return null;
    }
    return $manifest['name'];
}

/**
 * @param list<string> $errors
 * @param list<string> $notices
 */
function validateComposerManifest(string $root, array &$errors, array &$notices): void
{
    if (run('command -v composer', $root, $output) !== 0) {
        $notices[] = 'skipped composer validate --strict (composer is not on PATH)';
        return;
    }
    if (run('composer validate --strict --no-interaction 2>&1', $root, $output) !== 0) {
        $errors[] = "composer validate --strict failed:\n        " . str_replace("\n", "\n        ", trim($output));
        return;
    }
    $notices[] = 'composer validate --strict passed';
}

/**
 * @param list<string> $errors
 * @return list<string>
 */
function gitTags(string $root, array &$errors): array
{
    if (run('git tag --list 2>&1', $root, $output) !== 0) {
        $errors[] = "cannot list local git tags: " . trim($output);
        return [];
    }
    return array_values(array_filter(array_map('trim', explode("\n", $output)), static fn ($t) => $t !== ''));
}

/**
 * @param list<string> $errors
 * @return ?array<string, string> tag name => commit SHA, or null if the remote is unreachable
 */
function gitLsRemoteTags(string $root, string $remote, array &$errors, ?string $label = null): ?array
{
    $label ??= $remote;
    if (run('git ls-remote --tags ' . escapeshellarg($remote) . ' 2>&1', $root, $output) !== 0) {
        $errors[] = "cannot list tags on {$label}:\n        "
            . str_replace("\n", "\n        ", trim($output))
            . "\n        (pass --skip-remote to run the local checks only; the mirror repository must exist"
            . "\n        before the first release — see plan.md steps 9 and 15c)";
        return null;
    }

    $tags = [];
    foreach (explode("\n", $output) as $line) {
        if (!preg_match('#^([0-9a-f]{40})\s+refs/tags/(.+?)(\^\{\})?$#', trim($line), $m)) {
            continue;
        }
        // A peeled `^{}` line carries the commit an annotated tag points at; prefer it.
        if (isset($m[3]) && $m[3] !== '') {
            $tags[$m[2]] = $m[1];
        } elseif (!isset($tags[$m[2]])) {
            $tags[$m[2]] = $m[1];
        }
    }
    return $tags;
}

/** @param list<string> $errors */
function gitHead(string $root, array &$errors): ?string
{
    return gitRevParse($root, 'HEAD', $errors);
}

/** @param list<string> $errors */
function gitRevParse(string $root, string $rev, array &$errors): ?string
{
    if (run('git rev-parse ' . escapeshellarg($rev) . ' 2>&1', $root, $output) !== 0) {
        $errors[] = "cannot resolve {$rev}: " . trim($output);
        return null;
    }
    return trim($output);
}

/**
 * An existing tag is fatal unless it already points at the commit being released, in
 * which case this is a re-run and the workflow's steps will skip their own artifacts.
 *
 * @param list<string> $errors
 * @param list<string> $notices
 */
function checkExistingTag(
    string $what,
    ?string $existingTarget,
    ?string $head,
    array &$errors,
    array &$notices
): void {
    if ($existingTarget === null) {
        $notices[] = "{$what} does not exist yet";
        return;
    }
    if ($head !== null && $existingTarget === $head) {
        $notices[] = "{$what} already exists at HEAD — treating this as a re-run of a partial release";
        return;
    }
    $errors[] = "{$what} already exists and points at {$existingTarget}, not the dispatched commit "
        . ($head ?? 'HEAD') . ' — releasing a version twice from two different commits is never right';
}

/** Runs $command in $root, capturing combined output into $output. */
function run(string $command, string $root, ?string &$output): int
{
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open(['/bin/sh', '-c', $command], $descriptors, $pipes, $root);
    if (!is_resource($process)) {
        $output = 'failed to start /bin/sh';
        return 127;
    }
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return proc_close($process);
}
