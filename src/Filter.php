<?php

/*
 *
 *
 * (c) Allen, Li <morningbuses@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Goodcatch\FXK;

use InvalidArgumentException;


/**
 * Class FXK
 * @package Goodcatch\FXK
 */
class Filter
{

    const FILTER_OPERATOR = [
        '=' => 'EQ',
        '>' => 'GT',
        '<' => 'LT',
        '>=' => 'GTE',
        '<=' => 'LTE',
        '<>' => 'N',
        'LIKE' => 'LIKE',
        'NOT LIKE' => 'NLIKE',
        'IS' => 'IS',
        'IS NOT' => 'ISN',
        'IN' => 'IN',
        'NOT IN' => 'NIN',
        'BETWEEN' => 'BETWEEN',
        'NOT BETWEEN' => 'NBETWEEN',
        'STARTWITH' => 'STARTWITH',
        'ENDWITH' => 'ENDWITH',
        'NOT EXISTS' => 'NEXISTS',
        'EXISTS' => 'EXISTS',
        'ARRAY CONTAINS' => 'CONTAINS',
    ];

    /**
     * @var mixed caller
     */
    private $caller;

    /**
     * @var array filter
     */
    private $filter = [];

    /**
     * @var array order
     */
    private $order = [];

    /**
     * Filter constructor.
     * @param mixed $caller
     */
    public function __construct($caller)
    {
        $this->caller = $caller;
    }

    private function order ($field, $isAsc = true)
    {
        $this->order [] = [
            "fieldName"=> $field,
            "isAsc"=> $isAsc
        ];
    }

    public function asc ($field)
    {
        $this->order ($field);
    }

    public function desc ($field)
    {
        $this->order ($field, false);
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param  string $field
     * @param  mixed   $operator
     * @param  mixed   $value
     * @return $this
     */
    public function where($field, $operator = null, $value = null)
    {

        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        list($value, $operator) = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() == 2
        );

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalidOperator($operator)) {
            list($value, $operator) = [$operator, '='];
        }

        $this->filter[] = [
            'field_name' => $field,
            'field_values' => is_array($value) ? $value : [$value],
            'operator' => self::FILTER_OPERATOR [$operator]
        ];

        return $this;
    }

    /**
     * Prepare the value and operator for a where clause.
     *
     * @param  string  $value
     * @param  string  $operator
     * @param  bool  $useDefault
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    public function prepareValueAndOperator($value, $operator, $useDefault = false)
    {
        if ($useDefault) {
            return [$operator, '='];
        } elseif ($this->invalidOperatorAndValue($operator, $value)) {
            throw new InvalidArgumentException('Illegal operator and value combination.');
        }

        return [$value, $operator];
    }

    /**
     * Determine if the given operator and value combination is legal.
     *
     * Prevents using Null values with invalid operators.
     *
     * @param  string  $operator
     * @param  mixed  $value
     * @return bool
     */
    protected function invalidOperatorAndValue($operator, $value)
    {
        return is_null($value) && ! array_key_exists (strtoupper($operator), self::FILTER_OPERATOR);
    }

    /**
     * Determine if the given operator is supported.
     *
     * @param  string  $operator
     * @return bool
     */
    protected function invalidOperator($operator)
    {
        return ! array_key_exists(strtoupper($operator), self::FILTER_OPERATOR);
    }

    /**
     * @return array
     */
    public function build () {
        return $this->filter;
    }

    /**
     * @return array
     */
    public function buildOrder ()
    {
        if (count ($this->order) < 1 && count ($this->filter) > 0)
        {
            $this->order ($this->filter [0] ['field_name']);
        }
        return $this->order;
    }

    /**
     * @return mixed
     */
    public function end ()
    {
        return $this->caller;
    }

}