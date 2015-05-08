<?php
namespace Ably\Exceptions;

use Ably\Models\ErrorInfo;
use \Exception;

/**
 * Generic Ably exception
 */
class AblyException extends Exception {

    /**
     * @var ErrorInfo
     */
    public $errorInfo;

    public function __construct( $message, $code = 40000, $statusCode = 400 ) {
        parent::__construct( $message, $code );
        $this->errorInfo = new ErrorInfo();
        $this->errorInfo->message = $message;
        $this->errorInfo->code = $code;
        $this->errorInfo->statusCode = $statusCode;
    }

    public function getStatusCode() {
        return $this->errorInfo->statusCode;
    }

    // PHP doesn't allow overriding these methods

    // public function getCode() {
    //     return $this->errorInfo->code;
    // }

    // public function getMessage() {
    //     return $this->errorInfo->message;
    // }
}
