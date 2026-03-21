# Joomla Fediverse

**ActivityPub & Fediverse Integration for Joomla 6+**

Connect your Joomla site to the Fediverse. Publish articles to Mastodon, Pleroma, Pixelfed and every
ActivityPub platform. Build a following, receive reactions, and engage with the open social web —
right from your Joomla admin.

👉 **[joomlafedi.lemonsqueezy.com](https://joomlafedi.lemonsqueezy.com)** — website, docs & pricing

---

## Features

- **Full ActivityPub federation** — signed HTTP requests, shared inbox, follower delivery, Like and Announce
- **WebFinger & NodeInfo** — discoverable as `@user@yoursite.example` on any Fediverse app
- **Article publishing** — new and updated articles push automatically to followers
- **Actor & object types** — Person or Service actors; Note, Article, Image or Video objects
- **Reactions module** — Like, Boost and reply counts on article pages; optional Like/Boost action buttons
- **Shortcode plugin** — `{{fediverse:follow}}`, `{{fediverse:handle}}`, `{{fediverse:reactions}}`
- **Scheduled tasks** — delivery worker, inbox worker, key refresh, data cleanup
- **Domain policies** *(Pro)* — allow or block entire Fediverse instances
- **OAuth 2.0 / C2S** *(Pro)* — use Mastodon-compatible apps (Elk, Ivory, Tusky…) as your Joomla actor
- **HTTP Signature key rotation** *(Pro)* — scheduled key rotation with automatic re-announce

## Requirements

- Joomla 6.0 or later
- PHP 8.3 or later
- `ext-openssl`

## Installation

1. Download the latest `pkg_fediverse-x.y.z.zip` from the [Releases](../../releases) page.
2. In your Joomla admin go to **System → Install → Extensions** and upload the zip.
3. Go to **Components → Joomla Fediverse** to complete setup.

See the **[full tutorial](https://nibra.github.io/joomla-fediverse/docs/)** for step-by-step instructions.

## Contributing

Issues and feature requests are welcome — please use the [issue tracker](../../issues).

Pull requests are accepted. Please open an issue first for larger changes.
The extension source is in `src/`. A `composer.json` is provided; run `composer install` to set up autoloading.
There are no build tools in this repository — the test suite and Docker environment are maintained separately.

## License

GNU General Public License version 2 or later.
See [LICENSE.txt](LICENSE.txt).

Pro features require a commercial license — see [pricing](https://joomlafedi.lemonsqueezy.com/#pricing).
