<?php
/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YawikTest\Composer\Event;

use Yawik\Composer\Event\ConfigureEvent;
use Core\Options\ModuleOptions as CoreOptions;
use PHPUnit\Framework\TestCase;

class ConfigureEventTest extends TestCase
{
    public function testConstructor()
    {
        $options = $this->createMock(CoreOptions::class);
        $modules = ['some-module'];

        $event = new ConfigureEvent($options, $modules);
        $this->assertEquals($options, $event->getOptions());
        $this->assertEquals($modules, $event->getModules());
    }
}
