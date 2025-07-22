<?php

namespace AndrewGos\JsonPath\Parser;

use AndrewGos\JsonPath\Lexer\Lexer;
use AndrewGos\JsonPath\Parser\Enum\CompareOperatorEnum;
use AndrewGos\JsonPath\Parser\Node\ArraySliceSelectorNode;
use AndrewGos\JsonPath\Parser\Node\ChildSegmentNode;
use AndrewGos\JsonPath\Parser\Node\CompareExpressionNode;
use AndrewGos\JsonPath\Parser\Node\DescendantSegmentNode;
use AndrewGos\JsonPath\Parser\Node\ExpressionNodeInterface;
use AndrewGos\JsonPath\Parser\Node\FilterSelectorNode;
use AndrewGos\JsonPath\Parser\Node\FunctionExpressionNode;
use AndrewGos\JsonPath\Parser\Node\IndexSelectorNode;
use AndrewGos\JsonPath\Parser\Node\JsonPathQueryNode;
use AndrewGos\JsonPath\Parser\Node\LiteralNode;
use AndrewGos\JsonPath\Parser\Node\NameSelectorNode;
use AndrewGos\JsonPath\Parser\Node\NodeInterface;
use AndrewGos\JsonPath\Parser\Node\NotExpressionNode;
use AndrewGos\JsonPath\Parser\Node\OrExpressionNode;
use AndrewGos\JsonPath\Parser\Node\RelQueryNode;
use AndrewGos\JsonPath\Parser\Node\SegmentNodeInterface;
use AndrewGos\JsonPath\Parser\Node\SelectorNodeInterface;
use AndrewGos\JsonPath\Parser\Node\ShorthandNameSelectorNode;
use AndrewGos\JsonPath\Parser\Node\SingularAbsQueryNode;
use AndrewGos\JsonPath\Parser\Node\SingularQueryNodeInterface;
use AndrewGos\JsonPath\Parser\Node\SingularRelQueryNode;
use AndrewGos\JsonPath\Parser\Node\SingularSegmentNode;
use AndrewGos\JsonPath\Parser\Node\TestExpressionNode;
use AndrewGos\JsonPath\Parser\Node\UnionSelectorNode;
use AndrewGos\JsonPath\Parser\Node\WildcardSelectorNode;

class Parser
{
    /**
     * @param TokenIterator $tokens
     *
     * @return JsonPathQueryNode
     * @throws ParserException
     */
    public function parse(TokenIterator $tokens): JsonPathQueryNode
    {
        $tokens->consumeTokenType(Lexer::TOKEN_DOLLAR);
        $segments = [];
        while (!$tokens->isCurrentTokenType(Lexer::TOKEN_END)) {
            $tokens->skipTokens(...Lexer::S_TOKENS);
            $segments[] = $this->parseSegment($tokens);
        }
        $tokens->consumeTokenType(Lexer::TOKEN_END);
        return new JsonPathQueryNode($segments);
    }

    public function parseStringLiteral(TokenIterator $tokens): string
    {
        $tokens->checkTokenType(Lexer::TOKEN_SINGLE_QUOTE, Lexer::TOKEN_DOUBLE_QUOTE);
        $string = '';
        $quoteType = $tokens->currentTokenType();
        $unescapedQuote = $quoteType === Lexer::TOKEN_DOUBLE_QUOTE ? "'" : '"';

        $unescaped = [["\u{20}", "\u{21}"], ["\u{23}", "\u{26}"], ["\u{28}", "\u{5B}"], ["\u{5D}", "\u{D7FF}"], [$unescapedQuote]];
        $escapable = ['b', 'f', 'r', 't', '/', '\\', $quoteType];
        $escapableReplace = ["\u{62}", "\f", "\r", "\t", '/', '\\', $quoteType];
        $tokens->next();
        $escaped = false;
        while (
            !$tokens->isCurrentTokenType($quoteType)
            || $tokens->isCurrentTokenType($quoteType) && $escaped
        ) {
            if ($tokens->isCurrentTokenType(Lexer::TOKEN_BACKSLASH)) {
                if ($escaped) {
                    $string .= $tokens->currentTokenValue();
                }
                $escaped = !$escaped;
                $tokens->next();
                continue;
            }

            if ($escaped) {
                if ($tokens->isCurrentTokenValue('u')) {
                    $string .= $this->parseHexChar($tokens);
                } elseif (in_array($tokens->currentTokenValue(), $escapable)) {
                    $string .= str_replace($escapable, $escapableReplace, $tokens->currentTokenValue());
                    $tokens->next();
                } else {
                    throw new ParserException(
                        $tokens->currentTokenValue(),
                        $tokens->currentTokenType(),
                        $tokens->currentTokenOffset(),
                        [Lexer::TOKEN_OTHER],
                        "'" . implode("', '", $escapable) . "'",
                    );
                }
            } else {
                $regexp = '/^['
                    . implode(
                        '',
                        array_map(
                            fn($e) => implode('-', array_map(preg_quote(...), $e)),
                            $unescaped,
                        ),
                    )
                    . ']$/u';
                if (!preg_match($regexp, $tokens->currentTokenValue())) {
                    throw new ParserException(
                        $tokens->currentTokenValue(),
                        $tokens->currentTokenType(),
                        $tokens->currentTokenOffset(),
                        [Lexer::TOKEN_OTHER],
                        implode(", ", array_map(fn($e) => implode('-', $e), $unescaped)),
                    );
                }
                $string .= $tokens->currentTokenValue();
                $tokens->next();
            }

            $escaped = false;
        }
        $tokens->consumeTokenType($quoteType);
        return $string;
    }

