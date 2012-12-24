# ably-php

This is the Ably REST client library for PHP.

For complete API documentation, see the [ably documentation](https://ably.io/documentation).

## Usage

Include the library:

    require_once 'path/to/lib/ably.php';

Use it like this:

    $app = new Ably('{Your-Private-API-Key}');

Or, pass multiple options:

    $app = new Ably(array(
        'key'    => '{Your-Private-API-Key}',
        'format' => 'json',
        'debug'  => true,
    ));

Setup a channel and broadcast a message:

    $channel0 = $app->channel('my_channel');
    $channel0->publish('test', 'hey, this is awesome!');


## Testing

Before you can run the tests you will need to install some tools:

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

Note: before running any tests please ensure you have a config.php file in the root folder (see config.php.sample for example).
ABLY_KEY is required whereas ABLY_HOST is optional as the library defaults to rest.ably.io