# Fediverse Shortcodes

---
[🏠 Tutorial Home](index.md) | [← Scheduled Tasks](09-tasks.md) | [Using a Fediverse Client App →](11-client-apps.md)

The **Content - Fediverse Tags** plugin (`plg_content_fediverse_tags`) lets you embed live Fediverse data anywhere in a Joomla article using simple shortcodes. This is useful for building "Follow me on the Fediverse" sections, displaying engagement counts inline, or showing your actor handle prominently in a post.

---

## Prerequisites

- Joomla Fediverse installed and both required plugins enabled (see [Tutorial 01 — Installation](01-installation.md))
- At least one local actor created and published (see [Tutorial 03 — Creating Actors](03-actors.md))
- The **Content - Fediverse Tags** plugin enabled (see Step 1 below)

---

## Step 1: Enable the Plugin

1. Navigate to **Extensions → Plugins**.
2. Search for **Fediverse Tags** or filter by **Type = content**.
3. Click the plugin name to open it, then set **Status** to **Enabled**. Click **Save & Close**.

The plugin is disabled by default — it must be explicitly enabled before any tags are processed.

---

## Step 2: Available Shortcodes

Insert any of the following tags anywhere in an article body:

| Tag | Output | Description |
|-----|--------|-------------|
| `{{fediverse:handle}}` | `@grace@yoursite.example` | Renders the article author's Fediverse handle as inline text |
| `{{fediverse:follow}}` | Follow widget | Displays the handle with a **Copy handle** button and a **View profile** link |
| `{{fediverse:reactions}}` | ❤ 3 · 🔁 1 · 💬 2 | Inline engagement counts — likes, boosts, and replies |

The tag is resolved based on the article's **author** (the Joomla user set in the article's **Author** field). If the author does not have a Fediverse actor, the tag is left in place unchanged.

---

## Step 3: Insert a Handle Tag

Open any article in the Joomla editor. In the article body, type:

```
Follow me on the Fediverse: {{fediverse:handle}}
```

Save the article and view it on the front end. The tag is replaced with the author's handle:

> Follow me on the Fediverse: @grace@yoursite.example

---

## Step 4: Insert a Follow Widget

The `{{fediverse:follow}}` tag renders a small interactive widget:

```
{{fediverse:follow}}
```

On the front end this becomes a block containing:
- The actor's handle (e.g. `@grace@yoursite.example`)
- A **Copy handle** button — copies the handle to the clipboard
- A **View profile** link — opens the actor's public profile page on your Joomla site

Visitors can copy the handle and paste it into the search bar of their Fediverse server to find and follow the actor.

---

## Step 5: Insert Inline Reaction Counts

Display live engagement numbers inline within the article text:

```
This post has received {{fediverse:reactions}} so far.
```

On the front end:

> This post has received ❤ 3 · 🔁 1 · 💬 2 so far.

The counts update each time the Fediverse scheduler processes the inbox queue. They reflect the same data shown by the Reactions module (see [Tutorial 06 — Reactions Module](06-reactions.md)).

---

## Common Issues

| Symptom | Likely cause | Fix |
|---------|-------------|-----|
| Tag appears unchanged on the front end | Content - Fediverse Tags plugin disabled | Enable `plg_content_fediverse_tags` at **Extensions → Plugins** |
| Tag replaced with empty output | Article author has no Fediverse actor | Create an actor for the author (see [Tutorial 03](03-actors.md)) |
| `{{fediverse:reactions}}` shows `❤ 0 · 🔁 0 · 💬 0` | No inbound activities yet, or scheduler not running | Ensure the scheduler is configured and has run at least once (see [Tutorial 09](09-tasks.md)) |
| Follow widget **Copy** button does nothing | Site not served over HTTPS | The Clipboard API requires a secure context; serve the site over HTTPS |

---
[🏠 Tutorial Home](index.md) | [← Scheduled Tasks](09-tasks.md) | [Using a Fediverse Client App →](11-client-apps.md)
