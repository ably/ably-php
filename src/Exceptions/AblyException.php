<?php
namespace Ably\Exceptions;

use Exception;

/**
 * Generic exception for Ably classes
 */
class AblyException extends Exception {

	protected $ablyCode;
    
    public function __construct($message, $code = 400, $ablyCode = 40000) {
        parent::__construct($message, $code);

        $this->ablyCode = $ablyCode;
    }

    public function getAblyCode() {
    	return $this->ablyCode;
    }
}
