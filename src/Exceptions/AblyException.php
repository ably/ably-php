<?php
namespace Ably\Exceptions;

use Exception;

/**
 * Generic exception for Ably classes
 */
class AblyException extends Exception {
    
    public function __construct($message, $code = 0) {
        parent::__construct($message, $code);
    }
}
