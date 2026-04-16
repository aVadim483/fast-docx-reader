<?php

namespace avadim\FastDocxReader\Elements;

interface ElementInterface
{
    public function getType(): string;

    public function getText(): string;
    public function getStyle(): array;
    public function setStyle(array $style): void;
}
