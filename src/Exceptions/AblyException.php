<?php
namespace Ably\Exceptions;

/**
 * Generic Ably exception
 */
class AblyException extends ErrorInfo {

    public function __construct($message, $code = 40000, $statusCode = 400 ) {
        parent::__construct($message, $code, $statusCode);
    }
}
