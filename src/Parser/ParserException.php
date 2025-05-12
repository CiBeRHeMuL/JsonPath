<?php

namespace AndrewGos\JsonPath\Parser;

use AndrewGos\JsonPath\Lexer\Lexer;
use Exception;

class ParserException extends Exception
{
    /**
     * @param string $currentTokenValue
     * @param int $currentTokenType
     * @param int $currentOffset
     * @param int[] $expectedTokenTypes
     * @param string|null $expectedTokenValue
     */
    public function __construct(
        private string $currentTokenValue,
        private int $currentTokenType,
        private int $currentOffset,
        private array $expectedTokenTypes,
        private ?string $expectedTokenValue = null,
    ) {
        parent::__construct(
            sprintf(
                'Unexpected token %s, expected %s%s at offset %d',
                $this->formatValue($currentTokenValue),
                count($this->expectedTokenTypes) > 1
                    ? '(' . implode(', ', array_map(fn($t) => Lexer::TOKEN_LABELS[$t], $expectedTokenTypes)) . ')'
                    : Lexer::TOKEN_LABELS[$this->expectedTokenTypes[0]],
                $expectedTokenValue !== null ? sprintf(' (%s)', $this->formatValue($expectedTokenValue)) : '',
                $currentOffset,
            ),
        );
    }

    public function getCurrentTokenValue(): string
    {
        return $this->currentTokenValue;
    }

    public function getCurrentTokenType(): int
    {
        return $this->currentTokenType;
    }

    public function getCurrentOffset(): int
    {
        return $this->currentOffset;
    }

    public function getExpectedTokenType(): int
    {
        return $this->expectedTokenType;
    }

    public function getExpectedTokenValue(): ?string
    {
        return $this->expectedTokenValue;
    }

    private function formatValue(string $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        assert($json !== false);
        return $json;
    }
}
