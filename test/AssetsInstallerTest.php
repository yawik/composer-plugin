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
use Symfony\Component\Finder\Finder;
use Yawik\Composer\AssetsInstaller;
use PHPUnit\Framework\TestCase;

/**
 * Class AssetsInstallerTest
 *
 * @package YawikTest\Composer
 * @author  Anthonius Munthi <me@itstoni.com>
 * @since   0.32.0
 * @covers  \Yawik\Composer\AssetsInstaller
 */
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
        $this->assertEquals(__DIR__.'/sandbox/public/modules', $this->target->getModuleAssetDir());
    }

    public function testInstall()
    {
        $fixtures = __DIR__.'/fixtures/public1';
        $modules = [
            'Foo' => $fixtures,
            'Hello' => $fixtures,
        ];

        $moduleDir = $this->target->getModuleAssetDir();

        $this->target->install($modules, AssetsInstaller::METHOD_ABSOLUTE_SYMLINK);
        $display = $this->getDisplay(true);

        $this->assertContains('absolute symlink', $display);
        $this->assertDirectoryExists($moduleDir.'/Foo');
        $this->assertDirectoryExists($moduleDir.'/Hello');
        $this->assertFileExists($moduleDir.'/Foo/foo.js');
        $this->assertFileExists($moduleDir.'/Hello/foo.js');
        // should load loaded modules
        $this->assertFileExists($moduleDir.'/Core/Gruntfile.js');
    }

    public function testUninstall()
    {
        $target     = $this->target;
        $fixtures   = __DIR__.'/fixtures/public1';
        $moduleDir  = $target->getModuleAssetDir();
        $modules    = [
            'Foo' => $fixtures,
            'Hello' => $fixtures,
        ];

        $this->target->install($modules);
        $this->target->uninstall(['Foo']);
        $this->assertFileNotExists($moduleDir.'/Foo/foo.js');
        $this->assertFileExists($moduleDir.'/Hello/foo.js');


        $this->target->uninstall(['Hello']);
        $this->assertFileNotExists($moduleDir.'/Hello/foo.js');
    }

    public function testSymlink()
    {
        $fixtures = __DIR__.'/fixtures/public1';
        $modules = [
            'Foo' => $fixtures,
            'Hello' => $fixtures,
        ];

        $moduleDir = $this->target->getModuleAssetDir();

        $this->target->install($modules, AssetsInstaller::METHOD_ABSOLUTE_SYMLINK);
        $display = $this->getDisplay(true);

        $this->assertContains('absolute symlink', $display);
        $this->assertTrue(is_link($moduleDir.'/Foo'));
        $this->assertDirectoryExists($moduleDir.'/Foo');
        $this->assertDirectoryExists($moduleDir.'/Hello');
        $this->assertFileExists($moduleDir.'/Foo/foo.js');
        $this->assertFileExists($moduleDir.'/Hello/foo.js');
    }

    public function testRelative()
    {
        $fixtures = __DIR__.'/fixtures/public1';
        $modules = [
            'Foo' => $fixtures,
            'Hello' => $fixtures,
        ];

        $moduleDir = $this->target->getModuleAssetDir();

        $this->target->install($modules, AssetsInstaller::METHOD_RELATIVE_SYMLINK);
        $display = $this->getDisplay(true);

        $this->assertRegExp('/relative symlink/', $display);
        $this->assertDirectoryExists($moduleDir.'/Foo');
        $this->assertDirectoryExists($moduleDir.'/Hello');
        $this->assertFileExists($moduleDir.'/Foo/foo.js');
        $this->assertFileExists($moduleDir.'/Hello/foo.js');
    }

    public function testCopy()
    {
        $fixtures = __DIR__.'/fixtures/public1';
        $modules = [
            'Foo' => $fixtures,
            'Hello' => $fixtures,
        ];

        $moduleDir = $this->target->getModuleAssetDir();

        $this->target->install($modules, AssetsInstaller::METHOD_COPY);
        $display = $this->getDisplay(true);

        $this->assertContains('[NOTE] Some assets were installed via copy', $display);
        $this->assertDirectoryExists($moduleDir.'/Foo');
        $this->assertDirectoryExists($moduleDir.'/Hello');
        $this->assertFileExists($moduleDir.'/Foo/foo.js');
        $this->assertFileExists($moduleDir.'/Hello/foo.js');
    }

    public function testFixDirPermissions()
    {
        $rootDir        = __DIR__.'/sandbox';

        $filesystem     = $this->getMockBuilder(Filesystem::class)
            //->setMethods(['mkdir','chmod'])
            ->getMock()
        ;
        $filesystem->expects($this->exactly(4))
            ->method('chmod')
            ->withConsecutive(
                [$rootDir.'/var/cache',0777],
                [$rootDir.'/var/log',0777],
                [$rootDir.'/var/log/tracy',0777],
                [$rootDir.'/config/autoload',0777]
            )
        ;

        $this->target->setFilesystem($filesystem);
        $this->target->fixDirPermissions();
    }
}
