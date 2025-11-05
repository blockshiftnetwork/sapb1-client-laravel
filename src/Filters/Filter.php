<?php

namespace BlockshiftNetwork\SapB1Client\Filters;

/**
 * Base class for OData filter expressions used by the client query builder.
 *
 * Concrete filters extend this class and implement {@see execute()} to convert
 * the filter into the OData query string representation. The base class also
 * handles logical operator chaining and literal escaping helpers.
 */
abstract class Filter
{
    private ?string $op = null;

    /**
     * Set the logical operator that should precede this filter when combined
     * with other filters (e.g. `and` or `or`).
     */
    public function setOperator(string $op): void
    {
        $this->op = $op;
    }

    /**
     * Get the logical operator assigned to this filter, if any.
     */
    public function getOperator(): ?string
    {
        return $this->op;
    }

    /**
     * Escape a literal value so it can be safely embedded into an OData filter
     * expression.
     *
     * Numeric values are left untouched, strings are single-quoted with single
     * quotes escaped (`'` becomes `''`), and all other types are cast to string.
     */
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

    /**
     * Convert the filter into its string representation for an OData query.
     */
    abstract public function execute(): string;
}
