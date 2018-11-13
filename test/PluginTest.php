<?php

/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YawikTest\Composer;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\Installer\InstallationManager;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Yawik\Composer\AssetsInstaller;
use Yawik\Composer\Plugin;

class PluginTest extends TestCase
{
    /**
     * @var ObjectProphecy
     */
    private $package;

    /**
     * @var ObjectProphecy
     */
    private $installManager;

    /**
     * @var ObjectProphecy
     */
    private $composer;

    /**
     * @var ObjectProphecy
     */
    private $event;

    /**
     * @var ObjectProphecy
     */
    private $output;

    /**
     * @var ObjectProphecy
     */
    private $operation;

    /**
     * @var ObjectProphecy
     */
    private $assetsInstaller;

    /**
     * @var vfsStream
     */
    private $filesystem;

    private $testDir;

    public function setUp()
    {
        $this->testDir = sys_get_temp_dir().'/yawik/composer-plugin/vendor/package';
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }
    }

    public function testSubscribedEvents()
    {
        $this->assertEquals([
            'post-autoload-dump'    => 'onPostAutoloadDump',
            'post-package-install'  => 'onPostPackageInstall',
            'post-package-update'   => 'onPostPackageUpdate',
            'pre-package-uninstall' => 'onPrePackageUninstall',
        ], Plugin::getSubscribedEvents());
    }

    private function initializeMock()
    {
        $this->package = $this->prophesize(PackageInterface::class);
        $installManager = $this->prophesize(InstallationManager::class);
        $installManager
            ->getInstallPath($this->package->reveal())
            ->willReturn($this->testDir)
            ->shouldBeCalled()
        ;

        $composer = $this->prophesize(Composer::class);
        $composer->getInstallationManager()
            ->will([$installManager,'reveal'])
            ->shouldBeCalled()
        ;

        $operation = $this->prophesize(InstallOperation::class);
        $operation
            ->getPackage()
            ->will([$this->package,'reveal'])
            ->shouldBeCalled()
        ;

        $this->event = $this->prophesize(PackageEvent::class);
        $this->event
            ->getOperation()
            ->will([$operation,'reveal'])
            ->shouldBeCalled()
        ;

        $this->output         = $this->prophesize(IOInterface::class);
        $this->composer       = $composer;
        $this->operation      = $operation;
        $this->installManager = $installManager;
    }

    public function testOnPostPackageInstall()
    {
        $this->initializeMock();
        $event = $this->event;
        $package = $this->package;
        $package->getType()
            ->willReturn('yawik-module')
            ->shouldBeCalled()
        ;
        $package->getExtra()
            ->shouldBeCalled()
            ->willReturn([
                'zf' => [
                    'module' => 'SomeModule'
                ]
            ])
        ;
        $assetsInstaller = $this->prophesize(AssetsInstaller::class);
        $assetsInstaller->install([
                'SomeModule' => $this->testDir.'/public'
            ])
            ->shouldBeCalled()
        ;

        @mkdir($this->testDir.'/public');
        $plugin = new Plugin($this->filesystem);
        $plugin->setAssetsInstaller($assetsInstaller->reveal());
        $plugin->activate($this->composer->reveal(), $this->output->reveal());
        $plugin->onPostPackageInstall($event->reveal());
        $plugin->onPostAutoloadDump();
    }
}
