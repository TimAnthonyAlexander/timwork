<?php
/*
 * Copyright (c) 2022. Der Code ist geistiges Eigentum von Tim Anthony Alexander.
 * Der Code wurde geschrieben unter dem Arbeitstitel und im Auftrag von coalla.
 * Verwendung dieses Codes außerhalb von coalla von Dritten ist ohne ausdrückliche Zustimmung von Tim Anthony Alexander nicht gestattet.
 */

namespace src\module\Factory;

use src\module\InstantCache\InstantCache;

class Factory
{
    public static function getClass(
        string $class = Factory::class,
        mixed ...$params
    ): object {
        $paramsHash = md5(json_encode($params, JSON_THROW_ON_ERROR) ?: '');
        $classInstance = InstantCache::isset(sprintf('factories_%s_%s', $class, $paramsHash)) ? clone InstantCache::get(sprintf('factories_%s_%s', $class, $paramsHash)) : new $class(...$params);
        if (!InstantCache::isset(sprintf('factories_%s_%s', $class, $paramsHash))) {
            InstantCache::set(sprintf('factories_%s_%s', $class, $paramsHash), $classInstance);
        }
        assert($classInstance instanceof $class);
        return $classInstance;
    }
}
