<?php

require_once '../lib/ably.php';
require_once 'factories/TestOption.php';

class InitTest extends PHPUnit_Framework_TestCase {
    /**
     * Init library with a key only
     */

    /**
     * Init library with a key in options
     */

    /**
     * Init library with appId
     */

    /**
     * Verify library fails to init when both appId and key are missing
     */

    /**
     * Init library with specified host
     */

    /**
     * Init library with specified port
     */

    /**
     * Verify encrypted defaults to true
     */

    /**
     * Verify encrypted can be set to false
     */

    /**
     * Init with log handler; check called
     */

    /**
     * Init with log handler; check not called if logLevel == NONE
     */
}