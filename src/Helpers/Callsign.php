<?php

namespace VatsimData\Helpers;

/**
 * A parsed VATSIM callsign.
 *
 * Airport codes are only exposed when the first callsign component is a
 * four-character ICAO identifier. Callsigns which use another convention are
 * still parsed safely and retain their prefix and position.
 */
final class Callsign
{
    /** @var string[] */
    private const CONTROLLER_ROLES = ['DEL', 'GND', 'TWR', 'APP', 'DEP', 'CTR', 'FSS'];

    public readonly string $value;

    public readonly string $prefix;

    public readonly ?string $role;

    public readonly bool $observer;

    private function __construct(string $value, string $prefix, ?string $role, bool $observer)
    {
        $this->value = $value;
        $this->prefix = $prefix;
        $this->role = $role;
        $this->observer = $observer;
    }

    public static function parse(string $callsign): self
    {
        $value = strtoupper(trim($callsign));
        $parts = array_values(array_filter(explode('_', $value), static fn (string $part): bool => $part !== ''));
        $prefix = $parts[0] ?? $value;
        $position = $parts[count($parts) - 1] ?? '';
        $observer = $position === 'OBS';

        return new self(
            $value,
            $prefix,
            in_array($position, self::CONTROLLER_ROLES, true) ? $position : null,
            $observer,
        );
    }

    public function airport(): ?string
    {
        return preg_match('/^[A-Z]{4}$/', $this->prefix) === 1 ? $this->prefix : null;
    }

    public function isControllerPosition(): bool
    {
        return $this->role !== null;
    }

    public function matchesStation(self $station): bool
    {
        if ($this->value === $station->value) {
            return true;
        }

        return $this->airport() !== null
            && $this->airport() === $station->airport()
            && $this->role !== null
            && $this->role === $station->role;
    }
}
