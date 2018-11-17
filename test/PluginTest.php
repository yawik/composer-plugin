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
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Installer\InstallationManager;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Core\Application;
use Core\Module as CoreModule;
use Core\Options\ModuleOptions;
use Interop\Container\ContainerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Yawik\Composer\AssetsInstaller;
use Yawik\Composer\Event\ActivateEvent;
use Yawik\Composer\Event\ConfigureEvent;
use Yawik\Composer\PermissionsFixer;
use Yawik\Composer\PermissionsFixerModuleInterface;
use Yawik\Composer\Plugin;
use Zend\ModuleManager\ModuleManager;
use Zend\Mvc\Application as ZendMvcApplication;

/**
 * Class        PluginTest
 * @package     YawikTest\Composer
 * @author      Anthonius Munthi <me@itstoni.com>
 * @since       0.32.0
 * @covers      \Yawik\Composer\Plugin
 */
class PluginTest extends TestCase
{
    private $testDir;

    private $packageEvent;

    private $composer;

    private $operation;

    private $installManager;

    private $output;

    private $package;

    private $dispatcher;

    public function setUp()
    {
        $this->testDir = sys_get_temp_dir().'/yawik/composer-plugin/vendor/package';
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }
        @mkdir($this->testDir.'/public');
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

    private function setupMock($operationType = 'install')
    {
        $composer       = $this->prophesize(Composer::class);
        $installManager = $this->prophesize(InstallationManager::class);
        $package        = $this->prophesize(PackageInterface::class);
        $dispatcher     = $this->prophesize(EventDispatcher::class);

        $installManager->getInstallPath($package->reveal())
            ->willReturn($this->testDir)
        ;

        $composer->getInstallationManager()
            ->will([$installManager,'reveal'])
        ;
        $composer->getEventDispatcher()
            ->will([$dispatcher,'reveal'])
        ;

        if ($operationType == 'update') {
            $operation = $this->prophesize(UpdateOperation::class);
            $operation->getTargetPackage()
                ->will([$package,'reveal'])
            ;
        } else {
            $operation = $this->prophesize(InstallOperation::class);
            $operation
                ->getPackage()
                ->will([$package,'reveal'])
            ;
        }

        $packageEvent = $this->prophesize(PackageEvent::class);
        $packageEvent->getOperation()
            ->will([$operation,'reveal'])
        ;

        $this->composer         = $composer;
        $this->output           = $this->prophesize(IOInterface::class);
        $this->operation        = $operation;
        $this->installManager   = $installManager;
        $this->packageEvent     = $packageEvent;
        $this->package          = $package;
        $this->dispatcher       = $dispatcher;
    }

    private function setupActivateDispatcher($dispatcher)
    {
        $eventChecker = function ($event) {
            $this->assertInstanceOf(ActivateEvent::class, $event);
            $this->assertInstanceOf(IOInterface::class, $event->getOutput());
            $this->assertInstanceOf(Composer::class, $event->getComposer());
            return true;
        };
        $dispatcher->addSubscriber(Argument::type(AssetsInstaller::class))
            ->shouldBeCalled()
        ;
        $dispatcher->addSubscriber(Argument::type(PermissionsFixer::class))
            ->shouldBeCalled()
        ;
        $dispatcher->dispatch(Plugin::YAWIK_ACTIVATE_EVENT, Argument::that($eventChecker))
            ->shouldBeCalled()
        ;
    }

    public function testGetApplication()
    {
        $plugin = new Plugin();
        $this->assertInstanceOf(ZendMvcApplication::class, $plugin->getApplication());
    }

    public function testActivate()
    {
        $dispatcher = $this->prophesize(EventDispatcher::class);
        $composer   = $this->prophesize(Composer::class);
        $io         = $this->prophesize(IOInterface::class);

        $this->setupActivateDispatcher($dispatcher);

        $composer->getEventDispatcher()
            ->shouldBeCalled()
            ->willReturn($dispatcher->reveal())
        ;

        $plugin = new TestPlugin();
        $plugin->activate($composer->reveal(), $io->reveal());
    }

