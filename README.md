# FastDocxReader

Fast and simple DOCX reader for PHP.

## Supported public API

```php
use Avadim\FastDocxReader\Docx;

$doc = Docx::open('example.docx');

echo $doc->getText();

foreach ($doc->blocks() as $block) {
    echo $block->getType() . ': ' . $block->getText() . PHP_EOL;
}
```

## Structure

- `src/Docx.php` — Main reader class
- `src/Model/` — Immutable value objects (Paragraph, Table)

## Requirements

- PHP >= 7.4
- ext-zip
- ext-xml (XMLReader)
