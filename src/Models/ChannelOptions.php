<?php
namespace Ably\Models;

/**
 * Channel options
 */
class ChannelOptions extends BaseOptions {

    /**
     * @var boolean indicating if the channel should be encrypted
     */
    public $encrypted = false;
    /**
     * @var \Ably\Models\CipherParams parameters of the cipher used on the channel
     */
    public $cipherParams = null;
}