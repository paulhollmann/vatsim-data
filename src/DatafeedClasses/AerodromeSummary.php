<?php

namespace VatsimData\DatafeedClasses;

/**
 * Live traffic and ATC information for one ICAO aerodrome.
 */
final class AerodromeSummary
{
    /** @var Controller[] */
    public readonly array $controllers;

    /** @var Atis[] */
    public readonly array $atis;

    /** @var array<string, bool> */
    public readonly array $roles;

    public function __construct(
        public readonly string $icao,
        public readonly int $departures,
        public readonly int $arrivals,
        array $controllers,
        array $atis,
        array $roles,
    ) {
        $this->controllers = $controllers;
        $this->atis = $atis;
        $this->roles = $roles;
    }

    public function hasRole(string $role): bool
    {
        return $this->roles[strtoupper($role)] ?? false;
    }
}
