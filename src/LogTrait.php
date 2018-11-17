<?php

/*
 * This file is part of the Yawik project.
 *
 *      (c) Anthonius Munthi
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Yawik\Composer;

use Composer\IO\IOInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Yawik\Composer\Event\ActivateEvent;

trait LogTrait
{

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function onActivateEvent(ActivateEvent $event)
    {
        $this->setOutputFromComposerIO($event->getOutput());
    }

    public function setOutputFromComposerIO(IOInterface $output)
    {
        $level   = OutputInterface::VERBOSITY_NORMAL;

        if ($output->isVeryVerbose() || $output->isDebug()) {
            $level = OutputInterface::VERBOSITY_VERY_VERBOSE;
        } elseif ($output->isVerbose()) {
            $level = OutputInterface::VERBOSITY_VERBOSE;
        }

        $this->getOutput()->setVerbosity($level);
        $this->getOutput()->setDecorated($output->isDecorated());
    }

    public function getInput()
    {
        if (is_null($this->input)) {
            $this->input = new StringInput('');
        }
        return $this->input;
    }

    /**
     * @param InputInterface $input
     * @return $this
     */
    public function setInput($input)
    {
        $this->input = $input;
        return $this;
    }

    /**
     * @return OutputInterface
     */
    public function getOutput()
    {
        if (is_null($this->output)) {
            $this->output = new ConsoleOutput();
        }
        return $this->output;
    }

    /**
     * @param OutputInterface $output
     * @return $this
     */
    public function setOutput($output)
    {
        $this->output = $output;
        return $this;
    }


    /**
     * Set a logger to use
     * @param LoggerInterface $logger
     * @return $this
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * @param string $message
     */
    public function logDebug($message, $context = 'yawik')
    {
        $this->doLog(LogLevel::DEBUG, $message, $context);
    }

    /**
     * @param string $message
     */
    public function logError($message, $context = 'yawik')
    {
        $this->doLog(LogLevel::ERROR, $message, $context);
    }

    /**
     * @param string $message
     * @param string $context
     */
    public function log($message, $context = 'yawik')
    {
        $this->doLog(LogLevel::INFO, $message, $context);
    }

    public function isCli()
    {
        return php_sapi_name() === 'cli';
    }

    public function doLog($level, $message, $context = 'yawik')
    {
        $message = str_replace(getcwd().DIRECTORY_SEPARATOR, '', $message);
        if (is_object($this->logger)) {
            $this->logger->log($level, $message, [$context]);
        }
        if ($this->isCli()) {
            switch ($level) {
                case LogLevel::DEBUG:
                    $outputLevel = OutputInterface::VERBOSITY_VERY_VERBOSE;
                    break;
                case LogLevel::ERROR:
                    $message = '<error>'.$message.'</error>';
                    $outputLevel = OutputInterface::OUTPUT_NORMAL;
                    break;
                case LogLevel::INFO:
                default:
                    $outputLevel = OutputInterface::OUTPUT_NORMAL;
                    break;
            }
            $this->doWrite($message, $outputLevel, $context);
        }
    }

    public function doWrite($message, $outputLevel = 0, $context = 'yawik')
    {
        $message = sprintf(
            '<info>[%s]</info> %s',
            $context,
            $message
        );
        $this->getOutput()->writeln($message, $outputLevel);
    }
}
