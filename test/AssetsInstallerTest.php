<?php

/*
 * This file is part of the ModuleComposer project.
 *
 *      (c) Anthonius Munthi
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YawikTest\Composer;

include __DIR__.'/sandbox/src/Module.php';

use Core\Module as CoreModule;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Yawik\Composer\AssetProviderInterface;
use Yawik\Composer\AssetsInstaller;
use Yawik\Composer\Event\ConfigureEvent;

/**
 * Class AssetsInstallerTest
 *
 * @package YawikTest\Composer
 * @author  Anthonius Munthi <me@itstoni.com>
 * @author  Mathias Gelhausen <gelhausen@cross-solution.de>
 * @since   0.32.0
 * @since   3.0
 *          Upgrade to phpunit 8.5
 * @covers  \Yawik\Composer\AssetsInstaller
 */
class AssetsInstallerTest extends TestCase
{
    use TestOutputTrait;

    public function testOnConfigureEvent()
    {
        $mod1   = $this->createMock(AssetProviderInterface::class);
        $mod2   = new CoreModule();
        $event  = $this->createMock(ConfigureEvent::class);
        $mod1->expects($this->once())
            ->method('getPublicDir')
            ->willReturn(__DIR__.'/fixtures/foo')
        ;
        $event->expects($this->once())
            ->method('getModules')
            ->willReturn([$mod1,$mod2])
        ;

        $installer  = $this->getMockBuilder(AssetsInstaller::class)
            ->setMethods(['install'])
            ->getMock()
        ;
        $modulesAssert = function ($modules) {
            $this->assertCount(2, $modules);
            $this->assertArrayHasKey('Core', $modules);
            $this->assertStringContainsString('/vendor/yawik/core', $modules['Core']);
            return true;
        };
        $installer->expects($this->once())
            ->method('install')
            ->with($this->callback($modulesAssert))
        ;

        $installer->onConfigureEvent($event);
    }

    /**
     * @param string $flag
     * @param string $expectedMethod
     * @dataProvider getTestInstall
     */
    public function testInstall($flag, $expectedMethod)
    {
        $modules = [
            'Foo' => __DIR__.'/fixtures/foo',
            'Hello' => __DIR__.'/fixtures/hello',
        ];

        $fs = $this->createMock(Filesystem::class);
        $fs->expects($this->exactly(2))
            ->method('remove')
            ->withConsecutive(
                [$this->stringContains('public/modules/Foo')],
                [$this->stringContains('public/modules/Hello')]
            )
        ;

        $installer = $this->getMockBuilder(TestAssetInstaller::class)
            ->setMethods([
                'relativeSymlinkWithFallback',
                'absoluteSymlinkWithFallback',
                'renderInstallOutput',
                'hardcopy',
            ])
            ->getMock()
        ;

        $installer->expects($this->exactly(2))
            ->method($expectedMethod)
            ->withConsecutive(
                [$this->stringContains('fixtures/foo'), $this->stringContains('modules/Foo')],
                [$this->stringContains('fixtures/hello'), $this->stringContains('modules/Hello')]
            )
            ->willReturn($flag)
        ;
        $installer->expects($this->once())
            ->method('renderInstallOutput')
        ;
        $installer->setFilesystem($fs);
        $installer->install($modules, $flag);
    }

    public function getTestInstall()
    {
        return [
            // default using relative symlink
            [null, 'relativeSymlinkWithFallback'],
            [ AssetsInstaller::METHOD_RELATIVE_SYMLINK, 'relativeSymlinkWithFallback'],
            [ AssetsInstaller::METHOD_ABSOLUTE_SYMLINK, 'absoluteSymlinkWithFallback'],
            [ AssetsInstaller::METHOD_COPY, 'hardcopy'],
        ];
    }

    public function testInstallWithException()
    {
        $modules = [
            'Foo' => __DIR__.'/fixtures/foo',
            'Hello' => __DIR__.'/fixtures/hello',
        ];

        $installer = $this->getMockBuilder(TestAssetInstaller::class)
            ->setMethods([
                'relativeSymlinkWithFallback',
                'absoluteSymlinkWithFallback',
                'renderInstallOutput',
                'hardcopy',
            ])
            ->getMock()
        ;
        $fs = $this->createMock(Filesystem::class);
        $fs->expects($this->exactly(2))
            ->method('remove')
            ->willThrowException(new \Exception('some error'))
        ;

        $installer->expects($this->once())
            ->method('renderInstallOutput')
            ->with(false, $this->countOf(2), 1)
        ;

        $installer->setFilesystem($fs);
        $installer->install($modules);
    }

