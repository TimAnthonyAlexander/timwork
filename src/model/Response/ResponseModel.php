<?php

namespace src\model\Response;

use src\model\Data\DataModel;
use src\model\Header\HeaderModel;

class ResponseModel extends DataModel
{
    public int $status = 200;
    public ?string $responseMessage = null;
    public array $data = [];
    public HeaderModel $headers;

    public function __construct() {
        $this->headers = new HeaderModel();
    }

    public function toArray(): array
    {
        $array = get_object_vars($this);

        unset($array['headers']);

        return $array;
    }
}
