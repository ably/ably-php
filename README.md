# ably-php

This repo contains the ably PHP client libraries.

For complete API documentation, see the [ably documentation](https://ably.io/documentation).

## Usage

Include the library:

    require_once 'path/to/ably.php';

Use it like this:

    $ably = Ably::get_instance('{Your-Private-API-Key}');

Or, pass multiple options:

    $ably = Ably::get_instance(array(
        'key'    => '{Your-Private-API-Key}',
        'format' => 'xml',
        'debug'  => true,
    ));

## Testing

To run the tests you will need to install:

1. PHPUnit 3.7 (assumes PEAR already installed):

    $ sudo pear config-set auto_discover 1
    $ sudo pear install pear.phpunit.de/PHPUnit

2. PHPUnit_Selenium package

    $ pear install phpunit/PHPUnit_Selenium

3. Selenium Server

    $ brew install selenium-server-standalone

4. Start Selenium Server

    $ java -jar /path/to/selenium-server-standalone-2.25.0.jar -p 4444

5. Run the tests using the PHPUnit Command-line runner:

    $ cd /path/to/ably-php/test
    $ phpunit auth

