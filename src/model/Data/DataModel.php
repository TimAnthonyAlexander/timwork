<?php

namespace src\model\Data;

abstract class DataModel
{
    /**
     * This returns all protected properties of the inheriting class.
     * @return array
     */
    public function toArray(): array {
        return get_object_vars($this);
    }
}
