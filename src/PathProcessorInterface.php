<?php

namespace AndrewGos\JsonPath;

interface PathProcessorInterface
{
    /**
     * @param mixed $value
     * @param string $path
     *
     * @return array
     */
    public function query(mixed $value, string $path): array;
}
