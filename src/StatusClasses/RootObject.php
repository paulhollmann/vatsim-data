<?php


namespace VatsimData\StatusClasses;

class RootObject
{
    public Data $data;
    /** @var string[] */
    public array $user;
    /** @var string[] */
    public array $metar;

    public static function fromJson(\stdClass $data): self
    {
        $instance = new self();
        $instance->data = Data::fromJson($data->data);
        $instance->user = $data->user;
        $instance->metar = $data->metar;
        return $instance;
    }
}
