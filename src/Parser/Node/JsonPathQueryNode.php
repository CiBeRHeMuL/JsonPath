<?php

namespace AndrewGos\JsonPath\Parser\Node;

use AndrewGos\JsonPath\Parser\Node\NodeInterface;

class JsonPathQueryNode implements NodeInterface
{
    /**
     * @param SegmentNodeInterface[] $segments
     */
    public function __construct(
        private array $segments = [],
    ) {
    }

    public function getSegments(): array
    {
        return $this->segments;
    }

    public function __toString() : string
    {
        return '$' . implode('', $this->segments);
    }
}
