# FastDocxReader

Fast and simple DOCX reader for PHP. Extract text and tables from DOCX files, convert DOCX to HTML.

## Features

- Fast and memory-efficient DOCX parsing using `XMLReader`.
- Extract plain text from the entire document or specific parts.
- Extract tables and their content.
- Convert DOCX content to HTML.
- Access document metadata (author, title, etc.).
- Access headers, footers, and images.
- Support for lists (bullets and numbering).

## Installation

You can install the package via composer:

```bash
composer require avadim/fast-docx-reader
```

## Usage

### Open a document

```php
use avadim\FastDocxReader\Docx;

$doc = Docx::open('path/to/file.docx');
```

### Get plain text

You can get the full text of the document:

```php
$text = $doc->getText();
```

Or with specific options:

```php
$text = $doc->getText([
    'headers' => true,
    'footers' => true,
    'tables' => true,
    'footnotes' => true,
    'endnotes' => true,
]);
```

### Convert to HTML

```php
$html = $doc->toHtml();
```

### Iterate through blocks

You can iterate through all content blocks (paragraphs and tables) in the document:

```php
use Avadim\FastDocxReader\Blocks\Paragraph;
use Avadim\FastDocxReader\Blocks\Table;

foreach ($doc->blocks() as $block) {
    if ($block instanceof Paragraph) {
        echo '[Paragraph] ' . $block->getText() . PHP_EOL;
    } elseif ($block instanceof Table) {
        echo '[Table]' . PHP_EOL;
        $rows = $block->getRowsData();
        foreach ($rows as $row) {
            // Process row data
        }
    }
}
```

### Metadata, Headers, and Footers

```php
// Get metadata
$metadata = $doc->getMetadata();

// Iterate headers
foreach ($doc->headers() as $header) {
    echo $header->getName() . ': ' . $header->getText() . PHP_EOL;
}

// Iterate footers
foreach ($doc->footers() as $footer) {
    echo $footer->getName() . ': ' . $footer->getText() . PHP_EOL;
}
```

### Images

```php
foreach ($doc->images() as $image) {
    echo "Image: " . $image->getPartName() . ' (' . $image->getContentType() . ')' . PHP_EOL;
    // Get image binary content
    // $content = $image->getContent();
}
```

## Requirements

- PHP 7.4 or higher
- `ext-zip`
- `ext-xml`
- `ext-xmlreader`

## License

MIT

