<?php

/*
 * This file is part of the ModuleComposer project.
 *
 *      (c) Anthonius Munthi
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YawikTest\Composer;

use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Filesystem\Filesystem;
use Yawik\Composer\AssetsInstaller;
use PHPUnit\Framework\TestCase;

class AssetsInstallerTest extends TestCase
{
    /**
     * @var AssetsInstaller
     */
    private $target;

    /**
     * @var StreamOutput
     */
    private $output;

    private static $tempDir;

    private static $cwd;

    public function setUp()
    {
        $target         = new AssetsInstaller();
        $output         = new StreamOutput(fopen('php://memory', 'w'));
        $input          = new StringInput('some input');

        // setup the target
        $target->setOutput($output);
        $target->setInput($input);

        $this->output       = $output;
        $this->target       = $target;

        chdir(static::$tempDir);
    }

    public static function setUpBeforeClass()
    {
        static::$cwd = getcwd();
        static::$tempDir = sys_get_temp_dir().'/yawik/assets-install';
        if (!is_dir(static::$tempDir)) {
            mkdir(static::$tempDir, 0777, true);
        }
    }

    public static function tearDownAfterClass()
    {
        chdir(static::$cwd);
    }


    /**
     * Gets the display returned by the last execution of the command.
     *
     * @param bool $normalize Whether to normalize end of lines to \n or not
     *
     * @return string The display
     */
    public function getDisplay($normalize = false)
    {
        $output = $this->output;

        rewind($output->getStream());

        $display = stream_get_contents($output->getStream());

        if ($normalize) {
            $display = str_replace(PHP_EOL, "\n", $display);
        }

        return $display;
    }

    public function testDirectoriesScan()
    {
        $this->assertEquals(static::$tempDir.'/public/modules', $this->target->getModuleAssetDir());
    }

    public function testSymlink()
    {
        $fixtures = __DIR__.'/fixtures/public1';
        $modules = [
            'Core' => $fixtures,
            'Applications' => $fixtures,
        ];

        $target = static::$tempDir;
        $moduleDir = $target.'/public/modules';

        chdir(static::$tempDir);
        $this->assertTrue(is_dir(static::$tempDir));
        $this->target->install($modules, AssetsInstaller::METHOD_ABSOLUTE_SYMLINK);
        $display = $this->getDisplay(true);

        $this->assertRegExp('/absolute symlink/', $display);
        $this->assertDirectoryExists($moduleDir.'/Core');
        $this->assertDirectoryExists($moduleDir.'/Applications');
        $this->assertFileExists($moduleDir.'/Core/foo.js');
        $this->assertFileExists($moduleDir.'/Applications/foo.js');
    }

    public function testRelative()
    {
        $fixtures = __DIR__.'/fixtures/public1';
        $modules = [
            'Core' => $fixtures,
            'Applications' => $fixtures,
        ];

        $target = static::$tempDir;
        $moduleDir = $target.'/public/modules';

        chdir(static::$tempDir);
        $this->assertTrue(is_dir(static::$tempDir));
        $this->target->install($modules, AssetsInstaller::METHOD_RELATIVE_SYMLINK);
        $display = $this->getDisplay(true);

        $this->assertRegExp('/relative symlink/', $display);
        $this->assertDirectoryExists($moduleDir.'/Core');
        $this->assertDirectoryExists($moduleDir.'/Applications');
        $this->assertFileExists($moduleDir.'/Core/foo.js');
        $this->assertFileExists($moduleDir.'/Applications/foo.js');
    }

    public function testCopy()
    {
        $fixtures = __DIR__.'/fixtures/public1';
        $modules = [
            'Core' => $fixtures,
            'Applications' => $fixtures,
        ];

        $target = static::$tempDir;
        $moduleDir = $target.'/public/modules';

        chdir(static::$tempDir);
        $this->assertTrue(is_dir(static::$tempDir));
        $this->target->install($modules, AssetsInstaller::METHOD_COPY);
        $display = $this->getDisplay(true);

        $this->assertContains('[NOTE] Some assets were installed via copy', $display);
        $this->assertDirectoryExists($moduleDir.'/Core');
        $this->assertDirectoryExists($moduleDir.'/Applications');
        $this->assertFileExists($moduleDir.'/Core/foo.js');
        $this->assertFileExists($moduleDir.'/Applications/foo.js');
    }
}
