<?php

/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Yawik\Composer;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\EventDispatcher\EventDispatcher;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event as ScriptEvent;
use Core\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yawik\Composer\Event\ActivateEvent;
use Yawik\Composer\Event\ConfigureEvent;

/**
 * Class Plugin
 * @package Yawik\Composer
 * @author  Anthonius Munthi <me@itstoni.com>
 * @since   0.32.0
 * @TODO    Create more documentation for methods
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    const YAWIK_ACTIVATE_EVENT  = 'yawik.activate';

    const YAWIK_CONFIGURE_EVENT = 'yawik.configure';

    const YAWIK_MODULE_TYPE     = 'yawik-module';

    const ADD_TYPE_INSTALL      = 'install';

    const ADD_TYPE_REMOVE       = 'remove';

    /**
     * @var EventDispatcher
     */
    protected $dispatcher;

    /**
     * @var Application
     */
    protected $application;

    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @var IOInterface
     */
    protected $output;

    /**
     * An array list of available modules
     *
     * @var array
     */
    protected $installed = [];

    protected $uninstalled = [];

    /**
     * A lists of available installed yawik modules type
     * @var array
     */
    protected $yawikModules;

    public function activate(Composer $composer, IOInterface $io)
    {
        $dispatcher       = $composer->getEventDispatcher();
        $assets           = new AssetsInstaller();
        $fixer            = new PermissionsFixer();


        $event = new ActivateEvent($composer, $io);
        $dispatcher->addSubscriber($assets);
        $dispatcher->addSubscriber($fixer);

        $dispatcher->dispatch(static::YAWIK_ACTIVATE_EVENT, $event);
        $this->composer   = $composer;
        $this->output     = $io;
    }

    /**
     * Provide composer event listeners.
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'post-autoload-dump'     => 'onPostAutoloadDump',
            'post-package-install'   => 'onPostPackageInstall',
            'post-package-update'    => 'onPostPackageUpdate',
            'pre-package-uninstall'  => 'onPrePackageUninstall'
        ];
    }

    /**
     * Get Yawik Application to use
     * @return Application|\Zend\Mvc\Application
     */
    public function getApplication()
    {
        // @codeCoverageIgnoreStart
        if (is_file(__DIR__.'/../../vendor/autoload')) {
            include __DIR__.'/../../vendor/autoload';
        }
        // @codeCoverageIgnoreEnd

        if (!$this->application instanceof Application) {
            $this->application = Application::init();
        }
        return $this->application;
    }

    public function getInstalledModules()
    {
        return $this->installed;
    }

    public function getUninstalledModules()
    {
        return $this->uninstalled;
    }

    public function onPostAutoloadDump()
    {
        $app              = $this->getApplication();
        $modules          = $app->getServiceManager()->get('ModuleManager')->getLoadedModules();
        $installed        = $this->installed;
        $coreOptions      = $app->getServiceManager()->get('Core/Options');

        foreach ($installed as $moduleName) {
            $className = $moduleName . '\\Module';
            if (class_exists($className, true)) {
                $modules[] = new $className;
            }
        }

        $event = new ConfigureEvent($coreOptions, $modules);
        $dispatcher = $this->composer->getEventDispatcher();
        $dispatcher->dispatch(self::YAWIK_CONFIGURE_EVENT, $event);
    }

    public function onPostPackageInstall(PackageEvent $event)
    {
        $package = $event->getOperation()->getPackage();
        $this->addModules($package, static::ADD_TYPE_INSTALL);
    }

    public function onPostPackageUpdate(PackageEvent $event)
    {
        $package = $event->getOperation()->getTargetPackage();
        $this->addModules($package, static::ADD_TYPE_INSTALL);
    }

    public function onPrePackageUninstall(PackageEvent $event)
    {
        $package = $event->getOperation()->getPackage();
        $this->addModules($package, static::ADD_TYPE_REMOVE);
    }

    public function addModules(PackageInterface $package, $scanType='install')
    {
        $type           = $package->getType();
        $extras         = $package->getExtra();

        if ($type === static::YAWIK_MODULE_TYPE) {
            // we skip undefined zf module definition
            if (isset($extras['zf']['module'])) {
                // we register module class name
                $moduleName     = $extras['zf']['module'];
                if (self::ADD_TYPE_REMOVE == $scanType) {
                    $this->uninstalled[] = $moduleName;
                } else {
                    $this->installed[]   = $moduleName;
                }
            } else {
                $this->output->write('[warning] No module definition for: ' . $package->getName());
            }
        }
    }
}
