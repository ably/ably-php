<?php
namespace Ably\Models;

use Ably\Log;

/**
 * Client library options
 */
class ClientOptions extends AuthOptions {

    /**
     * @var boolean value indicating whether or not a TLS (“SSL”) secure connection should be used.
     */
    public $tls = true;
    
    /**
     * integer a number controlling the verbosity of the output from 1 (minimum, errors only) to 4 (most verbose);
     * @see \Ably\Log
     */
    public $logLevel = Log::WARNING;

    /**
     * @var function|null a function to handle each line of log output. If handler is not specified, console.log is used.
     * Note that the log level and log handler have global scope in the library and will thus not act independently between library instances when multiple library instances are existing concurrently.
     * @see \Ably\Log
     */
    public $logHandler;

    /**
     * @var bool If true, msgpack is used for communication, otherwise JSON is used
     * note that msgpack is currently NOT SUPPORTED because of lack of working msgpack libraries for PHP
     */
    public $useBinaryProtocol = false;

    /**
     * @var string alternate server domain
     * For development environments only.
     */
    public $restHost;

    /**
     * @var integer Allows a non-default Ably non-TLS port to be used.
     * For development environments only.
     */
    public $port;

    /**
     * @var integer Allows a non-default Ably TLS port to be used.
     * For development environments only.
     */
    public $tlsPort;

    /**
     * @var string optional prefix to be prepended to $restHost
     * Example: 'sandbox' -> 'sandbox-rest.ably.io'
     */
    public $environment;

    /**
     * @var string[] fallback hosts, used when connection to default host fails, populated automatically
     */
    public $fallbackHosts;

    /**
     * @var \Ably\Models\TokenParams defaultTokenParams – overrides the client library defaults described in TokenParams
     */
    public $defaultTokenParams;

    /**
     * @var integer connection timeout after which a next fallback host is used
     */
    public $httpRequestTimeout = 15000;

    /**
     * @var string a class that should be used for making HTTP connections
     * For use in development environments only.
     */
    public $httpClass = 'Ably\Http';

    public function __construct( $options = array() ) {
        parent::__construct( $options );

        if ( empty( $this->restHost ) ) {
            $this->restHost = 'rest.ably.io';

            if ( empty( $this->environment ) ) {
                $this->fallbackHosts = array(
                    'a.ably-realtime.com',
                    'b.ably-realtime.com',
                    'c.ably-realtime.com',
                    'd.ably-realtime.com',
                    'e.ably-realtime.com',
                );

                shuffle( $this->fallbackHosts );
            }
        }

        if ( empty( $this->defaultTokenParams ) ) {
            $this->defaultTokenParams = new TokenParams();
        }

        if ( !empty( $this->environment ) ) {
            $this->restHost = $this->environment . '-' . $this->restHost;
        }
    }
}