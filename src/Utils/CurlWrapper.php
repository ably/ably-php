<?php
namespace Ably\Utils;

/**
 * A wrapper class around cURL functions to allow mocking.
 * Also generates commands pastable to terminal for easier debugging.
 */
class CurlWrapper {
    protected $commands = array();

    public function init( $url = null ) {
        $handle = curl_init( $url );
        $commands[$handle] = array(
            'prefix' => 'curl',
            'command' => ' ',
            'url' => $url ? : '',
        );

        return $handle;
    }

    public function setOpt( $handle, $option, $value ) {
        if ( $option == CURLOPT_URL ) $commands[$handle]['url'] = $value;
        else if ( $option == CURLOPT_POST && $value ) $commands[$handle]['command'] .= '-X POST ';
        else if ( $option == CURLOPT_CUSTOMREQUEST ) $commands[$handle]['command'] .= '-X ' . $value . ' ';
        else if ( $option == CURLOPT_POSTFIELDS ) $commands[$handle]['command'] .= '--data "'. str_replace( '"', '\"', $value ) .'" ';
        else if ( $option == CURLOPT_HTTPHEADER ) {
            foreach($value as $header) {
                $commands[$handle]['command'] .= '-H "' . str_replace( '"', '\"', $header ).'" ';
            }
        }

        return curl_setopt( $handle, $option, $value );
    }

    public function exec( $handle ) {
        return curl_exec( $handle );
    }

    public function close( $handle ) {
        unset( $commands[$handle] );

        return curl_close( $handle );
    }

    public function getInfo( $handle ) {
        return curl_getinfo( $handle );
    }

    public function getErrNo( $handle ) {
        return curl_errno( $handle );
    }

    public function getError( $handle ) {
        return curl_error( $handle );
    }

    /**
     * Retrieve a command pastable to terminal for a handle
     */
    public function getCommand( $handle ) {
        return $commands[$handle]['prefix'] . $commands[$handle]['command'] . $commands[$handle]['url'];
    }
}