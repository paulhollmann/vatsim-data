<?php

namespace VatsimData;

use Illuminate\Support\Facades\Cache;
use VatsimData\DatafeedClasses\Pilot;
use VatsimData\DatafeedClasses\PilotPosition;
use VatsimData\Helpers\Coordinates;
use VatsimData\StandData\Aircraft;
use VatsimData\StandData\FlightStatus;
use VatsimData\StandData\Stand;

/**
 * Matches VATSIM pilots to airport parking stands.
 *
 * Stand input remains compatible with the legacy package: each row is
 * [identifier, latitude, longitude].
 */
final class StandStatus
{
    public const COORD_FORMAT_DECIMAL = 1;

    public const COORD_FORMAT_CAA = 2;

    /** @var array<string, Stand> */
    private array $stands = [];

    /** @var Aircraft[] */
    private array $aircraft = [];

    private float $maxStandDistance = 0.07;

    private bool $hideStandSidesWhenOccupied = true;

    private float $maxDistanceFromAirport = 2;

    private int $maxAircraftAltitude = 3000;

    private int $maxAircraftGroundspeed = 10;

    /** @var string[] */
    private array $standExtensions = ['L', 'C', 'R', 'A', 'B', 'N', 'E', 'S', 'W'];

    private string $standExtensionPattern = '<standroot><extensions>';

    private ?string $airportIcao;

    public function __construct(
        private readonly float $airportLatitude,
        private readonly float $airportLongitude,
        private readonly int $standCoordinateFormat = self::COORD_FORMAT_DECIMAL,
        ?string $airportIcao = null,
    ) {
        Coordinates::assertValid($airportLatitude, $airportLongitude);
        $this->airportIcao = $airportIcao !== null ? strtoupper(trim($airportIcao)) : null;
    }

    /** @param array<int, array{0: string|int, 1: string|float|int, 2: string|float|int}> $standData */
    public function loadStandDataFromArray(array $standData): self
    {
        $this->stands = [];
        foreach ($standData as $stand) {
            $this->addStand($stand[0], $stand[1], $stand[2]);
        }

        return $this;
    }

    public function loadStandDataFromCSV(string $filePath): self
    {
        $stream = @fopen($filePath, 'r');
        if ($stream === false) {
            throw new \RuntimeException("Unable to load stand data from '{$filePath}'.");
        }

        $this->stands = [];
        while (($row = fgetcsv($stream, 4096, ',', '"', '\\')) !== false) {
            if (count($row) < 3 || ctype_alpha($row[1])) {
                continue;
            }
            $this->addStand($row[0], $row[1], $row[2]);
        }
        fclose($stream);

        return $this;
    }

