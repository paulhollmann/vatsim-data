<?php

namespace VatsimData\Helpers;

use InvalidArgumentException;

class Polygon
{
    protected array $points = [];

    /**
     * Polygon constructor.
     *
     * @param  string  $wktPolygon  WKT polygon string: "POLYGON((lon lat, lon lat, ...))"
     *
     * @throws InvalidArgumentException
     */
    public function __construct(string $wktPolygon)
    {
        $this->points = $this->parseWktPolygon($wktPolygon);
    }

    /**
     * Validate if a string is a valid WKT polygon.
     */
    public static function isValidWkt(string $wktPolygon): bool
    {
        return (bool) preg_match('/^POLYGON\s*\(\(\s*(?:-?\d+(\.\d+)?\s+-?\d+(\.\d+)?\s*,?\s*)+\)\)$/i', trim($wktPolygon));
    }

    /**
     * Parse WKT polygon string into array of points.
     *
     * @return array [['lat'=>..,'lon'=>..], ...]
     *
     * @throws InvalidArgumentException
     */
    protected function parseWktPolygon(string $wkt): array
    {
        if (! self::isValidWkt($wkt)) {
            throw new InvalidArgumentException("Invalid WKT polygon: $wkt");
        }

        preg_match('/POLYGON\s*\(\(\s*(.+)\s*\)\)/i', $wkt, $matches);

        $points = [];
        foreach (explode(',', $matches[1]) as $pair) {
            [$lon, $lat] = array_map('floatval', preg_split('/\s+/', trim($pair)));
            $points[] = ['lat' => $lat, 'lon' => $lon];
        }

        return $points;
    }

    /**
     * Check if a given position is inside this polygon.
     */
    public function contains(float $lat, float $lon): bool
    {
        $inside = false;
        $j = count($this->points) - 1;

        for ($i = 0; $i < count($this->points); $i++) {
            $xi = $this->points[$i]['lon'];
            $yi = $this->points[$i]['lat'];
            $xj = $this->points[$j]['lon'];
            $yj = $this->points[$j]['lat'];

            $intersect = (($yi > $lat) != ($yj > $lat)) &&
                ($lon < ($xj - $xi) * ($lat - $yi) / ($yj - $yi + 0.0) + $xi);

            if ($intersect) {
                $inside = ! $inside;
            }

            $j = $i;
        }

        return $inside;
    }

    /**
     * Get raw points of the polygon.
     *
     * @return array [['lat'=>..,'lon'=>..], ...]
     */
    public function getPoints(): array
    {
        return $this->points;
    }
}
