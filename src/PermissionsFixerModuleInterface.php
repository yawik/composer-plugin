<?php

/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Yawik\Composer;

use Core\Options\ModuleOptions as CoreOptions;

/**
 * An interface for modules to determines which file and directories
 * need to fix during installation
 *
 * @package Yawik\Composer
 * @author  Anthonius Munthi <me@itstoni.com>
 * @since   0.32.0
 */
interface PermissionsFixerModuleInterface
{
    /**
     * Lists of files that permissions need to be fixed
     *
     * @param   CoreOptions     $options A CoreOptions object to use
     * @return  array           A list of files
     */
    public function getFilePermissionLists(CoreOptions $options);

    /**
     * Lists of directories that permissions need to be fixed
     *
     * @param   CoreOptions     $options A CoreOptions object to use
     * @return array A list of files
     */
    public function getDirectoryPermissionLists(CoreOptions $options);
}
