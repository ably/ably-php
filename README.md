# ably-php

This repo contains the ably PHP client libraries.

For complete API documentation, see the [ably documentation](https://ably.io/documentation).

## Usage

Include the library:

    require_once 'path/to/ably.php';

Use it like this:

    $ably = new Ably('{Your-Private-API-Key}');

Or, pass multiple options:

    $ably = new Ably(array(
        'key'    => '{Your-Private-API-Key}',
        'format' => 'xml',
        'debug'  => true,
    ));

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

Note: before running the tests ensure you have a config.php file in the root folder (sample provided),
then provided correct ABLY_HOST (optional) and ABLY_KEY values.