    private function parseSegment(TokenIterator $tokens): SegmentNodeInterface
    {
        try {
            $tokens->pushSavePoint();
            $segment = $this->parseDescendantSegment($tokens);
            $tokens->commit();
            return $segment;
        } catch (ParserException $e) {
            $tokens->rollback();
            $tokens->pushSavePoint();
            $segment = $this->parseChildSegment($tokens);
            $tokens->commit();
            return $segment;
        }
    }

    private function parseChildSegment(TokenIterator $tokens): ChildSegmentNode
    {
        try {
            $tokens->pushSavePoint();
            $segment = $this->parseBracketedSelection($tokens);
            $tokens->commit();
            return new ChildSegmentNode($segment);
        } catch (ParserException $e) {
            $tokens->rollback();
            $tokens->consumeTokenType(Lexer::TOKEN_DOT);
            try {
                $tokens->pushSavePoint();
                $selector = $this->parseWildcardSelector($tokens);
                $tokens->commit();
                return new ChildSegmentNode($selector);
            } catch (ParserException $e) {
                $tokens->rollback();
                $tokens->pushSavePoint();
                $selector = $this->parseSelectorNameShorthand($tokens);
                $tokens->commit();
                return new ChildSegmentNode($selector);
            }
        }
    }

    private function parseDescendantSegment(TokenIterator $tokens): DescendantSegmentNode
    {
        $tokens->consumeTokenType(Lexer::TOKEN_DOT);
        $tokens->consumeTokenType(Lexer::TOKEN_DOT);
        try {
            $tokens->pushSavePoint();
            $expr = $this->parseBracketedSelection($tokens);
            $tokens->commit();
            return new DescendantSegmentNode($expr);
        } catch (ParserException $e) {
            try {
                $tokens->rollback();
                $tokens->pushSavePoint();
                $expr = $this->parseWildcardSelector($tokens);
                $tokens->commit();
                return new DescendantSegmentNode($expr);
            } catch (ParserException $e) {
                $tokens->rollback();
                $tokens->pushSavePoint();
                $expr = $this->parseSelectorNameShorthand($tokens);
                $tokens->commit();
                return new DescendantSegmentNode($expr);
            }
        }
    }

    private function parseWildcardSelector(TokenIterator $tokens): WildcardSelectorNode
    {
        $tokens->consumeTokenType(Lexer::TOKEN_WILDCARD);
        return new WildcardSelectorNode();
    }

    private function parseSelectorNameShorthand(TokenIterator $tokens): ShorthandNameSelectorNode
    {
        $name = '';
        $tokens->checkTokenType(Lexer::TOKEN_OTHER);
        $firstChar = $tokens->currentTokenValue();
        if (!preg_match("/^[a-zA-Z_\u{80}-\u{D7FF}]$/u", $firstChar)) {
            throw new ParserException(
                $tokens->currentTokenValue(),
                $tokens->currentTokenType(),
                $tokens->currentTokenOffset(),
                [Lexer::TOKEN_OTHER],
                'a-z, A-Z, _, \u{80}-\u{D7FF}',
            );
        }
        $name .= $firstChar;
        $tokens->next();

        while ($tokens->isCurrentTokenType(Lexer::TOKEN_OTHER)) {
            $value = $tokens->currentTokenValue();
            if (!preg_match("/^[0-9a-zA-Z_\u{80}-\u{D7FF}]$/u", $value)) {
                throw new ParserException(
                    $tokens->currentTokenValue(),
                    $tokens->currentTokenType(),
                    $tokens->currentTokenOffset(),
                    [Lexer::TOKEN_OTHER],
                    '0-9, a-z, A-Z, _, \u{80}-\u{D7FF}',
                );
            }
            $name .= $value;
            $tokens->next();
        }

        return new ShorthandNameSelectorNode($name);
    }

