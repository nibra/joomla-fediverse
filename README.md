# Joomla Fediverse

**ActivityPub publishing for Joomla 6+**

Joomla Fediverse turns a Joomla site into a Fediverse publishing server on its own domain.
It is designed for publishers, blogs, magazines, associations, municipalities, and institutions
that want Fediverse reach without running a second platform.

This project is **not** a Mastodon replacement. It keeps publishing, moderation, editorial
controls, and operations inside Joomla.

## What It Helps You Do

- Publish Joomla content into the Fediverse from your normal editorial workflow
- Expose site and author identities on your own domain
- Receive inbound replies and reactions with moderation inside Joomla
- Control excerpts, images, hashtags, and links for federated output
- Monitor delivery, diagnostics, and operational status from Joomla-native screens
- Add governance and automation features for larger teams where the paid tiers allow it

## Plans

### Free

- 1 site actor
- 1 author profile
- discovery and publishing basics
- basic media support
- basic diagnostics

### Personal

Everything in Free, plus:

- up to 5 author profiles
- advanced excerpt, image, hashtag, and link controls
- moderated inbound replies
- moderation queue

### Pro

Everything in Personal, plus:

- organization and channel accounts
- domain policies and governance controls
- configuration transfer
- automation webhooks

## Requirements

- Joomla 6.0 or later
- PHP 8.3 or later
- `ext-openssl`

## Installation

1. Download the latest `pkg_fediverse-x.y.z.zip` from the [Releases](../../releases) page.
2. In Joomla administrator, go to **System -> Install -> Extensions**.
3. Upload the package zip.
4. Open **Components -> Fediverse** and complete the setup steps.

For the full guided setup, use the user manual:

- [User Manual](../docs/user-manual/index.md)
- [Install Joomla Fediverse](../docs/user-manual/setup/install-joomla-fediverse.md)
- [Configure Fediverse Options](../docs/user-manual/setup/configure-fediverse-options.md)
- [Create a Local Actor](../docs/user-manual/actors/create-local-actor.md)
- [Publish an Article](../docs/user-manual/publishing/publish-article.md)

## Documentation

- [Project website and pricing](https://joomlafedi.lemonsqueezy.com)
- [User manual](../docs/user-manual/index.md)
- [Developer manual](../docs/developer-manual/index.md)

## For Developers

The extension source code lives under `src/`.

If you want to work on the project itself, start with:

- [Developer manual](../docs/developer-manual/index.md)
- [Running tests](../docs/developer-manual/07-running-tests.md)
- [Building distribution packages](../docs/developer-manual/08-building-distribution-packages.md)

## Contributing

Issues and feature requests are welcome. For larger changes, open an issue first so the change can
be checked against the product direction.

## License

GNU General Public License version 2 or later.
See [LICENSE.txt](../LICENSE.txt).

Paid features require a commercial license.
