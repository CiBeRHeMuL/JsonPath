<?php

namespace AndrewGos\JsonPath\Lexer;

class Lexer
{
    public const TOKEN_DOLLAR = 1;
    public const TOKEN_OPEN_SQUARE_BRACKET = 2;
    public const TOKEN_CLOSE_SQUARE_BRACKET = 3;
    public const TOKEN_OPEN_PARENTHESES = 4;
    public const TOKEN_CLOSE_PARENTHESES = 5;
    public const TOKEN_COMMA = 6;
    public const TOKEN_COLON = 8;
    public const TOKEN_WILDCARD = 9;
    public const TOKEN_HORIZONTAL_TAB = 10;
    public const TOKEN_WHITESPACE = 11;
    public const TOKEN_SINGLE_QUOTE = 12;
    public const TOKEN_DOUBLE_QUOTE = 13;
    public const TOKEN_DIGIT = 14;
    public const TOKEN_SLASH = 15;
    public const TOKEN_BACKSLASH = 16;
    public const TOKEN_MINUS = 17;
    public const TOKEN_PLUS = 18;
    public const TOKEN_QUESTION = 19;
    public const TOKEN_UNION = 20;
    public const TOKEN_INTERSECTION = 21;
    public const TOKEN_AT = 22;
    public const TOKEN_NEGATED = 23;
    public const TOKEN_DOT = 24;
    public const TOKEN_LESS = 25;
    public const TOKEN_GREATER = 26;
    public const TOKEN_NEW_LINE = 27;
    public const TOKEN_CARRIAGE_RETURN = 28;
    public const TOKEN_END = 29;
    public const TOKEN_EQUAL = 30;
    public const TOKEN_OTHER = 31;

    public const S_TOKENS = [self::TOKEN_WHITESPACE, self::TOKEN_NEW_LINE, self::TOKEN_CARRIAGE_RETURN, self::TOKEN_HORIZONTAL_TAB];
    public const START_COMP_TOKENS = [self::TOKEN_LESS, self::TOKEN_GREATER, self::TOKEN_EQUAL, self::TOKEN_NEGATED];
    public const AFTER_QUERY_TOKENS = [
        ...self::START_COMP_TOKENS,
        ...self::S_TOKENS,
        self::TOKEN_CLOSE_PARENTHESES,
        self::TOKEN_CLOSE_SQUARE_BRACKET,
        self::TOKEN_COMMA,
        self::TOKEN_INTERSECTION,
        self::TOKEN_UNION,
    ];

    public const TOKEN_LABELS = [
        self::TOKEN_DOLLAR => "'$'",
        self::TOKEN_OPEN_SQUARE_BRACKET => "'['",
        self::TOKEN_CLOSE_SQUARE_BRACKET => "']'",
        self::TOKEN_OPEN_PARENTHESES => "'('",
        self::TOKEN_CLOSE_PARENTHESES => "')'",
        self::TOKEN_COMMA => "','",
        self::TOKEN_COLON => "':'",
        self::TOKEN_WILDCARD => "'*'",
        self::TOKEN_HORIZONTAL_TAB => "\\t",
        self::TOKEN_WHITESPACE => "' '",
        self::TOKEN_SINGLE_QUOTE => "\"'\"",
        self::TOKEN_DOUBLE_QUOTE => "'\"'",
        self::TOKEN_DIGIT => "any digit",
        self::TOKEN_SLASH => "'/'",
        self::TOKEN_BACKSLASH => "'\\'",
        self::TOKEN_MINUS => "'-'",
        self::TOKEN_PLUS => "'+'",
        self::TOKEN_QUESTION => "'?'",
        self::TOKEN_UNION => "'|'",
        self::TOKEN_INTERSECTION => "'&'",
        self::TOKEN_AT => "'@'",
        self::TOKEN_NEGATED => "'!'",
        self::TOKEN_DOT => "'.'",
        self::TOKEN_LESS => "'<'",
        self::TOKEN_GREATER => "'>'",
        self::TOKEN_NEW_LINE => "\\n",
        self::TOKEN_CARRIAGE_RETURN => "\\r",
        self::TOKEN_END => "end of input",
        self::TOKEN_EQUAL => "'='",
        self::TOKEN_OTHER => "any other char",
    ];

    public const VALUE_OFFSET = 0;
    public const TYPE_OFFSET = 1;

    private ?string $regexp = null;

    /**
     * @param string $s
     *
     * @return list<array{string, int}>
     */
    public function tokenize(string $s): array
    {
        if ($this->regexp === null) {
            $this->regexp = $this->generateRegexp();
        }
        preg_match_all($this->regexp, $s, $matches, PREG_SET_ORDER);
        $tokens = [];
        foreach ($matches as $match) {
            $type = (int)$match['MARK'];
            $tokens[] = [$match[0], $type];
        }
        $tokens[] = ['', self::TOKEN_END];
        return $tokens;
    }

    private function generateRegexp(): string
    {
        $patterns = [
            self::TOKEN_DOLLAR => '\\$',
            self::TOKEN_OPEN_SQUARE_BRACKET => '\\[',
            self::TOKEN_CLOSE_SQUARE_BRACKET => '\\]',
            self::TOKEN_OPEN_PARENTHESES => '\\(',
            self::TOKEN_CLOSE_PARENTHESES => '\\)',
            self::TOKEN_COMMA => ',',
            self::TOKEN_COLON => ':',
            self::TOKEN_WILDCARD => '\\*',
            self::TOKEN_HORIZONTAL_TAB => "\t",
            self::TOKEN_WHITESPACE => ' ',
            self::TOKEN_SINGLE_QUOTE => "'",
            self::TOKEN_DOUBLE_QUOTE => '"',
            self::TOKEN_DIGIT => '[0-9]',
            self::TOKEN_SLASH => '\\/',
            self::TOKEN_BACKSLASH => '\\\\',
            self::TOKEN_MINUS => '\\-',
            self::TOKEN_PLUS => '\\+',
            self::TOKEN_QUESTION => '\\?',
            self::TOKEN_UNION => '\\|',
            self::TOKEN_INTERSECTION => '&',
            self::TOKEN_AT => '@',
            self::TOKEN_NEGATED => '!',
            self::TOKEN_DOT => '\\.',
            self::TOKEN_LESS => '<',
            self::TOKEN_GREATER => '>',
            self::TOKEN_NEW_LINE => '\\r',
            self::TOKEN_CARRIAGE_RETURN => '\\r',
            self::TOKEN_EQUAL => '=',
            self::TOKEN_OTHER => '[^\\s]',
        ];
        foreach ($patterns as $type => &$pattern) {
            $pattern = '(?:' . $pattern . ')(*MARK:' . $type . ')';
        }
        return '~' . implode('|', $patterns) . '~Au';
    }
}
