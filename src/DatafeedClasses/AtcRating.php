<?php

namespace VatsimData\DatafeedClasses;

class AtcRating extends Rating
{
    public static function fromJson(object $data): self
    {
        return new self(
            $data->id,
            $data->short,
            $data->long
        );
    }
}
