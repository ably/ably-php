<?php
namespace Ably\Models;

use Ably\Exceptions\AblyException;

/**
 * Provides automatic pagination for applicable requests
 *
 * Requests for channel history, channel presence and app stats are wrapped in this class automatically.
 */
class PaginatedResult {

    private $ably;
    private $path;
    private $model;
    private $cipherParams;
    private $paginationHeaders = false;

    /**
     * @var array Array of returned models (either Message, PresenceMessage or Stats)
     */
    public $items = array();

    /**
     * Constructor.
     * @param \Ably\AblyRest $ably Ably API instance
     * @param mixed $model Name of a class that will populate this ArrayObject. It must implement a fromJSON() method.
     * @param CipherParams|null $cipherParams Optional cipher parameters if data should be decoded
     * @param string $path Request path
     * @param array $params Parameters to be sent with the request
     */
    public function __construct( \Ably\AblyRest $ably, $model, $cipherParams, $path, $params = array() ) {
        $this->ably = $ably;
        $this->model = $model;
        $this->cipherParams = $cipherParams;
        $this->path = $path;

        $response = $this->ably->get( $path, $headers = array(), $params, $withHeaders = true );

        if (isset($response['body']) && is_array($response['body'])) {

            $transformedArray = array();

            foreach ($response['body'] as $data) {

                $instance = new $model;

                if ( !method_exists( $model, 'fromJSON' ) ) {
                    throw new AblyException( 'Invalid model class provided: ' . $model . '. The model needs to implement fromJSON method.' );
                }
                if (!empty( $cipherParams ) ) {
                    $instance->setCipherParams( $cipherParams );
                }
                $instance->fromJSON( $data );

                $transformedArray[] = $instance;
            }

            $this->items = $transformedArray;
            $this->parseHeaders( $response['headers'] );
        }
    }

    /**
     * Fetches the first page of results
     * @return PaginatedResult Returns self if the current page is the first
     */
    public function first() {
        if (isset($this->paginationHeaders['first'])) {
            return new PaginatedResult( $this->ably, $this->model, $this->cipherParams, $this->paginationHeaders['first'] );
        } else {
            return null;
        }
    }

    /**
     * @return boolean Whether there is a first page
     */
    public function hasFirst() {
        return $this->isPaginated() && isset($this->paginationHeaders['first']);
    }

    /**
     * Fetches the next page of results
     * @return PaginatedResult|null Next page or null if the current page is the last
     */
    public function next() {
        if ($this->isPaginated() && isset($this->paginationHeaders['next'])) {
            return new PaginatedResult( $this->ably, $this->model, $this->cipherParams, $this->paginationHeaders['next'] );
        } else {
            return null;
        }
    }

    /**
     * @return boolean Whether there is a next page
     */
    public function hasNext() {
        return $this->isPaginated() && isset($this->paginationHeaders['next']);
    }

    /**
     * @return boolean Whether the current page is the last, always true for single-page results
     */
    public function isLast() {
        if (!$this->isPaginated() || !isset($this->paginationHeaders['next']) ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return boolean Whether the fetched results can be paginated (pagination headers received)
     */
    public function isPaginated() {
        return is_array($this->paginationHeaders) && count($this->paginationHeaders);
    }

    /**
     * Parses HTTP headers for pagination links
     */
    private function parseHeaders($headers) {

        $path = preg_replace('/\/[^\/]*$/', '/', $this->path);

        preg_match_all('/Link: *\<([^\>]*)\>; *rel="([^"]*)"/', $headers, $matches, PREG_SET_ORDER);

        if (!$matches) return;

        $this->paginationHeaders = array();

        foreach ($matches as $m) {
            $link = $m[1];
            $rel =  $m[2];

            if (substr($link, 0, 2) != './') {
                throw new AblyException( "Server error - only relative URLs are supported in pagination" );
            }

            $this->paginationHeaders[$rel] = $path.substr($link, 2);
        }
    }
}
