<?php

namespace VatsimData;

use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use VatsimData\DatafeedClasses\AerodromeSummary;
use VatsimData\DatafeedClasses\Atis;
use VatsimData\DatafeedClasses\Controller;
use VatsimData\DatafeedClasses\ControllerStationMatch;
use VatsimData\DatafeedClasses\ControllerWithTransceivers;
use VatsimData\DatafeedClasses\Pilot;
use VatsimData\DatafeedClasses\PilotPosition;
use VatsimData\DatafeedClasses\PilotTrack;
use VatsimData\DatafeedClasses\RootObject;
use VatsimData\Helpers\CacheFreshness;
use VatsimData\Helpers\Callsign;
use VatsimData\Helpers\Coordinates;
use VatsimData\Helpers\Polygon;

class Datafeed
{
    /** Request-local typed feed. This prevents each facade method from retaining another full graph. */
    private static ?RootObject $requestFeed = null;

    /** Request-local decoded payload used by scoped methods. */
    private static ?object $requestPayloadData = null;

    /** @var array<string, Pilot[]> */
    private static array $requestPilotsByCallsign = [];

    private static function do_curl(string $url): string|bool
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        $data = curl_exec($ch);
        curl_close($ch);

        return $data;
    }

    public static function get(): ?RootObject
    {
        if (self::$requestFeed !== null) {
            return self::$requestFeed;
        }

        $use_df_cache = Config::get('vatsimdata.use_datafeed_cache');
        $url_cached = Config::get('vatsimdata.datafeed_cached_url');
        $url_uncached = Config::get('vatsimdata.datafeed_uncached_url');
        $url = $use_df_cache ? $url_cached : $url_uncached;

        $cache_key = Config::get('vatsimdata.cache_key');
        $ttl = Config::get('vatsimdata.datafeed_cache_ttl', 15);

        $cacheKey = $cache_key.'datafeed.get';
        $payload = Cache::get($cacheKey);

        if (is_string($payload)) {
            $feed = self::hydrate($payload, $use_df_cache);

            if ($feed !== null && self::hasMinimumPilotCount($feed)) {
                return self::$requestFeed = $feed;
            }

            Cache::forget($cacheKey);
        } else {
            if ($payload !== null) {
                Cache::forget($cacheKey);
            }
        }

        $payload = self::fetch($url);

        if ($payload === null) {
            return self::lastKnownGoodFeed($use_df_cache);
        }

        $feed = self::hydrate($payload, $use_df_cache);

        if ($feed === null || ! self::hasMinimumPilotCount($feed)) {
            return self::lastKnownGoodFeed($use_df_cache);
        }

        self::cacheAcceptedFeed($payload, (int) $ttl);

        return self::$requestFeed = $feed;
    }

    /** Fetch the feed immediately, refresh its cache entry, and record pilot positions. */
    public static function refresh(): ?RootObject
    {
        $use_df_cache = Config::get('vatsimdata.use_datafeed_cache');
        $url = $use_df_cache
            ? Config::get('vatsimdata.datafeed_cached_url')
            : Config::get('vatsimdata.datafeed_uncached_url');
        $payload = self::fetch($url);

        if ($payload === null) {
            return self::lastKnownGoodFeed($use_df_cache);
        }

        $feed = self::hydrate($payload, $use_df_cache);

        if ($feed === null || ! self::hasMinimumPilotCount($feed)) {
            return self::lastKnownGoodFeed($use_df_cache);
        }

        self::cacheAcceptedFeed($payload, (int) Config::get('vatsimdata.datafeed_cache_ttl', 15));
        self::recordPilotHistory($feed);
        self::cachePilotTracks();

        self::$requestPayloadData = null;
        self::$requestPilotsByCallsign = [];

        return self::$requestFeed = $feed;
    }

    /**
     * Return when this package last fetched the datafeed successfully.
     */
    public static function FetchedAt(): ?DateTimeImmutable
    {
        return CacheFreshness::get(Config::get('vatsimdata.cache_key').'datafeed.get');
    }

    /**
     * Return VATSIM's own update timestamp from the current datafeed.
     */
    public static function UpdatedAt(): ?DateTimeImmutable
    {
        $timestamp = self::get()?->general->update_timestamp;

        if (! is_string($timestamp)) {
            return null;
        }

        try {
            return new DateTimeImmutable($timestamp);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, PilotPosition[]> keyed by VATSIM CID
     */
    public static function PilotHistory(): array
    {
        $cachedHistory = Cache::get(Config::get('vatsimdata.cache_key').'datafeed.pilot-history', []);

        if (! is_array($cachedHistory)) {
            return [];
        }

        $history = [];

        foreach ($cachedHistory as $cid => $points) {
            if ((! is_int($cid) && (! is_string($cid) || ! ctype_digit($cid))) || ! is_array($points)) {
                continue;
            }

            foreach ($points as $point) {
                if (! is_array($point)) {
                    continue;
                }

                $position = PilotPosition::fromArray($point);

                if ($position !== null) {
                    $history[(int) $cid][] = $position;
                }
            }
        }

        return $history;
    }

    /**
     * Return actual pilot history followed by predicted positions.
     * Predictions are extrapolated from the most recent two tracked points.
     *
     * @return array<int, PilotTrack> keyed by VATSIM CID
     */
    public static function PilotTracks(): array
    {
        $trackCacheKey = Config::get('vatsimdata.cache_key').'datafeed.pilot-tracks.v2';
        $cachedTracks = Cache::get($trackCacheKey);
        if (is_array($cachedTracks)) {
            $tracks = [];
            foreach ($cachedTracks as $cid => $track) {
                if (is_array($track) && ($hydrated = PilotTrack::fromArray($track)) !== null) {
                    $tracks[(int) $cid] = $hydrated;
                }
            }

            return $tracks;
        }

        $history = self::PilotHistory();
        $tracks = [];

        foreach ($history as $cid => $points) {
            $actualPoints = array_slice(
                array_values(array_filter(
                    $points,
                    static fn (mixed $point): bool => $point instanceof PilotPosition && ! $point->predicted,
                )),
                -5,
            );
            $tracks[$cid] = new PilotTrack($actualPoints, self::predictPilotPositions($actualPoints));
        }

        Cache::put(
            $trackCacheKey,
            array_map(static fn (PilotTrack $track): array => $track->toArray(), $tracks),
            Config::get('vatsimdata.datafeed_history_ttl', 86400),
        );

        return $tracks;
    }

    /**
     * Return tracks for only the requested CIDs. Cached points remain arrays
     * until this method is called, so an airport request does not hydrate the
     * history of every aircraft online.
     *
     * @param  iterable<int|string>  $cids
     * @return array<int, PilotTrack>
     */
    public static function PilotTracksForCids(iterable $cids): array
    {
        $wanted = [];
        foreach ($cids as $cid) {
            if (is_int($cid) || (is_string($cid) && ctype_digit($cid))) {
                $wanted[(int) $cid] = true;
            }
        }
        if ($wanted === []) {
            return [];
        }

        $cachedTracks = Cache::get(Config::get('vatsimdata.cache_key').'datafeed.pilot-tracks.v2');
        if (! is_array($cachedTracks)) {
            $cachedTracks = [];
        }

        $tracks = [];
        foreach (array_keys($wanted) as $cid) {
            $track = $cachedTracks[$cid] ?? $cachedTracks[(string) $cid] ?? null;
            if (is_array($track) && ($hydrated = PilotTrack::fromArray($track)) !== null) {
                $tracks[$cid] = $hydrated;
            }
        }

        return $tracks;
    }

    /**
     * @param  PilotPosition[]  $points
     * @return PilotPosition[]
     */
    private static function predictPilotPositions(array $points): array
    {
        if (count($points) < 2) {
            return [];
        }

        $last = $points[count($points) - 1];
        $previous = $points[count($points) - 2];
        $lastTime = new DateTimeImmutable($last->recorded_at);
        $samples = array_slice($points, -3);
        $sampleTimes = array_map(
            static fn (PilotPosition $point): int => (new DateTimeImmutable($point->recorded_at))->getTimestamp() - $lastTime->getTimestamp(),
            $samples,
        );

        // Duplicate timestamps cannot describe a curve; fall back to a straight line.
        $hasDistinctTimes = count(array_unique($sampleTimes)) === count($sampleTimes);
        $predictions = [];

        $lastLatitude = $last->latitude;
        $lastLongitude = $last->longitude;
        $originLatitude = $last->latitude;
        $originLongitude = $last->longitude;
        $lastForwardProgress = 0.0;
        $movementLatitude = $last->latitude - $previous->latitude;
        $movementLongitude = $last->longitude - $previous->longitude;
        $movementLengthSquared = ($movementLatitude ** 2) + ($movementLongitude ** 2);

        foreach ([5, 10, 15, 20, 25] as $seconds) {
            if ($hasDistinctTimes && count($samples) >= 3) {
                $latitude = self::extrapolateCoordinate($samples, $sampleTimes, $seconds, 'latitude');
                $longitude = self::extrapolateCoordinate($samples, $sampleTimes, $seconds, 'longitude');
                $altitude = (int) round(self::extrapolateCoordinate($samples, $sampleTimes, $seconds, 'altitude'));
            } else {
                $previous = $points[count($points) - 2];
                $interval = max(1, $lastTime->getTimestamp() - (new DateTimeImmutable($previous->recorded_at))->getTimestamp());
                $latitude = $last->latitude + (($last->latitude - $previous->latitude) / $interval * $seconds);
                $longitude = $last->longitude + (($last->longitude - $previous->longitude) / $interval * $seconds);
                $altitude = (int) round($last->altitude + (($last->altitude - $previous->altitude) / $interval * $seconds));
            }

            if ($movementLengthSquared > 0.000000000001) {
                $projectedLatitude = $latitude - $originLatitude;
                $projectedLongitude = $longitude - $originLongitude;
                $forwardProgress = ($projectedLatitude * $movementLatitude) + ($projectedLongitude * $movementLongitude);

                // A quadratic fit can overshoot during hard braking. Never draw a
                // future point behind the latest actual position or a prior
                // predicted point.
                if ($forwardProgress < $lastForwardProgress) {
                    $latitude = $lastLatitude;
                    $longitude = $lastLongitude;
                    $forwardProgress = $lastForwardProgress;
                }

                $lastForwardProgress = $forwardProgress;
            }

            $lastLatitude = $latitude;
            $lastLongitude = $longitude;

            $predictions[] = new PilotPosition(
                $latitude,
                $longitude,
                $altitude,
                $last->groundspeed,
                $last->heading,
                $lastTime->modify(sprintf('+%d seconds', $seconds))->format(DateTimeImmutable::ATOM),
                true,
            );
        }

        return $predictions;
    }

    /**
     * Evaluate a quadratic through the latest three samples at a future offset.
     * This is intentionally local: recent turns are useful, older route history is not.
     *
     * @param  PilotPosition[]  $samples
     * @param  int[]  $sampleTimes
     */
    private static function extrapolateCoordinate(array $samples, array $sampleTimes, int $seconds, string $coordinate): float
    {
        $value = 0.0;

        foreach ($samples as $index => $sample) {
            $term = (float) $sample->{$coordinate};
            foreach ($sampleTimes as $otherIndex => $otherTime) {
                if ($index !== $otherIndex) {
                    $term *= ($seconds - $otherTime) / ($sampleTimes[$index] - $otherTime);
                }
            }
            $value += $term;
        }

        return $value;
    }

    private static function fetch(string $url): ?string
    {
        $data = self::do_curl($url);
        if (! is_string($data) || $data === '') {
            return null;
        }

        return $data;
    }

    private static function hydrate(string $payload, bool $useDatafeedCache): ?RootObject
    {
        $decoded = json_decode($payload);
        $data = $useDatafeedCache ? ($decoded?->data ?? null) : $decoded;

        try {
            return is_object($data) ? RootObject::fromJson($data) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function hasMinimumPilotCount(RootObject $feed): bool
    {
        return count($feed->pilots) >= 50;
    }

    private static function cacheAcceptedFeed(string $payload, int $ttl): void
    {
        $cacheKey = Config::get('vatsimdata.cache_key').'datafeed.get';
        Cache::put($cacheKey, $payload, $ttl);
        Cache::put(
            $cacheKey.'.last-known-good',
            $payload,
            Config::get('vatsimdata.datafeed_stale_cache_ttl', 86400),
        );
        CacheFreshness::record($cacheKey, $ttl);
    }

    private static function lastKnownGoodFeed(bool $useDatafeedCache): ?RootObject
    {
        $payload = Cache::get(Config::get('vatsimdata.cache_key').'datafeed.get.last-known-good');
        if (! is_string($payload)) {
            return null;
        }

        $feed = self::hydrate($payload, $useDatafeedCache);

        return self::$requestFeed = $feed;
    }

    private static function recordPilotHistory(RootObject $feed): void
    {
        $cacheKey = Config::get('vatsimdata.cache_key').'datafeed.pilot-history';
        $history = self::PilotHistory();
        $maxPoints = max(1, (int) Config::get('vatsimdata.datafeed_history_count', 5));

        foreach ($feed->pilots as $pilot) {
            $points = array_values(array_filter(
                $history[$pilot->cid] ?? [],
                static fn (mixed $point): bool => $point instanceof PilotPosition,
            ));
            $points[] = PilotPosition::fromPilot($pilot);
            $history[$pilot->cid] = array_slice($points, -$maxPoints);
        }

        $serializableHistory = [];

        foreach ($history as $cid => $points) {
            $serializableHistory[$cid] = array_map(
                static fn (PilotPosition $point): array => $point->toArray(),
                array_values(array_filter(
                    $points,
                    static fn (mixed $point): bool => $point instanceof PilotPosition,
                )),
            );
        }

        Cache::put($cacheKey, $serializableHistory, Config::get('vatsimdata.datafeed_history_ttl', 86400));
    }

    private static function cachePilotTracks(): void
    {
        $tracks = [];
        foreach (self::PilotHistory() as $cid => $points) {
            $actualPoints = array_slice(array_values(array_filter(
                $points,
                static fn (mixed $point): bool => $point instanceof PilotPosition && ! $point->predicted,
            )), -5);
            $tracks[$cid] = new PilotTrack($actualPoints, self::predictPilotPositions($actualPoints));
        }

        Cache::put(
            Config::get('vatsimdata.cache_key').'datafeed.pilot-tracks.v2',
            array_map(static fn (PilotTrack $track): array => $track->toArray(), $tracks),
            Config::get('vatsimdata.datafeed_history_ttl', 86400),
        );
    }

    /**
     * @return Pilot[]
     */
    public static function Pilots(): array
    {
        $df = self::get();

        return $df ? $df->pilots : [];
    }

    /**
     * Return pilots within a radius of an aerodrome. The coordinates are
     * supplied by the caller because VATSIM's live feed does not contain an
     * aerodrome catalogue.
     *
     * @return Pilot[]
     */
    public static function PilotsNearAerodrome(
        string $icao,
        float $latitude,
        float $longitude,
        float $radiusKm = 2.0,
    ): array {
        Coordinates::assertValid($latitude, $longitude);
        if ($radiusKm < 0 || ! is_finite($radiusKm)) {
            throw new \InvalidArgumentException('Aerodrome radius must be a finite, non-negative value.');
        }

        self::NormaliseIcao($icao);
        $pilots = self::pilotsFromPayload();

        return array_values(array_filter($pilots, static function (Pilot $pilot) use ($latitude, $longitude, $radiusKm): bool {
            return Coordinates::distance($latitude, $longitude, $pilot->latitude, $pilot->longitude) <= $radiusKm;
        }));
    }

    /** @return array<string, array{latitude: float, longitude: float}> keyed by callsign */
    public static function PilotCoordinatesByCallsign(): array
    {
        if (self::$requestPilotsByCallsign !== []) {
            return array_map(static fn (Pilot $pilot): array => [
                'latitude' => $pilot->latitude,
                'longitude' => $pilot->longitude,
            ], self::$requestPilotsByCallsign);
        }

        foreach (self::pilotsFromPayload() as $pilot) {
            self::$requestPilotsByCallsign[(string) $pilot->callsign] = $pilot;
        }

        return array_map(static fn (Pilot $pilot): array => [
            'latitude' => $pilot->latitude,
            'longitude' => $pilot->longitude,
        ], self::$requestPilotsByCallsign);
    }

    /**
     * @return Pilot[]
     */
    public static function PilotsLocal(): array
    {
        $polygonWkt = Config::get('vatsimdata.local_airspace_polygon');

        return self::PilotsWithinPolygon(new Polygon($polygonWkt));
    }

    /**
     * @return Pilot[]
     */
    public static function PilotsWithinPolygon(Polygon $polygon): array
    {
        $cacheKey = Config::get('vatsimdata.cache_key').'datafeed.pilots.polygon.v2.'.sha1(json_encode($polygon->getPoints()));
        $pilotCids = Cache::remember($cacheKey, Config::get('vatsimdata.datafeed_cache_ttl', 15), static function () use ($polygon): array {
            return array_values(array_map(
                static fn (Pilot $pilot): int => $pilot->cid,
                array_filter(self::Pilots(), static function (Pilot $pilot) use ($polygon): bool {
                    return isset($pilot->latitude, $pilot->longitude)
                        && $polygon->contains($pilot->latitude, $pilot->longitude);
                }),
            ));
        });

        return self::pilotsForCids($pilotCids);
    }

    public static function PilotsArrivingAerodrome(string $icao): array
    {
        return self::PilotsArrivingAt($icao);
    }

    /**
     * @return Pilot[]
     */
    public static function PilotsArrivingAt(string $icao): array
    {
        return self::PilotsForFlightPlanAirport($icao, 'arrival');
    }

    /**
     * @return Pilot[]
     */
    public static function PilotsDepartingFrom(string $icao): array
    {
        return self::PilotsForFlightPlanAirport($icao, 'departure');
    }

    /**
     * @return Controller[]
     */
    public static function Controllers(): array
    {
        $df = self::get();

        return $df ? $df->controllers : [];
    }

    /**
     * @return Controller[]
     */
    public static function ControllersLocal(): array
    {
        $local_atc_pattern = Config::get('vatsimdata.local_atc_pattern');

        return array_values(array_filter(self::ControllersActive(), static function (Controller $controller) use ($local_atc_pattern): bool {
            return preg_match($local_atc_pattern, $controller->callsign) === 1;
        }));
    }

    /**
     * @return Controller[]
     */
    public static function ControllersActive(): array
    {
        return array_values(array_filter(self::Controllers(), static function (Controller $controller): bool {
            return ! Callsign::parse($controller->callsign)->observer;
        }));
    }

    /**
     * @return Controller[]
     */
    public static function ControllersForAerodrome(string $icao, bool $includeObservers = false): array
    {
        $icao = self::NormaliseIcao($icao);

        return array_values(array_filter(self::controllersFromPayload(), static function (Controller $controller) use ($icao, $includeObservers): bool {
            $callsign = Callsign::parse($controller->callsign);

            return $callsign->airport() === $icao && ($includeObservers || ! $callsign->observer);
        }));
    }

    public static function ControllerForStation(string $ident, string|float $frequency, bool $includeObservers = false): ?ControllerStationMatch
    {
        $stationCallsign = Callsign::parse($ident);
        $stationFrequency = self::NormaliseFrequency($frequency);
        $cacheKey = Config::get('vatsimdata.cache_key').'datafeed.controller.station.v2.'.$stationCallsign->value.'.'.$stationFrequency.'.'.(int) $includeObservers;
        $controllerCid = Cache::remember($cacheKey, Config::get('vatsimdata.datafeed_cache_ttl', 15), static function () use ($stationCallsign, $stationFrequency, $includeObservers): int {
            foreach (self::Controllers() as $controller) {
                $controllerCallsign = Callsign::parse($controller->callsign);

                if (
                    $controllerCallsign->matchesStation($stationCallsign)
                    && ($includeObservers || ! $controllerCallsign->observer)
                    && self::NormaliseFrequency($controller->frequency) === $stationFrequency
                ) {
                    return $controller->cid;
                }
            }

            return 0;
        });

        if (! is_int($controllerCid)) {
            return null;
        }

        foreach (self::Controllers() as $controller) {
            if ($controller->cid === $controllerCid) {
                return new ControllerStationMatch($controller, $stationCallsign->value, $stationFrequency);
            }
        }

        return null;
    }

    /**
     * @return ControllerWithTransceivers[]
     */
    public static function ControllersWithTransceiversForAerodrome(string $icao, bool $includeObservers = false): array
    {
        $icao = self::NormaliseIcao($icao);

        return array_map(
            static fn (Controller $controller): ControllerWithTransceivers => new ControllerWithTransceivers($controller),
            self::ControllersForAerodrome($icao, $includeObservers),
        );
    }

    /**
     * @return Atis[]
     */
    public static function Atis(): array
    {
        $df = self::get();

        return $df ? $df->atis : [];
    }

    /**
     * @return Atis[]
     */
    public static function AtisAerodrome(string $icao): array
    {
        $icao = self::NormaliseIcao($icao);
        $all_atises = self::Atis();
        $matches = [];
        foreach ($all_atises as $atis) {
            if (Callsign::parse($atis->callsign)->airport() === $icao) {
                $matches[] = $atis;
            }
        }

        return $matches;
    }

    public static function AerodromeSummary(string $icao): AerodromeSummary
    {
        $icao = self::NormaliseIcao($icao);
        $cacheKey = Config::get('vatsimdata.cache_key').'aerodrome.summary.v2.'.$icao;
        $summary = Cache::remember($cacheKey, Config::get('vatsimdata.aerodrome_summary_cache_ttl', 60), static function () use ($icao): array {
            $controllers = self::ControllersForAerodrome($icao);
            $roles = array_fill_keys(['DEL', 'GND', 'TWR', 'APP', 'DEP', 'CTR', 'FSS'], false);

            foreach ($controllers as $controller) {
                $role = Callsign::parse($controller->callsign)->role;
                if ($role !== null) {
                    $roles[$role] = true;
                }
            }

            return [
                'departures' => count(self::PilotsDepartingFrom($icao)),
                'arrivals' => count(self::PilotsArrivingAt($icao)),
                'controller_cids' => array_values(array_map(static fn (Controller $controller): int => $controller->cid, $controllers)),
                'atis_callsigns' => array_values(array_map(static fn (Atis $atis): string => $atis->callsign, self::AtisAerodrome($icao))),
                'roles' => $roles,
            ];
        });

        $controllers = self::controllersForCids($summary['controller_cids'] ?? []);
        $atises = self::atisForCallsigns($summary['atis_callsigns'] ?? []);
        $roles = is_array($summary['roles'] ?? null) ? $summary['roles'] : [];

        return new AerodromeSummary(
            $icao,
            (int) ($summary['departures'] ?? 0),
            (int) ($summary['arrivals'] ?? 0),
            $controllers,
            $atises,
            $roles,
        );
    }

    /**
     * @param  iterable<string>  $icaos
     * @return array<string, AerodromeSummary>
     */
    public static function AerodromeSummaries(iterable $icaos): array
    {
        $summaries = [];
        foreach ($icaos as $icao) {
            $icao = self::NormaliseIcao($icao);
            $summaries[$icao] = self::AerodromeSummary($icao);
        }

        return $summaries;
    }

    /**
     * @return Pilot[]
     */
    private static function PilotsForFlightPlanAirport(string $icao, string $field): array
    {
        $icao = self::NormaliseIcao($icao);

        return array_values(array_filter(self::pilotsFromPayload(), static function (Pilot $pilot) use ($icao, $field): bool {
            return $pilot->flight_plan !== null && strtoupper($pilot->flight_plan->{$field}) === $icao;
        }));
    }

    /** @return Pilot[] */
    private static function pilotsFromPayload(): array
    {
        if (self::$requestFeed !== null) {
            return self::$requestFeed->pilots;
        }

        $data = self::payloadData();
        if (! is_array($data->pilots ?? null)) {
            return [];
        }

        $pilots = [];
        foreach ($data->pilots as $pilotData) {
            if (is_object($pilotData)) {
                try {
                    $pilots[] = Pilot::fromJson($pilotData);
                } catch (\Throwable) {
                    // Ignore malformed individual records, as RootObject does.
                }
            }
        }

        return $pilots;
    }

    /** @return Controller[] */
    private static function controllersFromPayload(): array
    {
        if (self::$requestFeed !== null) {
            return self::$requestFeed->controllers;
        }

        $data = self::payloadData();
        if (! is_array($data->controllers ?? null)) {
            return [];
        }

        $controllers = [];
        foreach ($data->controllers as $controllerData) {
            if (is_object($controllerData)) {
                try {
                    $controllers[] = Controller::fromJson($controllerData);
                } catch (\Throwable) {
                    // Ignore malformed individual records, as RootObject does.
                }
            }
        }

        return $controllers;
    }

    private static function payloadData(): ?object
    {
        if (self::$requestPayloadData !== null) {
            return self::$requestPayloadData;
        }

        $useDatafeedCache = (bool) Config::get('vatsimdata.use_datafeed_cache');
        $payload = Cache::get(Config::get('vatsimdata.cache_key').'datafeed.get');
        if (! is_string($payload)) {
            $url = $useDatafeedCache ? Config::get('vatsimdata.datafeed_cached_url') : Config::get('vatsimdata.datafeed_uncached_url');
            $payload = self::fetch($url);
        }
        if (! is_string($payload)) {
            return null;
        }

        $decoded = json_decode($payload);
        $data = $useDatafeedCache ? ($decoded?->data ?? null) : $decoded;

        return self::$requestPayloadData = is_object($data) ? $data : null;
    }

    /**
     * @param  array<int, mixed>  $cids
     * @return Pilot[]
     */
    private static function pilotsForCids(array $cids): array
    {
        $pilotsByCid = [];

        foreach (self::pilotsFromPayload() as $pilot) {
            $pilotsByCid[$pilot->cid] = $pilot;
        }

        return array_values(array_filter(array_map(
            static fn (mixed $cid): ?Pilot => is_int($cid) ? ($pilotsByCid[$cid] ?? null) : null,
            $cids,
        )));
    }

    /**
     * @param  array<int, mixed>  $cids
     * @return Controller[]
     */
    private static function controllersForCids(array $cids): array
    {
        $controllersByCid = [];

        foreach (self::controllersFromPayload() as $controller) {
            $controllersByCid[$controller->cid] = $controller;
        }

        return array_values(array_filter(array_map(
            static fn (mixed $cid): ?Controller => is_int($cid) ? ($controllersByCid[$cid] ?? null) : null,
            $cids,
        )));
    }

    /**
     * @param  array<int, mixed>  $callsigns
     * @return Atis[]
     */
    private static function atisForCallsigns(array $callsigns): array
    {
        $atisesByCallsign = [];

        foreach (self::Atis() as $atis) {
            $atisesByCallsign[$atis->callsign] = $atis;
        }

        return array_values(array_filter(array_map(
            static fn (mixed $callsign): ?Atis => is_string($callsign) ? ($atisesByCallsign[$callsign] ?? null) : null,
            $callsigns,
        )));
    }

    private static function NormaliseIcao(string $icao): string
    {
        return strtoupper(trim($icao));
    }

    private static function NormaliseFrequency(string|float $frequency): string
    {
        return number_format((float) $frequency, 3, '.', '');
    }
}
