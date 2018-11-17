<?php
/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YawikTest\Composer;

use Symfony\Component\Console\Output\StreamOutput;

trait TestOutputTrait
{
    protected $output;

    public function getOutput()
    {
        if (is_null($this->output)) {
            $this->output = new StreamOutput(fopen('php://memory', 'w'));
        }
        return $this->output;
    }

    /**
     * Gets the display returned by the last execution of the command.
     *
     * @param bool $normalize Whether to normalize end of lines to \n or not
     *
     * @return string The display
     */
    public function getDisplay($normalize = true)
    {
        $output = $this->output;

        rewind($output->getStream());

        $display = stream_get_contents($output->getStream());

        if ($normalize) {
            $display = str_replace(PHP_EOL, "\n", $display);
        }

        return $display;
    }
}
