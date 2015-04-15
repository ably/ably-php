<?php
namespace Ably\Models;

class Message extends BaseMessage {

    /**
     * @var string|null The event name of the message.
     */
    public $name;
    
    protected function encode() {
        $msg = parent::encode();

        $msg->name = $this->name;

        return $msg;
    }
}