    public function testAddInstalledModules()
    {
        $package = $this->prophesize(PackageInterface::class);
        $package->getType()
            ->willReturn('yawik-module')
        ;
        $package->getExtra()
            ->willReturn([
            'zf' => [
                'module' => 'SomeModule'
            ]
        ]);

        $plugin = new TestPlugin();
        $plugin->addModules($package->reveal(), Plugin::ADD_TYPE_INSTALL);
        $this->assertContains('SomeModule', $plugin->getInstalledModules());

        // test non yawik-module type
        $package = $this->prophesize(PackageInterface::class);
        $package->getType()
            ->willReturn('some-type')
        ;
        $package->getExtra()
            ->willReturn([
                'zf' => [
                    'module' => 'SomeModule'
                ]
        ]);
        $plugin = new TestPlugin();
        $plugin->addModules($package->reveal(), Plugin::ADD_TYPE_INSTALL);
        $this->assertNotContains('SomeModule', $plugin->getInstalledModules());

        // test with no extras definition
        $package = $this->prophesize(PackageInterface::class);
        $package->getType()
            ->willReturn(Plugin::YAWIK_MODULE_TYPE)
        ;
        $package->getExtra()
            ->willReturn([])
        ;
        $package->getName()->willReturn('test/some-name');
        $output = $this->prophesize(IOInterface::class);
        $output
            ->write(Argument::containingString('No module definition for: test/some-name'))
            ->shouldBeCalled()
        ;

        $plugin = new TestPlugin();
        $plugin->setOutput($output->reveal());
        $plugin->addModules($package->reveal(), Plugin::ADD_TYPE_INSTALL);
        $this->assertNotContains('SomeModule', $plugin->getInstalledModules());
    }

    public function testAddUninstalledModules()
    {
        $package = $this->prophesize(PackageInterface::class);
        $package->getType()
            ->willReturn('yawik-module')
        ;
        $package->getExtra()
            ->willReturn([
                'zf' => [
                    'module' => 'SomeModule'
                ]
        ]);

        $plugin = new TestPlugin();
        $plugin->addModules($package->reveal(), Plugin::ADD_TYPE_REMOVE);
        $this->assertEmpty($plugin->getInstalledModules());
        $this->assertContains('SomeModule', $plugin->getUninstalledModules());
    }

    /**
     * @param string    $eventName
     * @param string    $expectedAddType
     * @dataProvider    getTestEventHandling
     */
    public function testEventHandling($eventName, $expectedAddType)
    {
        $setupType = ($eventName == 'onPostPackageUpdate') ? 'update':'install';
        $this->setupMock($setupType);

        $event      = $this->packageEvent;
        $package    = $this->package;

        $plugin = $this->getMockBuilder(Plugin::class)
            ->setMethods(['addModules'])
            ->getMock()
        ;

        $plugin->expects($this->once())
            ->method('addModules')
            ->with($package->reveal(), $expectedAddType)
        ;

        call_user_func_array([$plugin,$eventName], [$event->reveal()]);
    }

    public function getTestEventHandling()
    {
        return [
            ['onPostPackageInstall',Plugin::ADD_TYPE_INSTALL],
            ['onPostPackageUpdate',Plugin::ADD_TYPE_INSTALL],
            ['onPrePackageUninstall',Plugin::ADD_TYPE_REMOVE],
        ];
    }

    public function testOnPostAutoloadDump()
    {
        $this->setupMock();
        $container  = $this->prophesize(ContainerInterface::class);
        $app        = $this->prophesize(Application::class);
        $manager    = $this->prophesize(ModuleManager::class);
        $options    = $this->prophesize(ModuleOptions::class);
        $mod1       = $this->prophesize(PermissionsFixerModuleInterface::class)
            ->reveal();
        $modules    = [$mod1];
        $dispatcher = $this->dispatcher;

        $app->getServiceManager()->willReturn($container);
        $container->get('ModuleManager')->willReturn($manager);
        $container->get('Core/Options')->willReturn($options->reveal());
        $manager->getLoadedModules()->willReturn($modules);

        $this->setupActivateDispatcher($dispatcher);
        $assertEvent = function ($event) use ($mod1, $options) {
            $this->assertInstanceOf(ConfigureEvent::class, $event);
            $this->assertEquals($options->reveal(), $event->getOptions());

            $modules = $event->getModules();
            $this->assertCount(2, $modules);
            $this->assertContains($mod1, $modules);
            $this->assertInstanceOf(CoreModule::class, $modules[1]);
            return true;
        };
        $dispatcher->dispatch(Plugin::YAWIK_CONFIGURE_EVENT, Argument::that($assertEvent))
            ->shouldBeCalled()
        ;

        $plugin = new TestPlugin();
        $plugin->setInstalledModules(['Core']);
        $plugin->setApplication($app->reveal());
        $plugin->activate($this->composer->reveal(), $this->output->reveal());
        $plugin->onPostAutoloadDump();
    }
}
