<?php
namespace Ably\PubSub\Models\Stats;

/**
 * ConnectionTypes contains a breakdown of summary stats data for different
 * (TLS vs non-TLS) connection types
 */
class ConnectionTypes {
    /**
	 * @var \Ably\PubSub\Models\Stats\ResourceCount $all All connection count (includes both TLS & non-TLS connections).
	 * @var \Ably\PubSub\Models\Stats\ResourceCount $plain Non-TLS connection count (unencrypted).
	 * @var \Ably\PubSub\Models\Stats\ResourceCount $tls TLS connection count.
     */
	public $all;
	public $plain;
	public $tls;

	public function __construct() {
		$this->all   = new ResourceCount();
		$this->plain = new ResourceCount();
		$this->tls   = new ResourceCount();
	}
}