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

### Convert to HTML

```php
$html = $doc->toHtml();
```

## Requirements

- PHP 7.4 or higher
- `ext-zip`
- `ext-xml`
- `ext-xmlreader`

## License

MIT