    /**
     * Download and load OSM parking positions for an ICAO aerodrome.
     *
     * OSM-derived data is cached for three months. Consumers displaying this
     * data must provide the required OpenStreetMap attribution.
     */
    public function fetchAndLoadStandDataFromOSM(string $icao): self
    {
        $icao = strtoupper(trim($icao));
        if (preg_match('/^[A-Z]{4}$/', $icao) !== 1) {
            throw new \InvalidArgumentException("'{$icao}' is not a valid ICAO code.");
        }
        if ($this->standCoordinateFormat !== self::COORD_FORMAT_DECIMAL) {
            throw new \LogicException('OSM stand data requires decimal coordinates.');
        }

        /** @var array<int, array{0: string, 1: float, 2: float}> $stands */
        $stands = Cache::remember('vatsimdata.stands.osm.'.$icao, 60 * 60 * 24 * 90, function (): array {
            $radius = (int) ceil($this->maxDistanceFromAirport * 3000);
            $query = sprintf('[out:json];nwr["aeroway"="parking_position"](around:%d,%F,%F);out tags center;', $radius, $this->airportLatitude, $this->airportLongitude);
            $curl = curl_init('https://overpass-api.de/api/interpreter?data='.rawurlencode($query));
            curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 25]);
            $response = curl_exec($curl);
            $error = curl_error($curl);
            curl_close($curl);
            if (! is_string($response)) {
                throw new \RuntimeException('Unable to download OSM stand data: '.$error);
            }
            $elements = json_decode($response, true, 512, JSON_THROW_ON_ERROR)['elements'] ?? [];
            $stands = [];
            foreach ($elements as $element) {
                $name = $element['tags']['ref'] ?? $element['tags']['name'] ?? null;
                $latitude = $element['lat'] ?? $element['center']['lat'] ?? null;
                $longitude = $element['lon'] ?? $element['center']['lon'] ?? null;
                if (is_string($name) && is_numeric($latitude) && is_numeric($longitude)) {
                    $stands[] = [$name, (float) $latitude, (float) $longitude];
                }
            }

            return $stands;
        });

        return $this->loadStandDataFromArray($stands);
    }

    /** @param iterable<array<string, mixed>|Pilot> $pilots */
    public function parseData(?iterable $pilots = null): self
    {
        if ($this->stands === []) {
            throw new \LogicException('No stand data has been loaded.');
        }

        foreach ($this->stands as $stand) {
            $stand->clear();
        }
        $this->aircraft = [];

        foreach ($pilots ?? Datafeed::Pilots() as $pilot) {
            $aircraft = new Aircraft($pilot);
            if (! $this->isEligible($aircraft)) {
                continue;
            }
            $this->aircraft[] = $aircraft;
            $this->assignNearestStand($aircraft);
        }

        return $this;
    }

    /** @return Stand[] */
    public function allStands(bool $associative = false): array
    {
        return $associative ? $this->stands : array_values($this->stands);
    }

    /** @return Stand[] */
    public function stands(bool $associative = false): array
    {
        $stands = $this->hideStandSidesWhenOccupied
            ? array_filter($this->stands, static fn (Stand $stand): bool => ! $stand->isOccupied() || $stand->occupier->getStandIndex() === $stand->getKey())
            : $this->stands;

        return $associative ? $stands : array_values($stands);
    }

    /** @return Stand[] */
    public function occupiedStands(bool $associative = false): array
    {
        $stands = array_filter($this->stands, static fn (Stand $stand): bool => $stand->isOccupied());

        return $associative ? $stands : array_values($stands);
    }

    /** @return Stand[] */
    public function unoccupiedStands(bool $associative = false): array
    {
        $stands = array_filter($this->stands, static fn (Stand $stand): bool => ! $stand->isOccupied());

        return $associative ? $stands : array_values($stands);
    }

    /** @return Aircraft[] */
    public function allAircraft(): array
    {
        return $this->aircraft;
    }

    /**
     * Calculate an aircraft's current phase from the latest VATSIM snapshot.
     *
     * Set the airport ICAO to distinguish departure and arrival phases.
     */
    public function flightStatus(Aircraft $aircraft): FlightStatus
    {
        $isDeparture = $this->airportIcao !== null && $aircraft->flightPlanAirport('departure') === $this->airportIcao;
        $isArrival = $this->airportIcao !== null && $aircraft->flightPlanAirport('arrival') === $this->airportIcao;

        if ($aircraft->onStand()) {
            return $isArrival ? FlightStatus::ARRIVED_AT_GATE : FlightStatus::AT_GATE;
        }

        if ($aircraft->altitude <= 100) {
            if ($isDeparture) {
                if ($aircraft->groundspeed < 30) {
                    return FlightStatus::TAXI_FOR_DEPARTURE;
                }

                return $aircraft->groundspeed >= 40 ? FlightStatus::TAKING_OFF : FlightStatus::UNKNOWN;
            }

            return $isArrival && $aircraft->groundspeed < 30
                ? FlightStatus::TAXI_TO_GATE
                : FlightStatus::UNKNOWN;
        }

        if ($isArrival) {
            return FlightStatus::ARRIVING;
        }

        if ($isDeparture) {
            return $aircraft->altitude <= 2000 && $aircraft->groundspeed >= 40
                ? FlightStatus::TAKING_OFF
                : FlightStatus::DEPARTING;
        }

        return FlightStatus::UNKNOWN;
    }

    /** @param array<string, mixed>|Pilot $pilot */
    public function calculateFlightStatus(array|Pilot $pilot): FlightStatus
    {
        return $this->flightStatus(new Aircraft($pilot));
    }

    /**
     * @param  iterable<array<string, mixed>|Pilot>|null  $pilots
     * @return array<string, FlightStatus> keyed by callsign
     */
    public function flightStatuses(?iterable $pilots = null): array
    {
        $tracks = Datafeed::PilotTracks();
        $matchedAircraft = [];
        foreach ($this->aircraft as $aircraft) {
            $matchedAircraft[(string) $aircraft->callsign] = $aircraft;
        }

        $statuses = [];
        foreach ($pilots ?? Datafeed::Pilots() as $pilot) {
            $callsign = is_array($pilot) ? (string) ($pilot['callsign'] ?? '') : $pilot->callsign;
            $cid = is_array($pilot) ? (int) ($pilot['cid'] ?? 0) : $pilot->cid;
            $aircraft = $matchedAircraft[$callsign] ?? new Aircraft($pilot);
            $actualPoints = $tracks[$cid]?->actual ?? [];
            $previous = count($actualPoints) >= 2 ? $actualPoints[count($actualPoints) - 2] : null;
            $status = $this->trackedFlightStatus($aircraft, $previous);
            $statuses[$callsign] = $status;
        }

        return $statuses;
    }

    public function setAirportIcao(?string $icao): self
    {
        $this->airportIcao = $icao !== null ? strtoupper(trim($icao)) : null;

        return $this;
    }

    public function getAirportIcao(): ?string
    {
        return $this->airportIcao;
    }

    private function trackedFlightStatus(Aircraft $aircraft, ?PilotPosition $previous): FlightStatus
    {
        $status = $this->flightStatus($aircraft);
        if ($previous === null) {
            return $status;
        }

        $previousObservedAt = new \DateTimeImmutable($previous->recorded_at);
        $verticalSpeed = ((int) $aircraft->altitude - $previous->altitude) / max(1, time() - $previousObservedAt->getTimestamp());
        $distanceTravelled = Coordinates::distance(
            $previous->latitude,
            $previous->longitude,
            (float) $aircraft->latitude,
            (float) $aircraft->longitude,
        );

        if ($status === FlightStatus::UNKNOWN) {
            if ($aircraft->altitude > 100 && $verticalSpeed > 2.5) {
                return $previous->altitude <= 100 ? FlightStatus::TAKING_OFF : FlightStatus::DEPARTING;
            }
            if ($aircraft->altitude > 100 && $verticalSpeed < -2.5) {
                return FlightStatus::ARRIVING;
            }
            if ($aircraft->altitude <= 100 && $previous->groundspeed <= 5 && $distanceTravelled > 0.005) {
                return FlightStatus::TAXI_FOR_DEPARTURE;
            }
        }

        return $status;
    }

    public function setMaxStandDistance(float $distance): self
    {
        $this->maxStandDistance = $distance;

        return $this;
    }

    public function setMaxDistanceFromAirport(float $distance): self
    {
        $this->maxDistanceFromAirport = $distance;

        return $this;
    }

    public function setMaxAircraftAltitude(int $altitude): self
    {
        $this->maxAircraftAltitude = $altitude;

        return $this;
    }

    public function setMaxAircraftGroundspeed(int $speed): self
    {
        $this->maxAircraftGroundspeed = $speed;

        return $this;
    }

    public function setHideStandSidesWhenOccupied(bool $hide): self
    {
        $this->hideStandSidesWhenOccupied = $hide;

        return $this;
    }

    /** @param string[] $extensions */
    public function setStandExtensions(array $extensions): self
    {
        $this->standExtensions = $extensions;

        return $this;
    }

    public function setStandExtensionPattern(string $pattern): self
    {
        $this->standExtensionPattern = $pattern;

        return $this;
    }

    public function getMaxStandDistance(): float
    {
        return $this->maxStandDistance;
    }

    public function getMaxDistanceFromAirport(): float
    {
        return $this->maxDistanceFromAirport;
    }

    public function getMaxAircraftAltitude(): int
    {
        return $this->maxAircraftAltitude;
    }

    public function getMaxAircraftGroundspeed(): int
    {
        return $this->maxAircraftGroundspeed;
    }

    public function getHideStandSidesWhenOccupied(): bool
    {
        return $this->hideStandSidesWhenOccupied;
    }

    /** @return string[] */
    public function getStandExtensions(): array
    {
        return $this->standExtensions;
    }

    public function getStandExtensionPattern(): string
    {
        return $this->standExtensionPattern;
    }

    private function addStand(string|int $id, string|float|int $latitude, string|float|int $longitude): void
    {
        $latitude = $this->standCoordinateFormat === self::COORD_FORMAT_CAA ? Coordinates::caaToDecimal((string) $latitude, false) : (float) $latitude;
        $longitude = $this->standCoordinateFormat === self::COORD_FORMAT_CAA ? Coordinates::caaToDecimal((string) $longitude, true) : (float) $longitude;
        Coordinates::assertValid($latitude, $longitude);
        $stand = new Stand((string) $id, $latitude, $longitude, $this->standExtensions, $this->standExtensionPattern);
        if (isset($this->stands[$stand->id])) {
            throw new \InvalidArgumentException("Stand '{$stand->id}' is defined more than once.");
        }
        $this->stands[$stand->id] = $stand;
    }

    private function isEligible(Aircraft $aircraft): bool
    {
        return Coordinates::distance($aircraft->latitude, $aircraft->longitude, $this->airportLatitude, $this->airportLongitude) < $this->maxDistanceFromAirport
            && $aircraft->altitude <= $this->maxAircraftAltitude
            && $aircraft->groundspeed <= $this->maxAircraftGroundspeed;
    }

    private function assignNearestStand(Aircraft $aircraft): void
    {
        $match = null;
        $distance = INF;
        foreach ($this->stands as $stand) {
            $candidateDistance = Coordinates::distance($stand->latitude, $stand->longitude, $aircraft->latitude, $aircraft->longitude);
            if ($candidateDistance < $this->maxStandDistance && $candidateDistance < $distance) {
                $match = $stand;
                $distance = $candidateDistance;
            }
        }
        if ($match === null) {
            return;
        }
        $aircraft->setStandIndex($match->id);
        $root = $match->getRoot();
        foreach ($this->stands as $stand) {
            if ($stand === $match || ($root !== null && $stand->getRoot() === $root)) {
                $stand->occupier = $aircraft;
            }
        }
    }
}
