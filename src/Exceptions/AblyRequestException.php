<?php
namespace Ably\Exceptions;

/**
 * Exception thrown when a request to Ably API fails (HTTP response other than 200 or 201)
 */
class AblyRequestException extends AblyException {

    protected $response;
    
    public function __construct($message, $code, $ablyCode, $response = '') {
        parent::__construct($message, $code, $ablyCode);

        $this->response = $response;
    }

    public function getResponse() {
        return $this->response;
    }
}
