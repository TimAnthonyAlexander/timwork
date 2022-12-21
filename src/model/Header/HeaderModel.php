<?php

namespace src\model\Header;

use src\model\Data\DataModel;

class HeaderModel extends DataModel
{
    public array $headers = [];

    public function addHeader(string $headerKey, string $headerValue): void
    {
        $this->headers[] = sprintf('%s: %s', $headerKey, $headerValue);
    }

    public function allHeaders(): string
    {
        return implode("\r", $this->headers);
    }

    public function toArray(): array
    {
        return $this->headers;
    }
}
