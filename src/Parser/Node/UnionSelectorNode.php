<?php

namespace AndrewGos\JsonPath\Parser\Node;

use AndrewGos\JsonPath\Parser\Node\SelectorNodeInterface;

class UnionSelectorNode implements SelectorNodeInterface
{
    /**
     * @param SelectorNodeInterface[] $selectors
     */
    public function __construct(
        private array $selectors,
    ) {
    }

    public function getSelectors(): array
    {
        return $this->selectors;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return implode(', ', $this->selectors);
    }
}
