<?php

namespace avadim\FastDocxReader\Tests;

use avadim\FastDocxReader\Docx;
use avadim\FastDocxReader\Options\PlainTextOptions;
use PHPUnit\Framework\TestCase;

class DocxTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = __DIR__ . '/fixtures';
    }

    public function testOpen()
    {
        $file = $this->fixtureDir . '/test.docx';
        $docx = Docx::open($file);
        $this->assertInstanceOf(Docx::class, $docx);
    }

    public function testGetText()
    {
        $file = $this->fixtureDir . '/test.docx';
        $docx = Docx::open($file);
        
        $options = new PlainTextOptions();
        $options->blockSeparator = "\n";
        $text = $docx->getText($options);
        
        $this->assertIsString($text);
        // Мы не знаем точного содержимого test.docx, но он должен успешно прочитаться
        $this->assertNotEmpty($text);
    }

    public function testToHtml()
    {
        $file = $this->fixtureDir . '/test.docx';
        $docx = Docx::open($file);
        
        $html = $docx->toHtml();
        $this->assertIsString($html);
        $this->assertStringContainsString('<p', $html);
    }

    public function testGetPaperSize()
    {
        $file = $this->fixtureDir . '/test.docx';
        $docx = Docx::open($file);
        
        $size = $docx->getPaperSize();
        $this->assertIsArray($size);
        $this->assertArrayHasKey('w', $size);
        $this->assertArrayHasKey('h', $size);
    }

    public function testGetMargins()
    {
        $file = $this->fixtureDir . '/test.docx';
        $docx = Docx::open($file);
        
        $margins = $docx->getMargins();
        $this->assertIsArray($margins);
        $this->assertArrayHasKey('top', $margins);
        $this->assertArrayHasKey('right', $margins);
        $this->assertArrayHasKey('bottom', $margins);
        $this->assertArrayHasKey('left', $margins);
    }
}
