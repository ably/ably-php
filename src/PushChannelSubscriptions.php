<?php
namespace Ably;

use Ably\Models\PaginatedResult;
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

    /**
     *  Returns a PaginatedResult object with the list of PushChannelSubscription
     *  objects, filtered by the given parameters.
     *
     *  @param array $params the parameters used to filter the list
     */
    public function list_ (array $params = []) {
        $path = '/push/channelSubscriptions';
        return new PaginatedResult( $this->ably, 'Ably\Models\PushChannelSubscription',
                                    $cipher = false, 'GET', $path, $params );
    }

}
