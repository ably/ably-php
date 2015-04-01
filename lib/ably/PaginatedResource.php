<?php

class PaginatedResource extends ArrayObject {

    private $ably;
    private $domain;
    private $path;
    private $paginationHeaders = false;

    /*
     * Constructor
     */
    public function __construct( AblyRest $ably, $domain, $path, $params = array() ) {
        parent::__construct();

        $this->ably = $ably;
        $this->domain = $domain;
        $this->path = $path;

        $withHeaders = true;
        $response = $this->ably->get( $domain, $path, $this->ably->auth_headers(), $params, $withHeaders );

        if (isset($response['body'])) {
            $this->exchangeArray( $response['body'] );
            $this->parseHeaders( $response['headers'] );
        }
    }


    /*
     * Public methods
     */
    public function isPaginated() {
        return is_array($this->paginationHeaders) && !empty($this->paginationHeaders);
    }

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

    public function isLastPage() {
        if (!$this->isPaginated() || !isset($this->paginationHeaders['next']) ) {
            return true;
        } else {
            return false;
        }
    }

    public function getFirstPage() {
        if ($this->isPaginated() && !empty($this->paginationHeaders['first'])) {
            return new PaginatedResource( $this->ably, $this->domain, $this->paginationHeaders['first'], array());
        } else {
            return null;
        }
    }

    public function getNextPage() {
        if ($this->isPaginated() && !empty($this->paginationHeaders['next'])) {
            return new PaginatedResource( $this->ably, $this->domain, $this->paginationHeaders['next'], array());
        } else {
            return null;
        }
    }


    /*
     * Private methods
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
                throw new Exception("Only relative URLs supported in pagination", 1);
            }

            $this->paginationHeaders[$rel] = $path.substr($link, 2);
        }
    }
}