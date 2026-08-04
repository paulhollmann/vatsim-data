<?php

namespace VatsimData\StandData;

use VatsimData\DatafeedClasses\Pilot;

final class Aircraft
{
    private ?string $standIndex = null;

    /** @param array<string, mixed>|Pilot $pilot */
    public function __construct(private readonly array|Pilot $pilot) {}

    public function __get(string $name): mixed
    {
        return is_array($this->pilot)
            ? $this->pilot[$name] ?? null
            : (property_exists($this->pilot, $name) ? $this->pilot->{$name} : null);
    }

    public function onStand(): bool
    {
        return $this->standIndex !== null;
    }

    public function getStandIndex(): ?string
    {
        return $this->standIndex;
    }

    public function setStandIndex(string $standIndex): void
    {
        $this->standIndex = $standIndex;
    }

    /** @return array<string, mixed>|Pilot */
    public function pilot(): array|Pilot
    {
        return $this->pilot;
    }

    public function flightPlanAirport(string $field): ?string
    {
        $flightPlan = $this->flight_plan;
        $airport = is_array($flightPlan) ? ($flightPlan[$field] ?? null) : ($flightPlan?->{$field} ?? null);

        return is_string($airport) && $airport !== '' ? strtoupper($airport) : null;
    }
}
