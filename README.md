# [Ably](https://ably.io)

[![Build Status](https://travis-ci.org/ably/ably-php.png)](https://travis-ci.org/ably/ably-php)

This is the Ably REST client library for PHP.

For complete API documentation, see the [ably documentation](https://ably.io/documentation).

## Introduction

Include the library:

```php
require_once 'path/to/lib/ably.php';
```

## Using the REST API

### Publishing a message to a channel

```php
$client = AblyRest.new(array('key' => 'xxxx'));
$channel = $client->channel('my_channel');
$channel->publish('myEvent', 'Hello!'); // true
```

### Fetching a channel's history

```php
$client = AblyRest.new(array('key' => 'xxxx'));
$channel = $client->channel('my_channel');
$channel->history();
```

### Authentication with a token

```php
$client->auth->authorise(); # creates a token and will use token authentication moving forwards
$client->auth->current_token() #=> #<Ably::Models::Token>
$channel->publish("myEvent", "Hello!") #=> true, sent using token authentication
```

### Fetching your application's stats

```php
$client = AblyRest.new(array('key' => 'xxxx'));
$client->stats()
```

### Fetching the Ably service time

```php
$client = AblyRest.new(array('key' => 'xxxx'));
$client->time // => 2013-12-12 14:23:34 +0000
```

## Testing

Before you can run the tests you will need to install some tools:

```bash
# Install Composer from https://getcomposer.org/
# Then download dependencies
$ composer install

# Run a test
$ vendor/bin/phpunit test/TimeTest.php
```

Old instructions (Does not work!):

```bash
# Install PHPUnit 3.7 (assumes PEAR already installed)
$ sudo pear config-set auto_discover 1
$ sudo pear install pear.phpunit.de/PHPUnit

# Install the PHPUnit_Selenium package
$ pear install phpunit/PHPUnit_Selenium

# Install the Selenium Server
$ brew install selenium-server-standalone

# Start the Selenium Server
$ java -jar /path/to/selenium-server-standalone-2.25.0.jar -p 4444

# Finally, run a test
$ cd /path/to/ably-php/test
$ phpunit auth
```

Note: before running any tests please ensure you have a config.php file in the root folder (see config.php.sample for example).
ABLY_KEY is required whereas ABLY_HOST is optional as the library defaults to rest.ably.io

## Contributing

1. Fork it
2. Create your feature branch (`git checkout -b my-new-feature`)
3. Commit your changes (`git commit -am 'Add some feature'`)
4. Push to the branch (`git push origin my-new-feature`)
5. Create new Pull Request
