<?php
namespace Ably;

use Ably\AblyRest;
use Ably\Log;
use Ably\Exceptions\AblyException;
use Ably\Exceptions\AblyRequestException;
use Ably\Utils\CurlWrapper;

/**
 * Makes HTTP requests using cURL
 */
class Http {

    /**
     * @var string $postDataFormat How $params is interpreted when sent as a string.
     * Default: 'json'. 'msgpack' support may be added in future
     */
    protected $postDataFormat;

    /**
     * @var integer $timeout Timeout for a cURL connection in ms.
     */
    protected $connectTimeout;
    
    /**
     * @var integer $timeout Timeout for a cURL request in ms.
     */
    protected $requestTimeout;

    /**
     * @var \Ably\Utils\CurlWrapper $curl Holds a CurlWrapper instance used for building requests.
     */
    protected $curl;

    /**
     * Constructor
     */
    public function __construct( $clientOptions ) {
        $this->postDataFormat = $clientOptions->useBinaryProtocol ? 'msgpack' : 'json';
        $this->connectTimeout = $clientOptions->httpOpenTimeout;
        $this->requestTimeout = $clientOptions->httpRequestTimeout;
        $this->curl = new CurlWrapper();
    }

    /**
     * Wrapper to do a GET request
     * @see Http::request()
     */
    public function get( $url, $headers = array(), $params = array() ) {
        return $this->request( 'GET', $url, $headers, $params );
    }

    /**
     * Wrapper to do a POST request
     * @see Http::request()
     */
    public function post( $url, $headers = array(), $params = array() ) {
        return $this->request( 'POST', $url, $headers, $params );
    }

    /**
     * Wrapper to do a PUT request
     * @see Http::request()
     */
    public function put( $url, $headers = array(), $params = array() ) {
        return $this->request( 'PUT', $url, $headers, $params );
    }

    /**
     * Wrapper to do a DELETE request
     * @see Http::request()
     */
    public function delete( $url, $headers = array(), $params = array() ) {
        return $this->request( 'DELETE', $url, $headers, $params );
    }

    /**
     * Executes a cURL request
     * @param string $method HTTP method (GET, POST, PUT, DELETE, ...)
     * @param string $url Absolute URL to make a request on
     * @param array $headers HTTP headers to send
     * @param array|string $params Array of parameters to submit or a JSON string
     * @throws AblyRequestException if the request fails
     * @throws AblyRequestTimeoutException if the request times out
     * @return array with 'headers' and 'body' fields, body is automatically decoded
     */
    public function request( $method, $url, $headers = array(), $params = array() ) {

        $ch = $this->curl->init($url);

        $this->curl->setOpt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->connectTimeout); 
        $this->curl->setOpt($ch, CURLOPT_TIMEOUT_MS, $this->requestTimeout);

        if (!isset( $headers['X-Ably-Version'] )) {
            $headers['X-Ably-Version'] = AblyRest::API_VERSION;
        }

        if (!empty($params)) {
            if (is_array( $params )) {
                $paramsQuery = http_build_query( $params );

                if ($method == 'GET') {
                    $url .= '?' . $paramsQuery;
                    $this->curl->setOpt( $ch, CURLOPT_URL, $url );
                } else if ($method == 'POST') {
                    $this->curl->setOpt( $ch, CURLOPT_POST, true );
                    $this->curl->setOpt( $ch, CURLOPT_POSTFIELDS, $paramsQuery );
                } else {
                    $this->curl->setOpt( $ch, CURLOPT_CUSTOMREQUEST, $method );
                    $this->curl->setOpt( $ch, CURLOPT_POSTFIELDS, $paramsQuery );
                }
            } else if (is_string( $params )) { // json or msgpack
                if ($method == 'GET') {
                } else if ($method == 'POST') {
                    $this->curl->setOpt( $ch, CURLOPT_POST, true );
                } else {
                    $this->curl->setOpt( $ch, CURLOPT_CUSTOMREQUEST, $method );
                }

                $this->curl->setOpt( $ch, CURLOPT_POSTFIELDS, $params );

                if ($this->postDataFormat == 'json') {
                    array_push( $headers, 'Accept: application/json', 'Content-Type: application/json' );
                }
            } else {
                throw new AblyRequestException( 'Unknown $params format' );
            }
        }

        if (!empty($headers)) {
            $this->curl->setOpt( $ch, CURLOPT_HTTPHEADER, $headers );
        }

        $this->curl->setOpt( $ch, CURLOPT_RETURNTRANSFER, true );
        if ( Log::getLogLevel() >= Log::VERBOSE ) {
            $this->curl->setOpt( $ch, CURLOPT_VERBOSE, true );
        }
        $this->curl->setOpt( $ch, CURLOPT_HEADER, true ); // return response headers

        Log::d( 'cURL command:', $this->curl->getCommand( $ch ) );

        $raw = $this->curl->exec( $ch );
        $info = $this->curl->getInfo( $ch );
        $err = $this->curl->getErrNo( $ch );
        $errmsg = $err ? $this->curl->getError( $ch ) : '';
        
        $this->curl->close( $ch );

        if ( $err ) { // a connection error has occured (no data received)
            Log::e( 'cURL error:', $err, $errmsg );
            throw new AblyRequestException( 'cURL error: ' . $errmsg, 50003, 500 );
        }

        $response = null;

        $headers = substr( $raw, 0, $info['header_size'] );
        $body = substr( $raw, $info['header_size'] );
        $decodedBody = json_decode( $body );

        $response = array( 'headers' => $headers, 'body' => $decodedBody ? $decodedBody : $body );

        Log::v( 'cURL request response:', $info['http_code'], $response );

        if ( !in_array( $info['http_code'], array(200,201) ) ) {
            $ablyCode = empty( $decodedBody->error->code ) ? $info['http_code'] * 100 : $decodedBody->error->code * 1;
            $errorMessage = empty( $decodedBody->error->message ) ? 'cURL request failed' : $decodedBody->error->message;

            throw new AblyRequestException( $errorMessage, $ablyCode, $info['http_code'], $response );
        }

        return $response;
    }
}
