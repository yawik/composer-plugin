<?php

/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Yawik\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Core\Application;
use Yawik\Composer\Event\PreConfigureEvent;
use Yawik\Composer\Event\ConfigureEvent;
use Zend\EventManager\EventManager;

/**
 * Class Plugin
 * @package Yawik\Composer
 * @author  Anthonius Munthi <me@itstoni.com>
 * @since   0.32.0
 * @TODO:   Create more documentation for methods
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    const YAWIK_PRE_CONFIGURE_EVENT  = 'yawik.configure.pre';

    const YAWIK_CONFIGURE_EVENT      = 'yawik.configure';

    const YAWIK_MODULE_TYPE          = 'yawik-module';

    const ADD_TYPE_INSTALL           = 'install';

    const ADD_TYPE_REMOVE            = 'remove';

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

    /**
     * An array list of uninstalled packages
     * @var array
     */
    protected $uninstalled = [];

    /**
     * @var EventManager
     */
    protected $eventManager;

    protected function configureEvents()
    {
        $eventManager = $this->getEventManager();
        $assets       = new AssetsInstaller();
        $fixer        = new PermissionsFixer();

        // activate events
        $eventManager->attach(self::YAWIK_PRE_CONFIGURE_EVENT, [ $assets, 'onPreConfigureEvent']);
        $eventManager->attach(self::YAWIK_PRE_CONFIGURE_EVENT, [ $fixer, 'onPreConfigureEvent']);

        // configure events
        $eventManager->attach(self::YAWIK_CONFIGURE_EVENT, [$assets,'onConfigureEvent']);
        $eventManager->attach(self::YAWIK_CONFIGURE_EVENT, [$fixer,'onConfigureEvent']);
    }

    public function activate(Composer $composer, IOInterface $io)
    {
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
     * @return EventManager
     */
    public function getEventManager()
    {
        if (!is_object($this->eventManager)) {
            $this->eventManager = new EventManager();
        }
        return $this->eventManager;
    }

    /**
     * Get Yawik Application to use
     * @return Application|\Zend\Mvc\Application
     */
    public function getApplication()
    {
        if (!is_object($this->application)) {
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
        // @codeCoverageIgnoreStart
        if (is_file($file = __DIR__.'/../../../autoload.php')) {
            include $file;
        }
        // @codeCoverageIgnoreEnd

        $this->configureEvents();
        $event            = new PreConfigureEvent($this->output);
        $this->getEventManager()->triggerEvent($event);

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
        $this->getEventManager()->triggerEvent($event);
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
