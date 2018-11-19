<?php
/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Foo;

use Yawik\Composer\RequireDirectoryPermissionInterface;
use Yawik\Composer\RequireFilePermissionInterface;
use Core\Options\ModuleOptions as CoreOptions;

class Module implements RequireFilePermissionInterface, RequireDirectoryPermissionInterface
{
    public function getRequiredDirectoryLists(CoreOptions $options)
    {
        return [
            'public/static/Organizations/images'
        ];
    }

    public function getRequiredFileLists(CoreOptions $options)
    {
        return [];
    }
}
