<?php

/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Yawik\Composer;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

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

    public function __construct(Filesystem $filesystem = null)
    {
        if (is_null($filesystem)) {
            $filesystem = new Filesystem();
        }
        $this->filesystem = $filesystem;
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
     * @return InputInterface
     */
    public function getInput()
    {
        return $this->input;
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
        $rows = [];
        $exitCode = 0;
        $copyUsed = false;

        $publicDir = $this->getModuleAssetDir();
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
                $this->output->writeln("'Removed module assets: <info>${name}</info>'");
            }
        }
    }

    public function getModuleAssetDir()
    {
        return $this->getPublicDir().'/modules';
    }

    private function renderInstallOutput($copyUsed, $rows, $exitCode)
    {
        $io = new SymfonyStyle($this->input, $this->output);
        $io->newLine();

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

    private function getPublicDir()
    {
        $dirs = [
            getcwd().'/test/sandbox/public',
            getcwd().'/public'
        ];
        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                return $dir;
            }
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
            // fall back to copy
            $method = $this->hardCopy($originDir, $targetDir);
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
        } catch (\Exception $e) {
            $method = $this->absoluteSymlinkWithFallback($originDir, $targetDir);
        }

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
        if (!file_exists($targetDir)) {
            throw new \Exception(
                sprintf('Symbolic link "%s" was created but appears to be broken.', $targetDir),
                0,
                null
            );
        }
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