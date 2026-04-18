<?php

namespace avadim\FastDocxReader\Tests;

use avadim\FastDocxReader\Blocks\Paragraph;
use avadim\FastDocxReader\Blocks\Table;
use avadim\FastDocxReader\Options\PlainTextOptions;
use PHPUnit\Framework\TestCase;

class BlocksTest extends TestCase
{
    public function testParagraphText()
    {
        $xml = '<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:r><w:t>Hello World</w:t></w:r></w:p>';
        $paragraph = new Paragraph($xml);
        
        $this->assertEquals('Hello World', $paragraph->getText());
    }

    public function testParagraphHtml()
    {
        $xml = '<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:r><w:t>Hello World</w:t></w:r></w:p>';
        $paragraph = new Paragraph($xml);
        
        $html = $paragraph->toHtml();
        $this->assertStringContainsString('<p>', $html);
        $this->assertStringContainsString('Hello World', $html);
    }

    public function testTableText()
    {
        // Создаем упрощенную таблицу через конструктор
        $p1 = new Paragraph('<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:r><w:t>Cell 1</w:t></w:r></w:p>');
        $p2 = new Paragraph('<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:r><w:t>Cell 2</w:t></w:r></w:p>');
        
        $rows = [
            [
                'style' => [],
                'cells' => [
                    ['style' => [], 'value' => [$p1]],
                    ['style' => [], 'value' => [$p2]],
                ]
            ]
        ];
        
        $table = new Table($rows, []);
        $options = new PlainTextOptions();
        $options->tableCellSeparator = '|';
        
        $text = $table->getText($options);
        $this->assertStringContainsString('Cell 1|Cell 2', $text);
    }
}
