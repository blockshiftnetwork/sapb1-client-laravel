<?php

namespace BlockshiftNetwork\SapB1Client\Filters;

abstract class Filter
{
    private ?string $op = null;

    public function setOperator(string $op): void
    {
        $this->op = $op;
    }

    public function getOperator(): ?string
    {
        return $this->op;
    }

    public function escape(mixed $value): string|int|float
    {
        // Return numeric values as-is (without quotes)
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        // Escape and quote string values
        if (is_string($value)) {
            $value = str_replace("'", "''", $value);

            return "'".$value."'";
        }

        // For any other type, cast to string
        return (string) $value;
    }

    abstract public function execute(): string;
}
