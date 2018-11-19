<?php

/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Yawik\Composer\Event;

use Composer\Composer;
use Zend\EventManager\Event;
use Composer\IO\IOInterface;
use Yawik\Composer\Plugin;

class PreConfigureEvent extends Event
{
    /**
     * @var IOInterface
     */
    private $output;

    /**
     * @var Composer
     */
    private $composer;


    public function __construct(Composer $composer, IOInterface $output)
    {
        $this->output       = $output;
        $this->composer     = $composer;

        parent::__construct(Plugin::YAWIK_PRE_CONFIGURE_EVENT);
    }

    /**
     * @return IOInterface
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * @return Composer
     */
    public function getComposer()
    {
        return $this->composer;
    }
}