    public function testUninstall()
    {
        $targetDir = __DIR__.'/sandbox/public/modules';
        @mkdir($fooDir = $targetDir.'/Foo', 0777, true);
        @mkdir($helloDir = $targetDir.'/Hello', 0777, true);

        $installer = $this->getMockBuilder(TestAssetInstaller::class)
            ->setMethods(['log'])
            ->getMock()
        ;
        $installer->expects($this->exactly(2))
            ->method('log')
            ->withConsecutive(
                [$this->stringContains('Foo')],
                [$this->stringContains('Hello')]
            );

        $this->assertDirectoryExists($fooDir);
        $this->assertDirectoryExists($helloDir);

        $installer->uninstall(['Foo','Hello']);

        $this->assertDirectoryNotExists($fooDir);
        $this->assertDirectoryNotExists($helloDir);
    }

    public function testRenderInstallOutput()
    {
        $installer = new TestAssetInstaller();
        $installer->setOutput($this->getOutput());

        $installer->renderInstallOutput(true, [['Core','method','ok']], 0);
        $display = $this->getDisplay();
        $this->assertStringContainsString('Yawik Assets Installed!', $display);
        $this->assertStringContainsString('installed via copy', $display);

        $installer->renderInstallOutput(false, [['Core','method','ok']], 1);
        $display = $this->getDisplay();
        $this->assertStringContainsString('Some errors occurred while installing assets', $display);
    }

    public function testAbsoluteSymlinkWithFallback()
    {
        $installer = $this->getMockBuilder(AssetsInstaller::class)
            ->setMethods(['symlink'])
            ->getMock()
        ;
        $installer->expects($this->once())
            ->method('symlink')
            ->with('origin', 'target')
        ;
        $this->assertEquals(
            AssetsInstaller::METHOD_ABSOLUTE_SYMLINK,
            $installer->absoluteSymlinkWithFallback('origin', 'target')
        );
    }

    public function testAbsoluteSymlinkWithFallbackError()
    {
        $installer = $this->getMockBuilder(AssetsInstaller::class)
            ->setMethods(['symlink','hardCopy'])
            ->getMock()
        ;
        $installer->expects($this->once())
            ->method('symlink')
            ->with('origin', 'target')
            ->willThrowException(new \Exception('some error'))
        ;
        $installer->expects($this->once())
            ->method('hardCopy')
            ->with('origin', 'target')
            ->willReturn('some value')
        ;
        $this->assertEquals(
            'some value',
            $installer->absoluteSymlinkWithFallback('origin', 'target')
        );
    }

    public function testRelativeSymlinkWithFallback()
    {
        $installer = $this->getMockBuilder(AssetsInstaller::class)
            ->setMethods(['symlink'])
            ->getMock()
        ;
        $installer->expects($this->once())
            ->method('symlink')
            ->with('origin', 'target')
        ;
        $this->assertEquals(
            AssetsInstaller::METHOD_RELATIVE_SYMLINK,
            $installer->relativeSymlinkWithFallback('origin', 'target')
        );
    }

    public function testRelativeSymlinkWithFallbackError()
    {
        $installer = $this->getMockBuilder(AssetsInstaller::class)
            ->setMethods(['symlink','absoluteSymlinkWithFallback'])
            ->getMock()
        ;
        $installer->expects($this->once())
            ->method('symlink')
            ->with('origin', 'target')
            ->willThrowException(new \Exception('some error'))
        ;
        $installer->expects($this->once())
            ->method('absoluteSymlinkWithFallback')
            ->with('origin', 'target')
            ->willReturn('some value')
        ;
        $this->assertEquals(
            'some value',
            $installer->relativeSymlinkWithFallback('origin', 'target')
        );
    }

    public function testSymlinkShouldThrowWhenLinkIsBroken()
    {
        $filesystem = $this->createMock(Filesystem::class);

        $installer = new TestAssetInstaller();
        $installer->setFilesystem($filesystem);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Symbolic link "target" was created but appears to be broken.'
        );
        $installer->symlink('origin', 'target');
    }

    public function testSymlink()
    {
        $originDir      = __DIR__.'/fixtures/foo';
        $targetDir      = __DIR__.'/sandbox/public/modules';
        @mkdir($targetDir, 0777, true);

        //$this->expectException(\Exception::class);
        $installer = new TestAssetInstaller();
        $installer->symlink($originDir, $targetDir.'/Foo', true);

        $this->assertDirectoryExists($targetDir.'/Foo');
        $this->assertTrue(is_link($targetDir.'/Foo'));
    }

    public function testHardCopy()
    {
        $origin = __DIR__.'/fixtures/foo';

        $fs = $this->createMock(Filesystem::class);
        $fs->expects($this->once())
            ->method('mirror')
            ->with($origin, 'target', $this->isInstanceOf(Finder::class))
        ;
        $fs->expects($this->once())
            ->method('mkdir')
            ->with('target', 0777)
        ;
        $installer = new TestAssetInstaller();
        $installer->setFilesystem($fs);
        $installer->hardCopy($origin, 'target');
    }
}
