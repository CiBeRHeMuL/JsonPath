<?php

namespace AndrewGos\JsonPath\Parser\Node;

use AndrewGos\JsonPath\Parser\Node\SelectorNodeInterface;

class NameSelectorNode implements SelectorNodeInterface
{
    public function __construct(
        private string $name,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        $eName = str_replace('\\', '\\\\', $this->name);
        $name = '';
        $highLimit = 0xD7FF;
        foreach (mb_str_split($eName) as $char) {
            $ord = mb_ord($char);
            if ($ord > $highLimit) {
                $adjustedCodePoint = $ord - 0x10000;
                $highSurrogate = ($adjustedCodePoint >> 10) + 0xD800;
                $lowSurrogate = ($adjustedCodePoint & 0x3FF) + 0xDC00;
                $char = '\\u' . strtolower(dechex($highSurrogate)) . '\\u' . strtolower(dechex($lowSurrogate));
            } elseif (
                $ord >= 0x0000 && $ord <= 0x0007
                || $ord === 0x000B
                || $ord >= 0x000E && $ord <= 0x001F
            ) {
                $char = '\\u' . str_pad(strtolower(dechex($ord)), 4, '0', STR_PAD_LEFT);
            }
            $name .= $char;
        }
        $name = str_replace(
            ["'", "\u{8}", "\f", "\n", "\r", "\t", '/'],
            ["\\'", '\\b', '\\f', '\\n', '\\r', '\\t', '\\/'],
            $name,
        );
        return "'$name'";
    }
}
