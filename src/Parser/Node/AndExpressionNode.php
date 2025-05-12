<?php

namespace AndrewGos\JsonPath\Parser\Node;

class AndExpressionNode implements ExpressionNodeInterface
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
        return implode(' && ', $this->expressions);
    }
}
