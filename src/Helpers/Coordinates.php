<?php

namespace VatsimData\Helpers;

final class Coordinates
{
    public static function assertValid(float $latitude, float $longitude): void
    {
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new \InvalidArgumentException('Coordinates are outside their valid bounds.');
        }
    }

    public static function distance(float $latitude1, float $longitude1, float $latitude2, float $longitude2): float
    {
        $latitudeDelta = deg2rad($latitude2 - $latitude1);
        $longitudeDelta = deg2rad($longitude2 - $longitude1);
        $a = sin($latitudeDelta / 2) ** 2 + cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * sin($longitudeDelta / 2) ** 2;

        return 6371 * 2 * asin(sqrt($a));
    }

    public static function caaToDecimal(string $coordinate, bool $longitude): float
    {
        $degreesLength = $longitude ? 3 : 2;
        $degrees = (float) substr($coordinate, 0, $degreesLength);
        $minutes = (float) substr($coordinate, $degreesLength, 2);
        $seconds = (float) substr($coordinate, $degreesLength + 2, 5);
        $decimal = $degrees + (($minutes * 60 + $seconds) / 3600);

        return in_array(strtoupper(substr($coordinate, -1)), $longitude ? ['W'] : ['S'], true) ? -$decimal : $decimal;
    }
}
