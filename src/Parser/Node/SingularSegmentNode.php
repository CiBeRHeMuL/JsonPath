<?php

namespace AndrewGos\JsonPath\Parser\Node;

class SingularSegmentNode implements SegmentNodeInterface
{
    public function __construct(
        private NameSelectorNode|ShorthandNameSelectorNode|IndexSelectorNode $selector,
    ) {
    }

    public function getSelector(): ShorthandNameSelectorNode|NameSelectorNode|IndexSelectorNode
    {
        return $this->selector;
    }

    public function __toString(): string
    {
        return "[$this->selector]";
    }
}
