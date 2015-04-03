<?php
require_once 'AblyExceptions.php';

/**
 * Provides automatic pagination for applicable requests
 *
 * Requests for channel history, channel presence, stats, etc. are automatically
 * wrapped in this class. Instances of this class are created automatically by
 * the Ably API.
 */
class PaginatedResource extends ArrayObject {

    private $ably;
    private $path;
    private $paginationHeaders = false;

    /**
     * Constructor.
     * @param AblyRest $ably Ably API instance
     * @param string $path Request path
     * @param array $params Parameters to be sent with the request
     */
    public function __construct( AblyRest $ably, $path, $params = array() ) {
        parent::__construct();

        $this->ably = $ably;
        $this->path = $path;

        $withHeaders = true;
        $response = $this->ably->get( $path, $this->ably->auth_headers(), $params, $withHeaders );

        if (isset($response['body']) && is_array($response['body'])) {
            $this->exchangeArray( $response['body'] );
            $this->parseHeaders( $response['headers'] );
        }
    }


    /*
     * Public methods
     */

    /**
     * @return boolean whether the fetched results have multiple pages
     */
    public function isPaginated() {
        return is_array($this->paginationHeaders) && !empty($this->paginationHeaders);
    }

    /**
     * @return boolean whether the current page is the first, always true for single-page results
     */
    public function isFirstPage() {
        if (!$this->isPaginated() ) {
            return true;
        }
        
        if ( isset($this->paginationHeaders['first']) && isset($this->paginationHeaders['current'])
            && $this->paginationHeaders['first'] == $this->paginationHeaders['current'] ) {
            return true;
        }

        return false;
    }

    /**
     * @return boolean whether the current page is the last, always true for single-page results
     */
    public function isLastPage() {
        if (!$this->isPaginated() || !isset($this->paginationHeaders['next']) ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Fetches the first page of results
     * @return PaginatedResource returns self if the current page is the first
     */
    public function getFirstPage() {
        if ($this->isFirstPage()) {
            return this;
        } else if (isset($this->paginationHeaders['first'])) {
            return new PaginatedResource( $this->ably, $this->paginationHeaders['first']);
        } else {
            return null;
        }
    }

    /**
     * Fetches the next page of results
     * @return PaginatedResource or null if the current page is the last
     */
    public function getNextPage() {
        if ($this->isPaginated() && isset($this->paginationHeaders['next'])) {
            return new PaginatedResource( $this->ably, $this->paginationHeaders['next']);
        } else {
            return null;
        }
    }


    /*
     * Private methods
     */

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
                throw new AblyException("Server error - only relative URLs are supported in pagination");
            }

            $this->paginationHeaders[$rel] = $path.substr($link, 2);
        }
    }
}