# Tutorial 11 — Using a Fediverse Client App

Your Joomla site publishes content to the Fediverse and receives incoming
activities (follows, likes, boosts, replies) in its inbox. What it does **not**
provide — by design — is a social timeline or a reply composer.

This tutorial shows you how to connect a standard Mastodon-compatible client app
to your Joomla actor so you can:

- **Read your home timeline** — posts from accounts your actor follows
- **Reply to comments** — respond to people who replied to your articles
- **Follow and unfollow** remote accounts
- **Boost and like** — all from a familiar mobile or desktop app

Everything you do in the client app is published under your Joomla actor
identity (e.g. `@grace@yoursite.example`). Joomla continues to publish new
articles automatically as before — the two channels work side-by-side.

---

## How it works

**Joomla Fediverse** implements the
[ActivityPub Client-to-Server (C2S)](https://www.w3.org/TR/activitypub/#client-to-server-interactions)
profile and an OAuth 2.0 authorisation server. This is exactly the same API
that Mastodon exposes, so any Mastodon-compatible client can authenticate as
your Joomla actor.

> **Pro license required.** OAuth and C2S are Pro features.
> See [Pricing](../../site/index.html#pricing) to upgrade.

---

## Step 1 — Enable the OAuth endpoint

1. In the Joomla admin go to **Components → Joomla Fediverse → Options**.
2. On the **C2S / OAuth** tab make sure **Enable OAuth server** is set to **Yes**.
3. Click **Save & Close**.

---

## Step 2 — Choose a client app

Any app that works with Mastodon works with your Joomla actor. Popular choices:

| App | Platform | Notes |
|-----|----------|-------|
| [Ivory](https://tapbots.com/ivory/) | iOS / macOS | Polished, paid |
| [Mona](https://mastodon.social/@MonaApp) | iOS / macOS | Free tier available |
| [Elk](https://elk.zone) | Web browser | Open-source, free |
| [Tusky](https://tusky.app) | Android | Open-source, free |
| [Megalodon](https://sk22.github.io/megalodon/) | Android | Open-source, free |
| [Pinafore](https://pinafore.social) | Web browser | Lightweight, free |
| [Phanpy](https://phanpy.social) | Web browser | Clean UI, free |

---

## Step 3 — Connect the app to your Joomla actor

The exact steps vary slightly per app, but the flow is always the same:

1. Open the app and choose **Add account** or **Log in to another instance**.
2. When asked for your **instance / server URL**, enter your Joomla site's base
   URL — for example `https://yoursite.example`.
3. The app will open your site's OAuth authorisation page in a browser.
4. Log in with your **Joomla admin credentials** (the account linked to your actor).
5. Approve the requested permissions.
6. The app redirects back and you are logged in as
   `@yourname@yoursite.example`.

> **Tip:** If the app asks for a *username* rather than a server URL, enter
> your full Fediverse handle: `@grace@yoursite.example`.

---

## Step 4 — What you can do in the client

Once connected, the client app gives you everything a normal Mastodon user has:

| Action | What happens |
|--------|-------------|
| **Home timeline** | Posts from everyone your actor follows, in chronological order |
| **Notifications** | New followers, likes, boosts and replies to your articles |
| **Reply to a comment** | Composes a `Create{Note}` with `inReplyTo` pointing at the commenter's post — delivered back to their server |
| **Follow a remote account** | Sends a `Follow` activity; when accepted, their posts appear in your timeline |
| **Boost / Like** | Sends `Announce` / `Like` activities, visible on the remote post |
| **Write a standalone post** | Published as a `Note` from your actor — appears in your followers' timelines |

Your Joomla scheduled tasks continue to publish new articles independently —
you don't need to do anything in the client for that.

---

## Common Issues

| Symptom | Likely cause | Fix |
|---------|-------------|-----|
| App cannot find your instance | OAuth not enabled, or site not reachable over HTTPS | Enable OAuth in Options; ensure the site has a valid TLS certificate |
| Login page opens but shows an error | No actor linked to your Joomla user | Create an actor for your user first (see [Tutorial 03](03-actors.md)) |
| App connects but timeline is empty | Your actor doesn't follow anyone yet | Use the app to search for and follow remote accounts |
| Replies sent from the app don't appear on the remote post | Delivery queue stalled | Check the scheduled task status (see [Tutorial 09](09-tasks.md)) |
| App shows posts but not article publications | Articles are published as `Article` or `Note` type — some clients filter by type | Check the actor's object type setting (see [Tutorial 03](03-actors.md)) |

---

## Future enhancements

A native timeline and reply composer **inside the Joomla admin** is planned
as a future Pro feature. When available, it will let you read and respond to
Fediverse activity without leaving the Joomla interface.

---

[🏠 Tutorial Home](index.md) | [← Fediverse Shortcodes](10-shortcodes.md)
