# VATSIM Data

`vatsim-data` is a Laravel package for VATSIM's live network data. It provides typed pilots, controllers, ATIS, transceivers, METARs, aerodrome summaries, and airport stand occupancy in one package.

All live data access is cached internally. Application code queries the API and receives typed objects; it does not need to download, parse, or cache VATSIM payloads itself.

## Requirements

- PHP 8.3 or later
- Laravel 11, 12, or 13

## Installation

```bash
composer require paulhollmann/vatsim-data
php artisan vendor:publish --tag=vatsimdata-config
```

The published `config/vatsimdata.php` controls the VATSIM endpoint, cache-key prefix, and optional local-airspace helpers. The global methods described below do not depend on any VATSIM Germany-specific configuration.

### Cache lifetime

All cache entries are internal and use Laravel's configured cache store. The defaults balance live-data freshness against repeated slow requests:

| Environment variable | Default | Cached data |
| --- | ---: | --- |
| `VATSIM_DATAFEED_CACHE_TTL` | 60 seconds | Main VATSIM datafeed and derived station lookups |
| `VATSIM_DATAFEED_HISTORY_COUNT` | 5 | Movement points retained per pilot |
| `VATSIM_DATAFEED_HISTORY_TTL` | 86400 seconds | Lifetime of pilot movement history |
| `VATSIM_METAR_CACHE_TTL` | 300 seconds | METAR responses per ICAO code |
| `VATSIM_TRANSCEIVER_CACHE_TTL` | 120 seconds | Transceiver data and controller transceiver lookups |
| `VATSIM_AERODROME_SUMMARY_CACHE_TTL` | 60 seconds | Aerodrome summaries |

OpenStreetMap stand data is cached for three months. Change a TTL only when your application needs a different freshness/performance trade-off; no cache calls are required in application code.

## Live datafeed

```php
use VatsimData\Datafeed;

$feed = Datafeed::get();          // ?RootObject
$pilots = Datafeed::Pilots();     // Pilot[]
$controllers = Datafeed::Controllers(); // Controller[]
$atis = Datafeed::Atis();         // Atis[]
$history = Datafeed::PilotHistory(); // array<int, PilotPosition[]>
$tracks = Datafeed::PilotTracks(); // array<int, PilotTrack>
```

### Refresh worker and movement history

The package registers `vatsimdata:refresh`, which fetches the feed immediately, refreshes the main datafeed cache, and appends the current position of every pilot to a bounded history keyed by VATSIM CID. The default history contains the latest five actual points per pilot. `PilotTracks()` returns a `PilotTrack` for each CID. Each track contains the actual points and five predicted points at exactly 5, 10, 15, 20, and 25 seconds after the latest point. The predicted path follows a local quadratic fitted through the latest three actual positions, so recent turns are carried into the short projection. Backtracking caused by a sharp slowdown is clamped so the predicted path never reverses behind the latest valid point. Predicted points have `predicted === true`; actual points have `predicted === false`. Every point contains latitude, longitude, altitude, groundspeed, heading, and `recorded_at`.

Run it from Laravel's scheduler, for example in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('vatsimdata:refresh')->everyMinute()->withoutOverlapping();
```

Then run `php artisan schedule:work` (or configure your normal scheduler worker). The refresh command also precomputes and caches `PilotTrack` objects, so flightpath predictions are ready without recalculating them during an airport-view request. `StandStatus::parseData()` similarly precomputes its default airport flight-status map after stand assignment. The history and derived data are stored in the configured Laravel cache store, so use a shared store when multiple application instances collect or read it.

`RootObject`, `Pilot`, `Controller`, `Atis`, `FlightPlan`, and related classes are typed DTOs in the `VatsimData\DatafeedClasses` namespace. For example:

```php
foreach (Datafeed::Pilots() as $pilot) {
    echo $pilot->callsign;
    echo $pilot->latitude;
    echo $pilot->flight_plan?->departure;
}
```

### Pilot queries

```php
Datafeed::PilotsArrivingAt('EDDF');
Datafeed::PilotsDepartingFrom('EDDF');