    private function parseBracketedSelection(TokenIterator $tokens): SelectorNodeInterface
    {
        $tokens->consumeTokenType(Lexer::TOKEN_OPEN_SQUARE_BRACKET);
        $tokens->skipTokens(...Lexer::S_TOKENS);
        $selectors = [$this->parseSelector($tokens)];
        $tokens->skipTokens(...Lexer::S_TOKENS);

        while ($tokens->isCurrentTokenType(Lexer::TOKEN_COMMA)) {
            $tokens->next();
            $tokens->skipTokens(...Lexer::S_TOKENS);
            $selectors[] = $this->parseSelector($tokens);
            $tokens->skipTokens(...Lexer::S_TOKENS);
        }
        $tokens->consumeTokenType(Lexer::TOKEN_CLOSE_SQUARE_BRACKET);
        if (count($selectors) > 1) {
            return new UnionSelectorNode($selectors);
        }
        return array_shift($selectors);
    }

    private function parseSelector(TokenIterator $tokens): SelectorNodeInterface
    {
        try {
            $tokens->pushSavePoint();
            $selector = $this->parseNameSelector($tokens);
            $tokens->commit();
            return $selector;
        } catch (ParserException $e) {
            try {
                $tokens->rollback();
                $tokens->pushSavePoint();
                $selector = $this->parseWildcardSelector($tokens);
                $tokens->commit();
                return $selector;
            } catch (ParserException $e) {
                try {
                    $tokens->rollback();
                    $tokens->pushSavePoint();
                    $selector = $this->parseArraySliceSelector($tokens);
                    $tokens->commit();
                    return $selector;
                } catch (ParserException $e) {
                    try {
                        $tokens->rollback();
                        $tokens->pushSavePoint();
                        $selector = $this->parseIndexSelector($tokens);
                        $tokens->commit();
                        return $selector;
                    } catch (ParserException $e) {
                        $tokens->rollback();
                        $tokens->pushSavePoint();
                        $selector = $this->parseFilterSelector($tokens);
                        $tokens->commit();
                        return $selector;
                    }
                }
            }
        }
    }

    private function parseIndexSelector(TokenIterator $tokens): IndexSelectorNode
    {
        return new IndexSelectorNode($this->parseInt($tokens));
    }

    private function parseArraySliceSelector(TokenIterator $tokens): ArraySliceSelectorNode
    {
        $startIndex = null;
        $endIndex = null;
        $step = 1;

        try {
            $tokens->pushSavePoint();
            $startIndex = $this->parseInt($tokens);
            $tokens->skipTokens(...Lexer::S_TOKENS);
            $tokens->commit();
        } catch (ParserException $e) {
            $tokens->rollback();
        }

        $tokens->consumeTokenType(Lexer::TOKEN_COLON);
        $tokens->skipTokens(...Lexer::S_TOKENS);
        try {
            $tokens->pushSavePoint();
            $endIndex = $this->parseInt($tokens);
            $tokens->skipTokens(...Lexer::S_TOKENS);
            $tokens->commit();
        } catch (ParserException $e) {
            $tokens->rollback();
        }
        try {
            $tokens->pushSavePoint();
            $tokens->consumeTokenType(Lexer::TOKEN_COLON);
            $tokens->commit();
            try {
                $tokens->pushSavePoint();
                $tokens->skipTokens(...Lexer::S_TOKENS);
                $step = $this->parseInt($tokens);
                $tokens->commit();
            } catch (ParserException $e) {
                $tokens->rollback();
            }
        } catch (ParserException $e) {
            $tokens->rollback();
        }
        return new ArraySliceSelectorNode($startIndex, $endIndex, $step);
    }

