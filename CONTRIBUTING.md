## Contributing

1. Fork it
2. Create your feature branch (`git checkout -b my-new-feature`)
3. Commit your changes (`git commit -am 'Add some feature'`)
4. Ensure you have added suitable tests and the test suite is passing (run `vendor/bin/phpunit`)
4. Push to the branch (`git push origin my-new-feature`)
5. Create a new Pull Request

## Release process

Releases are automated. There is no manual tagging step, and there must not be —
see [Why a plain `2.x.y` tag must never be pushed here](#why-a-plain-2xy-tag-must-never-be-pushed-here).

1. Open a pull request that bumps `Defaults::LIB_VERSION` in `src/Defaults.php` and
   adds the release's section to `CHANGELOG.md`. Those two are the only version sites;
   `composer.json` carries no `version` field, because Packagist derives versions from
   git tags.
2. Merge it.
3. Run the **Release** workflow (`.github/workflows/release.yml`) from the Actions tab
   against the merged commit, with the version as its only input — `2.0.0`, or
   `2.0.0-rc1` for a pre-release. Composer treats a pre-release suffix as non-stable, so
   `composer require ably/pubsub-server` will not resolve it unless the consumer lowers
   `minimum-stability`.

You can run the same checks the workflow will run, before dispatching it:

```sh
php scripts/release-preflight.php --version 2.0.0
php scripts/release-preflight.php --version 2.0.0 --skip-remote   # no network
```

### What the pre-flight checks

Nothing is pushed anywhere until all of these pass, and it reports every failure
rather than the first one:

- the version input is `X.Y.Z` or `X.Y.Z-suffix`;
- it equals `Defaults::LIB_VERSION`;
- it equals the version in the top `## [x.y.z]` heading of `CHANGELOG.md`;
- `composer.json`'s `name` is `ably/pubsub-server` — this is what makes the copy of
  this workflow on `main` inert while the split still lives on `integration/v2`, and
  what stops anyone releasing the legacy layout through it;
- `composer validate --strict` passes;
- the tag `pubsub-server/<version>` does not already exist here, and the plain
  `<version>` tag does not already exist on the mirror — unless the existing tag
  already points at the commit being released, which is a re-run;
- **no Composer-valid tag at or above `2.0.0` exists in this repository.**

The same script runs in dry-run mode as the `release-dry-run` job on every pull
request, where there is no authoritative version, so the version sites only have to
agree with each other. The 2.x-tag guard runs there too.

### What the release workflow does

1. Runs the pre-flight, then `HttpTest`, `PackagingTest`, `DefaultsTest` and
   `ClientOptionsTest`. The full 8.1–8.5 × JSON/msgpack sandbox matrix already ran on
   the pull request.
2. Pushes the release commit to the distribution mirror's `main` and creates the
   annotated tag `<version>` there. This is the only place a plain version tag of the
   new package ever exists.
3. Creates the annotated tag `pubsub-server/<version>` here and a GitHub release on it,
   with that version's `CHANGELOG.md` section as the body, marked as a pre-release when
   the version carries a suffix.
4. Polls `https://repo.packagist.org/p2/ably/pubsub-server.json` until the version
   appears, and fails loudly if it does not. That is the automated proof that the
   mirror's Packagist webhook fired.

Each of those steps checks for its own artifact first and skips it if it is already
there, so a run that failed part-way through is completed by dispatching the same
version again at the same commit.

### The distribution mirror

`ably/pubsub-server` is published from **`ably/ably-pubsub-php-dist`**, a read-only
distribution mirror. Development, issues and pull requests happen here and only here;
the mirror never accepts either, and its README says so. It is a build artifact that
happens to be a git repository.

Operating it needs three things that live outside this repository:

- **The mirror repository itself.** It receives the full `main` history, so its commit
  SHAs match this repository's.
- **A `MIRROR_PUSH_TOKEN` secret here.** A fine-grained personal access token or a
  GitHub App installation token with `contents: write` on the mirror and nothing else.
  A workflow's own `GITHUB_TOKEN` cannot reach another repository at all, so without
  this secret the release fails at the mirror push with an explicit error.
- **The Packagist webhook on the mirror** (`https://packagist.org/api/github?username=ably`,
  the same hook this repository has), plus `ably/pubsub-server` registered on
  packagist.org against the mirror's URL. Without the webhook the release succeeds and
  the Packagist poll fails, which is the intended failure mode: loud and recoverable.

### Why a plain `2.x.y` tag must never be pushed here

A Packagist package indexes **every** Composer-valid tag of the repository it is bound
to, whatever that tag's `composer.json` says. Composer's `VcsRepository::preProcess`
deliberately overwrites each version's `name` with the default branch's name, so that
a renamed package's old tags stay installable, and Packagist's updater then stamps its
own package name over every version. There is no name-based filtering of versions
anywhere in that path.

The legacy `ably/ably-php` package is still bound to this repository, and has to stay
bound to it: its download count puts it behind Packagist's popular-package protection,
so its URL cannot be moved to another repository without Packagist support, and a
repository-ID change would freeze it outright.

So a plain `2.0.0` tag pushed here would become `ably/ably-php` version `2.0.0` — a
package with a different name, a different namespace and a different PHP floor, served
as the latest release to every consumer with `ably/ably-php: *` or `>=1.1`. They would
upgrade into an install that does not load. Deleting the tag afterwards does not undo
it; the Packagist version has to be pulled by hand by a maintainer.

Hence the split: plain version tags of the new package exist only on the mirror, and
this repository only ever carries `pubsub-server/<version>` tags, which Composer skips
as invalid version names. The pre-flight fails on any Composer-valid tag at or above
`2.0.0` found here, and the `release-dry-run` job checks the same thing on every pull
request — but a tag is cheap to create and those checks only run afterwards, so the
rule itself is the real protection.

**1.x maintenance releases are the exception, and they stay plain.** Releases from
`maintenance/1.x` are tagged `1.1.13`, `1.1.14` and so on, exactly as they always have
been, because `ably/ably-php` is *supposed* to index them. That package is bound to
this repository for the whole of its one-year maintenance window, and plain 1.x tags
here are how it gets its releases. Only tags at or above `2.0.0` are forbidden.