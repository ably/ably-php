<?php

/**
 * Generic exception for Ably classes
 */
class AblyException extends Exception {
    
    public function __construct($message, $code = 0) {
        parent::__construct($message, $code);
    }
    
}


/**
 * Exception thrown when a request to Ably API fails (HTTP response other than 200 or 201)
 */
class AblyRequestException extends AblyException {

	private $response;
    
    public function __construct($message, $code, $response) {
        parent::__construct($message, $code);

        $this->response = $response;
    }

    public function getResponse() {
    	return $this->response;
    }

}