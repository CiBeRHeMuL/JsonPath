<?php

namespace AndrewGos\JsonPath\Parser\Node;

use AndrewGos\JsonPath\Parser\Enum\CompareOperatorEnum;
use AndrewGos\JsonPath\Parser\Node\ExpressionNodeInterface;

class CompareExpressionNode implements ExpressionNodeInterface
{
    public function __construct(
        private SingularQueryNodeInterface|FunctionExpressionNode|LiteralNode $left,
        private CompareOperatorEnum $operator,
        private SingularQueryNodeInterface|FunctionExpressionNode|LiteralNode $right,
    ) {
    }

    public function getLeft(): SingularQueryNodeInterface|FunctionExpressionNode|LiteralNode
    {
        return $this->left;
    }

    public function getOperator(): CompareOperatorEnum
    {
        return $this->operator;
    }

    public function getRight(): SingularQueryNodeInterface|FunctionExpressionNode|LiteralNode
    {
        return $this->right;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return "$this->left {$this->operator->value} $this->right";
    }
}
