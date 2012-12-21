# ably-php

This repo contains the ably PHP client libraries.

For complete API documentation, see the [ably documentation](https://ably.io/documentation).

## Usage

Include the library:

    require_once 'path/to/ably.php';

Use it like this:

    $ably = Ably::get_instance("{Your-Private-API-Key}");

Or, pass multiple options:

    $ably = Ably::get_instance(array(
        'key'    => "{Your-Private-API-Key}",
        'format' => 'xml',
        'debug'  => true,
    ));