<?php

/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Yawik\Composer;

use Core\Application;
use Core\Asset\AssetProviderInterface;
use Core\Options\ModuleOptions;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Zend\ModuleManager\ModuleManager;

/**
 * Class AssetsInstaller
 * @package Yawik\Composer
 * @author  Anthonius Munthi <me@itstoni.com>
 * @TODO    Create more documentation for methods
 */
class AssetsInstaller
{
    const METHOD_COPY               = 'copy';
    const METHOD_ABSOLUTE_SYMLINK   = 'absolute symlink';
    const METHOD_RELATIVE_SYMLINK   = 'relative symlink';

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * The root dir of Yawik application
     * @var string
     */
    private $rootDir;

    /**
     * @var Application
     */
    private $application;

    public function __construct()
    {
        umask(0000);
        $this->filesystem = new Filesystem();
        // @codeCoverageIgnoreStart
        if (!class_exists('Core\\Application')) {
            include_once __DIR__.'/../../../autoload.php';
        }
        // @codeCoverageIgnoreEnd

        $this->application = Application::init();
    }

    /**
     * Set a logger to use
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return;
    }

    public function setFilesystem(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
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
     * @return AssetsInstaller
     */
    public function setOutput($output)
    {
        $this->output = $output;
        return $this;
    }

    /**
     * @param InputInterface $input
     * @return AssetsInstaller
     */
    public function setInput($input)
    {
        $this->input = $input;
        return $this;
    }

    /**
     * Install modules assets with the given $modules.
     * $modules should within this format:
     *
     * [module_name] => module_public_directory
     *
     * @param array     $modules An array of modules
     * @param string    $expectedMethod Expected install method
     */
    public function install($modules, $expectedMethod = self::METHOD_RELATIVE_SYMLINK)
    {
        $publicDir      = $this->getModuleAssetDir();
        $loadedModules  = $this->scanInstalledModules();
        $modules        = array_merge($modules, $loadedModules);
        $rows           = [];
        $exitCode       = 0;
        $copyUsed       = false;

        foreach ($modules as $name => $originDir) {
            $targetDir = $publicDir.DIRECTORY_SEPARATOR.$name;
            $message = $name;
            try {
                $this->filesystem->remove($targetDir);
                if (self::METHOD_RELATIVE_SYMLINK == $expectedMethod) {
                    $method = $this->relativeSymlinkWithFallback($originDir, $targetDir);
                } elseif (self::METHOD_ABSOLUTE_SYMLINK == $expectedMethod) {
                    $expectedMethod = self::METHOD_ABSOLUTE_SYMLINK;
                    $method = $this->absoluteSymlinkWithFallback($originDir, $targetDir);
                } else {
                    $expectedMethod = self::METHOD_COPY;
                    $method = $this->hardCopy($originDir, $targetDir);
                }

                if (self::METHOD_COPY === $method) {
                    $copyUsed = true;
                }

                if ($method === $expectedMethod) {
                    $rows[] = array(sprintf('<fg=green;options=bold>%s</>', '\\' === DIRECTORY_SEPARATOR ? 'OK' : "\xE2\x9C\x94" /* HEAVY CHECK MARK (U+2714) */), $message, $method);
                } else {
                    $rows[] = array(sprintf('<fg=yellow;options=bold>%s</>', '\\' === DIRECTORY_SEPARATOR ? 'WARNING' : '!'), $message, $method);
                }
            } catch (\Exception $e) {
                $exitCode = 1;
                $rows[] = array(sprintf('<fg=red;options=bold>%s</>', '\\' === DIRECTORY_SEPARATOR ? 'ERROR' : "\xE2\x9C\x98" /* HEAVY BALLOT X (U+2718) */), $message, $e->getMessage());
            }
        }

        // render this output only on cli environment
        if ($this->isCli()) {
            $this->renderInstallOutput($copyUsed, $rows, $exitCode);
        }
    }

    public function uninstall($modules)
    {
        $assetDir = $this->getModuleAssetDir();
        foreach ($modules as $name) {
            $publicPath = $assetDir.DIRECTORY_SEPARATOR.$name;
            if (is_dir($publicPath) || is_link($publicPath)) {
                $this->filesystem->remove($publicPath);
                $this->log("Removed module assets: <info>${name}</info>");
            }
        }
    }

    /**
     *
     */
    public function fixDirPermissions()
    {
        /* @var ModuleOptions $options */
        $app        = $this->application;
        $options    = $app->getServiceManager()->get('Core/Options');
        $filesystem = $this->filesystem;

        $logDir     = $options->getLogDir();
        $cacheDir   = $options->getCacheDir();
        $configDir  = realpath(Application::getConfigDir());

        if (!is_dir($options->getLogDir())) {
            $filesystem->mkdir($logDir, 0777);
        }

        if (!is_dir($cacheDir)) {
            $filesystem->mkdir($cacheDir, 0777);
        }

        $filesystem->chmod($cacheDir, 0777);
        $filesystem->chmod($logDir, 0777);
        $filesystem->chmod($logDir.'/tracy', 0777);
        $filesystem->chmod($configDir.'/autoload', 0777);
    }

