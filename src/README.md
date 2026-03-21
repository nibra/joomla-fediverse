# Fediverse for Joomla

An ActivityPub implementation for Joomla 6+ (requires PHP 8.3+).

## Roadmap

- [x] **MVP 1** — S2S Follow + Accept + Delivery Worker
    - [x] Actor endpoint + Inbox endpoint + Signature verify
    - [x] Inbox ingest + process Follow → Accept
    - [x] Delivery queue + worker (signed POST)

- [x] **MVP 2** — Publish Joomla Article (Create/Update/Delete)
    - [x] Content plugin → PublishService → Outbox → Delivery
    - [x] Object endpoint /ap/objects/{id} for canonical retrieval

- [x] **MVP 3** — Announce/Discovery
    - [x] WebFinger
    - [x] NodeInfo
    - [x] Module “Profile” (handle + remote follow link)

- [x] **MVP 4** — C2S minimal
    - [x] OAuth2 authorise/token
    - [x] No client registration endpoint (Joomla-native client provisioning only)
    - [x] Authenticated POST /outbox with Bearer token

- [ ] **MVP 5** — Product completeness
    - [x] Content provider interface + registry for third-party extensions
    - [x] Core provider: com_content articles
    - [x] Core provider: com_newsfeeds newsfeeds
    - [x] Outbox GET (OrderedCollection + paging)
    - [x] Media endpoint for attachments + upload flow
    - [x] Inbound activity processing beyond Follow/Undo (Create/Reply/Like/Announce/Update/Delete)
    - [ ] Reactions/comments module to display inbound activities on content
    - [ ] User-level federation controls (opt-in/out, profile metadata, defaults)
    - [ ] Per-content opt-in/out and visibility controls in edit forms
    - [ ] Admin configuration UI (enable federation, allow/deny lists, defaults)
    - [ ] Moderation tooling (block/mute actors, review inbound activities)
    - [ ] Delivery retry/backoff + health reporting
    - [ ] Retention/cleanup tasks for inbox/outbox/actors

## Running tests

Unit tests (no Joomla environment needed):

```bash
vendor/bin/phpunit -c tests/phpunit.local.xml
```

Integration tests (requires the Docker stack to be running):

```bash
docker compose exec joomla bash -c "vendor/bin/phpunit -c phpunit.docker.xml"
```

## Test environment guidance

Acceptance tests assume full control over the environment. Avoid adding fallback behavior in test code; prefer explicit configuration instead (for example, set `PEER_CONTROL_URL` or `PEER_CONTROL_COMMAND_*` to stop/start the Peer).
Do not add test-resilience logic to production or test helpers; keep tests explicit and deterministic.

## Third-party content providers

To federate content from other extensions, implement
`NX\Component\Fediverse\Administrator\Service\Publishing\ContentProviderInterface`
and register your provider using a `fediverse` plugin.

Create a plugin in the `fediverse` group and implement
`onFediverseRegisterContentProviders`. The system plugin will import this group
and dispatch the event during `onAfterInitialise`.

Example registration (plugin method):

```php
use Joomla\CMS\Factory;
use NX\Component\Fediverse\Administrator\Service\Publishing\ContentProviderRegistry;

public function onFediverseRegisterContentProviders(ContentProviderRegistry $registry): void
{
    $registry->addProvider(new YourContentProvider(...));
}
```

Provider keys should map to object ids like `/ap/objects/{providerKey}-{id}`.
Your provider must implement `parseObjectId()` accordingly.

## Exploratory testing with Docker

Use the bundled Joomla container (service `joomla`, container name `${CONTAINER_PREFIX}-web`, e.g. `fediverse-web`) and
the `fediverse_peer` echo server to validate inbox + delivery behavior end-to-end.

1. Start the stack: `TERM=xterm make up`
2. Install the extension and enable the `system - fediverse` and `task - fediverse` plugins.
3. Create a Joomla user in the admin UI and note the numeric user id.
4. Provision the local actor: `curl -i http://localhost:8080/ap/actors/u<ID>`
5. Insert a remote actor row that points to the peer container (use your Joomla table prefix, `dev_` by default):
   ```sql
   INSERT INTO dev_fediverse_actors
     (type, handle, preferred_username, uri, inbox_url, outbox_url, shared_inbox_url, is_enabled)
   VALUES
     ('remote', 'peer@example.test', 'peer', 'https://peer.example/actors/peer',
      'http://fediverse_peer/', 'http://fediverse_peer/', 'http://fediverse_peer/', 1);
   ```
6. Send a Follow activity to the local inbox:
   ```bash
   curl -i http://localhost:8080/ap/inbox \
     -H 'Content-Type: application/activity+json' \
     -d '{"@context":"https://www.w3.org/ns/activitystreams","id":"https://peer.example/activities/1","type":"Follow","actor":"https://peer.example/actors/peer","object":"http://localhost:8080/ap/actors/u<ID>"}'
   ```
7. Run the inbox + delivery workers:
   ```bash
   make bash
   php cli/joomla.php scheduler:list | grep fediverse
   php cli/joomla.php scheduler:run --id=<inbox-task-id>
   php cli/joomla.php scheduler:run --id=<delivery-task-id>
   ```
8. Verify delivery by checking the peer output:
    - `docker compose logs -f fediverse_peer`
    - or hit `http://localhost:8082` to see the echoed response.

### Namespace map refresh

Joomla caches extension namespaces in `administrator/cache/autoload_psr4.php`. If you add a new extension or namespace,
regenerate the map (or delete the cache file and restart Apache):

```bash
docker compose exec joomla php -r "define('_JEXEC',1); require 'includes/defines.php'; require 'libraries/vendor/autoload.php'; require 'libraries/namespacemap.php'; (new JNamespacePsr4Map())->create();"
```
