<?php

require_once '../lib/ably.php';
require_once 'factories/TestOption.php';

class TokenTest extends PHPUnit_Framework_TestCase {
    /**
     * Base requestToken case with null params
     */

    /**
     * Base requestToken case with non-null but empty params
     */

    /**
     * requestToken with explicit timestamp
     */

    /**
     * requestToken with explicit, invalid timestamp
     */

    /**
     * requestToken with system timestamp
     */

    /**
     * requestToken with duplicate nonce
     */

    /**
     * Base requestToken case with non-null but empty params
     */

    /**
     * Token generation with capability that subsets key capability
     */

    /**
     * Token generation with specified key
     */

    /**
     * requestToken with invalid mac
     */

    /**
     * Token generation with specified ttl
     */

    /**
     * Token generation with excessive ttl
     */

    /**
     * Token generation with invalid ttl
     */
}