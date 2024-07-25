<?php

namespace VatsimData\TransceiverClasses;

class RootObject
{

    /**
     * @param \stdClass $data
     * @return TransceiverOwner[]
     */
    public static function fromJson(\stdClass $data): array
    {
        return
            array_map(static function($data) {
                return RootObject::fromJson($data);
            }, $data->RootObject);
    }
}