// Existing compatibility method; it delegates to PilotsArrivingAt().
Datafeed::PilotsArrivingAerodrome('EDDF');
```

ICAO input is normalized, and pilots without a flight plan are ignored by arrival/departure queries.

For geographic filtering, pass the included polygon helper:

```php
use VatsimData\Helpers\Polygon;

$polygon = new Polygon('POLYGON((...))');
$pilots = Datafeed::PilotsWithinPolygon($polygon);
```

`PilotsLocal()` remains available as a convenience wrapper around the polygon configured in `vatsimdata.local_airspace_polygon`.

### Controllers, callsigns, and stations

```php
use VatsimData\Datafeed;
use VatsimData\Helpers\Callsign;

$active = Datafeed::ControllersActive();
$eddfControllers = Datafeed::ControllersForAerodrome('EDDF');
$withTransceivers = Datafeed::ControllersWithTransceiversForAerodrome('EDDF');

$callsign = Callsign::parse('EDDF_N_TWR');
$callsign->airport(); // 'EDDF'
$callsign->role;      // 'TWR'
$callsign->observer;  // false
```

`ControllersActive()` is global and excludes observers. `ControllersLocal()` is retained for applications using the configured regional callsign pattern.

To resolve a controller from station data, supply the station ident and frequency:

```php
$match = Datafeed::ControllerForStation('EDDF_TWR', 118.7);

$controller = $match?->controller;       // ?Controller
$ident = $match?->stationIdent;
$frequency = $match?->stationFrequency;
```

The matcher normalizes frequencies and accepts sectorised controller callsigns, so `EDDF_N_TWR` can match an `EDDF_TWR` station at the same frequency.

### Aerodrome summaries

`AerodromeSummary` combines a single airport's live controllers, ATIS, active controller roles, arrivals, and departures.

```php
$summary = Datafeed::AerodromeSummary('EDDF');

$summary->departures;       // int
$summary->arrivals;         // int
$summary->controllers;      // Controller[]
$summary->atis;             // Atis[]
$summary->roles;            // array<string, bool>
$summary->hasRole('TWR');   // bool
```

For airport lists:

```php
$summaries = Datafeed::AerodromeSummaries(['EDDF', 'EDDM', 'LOWW']);
$eddf = $summaries['EDDF'];
```

### ATIS, METAR, and transceivers

```php
use VatsimData\Metar;
use VatsimData\TransceiverData;

$atis = Datafeed::AtisAerodrome('EDDF');
$metar = Metar::get('EDDF');

$owner = TransceiverData::Owner('EDDF_N_TWR');
$transceivers = $owner?->transceivers ?? [];
```

## Stand status

`VatsimData\StandStatus` replaces the separate `vatsim-stand-status` package. It associates eligible VATSIM pilots with the nearest airport parking stand.

### Quick start

```php
use VatsimData\StandStatus;

$stands = new StandStatus(51.148056, -0.190278);

$stands->loadStandDataFromArray([
    ['43N', 51.15712, -0.17373],
    ['43W', 51.15712, -0.17373],
])->parseData();

foreach ($stands->occupiedStands() as $stand) {
    echo $stand->getName().' '.$stand->occupier->callsign;
}
```

`parseData()` reads the cached `Datafeed::Pilots()` result by default. For tests or application-owned sources, pass an iterable of typed `Pilot` objects or the legacy pilot-array shape:

```php
$stands->parseData([
    [
        'callsign' => 'TEST1',
        'latitude' => 51.15712,
        'longitude' => -0.17373,
        'altitude' => 0,
        'groundspeed' => 0,
    ],
]);
```

### Stand input

The legacy input contract is retained. Every stand row has exactly three values: `[identifier, latitude, longitude]`.

```php
$stands->loadStandDataFromArray([
    ['1', 51.154819, -0.164813],
    ['10', 51.155090, -0.164660],
]);

