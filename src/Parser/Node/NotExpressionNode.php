<?php

namespace AndrewGos\JsonPath\Parser\Node;

use AndrewGos\JsonPath\Parser\Node\ExpressionNodeInterface;

class NotExpressionNode implements ExpressionNodeInterface
{
    public function __construct(
        private ExpressionNodeInterface $expression,
    ) {
    }

    public function getExpression(): \AndrewGos\JsonPath\Parser\Node\ExpressionNodeInterface
    {
        return $this->expression;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return "!($this->expression)";
    }
}
