<?php

namespace VatsimData;

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
use VatsimData\Helpers\Callsign;
use VatsimData\Helpers\Polygon;

class Datafeed
{
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
        $use_df_cache = Config::get('vatsimdata.use_datafeed_cache');
        $url_cached = Config::get('vatsimdata.datafeed_cached_url');
        $url_uncached = Config::get('vatsimdata.datafeed_uncached_url');
        $url = $use_df_cache ? $url_cached : $url_uncached;

        $cache_key = Config::get('vatsimdata.cache_key');
        $ttl = Config::get('vatsimdata.datafeed_cache_ttl', 60);

        return Cache::remember($cache_key.'datafeed.get', $ttl, fn (): ?RootObject => self::fetch($use_df_cache, $url));
    }

    /** Fetch the feed immediately, refresh its cache entry, and record pilot positions. */
    public static function refresh(): ?RootObject
    {
        $use_df_cache = Config::get('vatsimdata.use_datafeed_cache');
        $url = $use_df_cache
            ? Config::get('vatsimdata.datafeed_cached_url')
            : Config::get('vatsimdata.datafeed_uncached_url');
        $feed = self::fetch($use_df_cache, $url);

        if ($feed === null) {
            return null;
        }

        $cacheKey = Config::get('vatsimdata.cache_key');
        Cache::put($cacheKey.'datafeed.get', $feed, Config::get('vatsimdata.datafeed_cache_ttl', 60));
        self::recordPilotHistory($feed);

        return $feed;
    }

    /**
     * @return array<int, PilotPosition[]> keyed by VATSIM CID
     */
    public static function PilotHistory(): array
    {
        return Cache::get(Config::get('vatsimdata.cache_key').'datafeed.pilot-history', []);
    }

    /**
     * Return actual pilot history followed by predicted positions.
     * Predictions are extrapolated from the most recent two tracked points.
     *
     * @return array<int, PilotTrack> keyed by VATSIM CID
     */
    public static function PilotTracks(): array
    {
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
        $lastTime = new \DateTimeImmutable($last->recorded_at);
        $previousTime = new \DateTimeImmutable($previous->recorded_at);
        $interval = max(1, $lastTime->getTimestamp() - $previousTime->getTimestamp());
        $latitudeDelta = ($last->latitude - $previous->latitude) / $interval;
        $longitudeDelta = ($last->longitude - $previous->longitude) / $interval;
        $altitudeDelta = (int) round(($last->altitude - $previous->altitude) / $interval);
        $predictions = [];

        foreach ([10, 20, 30] as $seconds) {
            $predictions[] = $last->predict($latitudeDelta, $longitudeDelta, $altitudeDelta, $seconds);
        }

        return $predictions;
    }

    private static function fetch(bool $use_df_cache, string $url): ?RootObject
    {
        $data = self::do_curl($url);
        if (! $data) {
            return null;
        }

        $decoded = json_decode($data);
        $payload = $use_df_cache ? ($decoded?->data ?? null) : $decoded;

        return $payload === null ? null : RootObject::fromJson($payload);
    }

    private static function recordPilotHistory(RootObject $feed): void
    {
        $cacheKey = Config::get('vatsimdata.cache_key').'datafeed.pilot-history';
        $history = Cache::get($cacheKey, []);
        $maxPoints = max(1, (int) Config::get('vatsimdata.datafeed_history_count', 5));

        foreach ($feed->pilots as $pilot) {
            $points = array_values(array_filter(
                $history[$pilot->cid] ?? [],
                static fn (mixed $point): bool => $point instanceof PilotPosition,
            ));
            $points[] = PilotPosition::fromPilot($pilot);
            $history[$pilot->cid] = array_slice($points, -$maxPoints);
        }

        Cache::put($cacheKey, $history, Config::get('vatsimdata.datafeed_history_ttl', 86400));
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
        return array_values(array_filter(self::Pilots(), static function (Pilot $pilot) use ($polygon): bool {
            return isset($pilot->latitude, $pilot->longitude)
                && $polygon->contains($pilot->latitude, $pilot->longitude);
        }));
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

        return array_values(array_filter(self::Controllers(), static function (Controller $controller) use ($icao, $includeObservers): bool {
            $callsign = Callsign::parse($controller->callsign);

            return $callsign->airport() === $icao && ($includeObservers || ! $callsign->observer);
        }));
    }

    public static function ControllerForStation(string $ident, string|float $frequency, bool $includeObservers = false): ?ControllerStationMatch
    {
        $stationCallsign = Callsign::parse($ident);
        $stationFrequency = self::NormaliseFrequency($frequency);
        $cacheKey = Config::get('vatsimdata.cache_key').'controller.station.'.$stationCallsign->value.'.'.$stationFrequency.'.'.(int) $includeObservers;

        return Cache::remember($cacheKey, Config::get('vatsimdata.datafeed_cache_ttl', 60), static function () use ($stationCallsign, $stationFrequency, $includeObservers): ?ControllerStationMatch {
            foreach (self::Controllers() as $controller) {
                $controllerCallsign = Callsign::parse($controller->callsign);

                if (
                    $controllerCallsign->matchesStation($stationCallsign)
                    && ($includeObservers || ! $controllerCallsign->observer)
                    && self::NormaliseFrequency($controller->frequency) === $stationFrequency
                ) {
                    return new ControllerStationMatch($controller, $stationCallsign->value, $stationFrequency);
                }
            }

            return null;
        });
    }

    /**
     * @return ControllerWithTransceivers[]
     */
    public static function ControllersWithTransceiversForAerodrome(string $icao, bool $includeObservers = false): array
    {
        $icao = self::NormaliseIcao($icao);
        $cacheKey = Config::get('vatsimdata.cache_key').'controllers.transceivers.'.$icao.'.'.(int) $includeObservers;

        return Cache::remember($cacheKey, Config::get('vatsimdata.transceiver_cache_ttl', 120), static function () use ($icao, $includeObservers): array {
            return array_map(
                static fn (Controller $controller): ControllerWithTransceivers => new ControllerWithTransceivers($controller),
                self::ControllersForAerodrome($icao, $includeObservers),
            );
        });
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
        $cacheKey = Config::get('vatsimdata.cache_key').'aerodrome.summary.'.$icao;

        return Cache::remember($cacheKey, Config::get('vatsimdata.aerodrome_summary_cache_ttl', 60), static function () use ($icao): AerodromeSummary {
            $controllers = self::ControllersForAerodrome($icao);
            $roles = array_fill_keys(['DEL', 'GND', 'TWR', 'APP', 'DEP', 'CTR', 'FSS'], false);

            foreach ($controllers as $controller) {
                $role = Callsign::parse($controller->callsign)->role;
                if ($role !== null) {
                    $roles[$role] = true;
                }
            }

            return new AerodromeSummary(
                $icao,
                count(self::PilotsDepartingFrom($icao)),
                count(self::PilotsArrivingAt($icao)),
                $controllers,
                self::AtisAerodrome($icao),
                $roles,
            );
        });
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

        return array_values(array_filter(self::Pilots(), static function (Pilot $pilot) use ($icao, $field): bool {
            return $pilot->flight_plan !== null && strtoupper($pilot->flight_plan->{$field}) === $icao;
        }));
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
