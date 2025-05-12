<?php

namespace AndrewGos\JsonPath\Parser\Node;

class FunctionExpressionNode implements ExpressionNodeInterface
{
    /**
     * @param string $name
     * @param (LiteralNode|JsonPathQueryNode|RelQueryNode|ExpressionNodeInterface|FunctionExpressionNode|SingularQueryNodeInterface)[] $parameters
     */
    public function __construct(
        private string $name,
        private array $parameters,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return "$this->name(" .  implode(', ', $this->parameters) . ")";
    }
}
