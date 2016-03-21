# [Ably](https://www.ably.io)

[![Build Status](https://travis-ci.org/ably/ably-php.png)](https://travis-ci.org/ably/ably-php)

A PHP REST client library for [ably.io](https://www.ably.io), the realtime messaging service.

## Documentation

Visit https://www.ably.io/documentation for a complete API reference and more examples.

## Installation

### Via composer

The client library is available as a [composer package on packagist](https://packagist.org/packages/ably/ably-php). If you don't have composer already installed, you can get it from https://getcomposer.org/.

Install Ably from the shell with:

    $ composer require ably/ably-php --update-no-dev

Omit the `--update-no-dev` parameter, if you want to run tests. Then simply require composer's autoloader:

```php
require_once __DIR__ . '/../vendor/autoload.php';
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
$presencePage = channel->presence->history(); // => \Ably\Models\PaginatedResult
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

## Support, feedback and troubleshooting

Please visit http://support.ably.io/ for access to our knowledgebase and to ask for any assistance.

You can also view the [community reported Github issues](https://github.com/ably/ably-php/issues).

To see what has changed in recent versions of Bundler, see the [CHANGELOG](CHANGELOG.md).

## Known limitations

[msgpack](http://msgpack.org/) support is currently missing in PHP client library, as there is no stable PHP msgpack library available.

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

## License

Copyright (c) 2016 Ably Real-time Ltd, Licensed under the Apache License, Version 2.0.  Refer to [LICENSE](LICENSE) for the license terms.
