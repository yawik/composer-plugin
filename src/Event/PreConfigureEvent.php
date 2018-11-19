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

    public function __construct(IOInterface $output)
    {
        $this->output       = $output;
        parent::__construct(Plugin::YAWIK_PRE_CONFIGURE_EVENT);
    }

    /**
     * @return IOInterface
     */
    public function getOutput()
    {
        return $this->output;
    }
}
