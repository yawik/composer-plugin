<?php

/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YawikTest\Composer\Event;

use Composer\Composer;
use Composer\IO\IOInterface;
use Yawik\Composer\Event\PreConfigureEvent;
use PHPUnit\Framework\TestCase;

/**
 * Class ActivateEventTest
 *
 * @package YawikTest\Composer\Event
 * @author  Anthonius Munthi <https://itstoni.com>
 * @since   0.32.0
 * @covers  \Yawik\Composer\Event\PreConfigureEvent
 */
class PreConfigureEventTest extends TestCase
{
    public function testConstructor()
    {
        $output   = $this->createMock(IOInterface::class);

        $event = new PreConfigureEvent($output);
        $this->assertEquals($output, $event->getOutput());
    }
}
