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

        return Cache::remember($cache_key.'datafeed.get', 20, function () use ($use_df_cache, $url) {
            $data = self::do_curl($url);
            if (! $data) {
                return null;
            }
            if ($use_df_cache) {
                return RootObject::fromJson(json_decode($data)?->data);
            } else {
                return RootObject::fromJson(json_decode($data));
            }
        });
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
    }

    /**
     * @return ControllerWithTransceivers[]
     */
    public static function ControllersWithTransceiversForAerodrome(string $icao, bool $includeObservers = false): array
    {
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
