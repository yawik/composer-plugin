<?php

/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YawikTest\Composer;

use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Filesystem\Filesystem;
use Yawik\Composer\PermissionsFixer;
use PHPUnit\Framework\TestCase;

/**
 * Class PermissionsFixerTest
 *
 * @package     YawikTest\Composer
 * @author      Anthonius Munthi <http://itstoni.com>
 * @since       0.32.0
 * @covers      \Yawik\Composer\PermissionsFixer
 * @covers      \Yawik\Composer\LogTrait
 */
class PermissionsFixerTest extends TestCase
{
    private $output;

    private $input;

    /**
     * @var PermissionsFixer
     */
    private $target;

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

    public function testFixDirPermissions()
    {
        $rootDir        = __DIR__.'/sandbox';

        $filesystem     = $this->getMockBuilder(Filesystem::class)
            //->setMethods(['mkdir','chmod'])
            ->getMock()
        ;
        $filesystem->expects($this->exactly(5))
            ->method('chmod')
            ->withConsecutive(
                [$rootDir.'/config/autoload',0777],
                [$rootDir.'/var/cache',0777],
                [$rootDir.'/var/log',0777],
                [$rootDir.'/var/log/tracy',0777],
                [$rootDir.'/var/log/yawik.log',0666]
            )
        ;

        $logger = $this->prophesize(LoggerInterface::class);
        $logger
            ->log(LogLevel::INFO, Argument::containingString('config/autoload'))
            ->shouldBeCalled()
        ;
        $logger
            ->log(LogLevel::INFO, Argument::containingString('var/cache'))
            ->shouldBeCalled()
        ;
        $logger
            ->log(LogLevel::INFO, Argument::containingString('var/log'))
            ->shouldBeCalled()
        ;
        $logger
            ->log(LogLevel::INFO, Argument::containingString('var/log/tracy'))
            ->shouldBeCalled()
        ;
        $logger
            ->log(LogLevel::INFO, Argument::containingString('var/log/yawik.log'))
            ->shouldBeCalled()
        ;


        $this->target->setFilesystem($filesystem);
        $this->target->setLogger($logger->reveal());
        $this->target->fix();
    }
}
