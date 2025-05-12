<?php

namespace AndrewGos\JsonPath;

use AndrewGos\JsonPath\Lexer\Lexer;
use AndrewGos\JsonPath\Parser\Enum\CompareOperatorEnum;
use AndrewGos\JsonPath\Parser\Node;
use AndrewGos\JsonPath\Parser\Node\ExpressionNodeInterface;
use AndrewGos\JsonPath\Parser\Node\FunctionExpressionNode;
use AndrewGos\JsonPath\Parser\Node\JsonPathQueryNode;
use AndrewGos\JsonPath\Parser\Node\LiteralNode;
use AndrewGos\JsonPath\Parser\Node\RelQueryNode;
use AndrewGos\JsonPath\Parser\Node\SingularQueryNodeInterface;
use AndrewGos\JsonPath\Parser\Parser;
use AndrewGos\JsonPath\Parser\TokenIterator;
use AndrewGos\JsonPath\Type\LogicalType;
use AndrewGos\JsonPath\Type\NodesType;
use AndrewGos\JsonPath\Type\NothingType;
use AndrewGos\JsonPath\Type\TypeInterface;
use AndrewGos\JsonPath\Type\ValueType;
use AndrewGos\JsonPath\Type\ValueTypeInterface;
use InvalidArgumentException;
use RuntimeException;

class JsonPathProcessor implements PathProcessorInterface
{
    private Lexer $lexer;
    private Parser $parser;

    private mixed $value;

    private array $functions = [];

    public function __construct()
    {
        $this->lexer = new Lexer();
        $this->parser = new Parser();

        $this->functions = $this->initFunctions();
    }

    private function initFunctions(): array
    {
        return [
            'length' => function (ValueType $v): ValueTypeInterface {
                if (is_string($v->getValue())) {
                    return new ValueType(mb_strlen($v->getValue()));
                } elseif (is_array($v->getValue())) {
                    return new ValueType(count($v->getValue()));
                } elseif (is_object($v->getValue())) {
                    return new ValueType(count((array) $v->getValue()));
                } else {
                    return new NothingType();
                }
            },
            'count' => function (NodesType $v): ValueType {
                return new ValueType(count($v->getNodes()));
            },
        ];
    }

    /**
     * @inheritDoc
     */
    public function query(mixed $value, string $path): array
    {
        $this->value = $value;
        $node = $this->parser->parse(new TokenIterator($this->lexer->tokenize($path)));
        return $this->processQuery($node, $value)->getNodes();
    }

    private function processQuery(Node\JsonPathQueryNode|Node\RelQueryNode $node, mixed $json): NodesType
    {
        $result = [$json];
        foreach ($node->getSegments() as $segment) {
            $result = $this->processSegment($segment, $result);
        }
        return new NodesType($result);
    }

