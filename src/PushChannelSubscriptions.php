<?php
namespace Ably;

use Ably\Models\PushChannelSubscription;

class PushChannelSubscriptions {

    private $ably;

    /**
     * Constructor
     * @param AblyRest $ably Ably API instance
     */
    public function __construct( AblyRest $ably ) {
        $this->ably = $ably;
    }

    /**
     * Creates a new push channel subscription. Returns a
     * PushChannelSubscription object.
     *
     * @param array $subscription an array with the subscription information
     */
    public function save ( $subscription ) {
        $obj = new PushChannelSubscription( $subscription );
        $path = '/push/channelSubscriptions' ;
        $params = $obj->toArray();
        $body = $this->ably->post( $path, [], json_encode($params) );
        $body = json_decode(json_encode($body), true); // Convert stdClass to array
        return new PushChannelSubscription ( $body );
    }

}
