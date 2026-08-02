<?php

namespace VatsimData\DatafeedClasses;

use DateTimeImmutable;

class PilotPosition
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly int $altitude,
        public readonly int $groundspeed,
        public readonly int $heading,
        public readonly string $recorded_at,
        public readonly bool $predicted = false,
    ) {}

    public function predict(float $latitudeDelta, float $longitudeDelta, int $altitudeDelta, int $seconds): self
    {
        $recordedAt = new DateTimeImmutable($this->recorded_at);

        return new self(
            $this->latitude + ($latitudeDelta * $seconds),
            $this->longitude + ($longitudeDelta * $seconds),
            $this->altitude + ($altitudeDelta * $seconds),
            $this->groundspeed,
            $this->heading,
            $recordedAt->modify(sprintf('+%d seconds', $seconds))->format(DateTimeImmutable::ATOM),
            true,
        );
    }

    public static function fromPilot(Pilot $pilot): self
    {
        return new self(
            $pilot->latitude,
            $pilot->longitude,
            $pilot->altitude,
            $pilot->groundspeed,
            $pilot->heading,
            (new DateTimeImmutable)->format(DateTimeImmutable::ATOM),
        );
    }
}
