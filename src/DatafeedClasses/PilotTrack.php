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
}
