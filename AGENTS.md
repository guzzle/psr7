# Agent Guidelines

This file captures the repository conventions for `guzzlehttp/psr7`. The
organization-wide
[contributing guidelines](https://github.com/guzzle/guzzle/blob/8.0/docs/overview.md#contributing)
are required reading and apply here too.

## Branches

- `3.0` is the unreleased next major version and `2.12` is the current stable
  branch. Bug fixes target the oldest maintained branch they apply to and are
  merged up; changes are never cherry-picked down.
- Backwards compatibility on released branches is paramount. Breaking changes
  are only acceptable on an unreleased major version branch.

## Commits and pull requests

- Write a single concise commit subject line in sentence case and the imperative
  mood, with no body and no `Co-Authored-By` or other attribution trailers.
- Keep pull request titles to at most 64 characters.
- Write pull request descriptions as concise prose in full sentences that
  explain why the change is being made and what it does. Prefer a single
  paragraph, and never use bullet points, headers, test plans, or checklists.
  Backticks are fine in titles and descriptions.
- Changes in behavior need tests, a `CHANGELOG.md` entry in the unreleased
  section of the target branch, and an `UPGRADING.md` note when the behavior
  differs between major versions.

## Code and tooling

- The minimum supported PHP version is 7.4, and all code must remain compatible
  with it.
- PHPStan and PHP-CS-Fixer must be run against PHP 7.4.
- Keep new tests consistent with the existing tests in style and structure, and
  only add tests that meaningfully cover behavior.

## Documentation

- Wrap markdown prose and PHPDoc text to 80 columns using greedy wrapping. Never
  split a markdown link or an inline code span across a line break; a line that
  cannot be broken may exceed the limit. Avoid em dashes.
- The pages under `docs/` document the public API and behavior. Keep PHPDoc and
  `docs/` in sync when either changes.
- `CHANGELOG.md` follows the Keep a Changelog format, with one concise bullet
  per change.

## Security

Never disclose security issues publicly. Follow the
[security policy](https://github.com/guzzle/psr7/security/policy) instead.
