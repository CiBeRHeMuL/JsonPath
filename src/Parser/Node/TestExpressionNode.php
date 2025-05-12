<?php

namespace AndrewGos\JsonPath\Parser\Node;

use AndrewGos\JsonPath\Parser\Node\ExpressionNodeInterface;

class TestExpressionNode implements ExpressionNodeInterface
{
    public function __construct(
        private RelQueryNode|JsonPathQueryNode|FunctionExpressionNode $query,
        private bool $negative,
    ) {
    }

    public function getQuery(): JsonPathQueryNode|RelQueryNode|FunctionExpressionNode
    {
        return $this->query;
    }

    public function isNegative(): bool
    {
        return $this->negative;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return ($this->negative ? '!' : '') . $this->query;
    }
}
