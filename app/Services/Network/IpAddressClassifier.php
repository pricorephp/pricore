<?php

namespace App\Services\Network;

use Symfony\Component\HttpFoundation\IpUtils;

class IpAddressClassifier
{
    private const NON_PUBLIC_IPV4_RANGES = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.88.99.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
    ];

    private const NON_PUBLIC_IPV6_RANGES = [
        '::/96',
        '100::/64',
        '2001::/23',
        '2001:db8::/32',
        '2002::/16',
        'fc00::/7',
        'fe80::/10',
        'fec0::/10',
        'ff00::/8',
    ];

    /**
     * The address in its shortest form, with an IPv4-mapped IPv6 address reduced
     * to the IPv4 address it stands for. Null when it is not an IP address in
     * standard notation: resolvers disagree on forms such as a leading zero.
     */
    public static function canonical(string $address): ?string
    {
        $address = trim($address, '[]');

        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $packed = @inet_pton($address);

        if ($packed === false) {
            return null;
        }

        if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10)."\xff\xff") {
            $packed = substr($packed, 12);
        }

        $canonical = inet_ntop($packed);

        return $canonical === false ? null : $canonical;
    }

    /**
     * Expects an address in canonical form.
     */
    public static function isPublic(string $address): bool
    {
        if (filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false) {
            return false;
        }

        $ranges = str_contains($address, ':')
            ? self::NON_PUBLIC_IPV6_RANGES
            : self::NON_PUBLIC_IPV4_RANGES;

        return ! IpUtils::checkIp($address, $ranges);
    }
}
