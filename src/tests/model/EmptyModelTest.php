<?php

namespace src\tests\model;

use PHPUnit\Framework\TestCase;
use src\model\Empty\EmptyModel;

/** @covers \src\model\Empty\EmptyModel */
class EmptyModelTest extends TestCase
{
    public function testEmptyModel(): void
    {
        $emptyModel = new EmptyModel(uniqid("", true));

        $emptyModel->content = "test";
        $emptyModel->save();

        self::assertTrue($emptyModel->exists());

        $emptyModel->delete();

        self::assertFalse($emptyModel->exists());
    }
}
