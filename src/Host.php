<?php
namespace Ably;

class Host {
    private function _checkExpired($timestamp, $expiration) {
        $result = false;
        if ($expiration !== 0) {
            $timeDiff = time() - $timestamp;
            $result = $timeDiff > $expiration;
        }
        return $result;
    }
}
