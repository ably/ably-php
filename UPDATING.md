# Upgrade / Migration Guide

## 1.x (`ably/ably-php`) → 2.0.0 (`ably/pubsub-server`)

> **Status: draft.** The final public API naming is still under review; [PDR-091d](https://ably.atlassian.net/wiki/spaces/product/pages/5363957781) may rename `AblyRest` to `HttpClient` before the 2.0.0 GA release. This section will be finalized before GA.

Version 2.0.0 ships from a new package, `ably/pubsub-server`, under a new namespace, `Ably\PubSub\`. `ably/ably-php` is superseded: it receives security and critical-bug fixes only for one year from the 2.0.0 release date, and is then end-of-life.

Under monthly-active-user pricing the platform has to classify every connection as device-side or server-side. The new package declares that automatically, on every request, in the `Ably-Agent` header; the old constructor cannot declare anything. That is the forcing function for this migration: once monthly-active-user pricing is live, `new Ably\AblyRest(...)` from `ably/ably-php` is rejected on accounts where it is enabled.

The client `Server::createHttpClient()` returns is the same REST client as before. Channels, message publishing, history, presence, authentication, push admin, crypto and every `ClientOptions` key behave exactly as they did in 1.x. For most applications the migration is confined to the `composer require` line, the `use` statements, and the constructor call.

### Mapping

| 1.x (`ably/ably-php`) | 2.0 (`ably/pubsub-server`) |
| --- | --- |
| `composer require ably/ably-php` | `composer require ably/pubsub-server` |
| `use Ably\AblyRest;` / `new AblyRest($opts)` | `use Ably\PubSub\Server;` / `Server::createHttpClient($opts)` |
| `use Ably\Models\Message;` (any `Ably\X` type) | `use Ably\PubSub\Models\Message;` (`Ably\PubSub\X`) |
| `AblyRest::setAblyAgentHeader('x', 'v')` | `Server::createHttpClient(['agents' => ['x' => 'v'], …])` |
| `AblyRest::setLibraryFlavourString('x')` | removed — use the `agents` option |
| `require 'ably-loader.php';` | removed — use Composer's autoloader (`vendor/autoload.php`) |
| PHP 7.2 – 8.0 | PHP `^8.1` (tested on 8.1 – 8.5) |
| ⚠️ [091d](https://ably.atlassian.net/wiki/spaces/product/pages/5363957781): `\Ably\AblyRest` type hints | `\Ably\PubSub\HttpClient` (not yet decided) |

Every class moves namespace and keeps its name, so the rename is mechanical: replace the prefix `Ably\` with `Ably\PubSub\` throughout, including in type hints, `catch` blocks and fully-qualified string class names.

### Example

```php
// 1.x
use Ably\AblyRest;

$ably = new AblyRest(['key' => getenv('ABLY_API_KEY'), 'clientId' => 'me']);
$ably->channel('test-channel')->publish('test-event', 'hello world');

// 2.0
use Ably\PubSub\Server;

$ably = Server::createHttpClient(['key' => getenv('ABLY_API_KEY'), 'clientId' => 'me']);
$ably->channel('test-channel')->publish('test-event', 'hello world');
```

`createHttpClient()` accepts everything the 1.x constructor accepted: an options array, an API key string, a token string, or a `ClientOptions` instance. A `ClientOptions` instance you pass in is copied rather than mutated.

### Declaring the side

Construct through the door. `new Ably\PubSub\AblyRest(...)` still works — the library and its own tests use it — but it declares no side, and will be rejected on monthly-active-user-enabled accounts just as the 1.x constructor is. The door produces:

```
Ably-Agent: ably-pubsub-php/2.0.0 php/8.3.4 ably-pubsub-server
```

If you are building an SDK or framework on top of this package, name it through the `agents` option instead of the removed static setters. Your entries are preserved, in order, ahead of the side entry:

```php
$ably = Server::createHttpClient([
    'key' => getenv('ABLY_API_KEY'),
    'agents' => ['laravel' => '11.0.0', 'laravel-broadcaster' => '1.0.4'],
]);

// Ably-Agent: ably-pubsub-php/2.0.0 php/8.3.4 laravel/11.0.0 laravel-broadcaster/1.0.4 ably-pubsub-server
```

The `agents` option is per-client, unlike the process-global static setters it replaces, so two clients in one process can carry different attribution.

### Removed in 2.0.0

* `AblyRest::setAblyAgentHeader()` and `AblyRest::$agents` — replaced by the per-client `agents` option.
* `AblyRest::setLibraryFlavourString()` — already deprecated in 1.x; replaced by the same option.
* `ably-loader.php`, the hand-rolled autoloader — Composer is the only supported install path.
* The `demo/` Heroku application and its `Procfile`.
* PHP 7.2 – 8.0 support.

`Auth::authorise()`, the British-spelling alias deprecated in favour of `Auth::authorize()`, is still present in 2.0.0. It may be removed in the 091d pass before GA.

### Unchanged

* The REST client and its whole surface: `channel()`, `channels`, publishing, message history, presence and presence history, `auth`, token requests and token issuing, `push` admin, `stats`, `time()`, and crypto.
* Every `ClientOptions` key, and the array / key-string / token-string / `ClientOptions` forms of the constructor argument.
* Message and error semantics, including `AblyException` and its codes.
* Requirements: `ext-json`, `ext-curl`, `ext-openssl`, and `rybakit/msgpack` for the msgpack protocol.

### Staying on 1.x

`ably/ably-php` 1.x is maintained on the `maintenance/1.x` branch of this repository. It gets security and critical-bug fixes for one year from the 2.0.0 release date and no new features, then reaches end-of-life. Both packages can be installed side by side during a migration: they declare different namespaces and different PSR-4 prefixes, so their autoloading does not collide.
