# VATSIM Data Laravel Library

This Laravel library allows to easily query VATSIM's data feed, METAR information, and status data. It provides a simple and efficient way to access real-time data for virtual air traffic simulation,
making it ideal for aviation enthusiasts, developers of flight simulation tools, or anyone interested in utilizing VATSIM's data.

## Installation

To install the package, you can use Composer by running the following commands in your Laravel project's root directory.
This package requires configuration to access VATSIM's data. Open the configuration file located at `config/vatsimdata.php` and update the settings as needed.

```bash
composer require paulhollmann/vatsim-data

php artisan vendor:publish --tag=vatsimdata-config
```

## Config

## Usage

### Fetch VATSIM Data Feed

To fetch the latest VATSIM data feed, use the `VatsimData` facade. All results are fully typed, so use your IDE to navigate the objects.

```php
use VatsimData\Datafeed;
// Get the whole datafeed
$datafeed = Datafeed::get();

// Retrieve all online pilots
$pilots = Datafeed::getPilots();

// Retrieve all online controllers
$controllers = Datafeed::getControllers();

// Retrieve all ATIS broadcasts
$atises = Datafeed::Atis();

```

#### Local filters

```php
    $localPilots = Datafeed::PilotsLocal();
```

### Fetch METAR Data

To fetch the latest METAR data for a specific airport:

```php
use VatsimData\Metar;

$metars = Metar::get('eddf'); // Replace 'eddf' with any ICAO code
```

### Fetch Transceiver Data

To fetch the latest transceiver data for a specific controller:

```php
use VatsimData\TransceiverData;

$owner = TransceiverData::Owner('eddf_n_app');
$transceivers = $owner->transceivers;
```

## Caching

The library supports caching of responses to reduce the number of requests to the VATSIM servers.

## Stand status

`VatsimData\StandStatus` assigns nearby, stationary VATSIM pilots to airport parking stands. It replaces the separate stand-status package while retaining its familiar stand input format.

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

The array input is `[stand identifier, latitude, longitude]`; `loadStandDataFromCSV()` accepts the same three columns. `parseData()` uses the cached `Datafeed::Pilots()` result automatically, or you may pass an iterable of `Pilot` objects or legacy pilot arrays directly.

For CAA/Aerospace coordinates, construct it with `StandStatus::COORD_FORMAT_CAA`. `fetchAndLoadStandDataFromOSM('EGLL')` loads OpenStreetMap parking positions and caches them internally for three months. OpenStreetMap-derived data must be attributed as required by the ODbL.

## Contributing

Contributions are welcome! If you'd like to contribute to this library, please follow these steps:

1. Fork the repository.
2. Create a new branch for your feature or bug fix.
3. Implement your changes and add tests as necessary.
4. Submit a pull request detailing your changes.

## License

This project is licensed under the GNU GPLv3 License. See the [LICENSE](LICENSE) file for more information.

## Contact

For any questions or issues, please contact me paul.hollmann@vatger.de
