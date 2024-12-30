<?php

namespace VatsimData\DatafeedClasses;

abstract class Rating
{
    public int $id;

    public string $short;

    public string $long;

    public function __construct(
        int $id,
        string $short,
        string $long
    ) {
        $this->id = $id;
        $this->short = $short;
        $this->long = $long;
    }
}
