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
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Installer;
use Composer\Installer\InstallationManager;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Output\OutputInterface;
use Yawik\Composer\AssetsInstaller;
use Yawik\Composer\Plugin;

/**
 * Class        PluginTest
 * @package     YawikTest\Composer
 * @author      Anthonius Munthi <me@itstoni.com>
 * @since       0.32.0
 * @covers      \Yawik\Composer\Plugin
 */
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

    public function testSetInstaller()
    {
        $plugin = new Plugin();
        $assetsInstaller = new AssetsInstaller();
        $plugin->setAssetsInstaller($assetsInstaller);
        $this->assertEquals($assetsInstaller, $plugin->getAssetsInstaller());
    }

    /**
     * Verify verbose for installer output
     * @param string $expect
     * @param bool $verbose
     * @param bool $debug
     * @param bool $veryVerbose
     * @param bool $decorated
     * @dataProvider getTestInstaller
     */
    public function testGetInstaller(
        $expectOutputLevel,
        $verbose = false,
        $debug = false,
        $veryVerbose=false,
        $decorated=false
    ) {
        $this->initializeMock();
        $output     = $this->output;
        $composer   = $this->composer;
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

        $plugin = new Plugin();
        $plugin->activate($composer->reveal(), $output->reveal());

        $installer = new AssetsInstaller();
        $installer->setOutput($installerOutput->reveal());
        $plugin->setAssetsInstaller($installer);
        $plugin->onPostPackageInstall($this->event->reveal());
    }

    public function getTestInstaller()
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
                0,
                false,
                false,
                false,
                false
            ]
        ];
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
        $assetsInstaller->fixDirPermissions()->shouldBeCalled();

        $plugin = new Plugin($this->filesystem);
        $plugin->setAssetsInstaller($assetsInstaller->reveal());
        $plugin->activate($this->composer->reveal(), $this->output->reveal());
        $plugin->onPostPackageInstall($event->reveal());
        $plugin->onPostAutoloadDump();
    }

    public function testOnPostPackageUpdate()
    {
        $this->initializeMock('update');
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
        $assetsInstaller->fixDirPermissions()->shouldBeCalled();

        $plugin = new Plugin();
        $plugin->setAssetsInstaller($assetsInstaller->reveal());
        $plugin->activate($this->composer->reveal(), $this->output->reveal());
        $plugin->onPostPackageUpdate($event->reveal());
        $plugin->onPostAutoloadDump();
    }

    public function testOnPrePackageUninstall()
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
        $assetsInstaller->uninstall([
            'SomeModule'
        ])
            ->shouldBeCalled()
        ;
        $assetsInstaller->fixDirPermissions()->shouldBeCalled();


        $plugin = new Plugin();
        $plugin->setAssetsInstaller($assetsInstaller->reveal());
        $plugin->activate($this->composer->reveal(), $this->output->reveal());
        $plugin->onPrePackageUninstall($event->reveal());
        $plugin->onPostAutoloadDump();
    }

    public function testOnNonYawikModule()
    {
        $this->initializeMock();
        $event = $this->event;
        $package = $this->package;
        $package->getType()
            ->willReturn('other-type')
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
        $assetsInstaller->install([])
            ->shouldBeCalled()
        ;
        $assetsInstaller->fixDirPermissions()->shouldBeCalled();

        $plugin = new Plugin();
        $plugin->setAssetsInstaller($assetsInstaller->reveal());
        $plugin->activate($this->composer->reveal(), $this->output->reveal());
        $plugin->onPostPackageInstall($event->reveal());
        $plugin->onPostAutoloadDump();
    }

    private function initializeMock($operationType = 'install')
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

        if ($operationType == 'update') {
            $operation = $this->prophesize(UpdateOperation::class);
            $operation->getTargetPackage()
                ->will([$this->package,'reveal'])
                ->shouldBeCalled()
            ;
        } else {
            $operation = $this->prophesize(InstallOperation::class);
            $operation
                ->getPackage()
                ->will([$this->package,'reveal'])
                ->shouldBeCalled()
            ;
        }

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
        @mkdir($this->testDir.'/public');
    }
}