    public function getPublicDir()
    {
        return $this->getRootDir().'/public';
    }

    public function getModuleAssetDir()
    {
        return $this->getPublicDir().'/modules';
    }

    /**
     * @return string
     */
    public function getRootDir()
    {
        return dirname(realpath(Application::getConfigDir()));
    }

    /**
     * @param $message
     */
    public function logDebug($message)
    {
        $this->doLog(LogLevel::DEBUG, $message);
        if ($this->isCli()) {
            $this->output->writeln($message, OutputInterface::VERBOSITY_VERY_VERBOSE);
        }
    }

    /**
     * @param $message
     */
    public function logError($message)
    {
        $this->doLog(LogLevel::ERROR, $message);
        if ($this->isCli()) {
            $this->output->writeln("<error>[error] {$message}</error>");
        }
    }

    public function log($message)
    {
        $this->doLog(LogLevel::INFO, $message, ['yawik.assets']);
        if ($this->isCli()) {
            $this->output->writeln($message);
        }
    }

    private function renderInstallOutput($copyUsed, $rows, $exitCode)
    {
        $io = new SymfonyStyle($this->input, $this->output);
        $io->newLine();

        $io->section('Yawik Assets Installed!');

        if ($rows) {
            $io->table(array('', 'Module', 'Method / Error'), $rows);
        }

        if (0 !== $exitCode) {
            $io->error('Some errors occurred while installing assets.');
        } else {
            if ($copyUsed) {
                $io->note('Some assets were installed via copy. If you make changes to these assets you have to run this command again.');
            }
            $io->success($rows ? 'All assets were successfully installed.' : 'No assets were provided by any bundle.');
        }
    }

    private function isCli()
    {
        return php_sapi_name() === 'cli';
    }

    private function scanInstalledModules()
    {
        /* @var ModuleManager $manager */
        $app            = $this->application;
        $manager        = $app->getServiceManager()->get('ModuleManager');
        $modules        = $manager->getLoadedModules(true);
        $moduleAssets   = array();

        foreach ($modules as $module) {
            try {
                $className = get_class($module);
                $moduleName = substr($className, 0, strpos($className, '\\'));
                $r = new \ReflectionClass($className);
                $file = $r->getFileName();
                $dir = null;
                if ($module instanceof AssetProviderInterface) {
                    $dir = $module->getPublicDir();
                } else {
                    $testDir = substr($file, 0, stripos($file, 'src'.DIRECTORY_SEPARATOR.'Module.php')).'/public';
                    if (is_dir($testDir)) {
                        $dir = $testDir;
                    }
                }
                if (is_dir($dir)) {
                    $moduleAssets[$moduleName] = realpath($dir);
                }
            } catch (\Exception $e) {
                $this->logError($e->getMessage());
            }
        }
        return $moduleAssets;
    }

    private function doLog($level, $message)
    {
        if ($this->logger instanceof LoggerInterface) {
            $this->logger->log($level, $message, ['yawik.assets']);
        }
    }

    /**
     * Try to create absolute symlink.
     *
     * Falling back to hard copy.
     */
    private function absoluteSymlinkWithFallback($originDir, $targetDir)
    {
        try {
            $this->symlink($originDir, $targetDir);
            $method = self::METHOD_ABSOLUTE_SYMLINK;
        } catch (\Exception $e) {
            // @codeCoverageIgnoreStart
            // fall back to copy
            $method = $this->hardCopy($originDir, $targetDir);
            // @codeCoverageIgnoreEnd
        }

        return $method;
    }

    /**
     * Try to create relative symlink.
     *
     * Falling back to absolute symlink and finally hard copy.
     */
    private function relativeSymlinkWithFallback($originDir, $targetDir)
    {
        try {
            $this->symlink($originDir, $targetDir, true);
            $method = self::METHOD_RELATIVE_SYMLINK;
        }
        // @codeCoverageIgnoreStart
        catch (\Exception $e) {
            $method = $this->absoluteSymlinkWithFallback($originDir, $targetDir);
        }
        // @codeCoverageIgnoreEnd

        return $method;
    }

    /**
     * Creates symbolic link.
     *
     * @throws \Exception if link can not be created
     */
    private function symlink($originDir, $targetDir, $relative = false)
    {
        if ($relative) {
            $this->filesystem->mkdir(dirname($targetDir));
            $originDir = $this->filesystem->makePathRelative($originDir, realpath(dirname($targetDir)));
        }
        $this->filesystem->symlink($originDir, $targetDir);
        // @codeCoverageIgnoreStart
        if (!file_exists($targetDir)) {
            throw new \Exception(
                sprintf('Symbolic link "%s" was created but appears to be broken.', $targetDir),
                0,
                null
            );
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Copies origin to target.
     */
    private function hardCopy($originDir, $targetDir)
    {
        $this->filesystem->mkdir($targetDir, 0777);
        // We use a custom iterator to ignore VCS files
        $this->filesystem->mirror($originDir, $targetDir, Finder::create()->ignoreDotFiles(false)->in($originDir));

        return self::METHOD_COPY;
    }
}
