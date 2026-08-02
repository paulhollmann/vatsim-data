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

    /**
     * Return a cache-safe, version-stable representation of this position.
     *
     * @return array{latitude: float, longitude: float, altitude: int, groundspeed: int, heading: int, recorded_at: string, predicted: bool}
     */
    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'altitude' => $this->altitude,
            'groundspeed' => $this->groundspeed,
            'heading' => $this->heading,
            'recorded_at' => $this->recorded_at,
            'predicted' => $this->predicted,
        ];
    }

    /**
     * Hydrate a position from a cache-safe representation.
     *
     * Invalid data returns null so stale cache values never escape into callers.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $requiredKeys = ['latitude', 'longitude', 'altitude', 'groundspeed', 'heading', 'recorded_at', 'predicted'];

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $data)) {
                return null;
            }
        }

        if (
            ! is_numeric($data['latitude'])
            || ! is_numeric($data['longitude'])
            || ! is_numeric($data['altitude'])
            || ! is_numeric($data['groundspeed'])
            || ! is_numeric($data['heading'])
            || ! is_string($data['recorded_at'])
            || ! is_bool($data['predicted'])
        ) {
            return null;
        }

        $latitude = (float) $data['latitude'];
        $longitude = (float) $data['longitude'];
        $altitude = (float) $data['altitude'];
        $groundspeed = (float) $data['groundspeed'];
        $heading = (float) $data['heading'];

        if (
            ! is_finite($latitude)
            || ! is_finite($longitude)
            || ! is_finite($altitude)
            || ! is_finite($groundspeed)
            || ! is_finite($heading)
            || $latitude < -90 || $latitude > 90
            || $longitude < -180 || $longitude > 180
            || $altitude !== floor($altitude)
            || $groundspeed !== floor($groundspeed)
            || $heading !== floor($heading)
            || $groundspeed < 0
            || $heading < 0 || $heading > 360
        ) {
            return null;
        }

        try {
            $recordedAt = new DateTimeImmutable($data['recorded_at']);
        } catch (\Exception) {
            return null;
        }

        return new self(
            $latitude,
            $longitude,
            (int) $altitude,
            (int) $groundspeed,
            (int) $heading,
            $recordedAt->format(DateTimeImmutable::ATOM),
            $data['predicted'],
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
