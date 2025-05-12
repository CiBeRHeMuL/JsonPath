<?php

namespace AndrewGos\JsonPath\Parser\Node;

use AndrewGos\JsonPath\Parser\Node\ExpressionNodeInterface;

class OrExpressionNode implements ExpressionNodeInterface
{
    /**
     * @param ExpressionNodeInterface[] $expressions
     */
    public function __construct(
        private array $expressions,
    ) {
    }

    public function getExpressions(): array
    {
        return $this->expressions;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return implode(' || ', $this->expressions);
    }
}
