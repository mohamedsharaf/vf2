<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_ModuleManager
 */

namespace Zend\ModuleManager\Feature;

use Zend\Console\AdapterInterface;

/**
 * @category   Zend
 * @package    Zend_ModuleManager
 * @subpackage Feature
 */
interface ConsoleBannerProviderInterface
{
    /**
     * Returns a string containing a banner text, that describes the module and/or the application.
     * The banner is shown in the console window, when the user supplies invalid command-line parameters or invokes
     * the application with no parameters.
     *
     * The method is called with active Zend\Console\AdapterInterface that can be used to directly access Console and send
     * output.
     *
     * @param \Zend\Console\AdapterInterface $console
     * @return string|null
     */
    public function getConsoleBanner(AdapterInterface $console);
}