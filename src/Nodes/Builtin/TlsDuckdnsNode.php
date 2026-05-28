<?php

namespace LoggedCloud\CaddyStudio\Nodes\Builtin;

use LoggedCloud\CaddyStudio\Nodes\CaddyNodeType;

/**
 * A TLS automation policy that issues certs for DuckDNS domains via the ACME
 * DNS-01 challenge · the only way to get certs for *.duckdns.org behind a
 * residential connection. Lists the subjects it covers and the DuckDNS token.
 *
 * Standard public domains need no TLS node · Caddy's default HTTP-01 issuer
 * provisions them automatically.
 */
class TlsDuckdnsNode extends CaddyNodeType
{
    public static function key(): string
    {
        return 'tls.duckdns';
    }

    public static function label(): string
    {
        return 'TLS · DuckDNS';
    }

    public static function icon(): string
    {
        return '🔐';
    }

    public static function group(): string
    {
        return 'tls';
    }

    public static function description(): string
    {
        return 'DNS-01 cert issuance for *.duckdns.org domains.';
    }

    public static function settings(): array
    {
        return [
            'subjects'  => ['kind' => 'text', 'label' => 'Subjects (comma separated)', 'default' => ''],
            'api_token' => ['kind' => 'text', 'label' => 'DuckDNS API token', 'default' => ''],
        ];
    }
}