    private function parseInt(TokenIterator $tokens): int
    {
        $tokens->checkTokenType(Lexer::TOKEN_DIGIT, Lexer::TOKEN_MINUS);
        $negative = $tokens->tryConsumeTokenType(Lexer::TOKEN_MINUS);
        $numberStr = $negative ? '-' : '';

        $tokens->checkTokenType(Lexer::TOKEN_DIGIT);
        $digit = intval($tokens->currentTokenValue());
        if ($negative && $digit === 0) {
            throw new ParserException(
                $tokens->currentTokenValue(),
                $tokens->currentTokenType(),
                $tokens->currentTokenOffset(),
                [Lexer::TOKEN_DIGIT],
                '1-9',
            );
        }
        $numberStr .= $digit;
        $tokens->next();

        while ($tokens->isCurrentTokenType(Lexer::TOKEN_DIGIT)) {
            $digit = intval($tokens->currentTokenValue());
            $numberStr .= $digit;
            $tokens->next();
        }
        return intval($numberStr);
    }

    private function parseNameSelector(TokenIterator $tokens): NameSelectorNode
    {
        return new NameSelectorNode($this->parseStringLiteral($tokens));
    }

    private function parseHexChar(TokenIterator $tokens): string
    {
        $tokens->consumeTokenValue(Lexer::TOKEN_OTHER, 'u');
        $hexDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F'];
        $char = '';
        if (
            in_array(
                mb_strtoupper($tokens->currentTokenValue()),
                ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'E', 'F'],
                true,
            )
        ) {
            $char .= $tokens->currentTokenValue();
            $tokens->next();
            $char .= $this->parseHexDigits($tokens, 3, $hexDigits);
            return mb_chr(hexdec($char), 'UTF-8');
        } elseif (mb_strtoupper($tokens->currentTokenValue()) === 'D') {
            $char .= $tokens->currentTokenValue();
            $tokens->next();
            if (in_array(mb_strtoupper($tokens->currentTokenValue()), ['0', '1', '2', '3', '4', '5', '6', '7'], true)) {
                $char .= $tokens->currentTokenValue();
                $tokens->next();
                $char .= $this->parseHexDigits($tokens, 2, $hexDigits);
                return mb_chr(hexdec($char), 'UTF-8');
            } else {
                if (in_array(mb_strtoupper($tokens->currentTokenValue()), ['8', '9', 'A', 'B'], true)) {
                    $char .= $tokens->currentTokenValue();
                    $tokens->next();
                    $char .= $this->parseHexDigits($tokens, 2, $hexDigits);
                    $highSurrogate = (hexdec($char) - 0xD800) << 10;

                    $tokens->consumeTokenType(Lexer::TOKEN_BACKSLASH);
                    $tokens->consumeTokenValue(Lexer::TOKEN_OTHER, 'u');
                    $tokens->consumeTokenValue(Lexer::TOKEN_OTHER, 'D');

                    $char = 'D';
                    if (in_array(mb_strtoupper($tokens->currentTokenValue()), ['C', 'D', 'E', 'F'], true)) {
                        $char .= $tokens->currentTokenValue();
                        $tokens->next();
                        $char .= $this->parseHexDigits($tokens, 2, $hexDigits);
                        $lowSurrogate = (hexdec($char) - 0xDC00);
                        return mb_chr($highSurrogate + $lowSurrogate + 0x10000, 'UTF-8');
                    } else {
                        throw new ParserException(
                            $tokens->currentTokenValue(),
                            $tokens->currentTokenType(),
                            $tokens->currentTokenOffset(),
                            [Lexer::TOKEN_DIGIT, Lexer::TOKEN_OTHER],
                            implode(', ', ['C', 'D', 'E', 'F']),
                        );
                    }
                } else {
                    throw new ParserException(
                        $tokens->currentTokenValue(),
                        $tokens->currentTokenType(),
                        $tokens->currentTokenOffset(),
                        [Lexer::TOKEN_DIGIT, Lexer::TOKEN_OTHER],
                        implode(', ', ['8', '9', 'A', 'B']),
                    );
                }
            }
        } else {
            throw new ParserException(
                $tokens->currentTokenValue(),
                $tokens->currentTokenType(),
                $tokens->currentTokenOffset(),
                [Lexer::TOKEN_DIGIT, Lexer::TOKEN_OTHER],
                implode(', ', $hexDigits),
            );
        }
    }

    private function parseHexDigits(TokenIterator $tokens, int $count, array $digits): string
    {
        $str = '';
        for ($i = 0; $i < $count; $i++) {
            if (!in_array(mb_strtoupper($tokens->currentTokenValue()), $digits, true)) {
                throw new ParserException(
                    $tokens->currentTokenValue(),
                    $tokens->currentTokenType(),
                    $tokens->currentTokenOffset(),
                    [Lexer::TOKEN_DIGIT, Lexer::TOKEN_OTHER],
                    implode(', ', $digits),
                );
            } else {
                $str .= $tokens->currentTokenValue();
            }
            $tokens->next();
        }
        return $str;
    }

    private function parseFilterSelector(TokenIterator $tokens): FilterSelectorNode
    {
        $tokens->consumeTokenType(Lexer::TOKEN_QUESTION);
        $tokens->skipTokens(...Lexer::S_TOKENS);
        return new FilterSelectorNode($this->parseOrExpression($tokens));
    }

    private function parseOrExpression(TokenIterator $tokens): ExpressionNodeInterface
    {
        $expressions = [$this->parseAndExpression($tokens)];

        $tokens->skipTokens(...Lexer::S_TOKENS);
        while ($tokens->isCurrentTokenType(Lexer::TOKEN_UNION)) {
            $tokens->next();
            $tokens->consumeTokenType(Lexer::TOKEN_UNION);
            $tokens->skipTokens(...Lexer::S_TOKENS);
            $expressions[] = $this->parseAndExpression($tokens);
        }
        if (count($expressions) > 1) {
            return new OrExpressionNode($expressions);
        }
        return array_shift($expressions);
    }

    private function parseAndExpression(TokenIterator $tokens): ExpressionNodeInterface
    {
        $expressions = [$this->parseBasicExpr($tokens)];

        $tokens->skipTokens(...Lexer::S_TOKENS);
        while ($tokens->isCurrentTokenType(Lexer::TOKEN_INTERSECTION)) {
            $tokens->next();
            $tokens->consumeTokenType(Lexer::TOKEN_INTERSECTION);
            $tokens->skipTokens(...Lexer::S_TOKENS);
            $expressions[] = $this->parseBasicExpr($tokens);
        }
        if (count($expressions) > 1) {
            return new OrExpressionNode($expressions);
        }
        return array_shift($expressions);
    }

    private function parseBasicExpr(TokenIterator $tokens): ExpressionNodeInterface
    {
        try {
            $tokens->pushSavePoint();
            $expr = $this->parseParentExpr($tokens);
            $tokens->commit();
            return $expr;
        } catch (ParserException $e) {
            try {
                $tokens->rollback();
                $tokens->pushSavePoint();
                $expr = $this->parseComparisonExpr($tokens);
                $tokens->commit();
                return $expr;
            } catch (ParserException $e) {
                $tokens->rollback();
                $tokens->pushSavePoint();
                $expr = $this->parseTestExpr($tokens);
                $tokens->commit();
                return $expr;
            }
        }
    }

    private function parseParentExpr(TokenIterator $tokens): ExpressionNodeInterface
    {
        $negative = $tokens->tryConsumeTokenType(Lexer::TOKEN_NEGATED);
        if ($negative) {
            $tokens->skipTokens(...Lexer::S_TOKENS);
        }
        $tokens->consumeTokenType(Lexer::TOKEN_OPEN_PARENTHESES);
        $tokens->skipTokens(...Lexer::S_TOKENS);
        $expression = $this->parseOrExpression($tokens);
        $tokens->skipTokens(...Lexer::S_TOKENS);
        $tokens->consumeTokenType(Lexer::TOKEN_CLOSE_PARENTHESES);
        return $negative ? new NotExpressionNode($expression) : $expression;
    }

    private function parseRelQuery(TokenIterator $tokens): RelQueryNode
    {
        $tokens->consumeTokenType(Lexer::TOKEN_AT);
        $tokens->skipTokens(...Lexer::S_TOKENS);
        $segments = [];
        while (!$tokens->isCurrentTokenType(...Lexer::AFTER_QUERY_TOKENS)) {
            $segments[] = $this->parseSegment($tokens);
            $tokens->skipTokens(...Lexer::S_TOKENS);
        }
        return new RelQueryNode($segments);
    }

    private function parseAbsQuery(TokenIterator $tokens): JsonPathQueryNode
    {
        $tokens->consumeTokenType(Lexer::TOKEN_DOLLAR);
        $tokens->skipTokens(...Lexer::S_TOKENS);
        $segments = [];
        while (!$tokens->isCurrentTokenType(...Lexer::AFTER_QUERY_TOKENS)) {
            $segments[] = $this->parseSegment($tokens);
        }
        return new JsonPathQueryNode($segments);
    }

    private function parseCompOperator(TokenIterator $tokens): CompareOperatorEnum
    {
        $tokens->checkTokenType(...Lexer::START_COMP_TOKENS);
        if ($tokens->isCurrentTokenType(Lexer::TOKEN_NEGATED)) {
            $tokens->next();
            $tokens->consumeTokenType(Lexer::TOKEN_EQUAL);
            return CompareOperatorEnum::NotEqual;
        } elseif ($tokens->isCurrentTokenType(Lexer::TOKEN_EQUAL)) {
            $tokens->next();
            $tokens->consumeTokenType(Lexer::TOKEN_EQUAL);
            return CompareOperatorEnum::Equal;
        } elseif ($tokens->isCurrentTokenType(Lexer::TOKEN_LESS)) {
            $tokens->next();
            if ($tokens->isCurrentTokenType(Lexer::TOKEN_EQUAL)) {
                $tokens->next();
                return CompareOperatorEnum::LessThanOrEqual;
            } else {
                return CompareOperatorEnum::LessThan;
            }
        } else {
            $tokens->next();
            if ($tokens->isCurrentTokenType(Lexer::TOKEN_EQUAL)) {
                $tokens->next();
                return CompareOperatorEnum::GreaterThanOrEqual;
            } else {
                return CompareOperatorEnum::GreaterThan;
            }
        }
    }

    private function parseComparisonExpr(TokenIterator $tokens): ExpressionNodeInterface
    {
        $comp1 = $this->parseComparable($tokens);
        $tokens->skipTokens(...Lexer::S_TOKENS);
        $op = $this->parseCompOperator($tokens);
        $tokens->skipTokens(...Lexer::S_TOKENS);
        $comp2 = $this->parseComparable($tokens);
        return new CompareExpressionNode($comp1, $op, $comp2);
    }

    private function parseTestExpr(TokenIterator $tokens): ExpressionNodeInterface
    {
        $negative = $tokens->tryConsumeTokenType(Lexer::TOKEN_NEGATED);
        $expr = null;
        try {
            $tokens->pushSavePoint();
            $expr = $this->parseFilterQuery($tokens);
            $tokens->commit();
        } catch (ParserException $e) {
            $tokens->rollback();
            $expr = $this->parseFunctionExpr($tokens);
        }
        return new TestExpressionNode($expr, $negative);
    }

    private function parseFilterQuery(TokenIterator $tokens): RelQueryNode|JsonPathQueryNode
    {
        try {
            $tokens->pushSavePoint();
            $query = $this->parseAbsQuery($tokens);
            $tokens->commit();
            return $query;
        } catch (ParserException $e) {
            $tokens->rollback();
            return $this->parseRelQuery($tokens);
        }
    }

    private function parseFunctionExpr(TokenIterator $tokens): FunctionExpressionNode
    {
        $name = $this->parseFunctionName($tokens);
        $tokens->consumeTokenType(Lexer::TOKEN_OPEN_PARENTHESES);
        $tokens->skipTokens(...Lexer::S_TOKENS);

        $arguments = [];
        $arguments[] = $this->parseFunctionArgument($tokens);
        $tokens->skipTokens(...Lexer::S_TOKENS);

        while ($tokens->isCurrentTokenValue(',')) {
            $tokens->next();
            $tokens->skipTokens(...Lexer::S_TOKENS);
            $arguments[] = $this->parseFunctionArgument($tokens);
            $tokens->skipTokens(...Lexer::S_TOKENS);
        }
        $tokens->consumeTokenType(Lexer::TOKEN_CLOSE_PARENTHESES);
        return new FunctionExpressionNode($name, $arguments);
    }

    private function parseComparable(TokenIterator $tokens): SingularQueryNodeInterface|FunctionExpressionNode|LiteralNode
    {
        try {
            $tokens->pushSavePoint();
            $expr = $this->parseSingularQuery($tokens);
            $tokens->commit();
            return $expr;
        } catch (ParserException $e) {
            try {
                $tokens->rollback();
                $tokens->pushSavePoint();
                $expr = $this->parseFunctionExpr($tokens);
                $tokens->commit();
                return $expr;
            } catch (ParserException $e) {
                $tokens->rollback();
                return $this->parseLiteral($tokens);
            }
        }
    }

    private function parseSingularQuery(TokenIterator $tokens): SingularQueryNodeInterface
    {
        try {
            $tokens->pushSavePoint();
            $expr = $this->parseSingularAbsQuery($tokens);
            $tokens->commit();
            return $expr;
        } catch (ParserException $e) {
            $tokens->rollback();
            return $this->parseSingularRelQuery($tokens);
        }
    }

    private function parseSingularAbsQuery(TokenIterator $tokens): SingularAbsQueryNode
    {
        $tokens->consumeTokenType(Lexer::TOKEN_DOLLAR);
        $tokens->skipTokens(...Lexer::S_TOKENS);
        $segments = [];
        while (!$tokens->isCurrentTokenType(...Lexer::AFTER_QUERY_TOKENS)) {
            $segments[] = $this->parseSingularSegment($tokens);
        }
        return new SingularAbsQueryNode($segments);
    }

    private function parseSingularRelQuery(TokenIterator $tokens): SingularRelQueryNode
    {
        $tokens->consumeTokenType(Lexer::TOKEN_AT);
        $tokens->skipTokens(...Lexer::S_TOKENS);
        $segments = [];
        while (!$tokens->isCurrentTokenType(...Lexer::AFTER_QUERY_TOKENS)) {
            $segments[] = $this->parseSingularSegment($tokens);
        }
        return new SingularRelQueryNode($segments);
    }

    private function parseSingularSegment(TokenIterator $tokens): SingularSegmentNode
    {
        try {
            $tokens->pushSavePoint();
            $segment = $this->parseNameSingularSegment($tokens);
            $tokens->commit();
            return $segment;
        } catch (ParserException $e) {
            $tokens->rollback();
            return $this->parseIndexSingularSegment($tokens);
        }
    }

    private function parseNameSingularSegment(TokenIterator $tokens): SingularSegmentNode
    {
        try {
            $tokens->pushSavePoint();
            $tokens->consumeTokenType(Lexer::TOKEN_OPEN_SQUARE_BRACKET);
            $selector = $this->parseNameSelector($tokens);
            $tokens->consumeTokenType(Lexer::TOKEN_CLOSE_SQUARE_BRACKET);
            $tokens->commit();
            return new SingularSegmentNode($selector);
        } catch (ParserException $e) {
            $tokens->rollback();
            $tokens->consumeTokenType(Lexer::TOKEN_DOT);
            return new SingularSegmentNode($this->parseSelectorNameShorthand($tokens));
        }
    }

    private function parseIndexSingularSegment(TokenIterator $tokens): SingularSegmentNode
    {
        $tokens->consumeTokenType(Lexer::TOKEN_OPEN_SQUARE_BRACKET);
        $selector = $this->parseIndexSelector($tokens);
        $tokens->consumeTokenType(Lexer::TOKEN_CLOSE_SQUARE_BRACKET);
        return new SingularSegmentNode($selector);
    }

    private function parseLiteral(TokenIterator $tokens): LiteralNode
    {
        try {
            $tokens->pushSavePoint();
            $literal = $this->parseNumber($tokens);
            $tokens->commit();
            return new LiteralNode($literal);
        } catch (ParserException $e) {
            try {
                $tokens->rollback();
                $tokens->pushSavePoint();
                $literal = $this->parseStringLiteral($tokens);
                $tokens->commit();
                return new LiteralNode($literal);
            } catch (ParserException $e) {
                try {
                    $tokens->rollback();
                    $tokens->pushSavePoint();
                    $literal = $this->parseTrue($tokens);
                    $tokens->commit();
                    return new LiteralNode($literal);
                } catch (ParserException $e) {
                    try {
                        $tokens->rollback();
                        $tokens->pushSavePoint();
                        $literal = $this->parseFalse($tokens);
                        $tokens->commit();
                        return new LiteralNode($literal);
                    } catch (ParserException $e) {
                        $tokens->rollback();
                        $tokens->pushSavePoint();
                        $literal = $this->parseNull($tokens);
                        $tokens->commit();
                        return new LiteralNode($literal);
                    }
                }
            }
        }
    }

    private function parseTrue(TokenIterator $tokens): true
    {
        $tokens->consumeTokenValue(Lexer::TOKEN_OTHER, 't');
        $tokens->consumeTokenValue(Lexer::TOKEN_OTHER, 'r');
        $tokens->consumeTokenValue(Lexer::TOKEN_OTHER, 'u');
        $tokens->consumeTokenValue(Lexer::TOKEN_OTHER, 'e');
        return true;
    }

    private function parseFalse(TokenIterator $tokens): false
    {
        $tokens->consumeTokenValue(Lexer::TOKEN_OTHER, 'f');
        $tokens->consumeTokenValue(Lexer::TOKEN_OTHER, 'a');
        $tokens->consumeTokenValue(Lexer::TOKEN_OTHER, 'l');
        $tokens->consumeTokenValue(Lexer::TOKEN_OTHER, 's');
        $tokens->consumeTokenValue(Lexer::TOKEN_OTHER, 'e');
        return false;
    }

    private function parseNull(TokenIterator $tokens): null
    {
        $tokens->consumeTokenValue(Lexer::TOKEN_OTHER, 'n');
        $tokens->consumeTokenValue(Lexer::TOKEN_OTHER, 'u');
        $tokens->consumeTokenValue(Lexer::TOKEN_OTHER, 'l');
        $tokens->consumeTokenValue(Lexer::TOKEN_OTHER, 'l');
        return null;
    }

    private function parseNumber(TokenIterator $tokens): int|float
    {
        $int = null;
        $frac = null;
        $exp = null;
        try {
            $tokens->pushSavePoint();
            $int = $this->parseInt($tokens);
            $tokens->commit();
        } catch (ParserException $e) {
            $tokens->rollback();
            $tokens->checkTokenType(Lexer::TOKEN_MINUS);
            $tokens->consumeTokenValue(Lexer::TOKEN_DIGIT, '0');
            $int = -0;
        }

        try {
            $tokens->pushSavePoint();
            $int = $this->parseFrac($tokens);
            $tokens->commit();
        } catch (ParserException $e) {
            $tokens->rollback();
        }
        try {
            $tokens->pushSavePoint();
            $int = $this->parseExp($tokens);
            $tokens->commit();
        } catch (ParserException $e) {
            $tokens->rollback();
        }

        $number = "$int" . ($frac === null ? '' : ".$frac") . ($exp === null ? '' : "e$exp);");
        return floatval($number);
    }

    private function parseFrac(TokenIterator $tokens): int
    {
        $frac = '';

        $tokens->consumeTokenType(Lexer::TOKEN_DOT);
        $tokens->checkTokenType(Lexer::TOKEN_DIGIT);
        while ($tokens->isCurrentTokenType(Lexer::TOKEN_DIGIT)) {
            $frac .= $tokens->currentTokenValue();
            $tokens->next();
        }
        return intval($frac);
    }

    private function parseExp(TokenIterator $tokens): int
    {
        $exp = '';

        $tokens->consumeTokenValues(Lexer::TOKEN_OTHER, 'e', 'E');
        $tokens->tryConsumeTokenType(Lexer::TOKEN_PLUS);
        $minus = $tokens->tryConsumeTokenType(Lexer::TOKEN_MINUS);
        $exp .= $minus ? '-' : '';
        $tokens->checkTokenType(Lexer::TOKEN_DIGIT);
        while ($tokens->isCurrentTokenType(Lexer::TOKEN_DIGIT)) {
            $exp .= $tokens->currentTokenValue();
            $tokens->next();
        }
        return intval($exp);
    }

    private function parseFunctionArgument(TokenIterator $tokens): LiteralNode|JsonPathQueryNode|RelQueryNode|ExpressionNodeInterface|FunctionExpressionNode|SingularQueryNodeInterface
    {
        try {
            $tokens->pushSavePoint();
            $literal = $this->parseLiteral($tokens);
            $tokens->commit();
            return $literal;
        } catch (ParserException $e) {
            try {
                $tokens->rollback();
                $tokens->pushSavePoint();
                $filterQuery = $this->parseSingularQuery($tokens);
                $tokens->commit();
                return $filterQuery;
            } catch (ParserException $e) {
                try {
                    $tokens->rollback();
                    $tokens->pushSavePoint();
                    $filterQuery = $this->parseFilterQuery($tokens);
                    $tokens->commit();
                    return $filterQuery;
                } catch (ParserException $e) {
                    try {
                        $tokens->rollback();
                        $tokens->pushSavePoint();
                        $expr = $this->parseOrExpression($tokens);
                        $tokens->commit();
                        return $expr;
                    } catch (ParserException $e) {
                        $tokens->rollback();
                        return $this->parseFunctionExpr($tokens);
                    }
                }
            }
        }
    }

    private function parseFunctionName(TokenIterator $tokens): string
    {
        $name = '';
        $isFirst = true;
        while ($tokens->isCurrentTokenType(Lexer::TOKEN_OTHER)) {
            $char = $tokens->currentTokenValue();
            $regexp = $isFirst ? '/^[a-zA-Z]$/u' : '/^[a-zA-Z_0-9]$/u';
            if (preg_match($regexp, $char)) {
                $name .= $char;
                $isFirst = false;
                $tokens->next();
                continue;
            }
            break;
        }
        return $name;
    }
}
