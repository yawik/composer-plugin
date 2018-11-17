<?php

/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Yawik\Composer\Event;

use Zend\EventManager\Event;
use Core\Options\ModuleOptions as CoreOptions;
use Yawik\Composer\Plugin;

/**
 * Class ConfigureYawikEvent
 * @package Yawik\Composer\Event
 */
class ConfigureEvent extends Event
{
    /**
     * @var array
     */
    private $modules;

    /**
     * @var CoreOptions
     */
    private $options;

    public function __construct(CoreOptions $options, $modules)
    {
        $this->modules  = $modules;
        $this->options  = $options;
        parent::__construct(Plugin::YAWIK_CONFIGURE_EVENT);
    }

    /**
     * @return array
     */
    public function getModules()
    {
        return $this->modules;
    }

    /**
     * @return CoreOptions
     */
    public function getOptions()
    {
        return $this->options;
    }
}
