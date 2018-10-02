<?php
namespace Ably;

class Push {

    private $ably;

    /**
     * Constructor
     * @param AblyRest $ably Ably API instance
     */
    public function __construct( AblyRest $ably ) {
        $this->ably = $ably;
        $this->admin = new PushAdmin( $ably );
    }

}
