<?php
/**
 * CLI Controller Module
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
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
namespace VuFindConsole\Controller;
use File_MARC, File_MARCXML, VuFind\Connection\Manager as ConnectionManager,
    VuFind\Sitemap, Zend\Console\Console;

/**
 * This controller handles various command-line tools
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
class UtilController extends AbstractBase
{
    /**
     * Display help for the index reserves action.
     *
     * @param string $msg Extra message to display
     *
     * @return \Zend\Console\Response
     */
    protected function indexReservesHelp($msg = '')
    {
        if (!empty($msg)) {
            foreach (explode("\n", $msg) as $line) {
                Console::writeLine($line);
            }
            Console::writeLine('');
        }

        Console::writeLine('Course reserves index builder');
        Console::writeLine('');
        Console::writeLine(
            'If run with no options, this will attempt to load data from your ILS.'
        );
        Console::writeLine('');
        Console::writeLine(
            'Switches may be used to index from delimited files instead:'
        );
        Console::writeLine('');
        Console::writeLine(
            ' -f [filename] loads a file (may be repeated for multiple files)'
        );
        Console::writeLine(
            ' -d [delimiter] specifies a delimiter (comma is default)'
        );
        Console::writeLine(
            ' -t [template] provides a template showing where important values'
        );
        Console::writeLine(
            '     can be found within the file.  The template is a comma-'
        );
        Console::writeLine('     separated list of values.  Choose from:');
        Console::writeLine('          BIB_ID     - bibliographic ID');
        Console::writeLine('          COURSE     - course name');
        Console::writeLine('          DEPARTMENT - department name');
        Console::writeLine('          INSTRUCTOR - instructor name');
        Console::writeLine('          SKIP       - ignore data in this position');
        Console::writeLine(
            '     Default template is BIB_ID,COURSE,INSTRUCTOR,DEPARTMENT'
        );
        Console::writeLine(' -h or -? display this help information.');

        return $this->getFailureResponse();
    }

    /**
     * Build the Reserves index.
     *
     * @return \Zend\Console\Response
     */
    public function indexreservesAction()
    {
        ini_set('memory_limit', '50M');
        ini_set('max_execution_time', '3600');

        $this->consoleOpts->setOptions(
            array(\Zend\Console\Getopt::CONFIG_CUMULATIVE_PARAMETERS => true)
        );
        $this->consoleOpts->addRules(
            array(
                'h|help' => 'Get help',
                'd-s' => 'Delimiter',
                't-s' => 'Template',
                'f-s' => 'File',
            )
        );

        if ($this->consoleOpts->getOption('h')
            || $this->consoleOpts->getOption('help')
        ) {
            return $this->indexReservesHelp();
        } elseif ($file = $this->consoleOpts->getOption('f')) {
            try {
                $delimiter = ($d = $this->consoleOpts->getOption('d')) ? $d : ',';
                $template = ($t = $this->consoleOpts->getOption('t')) ? $t : null;
                $reader = new \VuFind\Reserves\CsvReader(
                    $file, $delimiter, $template
                );
                $instructors = $reader->getInstructors();
                $courses = $reader->getCourses();
                $departments = $reader->getDepartments();
                $reserves = $reader->getReserves();
            } catch (\Exception $e) {
                return $this->indexReservesHelp($e->getMessage());
            }
        } elseif ($this->consoleOpts->getOption('d')) {
            return $this->indexReservesHelp('-d is meaningless without -f');
        } elseif ($this->consoleOpts->getOption('t')) {
            return $this->indexReservesHelp('-t is meaningless without -f');
        } else {
            try {
                // Connect to ILS and load data:
                $catalog = $this->getILS();
                $instructors = $catalog->getInstructors();
                $courses = $catalog->getCourses();
                $departments = $catalog->getDepartments();
                $reserves = $catalog->findReserves('', '', '');
            } catch (\Exception $e) {
                return $this->indexReservesHelp($e->getMessage());
            }
        }

        // Make sure we have reserves and at least one of: instructors, courses,
        // departments:
        if ((!empty($instructors) || !empty($courses) || !empty($departments))
            && !empty($reserves)
        ) {
            // Setup Solr Connection
            $solr = ConnectionManager::connectToIndex('SolrReserves');

            // Delete existing records
            $solr->deleteAll();

            // Build the index
            $solr->buildIndex($instructors, $courses, $departments, $reserves);

            // Commit and Optimize the Solr Index
            $solr->commit();
            $solr->optimize();

            Console::writeLine('Successfully loaded ' . count($reserves) . ' rows.');
            return $this->getSuccessResponse();
        }
        return $this->indexReservesHelp('Unable to load data.');
    }

    /**
     * Optimize the Solr index.
     *
     * @return \Zend\Console\Response
     */
    public function optimizeAction()
    {
        ini_set('memory_limit', '50M');
        ini_set('max_execution_time', '3600');

        // Setup Solr Connection -- Allow core to be specified as first command line
        // param.
        $argv = $this->consoleOpts->getRemainingArgs();
        $solr = ConnectionManager::connectToIndex(
            null, isset($argv[0]) ? $argv[0] : ''
        );

        // Commit and Optimize the Solr Index
        $solr->commit();
        $solr->optimize();
        return $this->getSuccessResponse();
    }

    /**
     * Generate a Sitemap
     *
     * @return \Zend\Console\Response
     */
    public function sitemapAction()
    {
        // Build sitemap and display appropriate warnings if needed:
        $generator = new Sitemap();
        $generator->generate();
        foreach ($generator->getWarnings() as $warning) {
            Console::writeLine("$warning");
        }
        return $this->getSuccessResponse();
    }

    /**
     * Command-line tool to batch-delete records from the Solr index.
     *
     * @return \Zend\Console\Response
     */
    public function deletesAction()
    {
        // Parse the command line parameters -- see if we are in "flat file" mode,
        // find out what file we are reading in,
        // and determine the index we are affecting!
        $argv = $this->consoleOpts->getRemainingArgs();
        $filename = isset($argv[0]) ? $argv[0] : null;
        $mode = isset($argv[1]) ? $argv[1] : 'marc';
        $index = isset($argv[2]) ? $argv[2] : 'Solr';

        // No filename specified?  Give usage guidelines:
        if (empty($filename)) {
            Console::writeLine("Delete records from VuFind's index.");
            Console::writeLine("");
            Console::writeLine("Usage: deletes.php [filename] [format] [index]");
            Console::writeLine("");
            Console::writeLine(
                "[filename] is the file containing records to delete."
            );
            Console::writeLine(
                "[format] is the format of the file -- "
                . "it may be one of the following:"
            );
            Console::writeLine(
                "\tflat - flat text format "
                . "(deletes all IDs in newline-delimited file)"
            );
            Console::writeLine(
                "\tmarc - binary MARC format (delete all record IDs from 001 fields)"
            );
            Console::writeLine(
                "\tmarcxml - MARC-XML format (delete all record IDs from 001 fields)"
            );
            Console::writeLine(
                '"marc" is used by default if no format is specified.'
            );
            Console::writeLine("[index] is the index to use (default = Solr)");
            return $this->getFailureResponse();
        }

        // File doesn't exist?
        if (!file_exists($filename)) {
            Console::writeLine("Cannot find file: {$filename}");
            return $this->getFailureResponse();
        }

        // Setup Solr Connection
        $solr = ConnectionManager::connectToIndex($index);

        // Build list of records to delete:
        $ids = array();

        // Flat file mode:
        if ($mode == 'flat') {
            foreach (explode("\n", file_get_contents($filename)) as $id) {
                $id = trim($id);
                if (!empty($id)) {
                    $ids[] = $id;
                }
            }
        } else {
            // MARC file mode...  We need to load the MARC record differently if it's
            // XML or binary:
            $collection = ($mode == 'marcxml')
                ? new File_MARCXML($filename) : new File_MARC($filename);

            // Once the records are loaded, the rest of the logic is always the same:
            while ($record = $collection->next()) {
                $idField = $record->getField('001');
                $ids[] = (string)$idField->getData();
            }
        }

        // Delete, Commit and Optimize if necessary:
        if (!empty($ids)) {
            $solr->deleteRecords($ids);
            $solr->commit();
            $solr->optimize();
        }
        return $this->getSuccessResponse();
    }

    /**
     * Command-line tool to clear unwanted entries
     * from search history database table.
     *
     * @return \Zend\Console\Response
     */
    public function expiresearchesAction()
    {
        // Get command-line arguments
        $argv = $this->consoleOpts->getRemainingArgs();

        // Use command line value as expiration age, or default to 2.
        $daysOld = isset($argv[0]) ? intval($argv[0]) : 2;

        // Abort if we have an invalid expiration age.
        if ($daysOld < 2) {
            Console::writeLine("Expiration age must be at least two days.");
            return $this->getFailureResponse();
        }

        // Delete the expired searches--this cleans up any junk left in the database
        // from old search histories that were not
        // caught by the session garbage collector.
        $search = $this->getTable('Search');
        $query = $search->getExpiredQuery($daysOld);
        if (($count = count($search->select($query))) == 0) {
            Console::writeLine("No expired searches to delete.");
            return $this->getSuccessResponse();
        }
        $search->delete($query);
        Console::writeLine("{$count} expired searches deleted.");
        return $this->getSuccessResponse();
    }

    /**
     * Command-line tool to delete suppressed records from the index.
     *
     * @return \Zend\Console\Response
     */
    public function suppressedAction()
    {
        // Setup Solr Connection
        $this->consoleOpts->addRules(
            array(
                'authorities' =>
                    'Delete authority records instead of bibliographic records'
            )
        );
        $core = $this->consoleOpts->getOption('authorities')
            ? 'authority' : 'biblio';

        $solr = ConnectionManager::connectToIndex('Solr', $core);

        // Make ILS Connection
        try {
            $catalog = $this->getILS();
            if ($core == 'authority') {
                $result = $catalog->getSuppressedAuthorityRecords();
            } else {
                $result = $catalog->getSuppressedRecords();
            }
        } catch (\Exception $e) {
            Console::writeLine("ILS error -- " . $e->getMessage());
            return $this->getFailureResponse();
        }

        // Validate result:
        if (!is_array($result)) {
            Console::writeLine("Could not obtain suppressed record list from ILS.");
            return $this->getFailureResponse();
        } else if (empty($result)) {
            Console::writeLine("No suppressed records to delete.");
            return $this->getSuccessResponse();
        }

        // Get Suppressed Records and Delete from index
        $status = $solr->deleteRecords($result);
        if ($status) {
            // Commit and Optimize
            $solr->commit();
            $solr->optimize();
        } else {
            Console::writeLine("Delete failed.");
            return $this->getFailureResponse();
        }
        return $this->getSuccessResponse();
    }

    /**
     * Tool to auto-fill hierarchy cache.
     *
     * @return \Zend\Console\Response
     */
    public function createhierarchytreesAction()
    {
        $solr = $this->getSearchManager()->setSearchClassId('Solr')->getResults();
        $hierarchies = $solr->getFullFieldFacets(array('hierarchy_top_id'));
        foreach ($hierarchies['hierarchy_top_id']['data']['list'] as $hierarchy) {
            Console::writeLine("Building tree for {$hierarchy['value']}...");
            $driver = $solr->getRecord($hierarchy['value']);
            if ($driver->getHierarchyType()) {
                // Only do this if the record is actually a hierarchy type record
                $driver->getHierarchyDriver()->getTreeSource()
                    ->getXML($hierarchy['value'], array('refresh' => true));
            }
        }
        return $this->getSuccessResponse();
    }
}
