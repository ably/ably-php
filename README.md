# [Ably](https://www.ably.com)

[![Features](https://github.com/ably/ably-php/actions/workflows/features.yml/badge.svg)](https://github.com/ably/ably-php/actions/workflows/features.yml)

[![Latest Stable Version](https://poser.pugx.org/ably/ably-php/v/stable)](https://packagist.org/packages/ably/ably-php)
[![Total Downloads](https://poser.pugx.org/ably/ably-php/downloads)](https://packagist.org/packages/ably/ably-php)
[![License](https://poser.pugx.org/ably/ably-php/license)](https://packagist.org/packages/ably/ably-php)

_[Ably](https://ably.com) is the platform that powers synchronized digital experiences in realtime. Whether attending an event in a virtual venue, receiving realtime financial information, or monitoring live car performance data – consumers simply expect realtime digital experiences as standard. Ably provides a suite of APIs to build, extend, and deliver powerful digital experiences in realtime for more than 250 million devices across 80 countries each month. Organizations like Bloomberg, HubSpot, Verizon, and Hopin depend on Ably’s platform to offload the growing complexity of business-critical realtime data synchronization at global scale. For more information, see the [Ably documentation](https://ably.com/docs)._

This is a PHP REST client library for Ably. The library currently targets the [Ably 1.1 client library specification](https://www.ably.com/docs/client-lib-development-guide/features/). You can jump to the '[Known Limitations](#known-limitations)' section to see the features this client library does not yet support or [view our client library SDKs feature support matrix](https://www.ably.com/download/sdk-feature-support-matrix) to see the list of all the available features.


## Supported Platforms

This SDK supports PHP >=7.2

We regression-test the library against a selection of PHP versions (which will change over time, but usually consists of the versions that are supported upstream). Please refer to [the check workflow](.github/workflows/check.yml) for the set of versions that currently undergo CI testing.

We'll happily support (and investigate reported problems with) any reasonably-widely-used PHP version.
If you find any compatibility issues, please [do raise an issue](https://github.com/ably/ably-php/issues/new) in this repository or [contact Ably customer support](https://support.ably.com/) for advice.

## Known Limitations

Currently, this SDK only supports [Ably REST](https://www.ably.com/docs/rest). However, you can use the [MQTT adapter](https://www.ably.com/docs/mqtt) to implement [Ably's Realtime](https://www.ably.com/docs/realtime) features using [Mosquitto PHP](https://github.com/mgdm/Mosquitto-PHP).

## Documentation

Visit https://www.ably.com/docs for a complete API reference and more examples.

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

### Getting the channel status
```php
$channelStatus = $channel->status(); // => \Ably\Models\Status\ChannelDetails
var_dump($channelStatus); 
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

## Laravel realtime broadcasting

If you're using Laravel and want to support **realtime broadcasting and events**, you may want to check out [laravel-broadcaster](https://packagist.org/packages/ably/laravel-broadcaster/).

> If you want **ably-php** as a rest dependency across service providers, check [ably-php-laravel](https://packagist.org/packages/ably/ably-php-laravel). **ably-php-laravel** is a simple wrapper over **ably-php** with laravel-specific classes. This has limited use-cases and **laravel-broadcaster** is recommended over **ably-php-laravel** for most use-cases.

### Making explicit HTTP requests to Ably Rest Endpoints / Batch publish
- The `AblyRest->Request` method can be used to make explicit HTTP requests to the [Ably REST API](https://ably.com/docs/api/rest-api).
- It automatically adds necessary auth headers based on the initial auth config and supports pagination.
- The following is an example of using the batch publish API based on the [Ably batch publish rest endpoint documentation](https://ably.com/docs/api/rest-api#batch-publish).

```php
    // batch publish needs php array to be passed and serialization is handled based on useBinaryProtocol
    $payload = array(
        "channels" => ["channel1", "channel2", "channel3", "channel4"],
        "messages" => array(
            "id" => "1",
            "data" => "foo"
        )
    ); 
    $batchPublishPaginatedResult = $client->request("POST", "/messages", [], $payload);
```
- See the [ably rest endpoint doc](https://ably.com/docs/api/rest-api) for more information on other endpoints.
- Ably uses `msgpack` as a default encoding for messages. Read [encode using msgpack for better efficiency](https://faqs.ably.com/do-you-binary-encode-your-messages-for-greater-efficiency).
- If you want to send payload as `json`, please set `useBinaryProtocol` as `false` in `clientOptions`.

## Support, feedback and troubleshooting

Please visit http://support.ably.com/ for access to our knowledgebase and to ask for any assistance.

You can also view the [community reported Github issues](https://github.com/ably/ably-php/issues).

To see what has changed in recent versions of Bundler, see the [CHANGELOG](CHANGELOG.md).

## Known limitations

1. This client library requires PHP version 5.4 or greater

## Running the tests

The client library uses the Ably sandbox environment to provision an app and run the tests against that app.  In order to run the tests, you need to:

	git clone https://github.com/ably/ably-php.git
	cd ably-php
    composer install
    git submodule init
    git submodule update
    ./vendor/bin/phpunit

Note - If there is a issue while running tests [SSL certificate error: unable to get local issuer certificate], please set SSL cert path in `php.ini`.  For more information, follow https://aboutssl.org/fix-ssl-certificate-problem-unable-to-get-local-issuer-certificate/

## Contributing

1. Fork it
2. Create your feature branch (`git checkout -b my-new-feature`)
3. Commit your changes (`git commit -am 'Add some feature'`)
4. Ensure you have added suitable tests and the test suite is passing (run `vendor/bin/phpunit`)
4. Push to the branch (`git push origin my-new-feature`)
5. Create a new Pull Request

## Release Process

This library uses [semantic versioning](http://semver.org/). For each release, the following needs to be done:

1. Update the version number in [src/Defaults.php](./src/Defaults.php)
2. Create a new branch for the release, named like `release/1.0.0` (where `1.0.0` is what you're releasing, being the new version).
3. Run [`github_changelog_generator`](https://github.com/github-changelog-generator/github-changelog-generator) to automate the update of the [CHANGELOG.md](CHANGELOG.md). This may require some manual intervention, both in terms of how the command is run and how the change log file is modified. Your mileage may vary:
- The command you will need to run will look something like this: `github_changelog_generator -u ably -p ably-php --since-tag 1.1.9 --output delta.md --token $GITHUB_TOKEN_WITH_REPO_ACCESS`. Generate token [here](https://github.com/settings/tokens/new?description=GitHub%20Changelog%20Generator%20token).
- Using the command above, `--output delta.md` writes changes made after `--since-tag` to a new file.
- The contents of that new file (`delta.md`) then need to be manually inserted at the top of the `CHANGELOG.md`, changing the "Unreleased" heading and linking with the current version numbers.
- Also ensure that the "Full Changelog" link points to the new version tag instead of the `HEAD`.
4. Commit generated [CHANGELOG.md](./CHANGELOG.md) file.
5. Make a PR against `main`.
6. Once the PR is approved, merge it into `main`.
7. Add a tag and push to origin such as `git tag 1.0.0 && git push origin 1.0.0`.
8. Visit https://github.com/ably/ably-php/tags and add release notes for the release including links to the changelog entry.
9. Visit https://packagist.org/packages/ably/ably-php, log in to Packagist, and click the "Update" button.
10. Remember to make a release update for [laravel-broadcaster](https://github.com/ably/laravel-broadcaster) and [ably-php-laravel](https://github.com/ably/ably-php-laravel).
