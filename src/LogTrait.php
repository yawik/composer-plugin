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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;

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

    public function setOutputFromComposerIO(IOInterface $output)
    {
        if (!is_null($output)) {
            $level   = OutputInterface::VERBOSITY_NORMAL;

            if ($output->isVeryVerbose() || $output->isDebug()) {
                $level = OutputInterface::VERBOSITY_VERY_VERBOSE;
            } elseif ($output->isVerbose()) {
                $level = OutputInterface::VERBOSITY_VERBOSE;
            }

            $this->getOutput()->setVerbosity($level);
            $this->getOutput()->setDecorated($output->isDecorated());
        }
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
    public function logDebug($message)
    {
        $this->doLog(LogLevel::DEBUG, $message);
    }

    /**
     * @param string $message
     */
    public function logError($message)
    {
        $this->doLog(LogLevel::ERROR, $message);
    }

    /**
     * @param string $message
     */
    public function log($message)
    {
        $this->doLog(LogLevel::INFO, $message);
    }

    public function isCli()
    {
        return php_sapi_name() === 'cli';
    }

    public function doLog($level, $message)
    {
        $message = str_replace(getcwd().DIRECTORY_SEPARATOR, '', $message);
        if (is_object($this->logger)) {
            $this->logger->log($level, $message);
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
            $this->doWrite($message, $outputLevel);
        }
    }

    public function doWrite($message, $outputLevel = 0)
    {
        $message = sprintf(
            '<info>[yawik]</info> %s',
            $message
        );
        $this->output->writeln($message, $outputLevel);
    }
}
