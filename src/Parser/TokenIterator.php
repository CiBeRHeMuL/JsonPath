<?php

namespace AndrewGos\JsonPath\Parser;

use AndrewGos\JsonPath\Lexer\Lexer;
use LogicException;

class TokenIterator
{
    /** @var list<array{int}> $savePoints */
    private array $savePoints = [];

    /**
     * @param list<array{string, int}> $tokens
     * @param int $index
     */
    public function __construct(
        private array $tokens,
        private int $index = 0,
    ) {
    }

    /**
     * @return list<array{string, int}>
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    public function getContentBetween(int $startPos, int $endPos): string
    {
        if ($startPos < 0 || $endPos > count($this->tokens)) {
            throw new LogicException();
        }
        $content = '';
        for ($i = $startPos; $i < $endPos; $i++) {
            $content .= $this->tokens[$i][Lexer::VALUE_OFFSET];
        }
        return $content;
    }

    public function getTokenCount(): int
    {
        return count($this->tokens);
    }

    public function currentTokenValue(): string
    {
        return $this->tokens[$this->index][Lexer::VALUE_OFFSET];
    }

    public function currentTokenType(): int
    {
        return $this->tokens[$this->index][Lexer::TYPE_OFFSET];
    }

    public function currentTokenOffset(): int
    {
        $offset = 0;
        for ($i = 0; $i < $this->index; $i++) {
            $offset += strlen($this->tokens[$i][Lexer::VALUE_OFFSET]);
        }
        return $offset;
    }

    public function currentTokenIndex(): int
    {
        return $this->index;
    }

    public function isCurrentTokenValue(string $tokenValue): bool
    {
        return $this->tokens[$this->index][Lexer::VALUE_OFFSET] === $tokenValue;
    }

    public function isCurrentTokenType(int ...$tokenType): bool
    {
        return in_array($this->tokens[$this->index][Lexer::TYPE_OFFSET], $tokenType, true);
    }

    /**
     * @throws ParserException
     */
    public function consumeTokenType(int $tokenType): void
    {
        if ($this->tokens[$this->index][Lexer::TYPE_OFFSET] !== $tokenType) {
            $this->throwError([$tokenType]);
        }
        $this->next();
    }

    /**
     * @param int ...$tokenType
     *
     * @return void
     * @throws ParserException
     */
    public function checkTokenType(int ...$tokenType): void
    {
        if (!in_array($this->tokens[$this->index][Lexer::TYPE_OFFSET], $tokenType, true)) {
            $this->throwError($tokenType);
        }
    }

    /**
     * @throws ParserException
     */
    public function consumeTokenValue(int $tokenType, string $tokenValue): void
    {
        if ($this->tokens[$this->index][Lexer::TYPE_OFFSET] !== $tokenType || $this->tokens[$this->index][Lexer::VALUE_OFFSET] !== $tokenValue) {
            $this->throwError([$tokenType], $tokenValue);
        }
        $this->next();
    }
    /**
     * @throws ParserException
     */
    public function consumeTokenValues(int $tokenType, string ...$tokenValue): void
    {
        if ($this->tokens[$this->index][Lexer::TYPE_OFFSET] !== $tokenType || !in_array($this->tokens[$this->index][Lexer::VALUE_OFFSET], $tokenValue, true)) {
            $this->throwError([$tokenType], implode(', ', $tokenValue));
        }
        $this->next();
    }

    public function tryConsumeTokenValue(string $tokenValue): bool
    {
        if ($this->tokens[$this->index][Lexer::VALUE_OFFSET] !== $tokenValue) {
            return false;
        }
        $this->next();
        return true;
    }


    public function tryConsumeTokenType(int $tokenType): bool
    {
        if ($this->tokens[$this->index][Lexer::TYPE_OFFSET] !== $tokenType) {
            return false;
        }
        $this->next();
        return true;
    }

    public function joinUntil(int ...$tokenType): string
    {
        $s = '';
        while (!in_array($this->tokens[$this->index][Lexer::TYPE_OFFSET], $tokenType, true)) {
            $s .= $this->tokens[$this->index++][Lexer::VALUE_OFFSET];
        }
        return $s;
    }

    public function next(): void
    {
        $this->index++;
    }

    public function forwardToTheEnd(): void
    {
        $lastToken = count($this->tokens) - 1;
        $this->index = $lastToken;
    }

    public function skipTokens(int ...$tokenType): void
    {
        while (in_array($this->tokens[$this->index][Lexer::TYPE_OFFSET], $tokenType, true)) {
            $this->next();
        }
    }

    /**
     * @param int[] $expectedTokenTypes
     * @param string|null $expectedTokenValue
     *
     * @return void
     * @throws ParserException
     */
    private function throwError(array $expectedTokenTypes, ?string $expectedTokenValue = null): void
    {
        throw new ParserException(
            $this->currentTokenValue(),
            $this->currentTokenType(),
            $this->currentTokenOffset(),
            $expectedTokenTypes,
            $expectedTokenValue,
        );
    }

    public function pushSavePoint(): void
    {
        $this->savePoints[] = [$this->index];
    }

    public function commit(): void
    {
        array_pop($this->savePoints);
    }

    public function rollback() : void
    {
        $savepoint = array_pop($this->savePoints);
        assert($savepoint !== null);
        [$this->index] = $savepoint;
    }
}
