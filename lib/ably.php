<?php
namespace Ably;

function ably_autoloader( $class ) {
	if (strpos($class, 'Ably\\') !== 0) { // attempt auto-loading only classes from the Ably namespace
		return;
	}

	require_once ( dirname(__FILE__) . '/' . str_replace('\\', '/', $class) . '.php' );
}

spl_autoload_register('\Ably\ably_autoloader', true, true);
