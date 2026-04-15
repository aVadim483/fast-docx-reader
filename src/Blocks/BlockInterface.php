<?php

namespace Avadim\FastDocxReader\Blocks;

interface BlockInterface
{
    /**
     * @return string
     */
    public function getText(): string;

    /**
     * @return string
     */
    public function getType(): string;
}
