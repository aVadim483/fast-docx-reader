<?php

namespace avadim\FastDocxReader\Reader;

use XMLReader;

class RelationshipMap
{
    /** @var array */
    protected array $relMap = [];

    /**
     * @param string $file
     */
    public function __construct(string $file)
    {
        $xmlReader = new Reader($file);
        if ($xmlReader->openZip('word/_rels/document.xml.rels')) {
            $this->parse($xmlReader);
        }
        $xmlReader->close();
    }

    /**
     * @param XMLReader $xmlReader
     * @return void
     */
    public function parse(XMLReader $xmlReader): void
    {
        while ($xmlReader->read()) {
            if ($xmlReader->nodeType === XMLReader::ELEMENT && $xmlReader->name === 'Relationship') {
                $id = $xmlReader->getAttribute('Id');
                $target = $xmlReader->getAttribute('Target');
                $type = $xmlReader->getAttribute('Type');
                if ($id && $target) {
                    $this->relMap[$id] = [
                        'target' => $target,
                        'type' => $type,
                    ];
                }
            }
        }
    }

    /**
     * @param string $id
     * @return string|null
     */
    public function getTarget(string $id): ?string
    {
        return $this->relMap[$id]['target'] ?? null;
    }
}
