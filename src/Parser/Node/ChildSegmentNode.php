<?php

namespace AndrewGos\JsonPath\Parser\Node;

class ChildSegmentNode implements SegmentNodeInterface
{
    public function __construct(
        private SelectorNodeInterface $selector,
    ) {
    }

    public function getSelector(): SelectorNodeInterface
    {
        return $this->selector;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return "[$this->selector]";
    }
}
