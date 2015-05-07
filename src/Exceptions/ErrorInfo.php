<?php
namespace Ably\Exceptions;

use \Exception;

/**
 * Base class for Ably exceptions, provides read-only access to its fields
 * @property-read string $message Exception message
 * @property-read int $code Exception code @see https://github.com/ably/ably-common/blob/master/protocol/errors.json
 * @property-read int $statusCode HTTP error code
 */
class ErrorInfo extends Exception {

    protected $statusCode;

    public function __get( $name ) {
        return $this->$name;
    }
    

    public function __construct( $message, $code, $statusCode ) {
        parent::__construct( $message, $code );

        $this->statusCode = $statusCode;
    }

    public function getStatusCode() {
        return $this->statusCode;
    }
}
