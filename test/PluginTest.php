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
use Yawik\Composer\Event\PreConfigureEvent;
use Yawik\Composer\Event\ConfigureEvent;
use Yawik\Composer\RequireFilePermissionInterface;
use Yawik\Composer\Plugin;
use Laminas\EventManager\EventManager;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\Application as LaminasMvcApplication;

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

    public function testGetEventManager()
    {
        $target = new Plugin();
        $this->assertInstanceOf(EventManager::class, $target->getEventManager());
    }
    private function setupMock($operationType = 'install')
    {
        $composer       = $this->prophesize(Composer::class);
        $installManager = $this->prophesize(InstallationManager::class);
        $package        = $this->prophesize(PackageInterface::class);

        $installManager->getInstallPath($package->reveal())
            ->willReturn($this->testDir)
        ;

        $composer->getInstallationManager()
            ->will([$installManager,'reveal'])
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
    }

    private function configureEventManager($manager)
    {
        $manager->expects($this->exactly(4))
            ->method('attach')
            ->withConsecutive(
                [ Plugin::YAWIK_PRE_CONFIGURE_EVENT, $this->isType('array')],
                [ Plugin::YAWIK_PRE_CONFIGURE_EVENT, $this->isType('array')],
                [Plugin::YAWIK_CONFIGURE_EVENT,$this->isType('array')],
                [Plugin::YAWIK_CONFIGURE_EVENT,$this->isType('array')]
            )
        ;
    }

    public function testGetApplication()
    {
        $plugin = new Plugin();
        $this->assertInstanceOf(LaminasMvcApplication::class, $plugin->getApplication());
    }

    public function testActivate()
    {
        $composer   = $this->prophesize(Composer::class);
        $io         = $this->prophesize(IOInterface::class);

        $plugin = new TestPlugin();
        $plugin->activate($composer->reveal(), $io->reveal());

        $this->assertEquals($composer->reveal(), $plugin->getComposer());
        $this->assertEquals($io->reveal(), $plugin->getOutput());
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
        $mod1       = $this->prophesize(RequireFilePermissionInterface::class)->reveal();
        $modules    = [$mod1];
        $events     = $this->createMock(EventManager::class);

        $this->configureEventManager($events);

        $app->getServiceManager()->willReturn($container);
        $container->get('ModuleManager')->willReturn($manager);
        $container->get('Core/Options')->willReturn($options->reveal());
        $manager->getLoadedModules()->willReturn($modules);

        //$this->configureEventManager($events);
        $assertConfigureEvent = function ($event) use ($mod1, $options) {
            $this->assertInstanceOf(ConfigureEvent::class, $event);
            $this->assertEquals($options->reveal(), $event->getOptions());
            $modules = $event->getModules();
            $this->assertCount(2, $modules);
            $this->assertContains($mod1, $modules);
            $this->assertInstanceOf(CoreModule::class, $modules[1]);
            return true;
        };
        $assertActivateEvent = function ($event) {
            $this->assertInstanceOf(PreConfigureEvent::class, $event);
            $this->assertInstanceOf(IOInterface::class, $event->getOutput());
            $this->assertInstanceOf(Composer::class, $event->getComposer());
            return true;
        };

        $events->expects($this->exactly(2))
            ->method('triggerEvent')
            ->withConsecutive(
                $this->callback($assertActivateEvent),
                $this->callback($assertConfigureEvent)
            )
        ;

        $plugin = new TestPlugin();
        $plugin->setEventManager($events);
        $plugin->setInstalledModules(['Core']);
        $plugin->activate($this->composer->reveal(), $this->output->reveal());
        $plugin->setApplication($app->reveal());
        $plugin->onPostAutoloadDump();
    }
}
