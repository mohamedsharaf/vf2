<?php

/**
 * A group of single/simples queries, joined by boolean operator.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */

namespace VuFindSearch\Query;

use VuFindSearch\Exception\InvalidArgumentException;

/**
 * A group of single/simples queries, joined by boolean operator.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class QueryGroup extends AbstractQuery
{

    /**
     * Valid boolean operators.
     *
     * @var array
     */
    protected static $operators = array('AND', 'OR', 'NOT');

    /**
     * Name of the handler to be used if the query group is reduced.
     *
     * @see VuFindSearch\Backend\Solr\QueryBuilder::reduceQueryGroup()
     *
     * @var string
     *
     * @todo Check if we actually use/need this feature
     */
    protected $reducedHandler;

    /**
     * Boolean operator.
     *
     * @var string
     */
    protected $operator;

    /**
     * Is the query group negated?
     *
     * @var boolean
     */
    protected $negation;

    /**
     * Queries.
     *
     * @var array
     */
    protected $queries;

    /**
     * Constructor.
     *
     * @param string $operator       Boolean operator
     * @param array  $queries        Queries
     * @param string $reducedHandler Handler to be uses if reduced
     *
     * @return void
     */
    public function __construct ($operator, array $queries = array(), $reducedHandler = null)
    {
        $this->setOperator($operator);
        $this->setQueries($queries);
        $this->setReducedHandler($reducedHandler);
    }

    /**
     * Return name of reduced handler.
     *
     * @return string|null
     */
    public function getReducedHandler ()
    {
        return $this->reducedHandler;
    }

    /**
     * Set name of reduced handler.
     *
     * @param string $handler Reduced handler
     *
     * @return void
     */
    public function setReducedHandler ($handler)
    {
        $this->reducedHandler = $handler;
    }

    /**
     * Unset reduced handler.
     *
     * @return void
     */
    public function unsetReducedHandler ()
    {
        $this->reducedHandler = null;
    }

    /**
     * Add a query to the group.
     *
     * @param \VuFind\Search\AbstractQuery $query Query to add
     *
     * @return void
     */
    public function addQuery (AbstractQuery $query)
    {
        $this->queries[] = $query;
    }

    /**
     * Return group queries.
     *
     * @return array
     */
    public function getQueries ()
    {
        return $this->queries;
    }

    /**
     * Set group queries.
     *
     * @param array $queries Group queries
     *
     * @return void
     */
    public function setQueries (array $queries)
    {
        foreach ($queries as $query) {
            $this->addQuery($query);
        }
    }

    /**
     * Set boolean operator.
     *
     * @param string $operator Boolean operator
     *
     * @return void
     *
     * @throws \InvalidArgumentException Unknown or invalid boolean operator
     */
    public function setOperator ($operator)
    {
        if (!in_array($operator, self::$operators)) {
            throw new InvalidArgumentException("Unknown or invalid boolean operator: {$operator}");
        }
        if ($operator == 'NOT') {
            $this->operator = 'OR';
            $this->negation = true;
        } else {
            $this->operator = $operator;
        }
    }

    /**
     * Return boolean operator.
     *
     * @return string
     */
    public function getOperator ()
    {
        return $this->operator;
    }

    /**
     * Return true if group is an exclusion group.
     *
     * @return boolean
     */
    public function isNegated ()
    {
        return $this->negation;
    }
}