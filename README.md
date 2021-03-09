# [Ably](https://www.ably.io)

[![Latest Stable Version](https://poser.pugx.org/ably/ably-php/v/stable)](https://packagist.org/packages/ably/ably-php)
[![Total Downloads](https://poser.pugx.org/ably/ably-php/downloads)](https://packagist.org/packages/ably/ably-php)
[![License](https://poser.pugx.org/ably/ably-php/license)](https://packagist.org/packages/ably/ably-php)

A PHP REST client library for [www.ably.io](https://www.ably.io), the realtime messaging service. This library currently targets the [Ably 1.1 client library specification](https://www.ably.io/documentation/client-lib-development-guide/features/). You can jump to the '[Known Limitations](#known-limitations)' section to see the features this client library does not yet support or [view our client library SDKs feature support matrix](https://www.ably.io/download/sdk-feature-support-matrix) to see the list of all the available features.


## Supported Platforms

This SDK supports PHP 7.2+ and 8.0

We regression-test the library against a selection of PHP versions (which will change over time, but usually consists of the versions that are supported upstream). Please refer to [the check workflow](.github/workflows/check.yml) for the set of versions that currently undergo CI testing.

We'll happily support (and investigate reported problems with) any reasonably-widely-used PHP version.
If you find any compatibility issues, please [do raise an issue](https://github.com/ably/ably-php/issues/new) in this repository or [contact Ably customer support](https://support.ably.io/) for advice.

## Known Limitations

Currently, this SDK only supports [Ably REST](https://www.ably.io/documentation/rest). However, you can use the [MQTT adapter](https://www.ably.io/documentation/mqtt) to implement [Ably's Realtime](https://www.ably.io/documentation/realtime) features using Python. 

This SDK is *not compatible* with some of the Ably features:

| Feature |
| :--- |
| [Remember fallback host during failures](https://www.ably.io/documentation/realtime/usage#client-options) |
| [MsgPack Binary Protocol](https://www.ably.io/documentation/realtime/usage#client-options) |


## Documentation

Visit https://www.ably.io/documentation for a complete API reference and more examples.

## Installation

### Via composer

The client library is available as a [composer package on packagist](https://packagist.org/packages/ably/ably-php). If you don't have composer already installed, you can get it from https://getcomposer.org/.

Install Ably from the shell with:

    $ composer require ably/ably-php --update-no-dev

Then simply require composer's autoloader:

```php
require_once __DIR__ . '/vendor/autoload.php';
```

### Manual installation
Clone or download Ably from this repo and require `ably-loader.php`:
```php
require_once __DIR__ . '/ably-php/ably-loader.php';
```

## Using the REST API

### Introduction

All examples assume a client and/or channel has been created as follows:

```php
$client = new Ably\AblyRest('your.appkey:xxxxxx');
$channel = $client->channel('test');
```

### Publishing a message to a channel

```php
$channel->publish('myEvent', 'Hello!'); // => true
```

### Querying the History

```php
$messagesPage = $channel->history(); // => \Ably\Models\PaginatedResult
$messagesPage->items[0]; // => \Ably\Models\Message
$messagesPage->items[0]->data; // payload for the message
$messagesPage->next(); // retrieves the next page => \Ably\Models\PaginatedResult
$messagesPage->hasNext(); // false, there are no more pages
```

### Presence on a channel

```php
$membersPage = $channel->presence->get(); // => \Ably\Models\PaginatedResult
$membersPage->items[0]; // first member present in this page => \Ably\Models\PresenceMessage
$membersPage->items[0]->clientId; // client ID of first member present
$membersPage->next(); // retrieves the next page => \Ably\Models\PaginatedResult
$membersPage->hasNext(); // false, there are no more pages
```

### Querying the Presence History

```php
$presencePage = $channel->presence->history(); // => \Ably\Models\PaginatedResult
$presencePage->items[0]; // => \Ably\Models\PresenceMessage
$presencePage->items[0]->clientId; // client ID of first member
$presencePage->next(); // retrieves the next page => \Ably\Models\PaginatedResult
```

### Generate Token and Token Request

```php
$tokenDetails = $client->auth->requestToken();
// => \Ably\Models\PresenceMessage
$tokenDetails->token; // => "xVLyHw.CLchevH3hF....MDh9ZC_Q"

$client = new Ably\AblyRest( $tokenDetails->token );
// or
$client = new Ably\AblyRest( array( 'tokenDetails' => $tokenDetails ) );

$token = $client->auth->createTokenRequest();
// => {"id" => ...,
//     "clientId" => null,
//     "ttl" => 3600,
//     "timestamp" => ...,
//     "capability" => "{\"*\":[\"*\"]}",
//     "nonce" => ...,
//     "mac" => ...}
```

### Fetching your application's stats

```php
$statsPage = client->stats(); // => \Ably\Models\PaginatedResult
$statsPage->items[0]; // => \Ably\Models\Stats
$statsPage->next(); // retrieves the next page => \Ably\Models\PaginatedResult
```

### Fetching the Ably service time

```php
$client->time(); // in milliseconds => 1430313364993
```

## Laravel

If you're using Laravel, you may want to check out [ably-php-laravel](https://packagist.org/packages/ably/ably-php-laravel) wrapper, which is a wrapper with Laravel-specific helper classes.

## Support, feedback and troubleshooting

Please visit http://support.ably.io/ for access to our knowledgebase and to ask for any assistance.

You can also view the [community reported Github issues](https://github.com/ably/ably-php/issues).

To see what has changed in recent versions of Bundler, see the [CHANGELOG](CHANGELOG.md).

## Known limitations

1. This client library requires PHP version 5.4 or greater
2. [msgpack](http://msgpack.org/) support is currently missing in PHP client library, as there is no stable PHP msgpack library available.

## Running the tests

The client library uses the Ably sandbox environment to provision an app and run the tests against that app.  In order to run the tests, you need to:

	git clone https://github.com/ably/ably-php.git
	cd ably-php
    composer install
    git submodule init
    git submodule update
    ./vendor/bin/phpunit

## Contributing

1. Fork it
2. Create your feature branch (`git checkout -b my-new-feature`)
3. Commit your changes (`git commit -am 'Add some feature'`)
4. Ensure you have added suitable tests and the test suite is passing (run `vendor/bin/phpunit`)
4. Push to the branch (`git push origin my-new-feature`)
5. Create a new Pull Request

## Release Process

This library uses [semantic versioning](http://semver.org/). For each release, the following needs to be done:

* Update the version number in [src/AblyRest.php](./src/AblyRest.php)
* Run [`github_changelog_generator`](https://github.com/skywinder/Github-Changelog-Generator) to automate the update of the [CHANGELOG](./CHANGELOG.md). Once the `CHANGELOG` update has completed, manually change the `Unreleased` heading and link with the current version number such as `1.0.0`. Also ensure that the `Full Changelog` link points to the new version tag instead of the `HEAD`.
* Commit
* Add a tag and push to origin such as `git tag 1.0.0 && git push origin 1.0.0`
* Visit https://github.com/ably/ably-php/tags and add release notes for the release including links to the changelog entry.
* Visit https://packagist.org/packages/ably/ably-php, log in to Packagist, and click the "Update" button.
* Remember to release an update for the [PHP Laravel library](https://github.com/ably/ably-php-laravel)
