<?php
/**
 * AlphaBrowse Module Controller
 *
 * PHP Version 5
 *
 * Copyright (C) Villanova University 2011.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Mark Triggs <vufind-tech@lists.sourceforge.net>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/alphabetical_heading_browse Wiki
 */
namespace VuFind\Controller;
use VuFind\Config\Reader as ConfigReader,
    VuFind\Connection\Manager as ConnectionManager,
    VuFind\Exception\Solr as SolrException;

/**
 * AlphabrowseController Class
 *
 * Controls the alphabetical browsing feature
 *
 * @category VuFind2
 * @package  Controller
 * @author   Mark Triggs <vufind-tech@lists.sourceforge.net>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/alphabetical_heading_browse Wiki
 */
class AlphabrowseController extends AbstractBase
{
    /**
     * Gathers data for the view of the AlphaBrowser and does some initialization
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function homeAction()
    {
        $config = ConfigReader::getConfig();

        // Load browse types from config file, or use defaults if unavailable:
        if (isset($config->AlphaBrowse_Types)
            && !empty($config->AlphaBrowse_Types)
        ) {
            $types = array();
            foreach ($config->AlphaBrowse_Types as $key => $value) {
                $types[$key] = $value;
            }
        } else {
            $types = array(
                'topic'  => 'By Topic',
                'author' => 'By Author',
                'title'  => 'By Title',
                'lcc'    => 'By Call Number'
            );
        }

        // Connect to Solr:
        $db = ConnectionManager::connectToIndex();

        // Process incoming parameters:
        $source = $this->params()->fromQuery('source', false);
        $from   = $this->params()->fromQuery('from', false);
        $page   = intval($this->params()->fromQuery('page', 0));
        $limit  = isset($config->AlphaBrowse->page_size)
            ? $config->AlphaBrowse->page_size : 20;


        // Create view model:
        $view = $this->createViewModel();

        // If required parameters are present, load results:
        if ($source && $from !== false) {
            // Load Solr data or die trying:
            try {
                $result = $db->alphabeticBrowse($source, $from, $page, $limit);

                // No results?    Try the previous page just in case we've gone past
                // the end of the list....
                if ($result['Browse']['totalCount'] == 0) {
                    $page--;
                    $result = $db->alphabeticBrowse($source, $from, $page, $limit);
                }
            } catch (SolrException $e) {
                if ($e->isMissingBrowseIndex()) {
                    throw new \Exception(
                        "Alphabetic Browse index missing.    See " .
                        "http://vufind.org/wiki/alphabetical_heading_browse for " .
                        "details on generating the index."
                    );
                }
                throw $e;
            }

            // Only display next/previous page links when applicable:
            if ($result['Browse']['totalCount'] > $limit) {
                $view->nextpage = $page + 1;
            }
            if ($result['Browse']['offset'] + $result['Browse']['startRow'] > 1) {
                $view->prevpage = $page - 1;
            }
            $view->result = $result;
        }

        $view->alphaBrowseTypes = $types;
        $view->from = $from;
        $view->source = $source;
        return $view;
    }
}