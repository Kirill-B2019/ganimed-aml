<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Support;

class TronAddress
{
    private const ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    public static function isTron(string $address): bool
    {
        return (bool) preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $address);
    }

    public static function explorerUrl(string $address): ?string
    {
        $address = self::normalized($address);
        if ($address === null) {
            return null;
        }

        return 'https://tronscan.org/#/address/'.$address;
    }

    public static function contractUrl(string $address): ?string
    {
        $address = self::normalized($address);
        if ($address === null) {
            return null;
        }

        return 'https://tronscan.org/#/contract/'.$address;
    }

    public static function tokenUrl(string $address): ?string
    {
        $address = self::normalized($address);
        if ($address === null) {
            return null;
        }

        return 'https://tronscan.org/#/token20/'.$address;
    }

    private static function normalized(string $address): ?string
    {
        $address = trim($address);
        if ($address === '' || strcasecmp($address, 'unknown') === 0) {
            return null;
        }

        $hex = strtolower(ltrim($address, '0x'));
        if (str_starts_with($hex, '41') && ctype_xdigit($hex) && strlen($hex) >= 42) {
            $address = self::fromHex($hex);
        }

        if (! self::isTron($address) && ! str_starts_with($address, 'T')) {
            return null;
        }

        return $address;
    }

    public static function short(string $address): string
    {
        $address = trim($address);
        if (strlen($address) < 12) {
            return $address;
        }

        return substr($address, 0, 6).'…'.substr($address, -4);
    }

    public static function fromHex(string $hex): string
    {
        $hex = strtolower(ltrim($hex, '0x'));
        if ($hex === '' || ! ctype_xdigit($hex)) {
            return $hex;
        }
        if (! str_starts_with($hex, '41')) {
            $hex = '41'.$hex;
        }
        if (strlen($hex) % 2 !== 0) {
            return $hex;
        }

        $payload = hex2bin($hex);
        if ($payload === false) {
            return $hex;
        }

        $checksum = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);

        return self::base58Encode($payload.$checksum);
    }

    private static function base58Encode(string $bytes): string
    {
        $digits = [0];
        foreach (array_values(unpack('C*', $bytes) ?: []) as $byte) {
            $carry = $byte;
            foreach ($digits as $i => $digit) {
                $carry += $digit << 8;
                $digits[$i] = $carry % 58;
                $carry = intdiv($carry, 58);
            }
            while ($carry > 0) {
                $digits[] = $carry % 58;
                $carry = intdiv($carry, 58);
            }
        }

        $encoded = '';
        foreach (str_split($bytes) as $char) {
            if ($char === "\x00") {
                $encoded .= '1';
            } else {
                break;
            }
        }

        foreach (array_reverse($digits) as $digit) {
            $encoded .= self::ALPHABET[$digit];
        }

        return $encoded;
    }
}
