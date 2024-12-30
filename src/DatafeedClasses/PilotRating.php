<?php

namespace VatsimData\DatafeedClasses;

class PilotRating extends Rating
{
    public static function fromJson(object $data): self
    {
        return new self(
            $data->id,
            $data->short_name,
            $data->long_name
        );
    }
}
