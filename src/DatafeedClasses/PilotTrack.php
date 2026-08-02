<?php

namespace VatsimData\DatafeedClasses;

class PilotTrack
{
    /** @var PilotPosition[] */
    public readonly array $actual;

    /** @var PilotPosition[] */
    public readonly array $predicted;

    /** @var PilotPosition[] Actual points followed by five predicted points. */
    public readonly array $points;

    /**
     * @param  PilotPosition[]  $actual
     * @param  PilotPosition[]  $predicted
     */
    public function __construct(array $actual, array $predicted)
    {
        $this->actual = $actual;
        $this->predicted = $predicted;
        $this->points = array_merge($actual, $predicted);
    }

    /** @return array{actual: array<int, array<string, mixed>>, predicted: array<int, array<string, mixed>>} */
    public function toArray(): array
    {
        return [
            'actual' => array_map(static fn (PilotPosition $point): array => $point->toArray(), $this->actual),
            'predicted' => array_map(static fn (PilotPosition $point): array => $point->toArray(), $this->predicted),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): ?self
    {
        $actual = self::positionsFromArray($data['actual'] ?? null);
        $predicted = self::positionsFromArray($data['predicted'] ?? null);

        return $actual === null || $predicted === null ? null : new self($actual, $predicted);
    }

    /** @return PilotPosition[]|null */
    private static function positionsFromArray(mixed $data): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        $positions = [];
        foreach ($data as $point) {
            if (! is_array($point) || ($position = PilotPosition::fromArray($point)) === null) {
                return null;
            }
            $positions[] = $position;
        }

        return $positions;
    }
}
