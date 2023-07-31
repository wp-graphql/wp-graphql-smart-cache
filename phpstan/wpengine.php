<?php

class WpeCommon {
    /**
     * @param string $method HTTP method, e.g. "PURGE"
     * @param string $hostname string to use for the "Host" header on the target machine (null to copy $domain)
     * @param array<string>  $headers
     *
     * @return bool
     */
    public static function http_to_varnish( $method = 'PURGE', $hostname = null, $headers = [] ) {
    }
}
