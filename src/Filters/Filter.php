<?php

namespace BlockshiftNetwork\SapB1Client\Filters;

abstract class Filter
{
    private $op;

    public function setOperator($op)
    {
        $this->op = $op;
    }

    public function getOperator()
    {
        return $this->op;
    }

    public function escape($value)
    {
        if (is_string($value)) {
            $value = str_replace("'", "''", $value);

            return "'" . $value . "'";
        }

        return $value;
    }

    public abstract function execute();
}