$stands->loadStandDataFromCSV(storage_path('stands.csv'));
```

CSV files use the same three columns; an optional header row is accepted.

For CAA/Aerospace coordinate input, use the legacy-compatible format constant:

```php
$stands = new StandStatus(
    51.148056,
    -0.190278,
    StandStatus::COORD_FORMAT_CAA,
);
```

### OpenStreetMap stand data

```php
$stands = new StandStatus(51.4775, -0.461389); // EGLL
$stands->fetchAndLoadStandDataFromOSM('EGLL')->parseData();
```

This downloads OSM `aeroway=parking_position` data around the airport and caches it internally for three months. OSM data may be incomplete; consumers displaying or redistributing it must provide OpenStreetMap attribution as required by the ODbL.

### Matching settings

All setters are fluent. Run `parseData()` again after changing one.

| Setter | Default | Meaning |
| --- | ---: | --- |
| `setMaxStandDistance(float $km)` | `0.07` km | Maximum aircraft-to-stand distance. |
| `setMaxDistanceFromAirport(float $km)` | `2` km | Aircraft outside this airport-centre radius are ignored. |
| `setMaxAircraftAltitude(int $feet)` | `3000` ft | Aircraft above this altitude are ignored. |
| `setMaxAircraftGroundspeed(int $knots)` | `10` kt | Aircraft faster than this are ignored. |
| `setHideStandSidesWhenOccupied(bool $hide)` | `true` | Hides related stands such as `42L` and `42R`. |
| `setStandExtensions(array $extensions)` | `['L', 'C', 'R', 'A', 'B', 'N', 'E', 'S', 'W']` | Defines side-stand suffixes. |
| `setStandExtensionPattern(string $pattern)` | `'<standroot><extensions>'` | Defines how stand groups are detected. |

### Results

```php
$all = $stands->allStands();
$visible = $stands->stands();
$occupied = $stands->occupiedStands();
$unoccupied = $stands->unoccupiedStands();
$aircraft = $stands->allAircraft();

// Pass true for arrays keyed by stand identifier.
$standsByName = $stands->allStands(true);
```

Each returned `Stand` exposes `id`, `latitude`, `longitude`, `occupier`, `isOccupied()`, `getName()`, `getRoot()`, and `getExtension()`. `Aircraft` exposes the VATSIM pilot fields through properties such as `callsign`, `latitude`, `longitude`, `altitude`, and `groundspeed`, plus `onStand()` and `getStandIndex()`.

### Flight phases

Set the airport ICAO as the optional fourth constructor argument (or with `setAirportIcao()`) to calculate a typed `FlightStatus` for pilots around an airport:

```php
use VatsimData\StandData\FlightStatus;

$stands = new StandStatus(50.033333, 8.570556, StandStatus::COORD_FORMAT_DECIMAL, 'EDDF');

$status = $stands->calculateFlightStatus($pilot);

if ($status === FlightStatus::TAXI_FOR_DEPARTURE) {
    // …
}
```

The possible values are `AT_GATE`, `TAXI_FOR_DEPARTURE`, `TAKING_OFF`, `DEPARTING`, `ARRIVING`, `TAXI_TO_GATE`, `ARRIVED_AT_GATE`, and `UNKNOWN`.

`flightStatuses()` calculates statuses for every current VATSIM pilot; pass an iterable of `Pilot` objects or legacy arrays to classify a supplied data set. The classifier is snapshot-based: it uses stand occupancy, altitude, groundspeed, and flight-plan departure/arrival ICAOs. An aircraft not on a stand and at airport-surface altitude is classified as `TAXI_FOR_DEPARTURE` or `TAXI_TO_GATE` when its groundspeed is below 30 knots. It therefore returns `UNKNOWN` where a current snapshot cannot establish a reliable phase, rather than inferring a route-specific status.

`flightStatuses()` uses the first-class pilot history exposed by `Datafeed::PilotTracks()`. This improves transitions such as climb, descent, and gate departure movement when a flight plan is missing, without adding a second tracking store or per-aircraft cache writes.

Record history by scheduling the built-in refresh command at the interval your application needs (one minute is a sensible default):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('vatsimdata:refresh')->everyMinute();
```

The command refreshes the datafeed cache and stores a compact position history per VATSIM CID. History length and retention are controlled by `VATSIM_DATAFEED_HISTORY_COUNT` (default `5`) and `VATSIM_DATAFEED_HISTORY_TTL` (default `86400` seconds).

## License

GPL-3.0-only. See [LICENSE](LICENSE).
