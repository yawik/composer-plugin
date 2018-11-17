<?php
/*
 * This file is part of the Yawik project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Foo;

use Yawik\Composer\PermissionsFixerModuleInterface;

class Module implements PermissionsFixerModuleInterface
{
    public function getDirectoryPermissionLists()
    {
        return [
            'public/static/Organizations/images'
        ];
    }

    public function getFilePermissionLists()
    {
        return [];
    }
}
