<?php
namespace Ably;

use Ably\Log;
use Ably\Exceptions\AblyException;
use Ably\Exceptions\AblyRequestException;
use Ably\Exceptions\AblyRequestTimeoutException;

/**
 * Makes HTTP requests using cURL
 */
class Http {

    /**
     * @var string $postDataFormat How $params is interpreted when sent as a string
     * Default: 'json'. 'msgpack' support may be added in future
     */
    protected $postDataFormat;
    /**
     * @var integer $timeout Timeout for a cURL request in seconds.
     * Note that the same value is used both for connection and waiting for data, which means that
     * in worst case scenario, the total time for request could be almost double the specified value.
     */
    protected $timeout;

    /**
     * Constructor
     */
    public function __construct( $timeout = 10000, $postDataFormat = 'json' ) {
        $this->postDataFormat = $postDataFormat;
        $this->timeout = $timeout;
    }

    /**
     * Wrapper to do a GET request
     * @throws AblyRequestException if the request fails
     * @return array with 'headers' and 'body' fields, body is automatically decoded
     */
    public function get( $url, $headers = array(), $params = array() ) {
        return $this->request( 'GET', $url, $headers, $params );
    }

    /**
     * Wrapper to do a POST request
     * @throws AblyRequestException if the request fails
     * @return array with 'headers' and 'body' fields, body is automatically decoded
     */
    public function post( $url, $headers = array(), $params = array() ) {
        return $this->request( 'POST', $url, $headers, $params );
    }

    /**
     * Wrapper to do a PUT request
     * @throws AblyRequestException if the request fails
     * @return array with 'headers' and 'body' fields, body is automatically decoded
     */
    public function put( $url, $headers = array(), $params = array() ) {
        return $this->request( 'PUT', $url, $headers, $params );
    }

    /**
     * Wrapper to do a DELETE request
     * @throws AblyRequestException if the request fails
     * @return array with 'headers' and 'body' fields, body is automatically decoded
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

        $curl_cmd = 'curl ';
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->timeout); 
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->timeout);

        if (!empty($params)) {
            if (is_array( $params )) {
                $paramsQuery = http_build_query( $params );

                if ($method == 'GET') {
                    $url .= '?' . $paramsQuery;
                    curl_setopt( $ch, CURLOPT_URL, $url );
                } else if ($method == 'POST') {
                    curl_setopt( $ch, CURLOPT_POST, true );
                    curl_setopt( $ch, CURLOPT_POSTFIELDS, $paramsQuery );
                    $curl_cmd .= '-X POST ';
                    $curl_cmd .= '--data "'. str_replace( '"', '\"', $paramsQuery ) .'" ';
                } else {
                    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
                    curl_setopt( $ch, CURLOPT_POSTFIELDS, $paramsQuery );
                    $curl_cmd .= '-X ' . $method . ' ';
                    $curl_cmd .= '--data "'. str_replace( '"', '\"', $paramsQuery ) .'" ';
                }
            } else if (is_string( $params )) { // json or msgpack
                if ($method == 'GET') {
                } else if ($method == 'POST') {
                    curl_setopt( $ch, CURLOPT_POST, true );
                    $curl_cmd .= '-X POST ';
                } else {
                    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
                    $curl_cmd .= '-X ' . $method . ' ';
                }

                curl_setopt( $ch, CURLOPT_POSTFIELDS, $params );

                if ($this->postDataFormat == 'json') {
                    array_push( $headers, 'Accept: application/json', 'Content-Type: application/json' );
                    $curl_cmd .= '--data "'.str_replace( '"', '\"', $params ).'" ';
                }
            } else {
                throw new AblyRequestException('Unknown $params format', 400, 40000);
            }
        }

        if (!empty($headers)) {
            curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

            foreach($headers as $header) {
                $curl_cmd .= '-H "' . str_replace( '"', '\"', $header ).'" ';
            }
        }

        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        //curl_setopt( $ch, CURLOPT_VERBOSE, $this->getopt('debug') );
        curl_setopt( $ch, CURLOPT_HEADER, true ); // return response headers

        $curl_cmd .= $url;

        Log::d( 'cURL request:', $curl_cmd );

        $raw = curl_exec($ch);
        $info = curl_getinfo($ch);
        $err = curl_errno($ch);
        if ($err) {
            Log::e('cURL error:', $err, curl_error($ch));
        }

        curl_close ($ch);

        if ($err == 28) { // code for timeout, the constant name is inconsistent - either CURLE_OPERATION_TIMEDOUT or CURLE_OPERATION_TIMEOUTED 
            throw new AblyRequestTimeoutException( 'cURL request timed out', 500, 50003 );
        }

        $response = null;

        $headers = substr($raw, 0, $info['header_size']);
        $body = substr($raw, $info['header_size']);
        $decodedBody = json_decode( $body );

        $response = array( 'headers' => $headers, 'body' => $decodedBody ? $decodedBody : $body );

        Log::d( 'cURL request response:', $info['http_code'], $response );

        if ( !in_array( $info['http_code'], array(200,201) ) ) {
            $ablyCode = $decodedBody && isset($decodedBody->error->code) ? $decodedBody->error->code : null;

            throw new AblyRequestException( 'cURL request failed', $info['http_code'], $ablyCode, $response );
        }

        return $response;
    }
}