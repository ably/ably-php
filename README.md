![Ably Pub/Sub PHP Header](images/php-SDK-github.png)
[![Latest Stable Version](https://poser.pugx.org/ably/pubsub-server/v/stable)](https://packagist.org/packages/ably/pubsub-server)
[![License](https://poser.pugx.org/ably/pubsub-server/license)](https://github.com/ably/ably-pubsub-php/blob/main/LICENSE)

---

# Ably Pub/Sub PHP SDK

Build using Ably’s Pub/Sub PHP SDK, supported on all popular platforms and frameworks.

Ably Pub/Sub provides flexible APIs that deliver features such as pub-sub messaging, message history, presence, and push notifications. Utilizing Ably’s realtime messaging platform, applications benefit from its highly performant, reliable, and scalable infrastructure.

Find out more:

* [Ably Pub/Sub docs.](https://ably.com/docs/basics)
* [Ably Pub/Sub examples.](https://ably.com/examples?product=pubsub)

---

## Getting started

Everything you need to get started with Ably:

* [Getting started with Pub/Sub using PHP.](https://ably.com/docs/getting-started/php)
* [SDK Setup for PHP.](https://ably.com/docs/getting-started/setup?lang=php)

---

## Package

This SDK ships as a single package, `ably/pubsub-server`.

The package name declares where your code runs. A server is a trusted runtime: it typically authenticates with an API key, one that a browser or a mobile app must never hold, and its connections are exempt from monthly-active-user counting. That declaration has to reach Ably rather than only the README, so the package sends it on every request in the `Ably-Agent` header:

```
Ably-Agent: ably-pubsub-php/2.0.0 php/8.3.4 ably-pubsub-server
```

The trailing `ably-pubsub-server` entry is the part the platform matches on. It is stamped by `Ably\PubSub\Server::createHttpClient()`, so a client constructed any other way declares no side, and will be rejected on accounts that have monthly-active-user pricing enabled.

This is the only Ably Pub/Sub package for PHP. There is no device package and no separate core package to depend on, because PHP is a server-side language: this SDK is REST-only and there is no PHP realtime client. See the [Ably REST API](#ably-rest-api) note below for realtime options.

---

## Supported platforms

Ably aims to support a wide range of platforms. If you experience any compatibility issues, open an issue in the repository or contact [Ably support](https://ably.com/support).

| Platform | Support |
| --- | --- |
| PHP | 8.1, 8.2, 8.3, 8.4, 8.5 |

---

## Laravel packages

For Laravel applications, consider these framework-integrated alternatives that provide Laravel integration with automatic configuration and native broadcasting support, eliminating the boilerplate setup required when using the raw PHP SDK directly:

* **[Ably Pub/Sub PHP Laravel SDK](https://github.com/ably/ably-php-laravel)** - Laravel integration package with clean facade and dependency injection interface.
* **[Ably Broadcaster for Laravel](https://github.com/ably/laravel-broadcaster)** - Official Laravel broadcaster for real-time event broadcasting.

Each needs a new major version to run on `ably/pubsub-server`; their current releases depend on `ably/ably-php` 1.x. Those majors ship in the same release window as this package.

---

## Installation

To get started with your project, install the package:

```sh
composer require ably/pubsub-server
```
---


## Usage

The following code connects to Ably's REST messaging service, gets reference to a channel to receive messages, and publishes a test message to that same channel:

```php
use Ably\PubSub\Server;

// Initialize the Ably HTTP (REST) client for a server
$ably = Server::createHttpClient(['key' => 'your-ably-api-key', 'clientId' => 'me']);

// Get a reference to the 'test-channel' channel
$channel = $ably->channel('test-channel');

// Publish a test message to the channel
$channel->publish('test-event', 'hello world');
```

`createHttpClient()` accepts everything the 1.x client constructor accepted: an options array, an API key string, a token string, or a `ClientOptions` instance.

If your own SDK or framework wraps this package, name it so its traffic is attributed to it:

```php
$ably = Server::createHttpClient([
    'key' => 'your-ably-api-key',
    'agents' => ['my-framework' => '1.2.3'],
]);
```

---

## Migrating from `ably/ably-php` 1.x

`ably/pubsub-server` 2.0.0 supersedes `ably/ably-php`. The client it returns is the same REST client, so for most applications the migration is confined to the `composer require` line, the `use` statements, and the constructor call. [UPDATING.md](./UPDATING.md) has the full mapping table and a before/after example.

If you are staying on 1.x for now, it is maintained on the `maintenance/1.x` branch of this repository, and receives security and critical-bug fixes only for one year from the 2.0.0 release.

---

## Releases

The [CHANGELOG.md](./CHANGELOG.md) contains details of the latest releases for this SDK. You can also view all Ably releases on [changelog.ably.com](https://changelog.ably.com).

---

## Contributing

Read the [CONTRIBUTING.md](./CONTRIBUTING.md) guidelines to contribute to Ably.

Development happens in this repository, `ably-pubsub-php`. The Packagist package is published from a read-only distribution mirror, so issues and pull requests belong here.

---

## Support, feedback, and troubleshooting

For help or technical support, visit the [Ably Support page](https://ably.com/support).

### Ably REST API

This SDK currently supports only the [Ably REST API](https://www.ably.com/docs/rest). For realtime capabilities, you can use the [MQTT adapter](https://www.ably.com/docs/mqtt) alongside [Mosquitto PHP](https://github.com/mgdm/Mosquitto-PHP) to implement Ably's Realtime features.
