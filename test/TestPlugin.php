<?php

/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YawikTest\Composer;

use Composer\IO\IOInterface;
use Core\Application;
use Yawik\Composer\Plugin;
use Zend\EventManager\EventManager;

/**
 * A class to make Plugin class testable
 * @package YawikTest\Composer
 */
class TestPlugin extends Plugin
{
    /**
     * @param EventManager $manager
     */
    public function setEventManager(EventManager $manager)
    {
        $this->eventManager = $manager;
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

    public function getComposer()
    {
        return $this->composer;
    }

    public function getOutput()
    {
        return $this->output;
    }
}
