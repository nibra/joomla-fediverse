# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0-beta] – 2026-03-21

### Added

- ActivityPub server-to-server (S2S) federation: Follow, Accept, Create, Update, Delete, Like, and Announce activities.
- WebFinger discovery (`@user@site.example`) and NodeInfo 2.0 endpoint for standard Fediverse compatibility.
- Local actor management — Joomla users are automatically mapped to Fediverse identities on first publish.
- Article publishing as ActivityPub activities via the `plg_content_fediverse` content plugin (automatic, no manual steps required).
- Configurable actor type (`Person` / `Service`) and object type (`Note` / `Article` / `Image` / `Video`) per actor.
- `mod_fediverse_reactions` module — displays Like, Boost, and Reply counts on articles with optional Like/Boost action buttons.
- `plg_content_fediverse_tags` content plugin — shortcode tags for embedding Fediverse data in article text:
  - `{{fediverse:follow}}` — renders a Follow button for the article's actor.
  - `{{fediverse:handle}}` — renders the actor's Fediverse handle.
  - `{{fediverse:reactions}}` — renders inline Like/Boost/Reply counters.
- Domain allow/block federation policies (Pro).
- OAuth 2.0 / Client-to-Server (C2S) ActivityPub support (Pro) — use Mastodon-compatible apps with your Joomla actor.
- HTTP Signature key management with scheduled automatic rotation (Pro).
- Joomla scheduler tasks:
  - **Fediverse Delivery Worker** — outbound activity queue with retry and exponential back-off.
  - **Fediverse Inbox Worker** — processes queued inbound activities.
  - **Fediverse Key Rotation** — rotates signing key pairs past the configured age threshold.
  - **Fediverse Cleanup** — purges historical records according to retention settings.
- Free / Pro licensing system with JWT license keys (offline verification, no call-home required).
- Audit log for federation events.
- Media attachment support (Pro).
- Admin UI: Dashboard, Actors, Inbox, Deliveries, and Domain Policies views under `Components` → `Fediverse`.

### Notes

> **Closed beta release.** Not all features are production-hardened. Breaking changes may still occur before the stable 1.0 release.

- Requires **Joomla 6.0+** and **PHP 8.2+**.
- Actor limits by tier: **Free** — 1 actor; **Personal** — 3; **Developer** — 10 (across up to 5 sites); **Agency** — unlimited.
- Beta license keys are issued separately by the developer and are not available through the standard licensing flow.

[0.1.0-beta]: https://github.com/nibra/pkg_fediverse/releases/tag/v0.1.0-beta
