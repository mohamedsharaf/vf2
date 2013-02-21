<?php

/**
 * Simple JSON-based record collection.
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

namespace VuFindSearch\Backend\Solr\Response\Json;

use VuFindSearch\Response\RecordCollectionInterface;
use VuFindSearch\Response\RecordInterface;

use VuFindSearch\Exception\RuntimeException;

/**
 * Simple JSON-based record collection.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class RecordCollection implements RecordCollectionInterface
{

    /**
     * Template of deserialized SOLR response.
     *
     * @see self::__construct()
     *
     * @var array
     */
    protected static $template = array(
        'responseHeader' => array('QTime' => 0),
        'response'       => array('start' => 0),
        'facet_counts'   => array(),
    );

    /**
     * Deserialized SOLR response.
     *
     * @var array
     */
    protected $response;

    /**
     * Response records.
     *
     * @var array
     */
    protected $records;

    /**
     * Facets.
     *
     * @var Facets
     */
    protected $facets;

    /**
     * Constructor.
     *
     * @param array $response Deserialized SOLR response
     *
     * @return void
     */
    public function __construct (array $response)
    {
        $this->response = array_replace_recursive(static::$template, $response);
        $this->offset   = $response['response']['start'];
        $this->records  = array();
        $this->rewind();
    }

    /**
     * Return raw deserialized response.
     *
     * @return array
     *
     * @todo Remove once we don't need it anymore (02/2013)
     */
    public function getRawResponse ()
    {
        return $this->response;
    }

    /**
     * Return total number of records found.
     *
     * @return int
     */
    public function getTotal ()
    {
        return $this->response['response']['numFound'];
    }

    /**
     * Return query time in milli-seconds.
     *
     * @return float
     */
    public function getQueryTime ()
    {
        return $this->response['responseHeader']['QTime'];
    }

    /**
     * Return SOLR facet information.
     *
     * @return array
     */
    public function getFacets ()
    {
        if (!$this->facets) {
            $this->facets = new Facets($this->response['facet_counts']);
        }
        return $this->facets;
    }

    /**
     * Return records.
     *
     * @return array
     */
    public function getRecords ()
    {
        return $this->records;
    }

    /**
     * Return offset in the total search result set.
     *
     * @return int
     */
    public function getOffset ()
    {
        return $this->response['response']['start'];
    }

    /**
     * Return first record in response.
     *
     * @return RecordInterface|null
     */
    public function first ()
    {
        return isset($this->records[$this->offset]) ? $this->records[$this->offset] : null;
    }

    /**
     * Set the source backend identifier.
     *
     * @param string $identifier Backend identifier
     *
     * @return void
     */
    public function setSourceIdentifier ($identifier)
    {
        $this->source = $identifier;
    }

    /**
     * Return the source backend identifier.
     *
     * @return string
     */
    public function getSourceIdentifier ()
    {
        return $this->source;
    }

    /**
     * Add a record to the collection.
     *
     * @param RecordInterface $record Record to add
     *
     * @return void
     */
    public function add (RecordInterface $record)
    {
        if (!in_array($record, $this->records, true)) {
            $this->records[$this->pointer] = $record;
            $this->next();
        }
    }

    /// Iterator interface

    /**
     * Return true if current collection index is valid.
     *
     * @return boolean
     */
    public function valid ()
    {
        return isset($this->records[$this->pointer]);
    }

    /**
     * Return record at current collection index.
     *
     * @return RecordInterface
     */
    public function current ()
    {
        return $this->records[$this->pointer];
    }

    /**
     * Rewind collection index.
     *
     * @return void
     */
    public function rewind ()
    {
        $this->pointer = $this->offset;
    }

    /**
     * Move to next collection index.
     *
     * @return void
     */
    public function next ()
    {
        $this->pointer++;
    }

    /**
     * Return current collection index.
     *
     * @return integer
     */
    public function key ()
    {
        return $this->pointer;
    }

    /// Countable interface

    /**
     * Return number of records in collection.
     *
     * @return integer
     */
    public function count ()
    {
        return count($this->records);
    }

}