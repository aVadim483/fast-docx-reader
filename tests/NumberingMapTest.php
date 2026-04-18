<?php

namespace avadim\FastDocxReader\Tests;

use avadim\FastDocxReader\Reader\NumberingMap;
use PHPUnit\Framework\TestCase;

class NumberingMapTest extends TestCase
{
    private $numberingMap;

    protected function setUp(): void
    {
        // Создаем экземпляр без вызова конструктора, чтобы протестировать методы форматирования отдельно
        $this->numberingMap = $this->getMockBuilder(NumberingMap::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    public function testToLetters()
    {
        $reflection = new \ReflectionClass(NumberingMap::class);
        $method = $reflection->getMethod('toLetters');
        $method->setAccessible(true);

        $this->assertEquals('a', $method->invoke($this->numberingMap, 1, false));
        $this->assertEquals('z', $method->invoke($this->numberingMap, 26, false));
        $this->assertEquals('aa', $method->invoke($this->numberingMap, 27, false));
        $this->assertEquals('A', $method->invoke($this->numberingMap, 1, true));
        $this->assertEquals('Z', $method->invoke($this->numberingMap, 26, true));
    }

    public function testToRoman()
    {
        $reflection = new \ReflectionClass(NumberingMap::class);
        $method = $reflection->getMethod('toRoman');
        $method->setAccessible(true);

        $this->assertEquals('i', $method->invoke($this->numberingMap, 1, false));
        $this->assertEquals('iv', $method->invoke($this->numberingMap, 4, false));
        $this->assertEquals('v', $method->invoke($this->numberingMap, 5, false));
        $this->assertEquals('ix', $method->invoke($this->numberingMap, 9, false));
        $this->assertEquals('x', $method->invoke($this->numberingMap, 10, false));
        $this->assertEquals('XL', $method->invoke($this->numberingMap, 40, true));
        $this->assertEquals('L', $method->invoke($this->numberingMap, 50, true));
        $this->assertEquals('XC', $method->invoke($this->numberingMap, 90, true));
        $this->assertEquals('C', $method->invoke($this->numberingMap, 100, true));
        $this->assertEquals('D', $method->invoke($this->numberingMap, 500, true));
        $this->assertEquals('M', $method->invoke($this->numberingMap, 1000, true));
        $this->assertEquals('mcmlxxxiv', $method->invoke($this->numberingMap, 1984, false));
    }

    public function testFormatNumber()
    {
        $reflection = new \ReflectionClass(NumberingMap::class);
        $method = $reflection->getMethod('formatNumber');
        $method->setAccessible(true);

        $this->assertEquals('1', $method->invoke($this->numberingMap, 1, 'decimal'));
        $this->assertEquals('a', $method->invoke($this->numberingMap, 1, 'lowerLetter'));
        $this->assertEquals('A', $method->invoke($this->numberingMap, 1, 'upperLetter'));
        $this->assertEquals('i', $method->invoke($this->numberingMap, 1, 'lowerRoman'));
        $this->assertEquals('I', $method->invoke($this->numberingMap, 1, 'upperRoman'));
    }
}
