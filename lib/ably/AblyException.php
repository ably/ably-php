<?php

/**
 * Common exception for Ably classes
 */
class AblyException extends Exception {
    
    public function __construct($message, $code = 0) {
        parent::__construct($message, $code);
    }
    
}