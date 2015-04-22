<?php
namespace Ably\Exceptions;

/**
 * Exception thrown when an operation timeout has expired
 */
class AblyRequestTimeoutException extends AblyRequestException {

    public function __construct($message, $code = 500, $ablyCode = 50003) {
        parent::__construct($message, $code, $ablyCode);
    }
}
