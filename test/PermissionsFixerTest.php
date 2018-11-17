<?php

/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YawikTest\Composer;

use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Filesystem\Filesystem;
use Yawik\Composer\Event\ConfigureEvent;
use Yawik\Composer\PermissionsFixer;
use PHPUnit\Framework\TestCase;
use Yawik\Composer\PermissionsFixerModuleInterface;
use Core\Options\ModuleOptions as CoreOptions;

/**
 * Class PermissionsFixerTest
 *
 * @package     YawikTest\Composer
 * @author      Anthonius Munthi <http://itstoni.com>
 * @since       0.32.0
 * @covers      \Yawik\Composer\PermissionsFixer
 */
class PermissionsFixerTest extends TestCase
{
    /**
     * @var PermissionsFixer
     */
    private $target;

    private $output;

    public function setUp()
    {
        $output   = new StreamOutput(fopen('php://memory', 'w'));
        $input    = new StringInput('some input');

        // setup the target
        $target = new PermissionsFixer();
        $target->setOutput($output);
        $target->setInput($input);

        $this->output       = $output;
        $this->target       = $target;
    }

    public function testOnConfigureEvent()
    {
        $module1    = $this->createMock(PermissionsFixerModuleInterface::class);
        $module2    = $this->createMock(PermissionsFixerModuleInterface::class);
        $modError   = $this->createMock(PermissionsFixerModuleInterface::class);
        $modules    = [$module1,$module2];
        $plugin     = $this->getMockBuilder(PermissionsFixer::class)
            ->setMethods(['touch','chmod','mkdir','logError'])
            ->getMock()
        ;

        foreach ($modules as $index => $module) {
            $index = $index+1;
            $module->expects($this->once())
                ->method('getDirectoryPermissionLists')
                ->willReturn([
                    'public/static/module'.$index
                ])
            ;
            $module->expects($this->once())
                ->method('getFilePermissionLists')
                ->willReturn([
                    'public/module'.$index.'.log'
                ])
            ;
        }

        $modError->expects($this->once())
            ->method('getDirectoryPermissionLists')
            ->willReturn('foo')
        ;
        $modError->expects($this->once())
            ->method('getFilePermissionLists')
            ->willReturn('bar')
        ;
        $modules[] = $modError;
        $options = $this->prophesize(CoreOptions::class);
        $event = $this->prophesize(ConfigureEvent::class);
        $event->getModules()
            ->willReturn($modules)
            ->shouldBeCalled()
        ;
        $event->getOptions()
            ->willReturn($options)
            ->shouldBeCalled()
        ;

        $plugin->expects($this->exactly(2))
            ->method('touch')
            ->withConsecutive(
                ['public/module1.log'],
                ['public/module2.log']
            )
        ;
        $plugin->expects($this->exactly(2))
            ->method('mkdir')
            ->withConsecutive(
                ['public/static/module1'],
                ['public/static/module2']
            );
        $plugin->expects($this->exactly(4))
            ->method('chmod')
            ->withConsecutive(
                ['public/static/module1'],
                ['public/static/module2'],
                ['public/module1.log'],
                ['public/module2.log']
            )
        ;

        $plugin->expects($this->exactly(2))
            ->method('logError')
            ->withConsecutive(
                [$this->stringContains('getDirectoryPermissionList()')],
                [$this->stringContains('getFilePermissionList()')]
            )
        ;

        $plugin->onConfigureEvent($event->reveal());
    }

    /**
     * @param string    $method
     * @param array     $args
     * @param string    $expectLog
     * @param string    $logType
     * @dataProvider    getTestFilesystemAction
     */
    public function testFilesystemAction($method, $args, $expectLog, $logType='log')
    {
        $plugin = $this->getMockBuilder(TestPermissionFixer::class)
            ->setMethods([$logType])
            ->getMock()
        ;

        $fs = $this->createMock(Filesystem::class);
        if ('log' == $logType) {
            $fs->expects($this->once())
                ->method($method)
            ;
        } else {
            $fs->expects($this->once())
                ->method($method)
                ->willThrowException(new \Exception($expectLog))
            ;
        }

        $plugin->expects($this->once())
            ->method($logType)
            ->with($this->stringContains($expectLog), $method)
        ;

        $plugin->setFilesystem($fs);
        call_user_func_array([$plugin,$method], $args);
    }

    public function getTestFilesystemAction()
    {
        return [
            ['mkdir',['some/dir'],'some/dir'],
            ['mkdir',['some/dir'],'some error','logError'],

            ['chmod',['some/dir'],'some/dir'],
            ['chmod',['some/file',0775],'some error','logError'],

            ['touch',['some/file'],'some/file'],
            ['touch',['some/file'],'some error','logError'],
        ];
    }
}
