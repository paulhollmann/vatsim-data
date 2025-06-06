<?php

namespace VatsimData\DatafeedClasses;

class Facility
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

    public static function fromJson(object $data): self
    {
        return new self(
            $data->id,
            $data->short,
            $data->long
        );
    }
}
