<?php
/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YawikTest\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Yawik\Composer\Event\PreConfigureEvent;
use Yawik\Composer\LogTrait;
use PHPUnit\Framework\TestCase;

class TestLogTrait
{
    use LogTrait;
}

class LogTraitTest extends TestCase
{
    use TestOutputTrait;

    public function testOnPreConfigureEvent()
    {
        $output = $this->prophesize(IOInterface::class);
        $composer = $this->prophesize(Composer::class);

        $event = new PreConfigureEvent($composer->reveal(), $output->reveal());
        $target = $this->getMockBuilder(TestLogTrait::class)
            ->setMethods(['setOutputFromComposerIO'])
            ->getMock()
        ;
        $target->expects($this->once())
            ->method('setOutputFromComposerIO')
            ->with($output->reveal())
        ;

        $target->onPreConfigureEvent($event);
    }

    /**
     * Verify verbose for installer output
     * @param string $expect
     * @param bool $verbose
     * @param bool $debug
     * @param bool $veryVerbose
     * @param bool $decorated
     * @dataProvider getTestSetupOutput
     */
    public function testSetupOutput(
        $expectOutputLevel,
        $verbose = false,
        $debug = false,
        $veryVerbose=false,
        $decorated=false
    ) {
        $output             = $this->prophesize(IOInterface::class);
        $installerOutput    = $this->prophesize(OutputInterface::class);

        $output->isVerbose()->willReturn($verbose);
        $output->isDebug()->willReturn($debug);
        $output->isVeryVerbose()->willReturn($veryVerbose);
        $output->isDecorated()->willReturn($decorated);

        $installerOutput->setVerbosity($expectOutputLevel)
            ->shouldBeCalled()
        ;
        $installerOutput->setDecorated($decorated)
            ->shouldBeCalled()
        ;

        $target = new TestLogTrait();
        $target->setOutput($installerOutput->reveal());
        $target->setOutputFromComposerIO($output->reveal());
    }

    public function getTestSetupOutput()
    {
        return[
            [
                OutputInterface::VERBOSITY_VERY_VERBOSE,
                false,
                false,
                true,
                true
            ],
            [
                OutputInterface::VERBOSITY_VERY_VERBOSE,
                false,
                true,
                false,
                false
            ],
            [
                OutputInterface::VERBOSITY_VERBOSE,
                true,
                false,
                false,
                false
            ],
            [
                OutputInterface::VERBOSITY_NORMAL,
                false,
                false,
                false,
                false
            ]
        ];
    }

    public function testDefaultValues()
    {
        $target = new TestLogTrait();
        $this->assertInstanceOf(InputInterface::class, $target->getInput());
        $this->assertInstanceOf(OutputInterface::class, $target->getOutput());

        $input = new StringInput('foo');
        $target->setInput($input);
        $this->assertEquals($input, $target->getInput());
    }

    /**
     * @param string    $method
     * @param string    $expectLevel
     * @param string    $message
     * @param string    $context
     * @dataProvider    getTestLog
     */
    public function testLog($method, $expectLevel, $message = 'some message', $context = 'yawik')
    {
        $target = $this->getMockBuilder(TestLogTrait::class)
            ->setMethods(['doLog'])
            ->getMock()
        ;
        $target->expects($this->once())
            ->method('doLog')
            ->with($expectLevel, $message, $context)
        ;

        call_user_func_array([$target,$method], [$message,$context]);
    }

    public function getTestLog()
    {
        return [
            ['logDebug',LogLevel::DEBUG],
            ['logError',LogLevel::ERROR],
            ['log',LogLevel::INFO],
        ];
    }

    /**
     * @param string $expectOutputLevel
     * @param string $logLevel
     * @param string $message
     * @param string $context
     * @dataProvider getTestDoLog
     */
    public function testDoLog($expectOutputLevel, $logLevel, $message = 'some message', $context = 'yawik')
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with($logLevel, $message, [$context])
        ;

        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())
            ->method('writeln')
            ->with($this->stringContains($message), $expectOutputLevel)
        ;

        $target = new TestLogTrait();
        $target->setLogger($logger);
        $target->setOutput($output);

        $target->doLog($logLevel, $message, $context);
    }

    public function getTestDoLog()
    {
        return [
            [OutputInterface::VERBOSITY_VERY_VERBOSE, LogLevel::DEBUG],
            [OutputInterface::OUTPUT_NORMAL, LogLevel::ERROR],
            [OutputInterface::OUTPUT_NORMAL, LogLevel::INFO],
        ];
    }
}
