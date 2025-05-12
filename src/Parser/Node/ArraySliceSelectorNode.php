<?php

namespace AndrewGos\JsonPath\Parser\Node;

use AndrewGos\JsonPath\Parser\Node\SelectorNodeInterface;

class ArraySliceSelectorNode implements SelectorNodeInterface
{
    public function __construct(
        private int|null $startIndex,
        private int|null $endIndex,
        private int $step,
    ) {
    }

    public function getStartIndex(): int|null
    {
        return $this->startIndex;
    }

    public function getEndIndex(): int|null
    {
        return $this->endIndex;
    }

    public function getStep(): int
    {
        return $this->step;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return "$this->startIndex:$this->endIndex:$this->step";
    }
}
