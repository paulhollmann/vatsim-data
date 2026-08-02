<?php

namespace VatsimData\DatafeedClasses;

/**
 * A controller matched to a station identifier and frequency.
 */
final class ControllerStationMatch
{
    public function __construct(
        public readonly Controller $controller,
        public readonly string $stationIdent,
        public readonly string $stationFrequency,
    ) {}
}
