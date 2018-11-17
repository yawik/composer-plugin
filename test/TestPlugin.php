<?php

/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YawikTest\Composer;

use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Core\Application;
use Yawik\Composer\Plugin;

/**
 * A class to make Plugin class testable
 * @package YawikTest\Composer
 */
class TestPlugin extends Plugin
{
    /**
     * @param EventDispatcher $dispatcher
     */
    public function setDispatcher($dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param Application $application
     */
    public function setApplication($application)
    {
        $this->application = $application;
    }

    public function setInstalledModules(array $modules = [])
    {
        $this->installed = $modules;
    }

    public function setUninstalledModules(array $modules = [])
    {
        $this->uninstalled = $modules;
    }

    public function setOutput(IOInterface $output)
    {
        $this->output = $output;
    }
}