    private function processSegment(Node\SegmentNodeInterface $segment, array $json): array
    {
        if ($segment instanceof Node\DescendantSegmentNode) {
            $result = [];
            $selector = $segment->getSelector();
            foreach ($json as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    foreach ($this->applySelector($selector, $value) as $res) {
                        $result[] = $res;
                    }
                    foreach ($this->processSegment($segment, (array)$value) as $res) {
                        $result[] = $res;
                    }
                }
            }
            return $result;
        } elseif ($segment instanceof Node\ChildSegmentNode) {
            $result = [];
            $selector = $segment->getSelector();
            array_walk(
                $json,
                function ($value) use (&$result, &$selector) {
                    if (is_array($value) || is_object($value)) {
                        foreach ($this->applySelector($selector, $value) as $res) {
                            $result[] = $res;
                        }
                    }
                },
            );
            return $result;
        }
        throw new InvalidArgumentException('Undefined segment of type "' . get_debug_type($segment) . '"');
    }

    private function applySelector(Node\SelectorNodeInterface $selector, array|object $json): array
    {
        return match (true) {
            $selector instanceof Node\WildcardSelectorNode => $this->applyWildcardSelector($selector, $json),
            $selector instanceof Node\ShorthandNameSelectorNode => $this->applyShorthandNameSelector($selector, $json),
            $selector instanceof Node\UnionSelectorNode => $this->applyUnionSelector($selector, $json),
            $selector instanceof Node\IndexSelectorNode => $this->applyIndexSelector($selector, $json),
            $selector instanceof Node\ArraySliceSelectorNode => $this->applyArraySliceSelector($selector, $json),
            $selector instanceof Node\NameSelectorNode => $this->applyNameSelector($selector, $json),
            $selector instanceof Node\FilterSelectorNode => $this->applyFilterSelector($selector, $json),
            default => [],
        };
    }

    private function applyArraySliceSelector(Node\ArraySliceSelectorNode $selector, array|object $value): array
    {
        if ($selector->getStep() === 0 || !is_array($value) || !array_is_list($value)) {
            return [];
        }

        $length = count($value);
        $start = $selector->getStartIndex();
        $end = $selector->getEndIndex();
        $step = $selector->getStep();

        $result = [];
        if ($step > 0) {
            $start ??= 0;
            $end ??= $length;
            $start = $start < 0 ? $length + $start : $start;
            $end = $end < 0 ? $length + $end : $end;
            $start = max($start, 0);
            $end = min($end, $length);
            for ($i = $start; $i < $end; $i += $step) {
                $result[] = $value[$i];
            }
        } else {
            $start ??= $length - 1;
            $end ??= -$length - 1;
            $start = $start < 0 ? $length + $start : $start;
            $end = $end < 0 ? $length + $end : $end;
            $start = min($start, $length - 1);
            $end = max($end, -1);
            for ($i = $start; $i > $end; $i += $step) {
                $result[] = $value[$i];
            }
        }
        return $result;
    }

    private function applyWildcardSelector(Node\WildcardSelectorNode $selector, array|object $json): array
    {
        return (array)$json;
    }

    private function applyShorthandNameSelector(Node\ShorthandNameSelectorNode $selector, array|object $json): array
    {
        if (is_object($json)) {
            return isset($json->{$selector->getName()}) ? [$json->{$selector->getName()}] : [];
        } elseif (is_array($json) && !array_is_list($json)) {
            return isset($json[$selector->getName()]) ? [$json[$selector->getName()]] : [];
        }
        return [];
    }

    private function applyUnionSelector(Node\UnionSelectorNode $selector, array|object $json): array
    {
        $result = [];
        foreach ($selector->getSelectors() as $s) {
            foreach ($this->applySelector($s, $json) as $res) {
                $result[] = $res;
            }
        }
        return $result;
    }

    private function applyIndexSelector(Node\IndexSelectorNode $selector, array|object $json): array
    {
        if (is_array($json) && array_is_list($json)) {
            return isset($json[$selector->getIndex()]) ? [$json[$selector->getIndex()]] : [];
        }
        return [];
    }

    private function applyNameSelector(Node\NameSelectorNode $selector, array|object $json): array
    {
        if (is_object($json)) {
            return isset($json->{$selector->getName()}) ? [$json->{$selector->getName()}] : [];
        } elseif (is_array($json) && !array_is_list($json)) {
            return isset($json[$selector->getName()]) ? [$json[$selector->getName()]] : [];
        }
        return [];
    }

    private function applyFilterSelector(Node\FilterSelectorNode $selector, array|object $json): array
    {
        $result = [];
        $expr = $selector->getExpression();
        foreach ((array)$json as $key => $value) {
            if ($this->executeExpr($expr, $value, $key)) {
                $result[] = $value;
            }
        }
        return $result;
    }

    private function executeExpr(Node\ExpressionNodeInterface $expr, mixed $value, int|string $key): bool
    {
        return match (true) {
            $expr instanceof Node\OrExpressionNode => $this->executeOrExpr($expr, $value, $key),
            $expr instanceof Node\AndExpressionNode => $this->executeAndExpr($expr, $value, $key),
            $expr instanceof Node\NotExpressionNode => $this->executeNotExpr($expr, $value, $key),
            $expr instanceof Node\TestExpressionNode => $this->executeTestExpr($expr, $value, $key),
            $expr instanceof Node\FunctionExpressionNode => $this->executeFunctionExpr($expr, $value, $key),
            $expr instanceof Node\CompareExpressionNode => $this->executeCompareExpr($expr, $value, $key),
        };
    }

    private function executeOrExpr(Node\OrExpressionNode $expr, mixed $value, int|string $key): bool
    {
        foreach ($expr->getExpressions() as $subExpr) {
            if ($this->executeExpr($subExpr, $value, $key)) {
                return true;
            }
        }
        return false;
    }

    private function executeAndExpr(Node\AndExpressionNode $expr, mixed $value, int|string $key): bool
    {
        foreach ($expr->getExpressions() as $subExpr) {
            if (!$this->executeExpr($subExpr, $value, $key)) {
                return false;
            }
        }
        return true;
    }

    private function executeNotExpr(Node\NotExpressionNode $expr, mixed $value, int|string $key): bool
    {
        return !$this->executeExpr($expr->getExpression(), $value, $key);
    }

    private function executeTestExpr(Node\TestExpressionNode $expr, mixed $value, int|string $key): bool
    {
        $subQuery = $expr->getQuery();
        if ($subQuery instanceof FunctionExpressionNode) {
            return $this->executeFunctionExpr($subQuery, $value, $key);
        } else {
            $subQueryResult = $this->processQuery(
                $subQuery,
                match (true) {
                    $subQuery instanceof Node\RelQueryNode => $value,
                    $subQuery instanceof Node\JsonPathQueryNode => $this->value,
                    default => throw new RuntimeException('Undefined query type in test expression'),
                },
            );
            return !!$subQueryResult->getNodes();
        }
    }

    private function executeFunctionExpr(Node\FunctionExpressionNode $expr, mixed $value, int|string $key): bool
    {
        $result = $this->executeFunction($expr, $value, $key);
        if ($result instanceof LogicalType) {
            return $result->isTrue();
        } elseif ($result instanceof NodesType) {
            return !!$result->getNodes();
        } else {
            throw new RuntimeException("Cannot use function {$expr->getName()} in test context");
        }
    }

    private function executeCompareExpr(Node\CompareExpressionNode $expr, mixed $value, int|string $key): bool
    {
        $arg1 = $this->calcComparable($expr->getLeft(), $value, $key);
        $arg2 = $this->calcComparable($expr->getRight(), $value, $key);

        $val1 = $arg1 instanceof ValueType ? $arg1->getValue() : null;
        $val2 = $arg2 instanceof ValueType ? $arg2->getValue() : null;

        return match ($expr->getOperator()) {
            CompareOperatorEnum::Equal => $val1 === $val2,
            CompareOperatorEnum::NotEqual => !($val1 === $val2),
            CompareOperatorEnum::LessThan => $val1 < $val2,
            CompareOperatorEnum::LessThanOrEqual => $val1 < $val2 || $val1 === $val2,
            CompareOperatorEnum::GreaterThan => $val2 < $val1,
            CompareOperatorEnum::GreaterThanOrEqual => $val2 < $val1 || $val1 === $val2,
        };
    }

    private function executeFunction(FunctionExpressionNode $node, mixed $value, int|string $key): TypeInterface
    {
        $arguments = array_map(
            fn($arg) => $this->calcFunctionArgument($arg, $value, $key),
            $node->getParameters(),
        );

        $fn = $this->functions[$node->getName()] ?? null;
        if ($fn) {
            try {
                return $fn(...$arguments);
            } catch (\Throwable $e) {
                throw new RuntimeException("Cannot use function {$node->getName()}");
            }
        } else {
            throw new RuntimeException("Undefined function {$node->getName()}");
        }
    }

    private function calcComparable(
        SingularQueryNodeInterface|FunctionExpressionNode|LiteralNode $comp,
        mixed $value,
        int|string $key,
    ): ValueTypeInterface {
        return match (true) {
            $comp instanceof SingularQueryNodeInterface => $this->querySingular($comp, $value, $key),
            $comp instanceof FunctionExpressionNode => $this->executeFunction($comp, $value, $key),
            $comp instanceof LiteralNode => new ValueType($comp->getValue()),
        };
    }

    private function calcFunctionArgument(
        LiteralNode|JsonPathQueryNode|RelQueryNode|ExpressionNodeInterface|FunctionExpressionNode|SingularQueryNodeInterface $arg,
        mixed $value,
        int|string $key,
    ): TypeInterface {
        return match (true) {
            $arg instanceof LiteralNode => new ValueType($arg->getValue()),
            $arg instanceof JsonPathQueryNode => $this->processQuery($arg, $this->value),
            $arg instanceof RelQueryNode => $this->processQuery($arg, $value),
            $arg instanceof ExpressionNodeInterface => new LogicalType($this->executeExpr($arg, $value, $key)),
            $arg instanceof FunctionExpressionNode => $this->executeFunction($arg, $value, $key),
            $arg instanceof SingularQueryNodeInterface => $this->querySingular($arg, $value, $key),
        };
    }

    private function querySingular(Node\SingularQueryNodeInterface $query, mixed $json, int|string $key): ValueTypeInterface
    {
        $result = [$json];
        foreach ($query->getSegments() as $segment) {
            $result = $this->processSingularSegment($segment, $result);
        }
        if (count($result) > 0) {
            return new ValueType(array_shift($result));
        }
        return new NothingType();
    }

    private function processSingularSegment(Node\SingularSegmentNode $segment, array $json): array
    {
        $result = [];
        $selector = $segment->getSelector();
        array_walk(
            $json,
            function ($value) use (&$result, &$selector) {
                if (is_array($value) || is_object($value)) {
                    foreach ($this->applySelector($selector, $value) as $res) {
                        $result[] = $res;
                    }
                }
            },
        );
        return $result;
    }
}
