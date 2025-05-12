<?php

namespace AndrewGos\JsonPath\Parser\Node;

use AndrewGos\JsonPath\Parser\Node\SelectorNodeInterface;

class FilterSelectorNode implements SelectorNodeInterface
{
    public function __construct(
        private ExpressionNodeInterface $expression,
    ) {
    }

    public function getExpression(): ExpressionNodeInterface
    {
        return $this->expression;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return "?$this->expression";
    }
}
