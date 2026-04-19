<?php

namespace avadim\FastDocxReader\Options;

class PlainTextOptions
{
    public string $blockSeparator = "\n\n";
    public string $breakChar = "\n";
    public string $tabChar = "\t";
    public string $tableRowSeparator = "\n";
    public string $tableCellSeparator = "\t";

    public bool $normalizeWhitespace = true;
    public bool $trimBlockText = true;

    public bool $includeListMarkers = true;
    public string $bulletMarker = "- ";

    public bool $ignoreImages = false;
    public string $imagePlaceholder = "[[IMAGE]]";
}