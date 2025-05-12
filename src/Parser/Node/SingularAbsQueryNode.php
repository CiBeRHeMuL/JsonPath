<?php

namespace AndrewGos\JsonPath\Parser\Node;

class SingularAbsQueryNode implements SingularQueryNodeInterface
{
    /**
     * @param SingularSegmentNode[] $segments
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
