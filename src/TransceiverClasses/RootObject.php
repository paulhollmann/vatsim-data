<?php

namespace VatsimData\TransceiverClasses;

use stdClass;

class RootObject
{

    /**
     * @param stdClass|array $data
     * @return TransceiverOwner[]
     */
    public static function fromJson(stdClass|array $data): array
    {
        return
            array_map(static function($data) {
                return RootObject::fromJson($data);
            }, $data->RootObject);
    }
}
