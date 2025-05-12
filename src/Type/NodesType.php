<?php

namespace AndrewGos\JsonPath\Type;

readonly class NodesType implements TypeInterface
{
    /**
     * @param array $nodes
     */
    public function __construct(
        private array $nodes,
    ) {
    }

    public function getNodes(): array
    {
        return $this->nodes;
    }
}
