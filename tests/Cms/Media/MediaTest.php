<?php

namespace Kirby\Cms;

use Kirby\Toolkit\Dir;
use Kirby\Toolkit\F;
use PHPUnit\Framework\TestCase;

class MediaTest extends TestCase
{
    protected $app;
    protected $fixtures;

    public function setUp(): void
    {
        $this->app = new App([
            'roots' => [
                'index' => $this->fixtures = __DIR__ . '/fixtures/MediaTest',
            ],
        ]);

        Dir::make($this->fixtures);
    }

    public function tearDown(): void
    {
        Dir::remove($this->fixtures);
    }

    public function testLinkSiteFile()
    {
        F::write($this->fixtures . '/content/test.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');

        $file   = $this->app->file('test.svg');
        $result = Media::link($this->app->site(), $file->mediaHash(), $file->filename());

        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(200, $result->code());
        $this->assertEquals('image/svg+xml', $result->type());
        $this->assertFileExists($file->mediaRoot());
    }

    public function testLinkPageFile()
    {
        F::write($this->fixtures . '/content/projects/test.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');

        $file   = $this->app->file('projects/test.svg');
        $result = Media::link($this->app->page('projects'), $file->mediaHash(), $file->filename());

        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(200, $result->code());
        $this->assertEquals('image/svg+xml', $result->type());
        $this->assertFileExists($file->mediaRoot());
    }

    public function testLinkWithInvalidHash()
    {
        F::write($this->fixtures . '/content/projects/test.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');

        $file   = $this->app->file('projects/test.svg');
        $result = Media::link($this->app->page('projects'), 'abc', $file->filename());

        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(307, $result->code());
    }

    public function testLinkWithoutModel()
    {
        $this->assertFalse(Media::link(null, 'hash', 'filename.jpg'));
    }

    public function testLinkProtected()
    {
        F::write($this->fixtures . '/content/nomedia.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        F::write($this->fixtures . '/content/nomedia.svg.txt', 'Template: nomedia');
        F::write($this->fixtures . '/content/protected.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        F::write($this->fixtures . '/content/protected.svg.txt', 'Template: protected');

        $app = $this->app->clone([
            'blueprints' => [
                'files/nomedia' => [
                    'createMedia' => false
                ],
                'files/protected' => [
                    'protect' => true
                ]
            ]
        ]);
        $app->impersonate('kirby');

        $nomedia = $app->file('nomedia.svg');
        $result  = Media::link($app->site(), $nomedia->mediaHash(), $nomedia->filename());
        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(200, $result->code());
        $this->assertSame('image/svg+xml', $result->type());
        $this->assertSame([], $result->headers());
        $this->assertFileNotExists($nomedia->mediaRoot());

        $protected = $app->file('protected.svg');
        $result    = Media::link($app->site(), $protected->mediaHash(), $protected->filename());
        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(200, $result->code());
        $this->assertSame('image/svg+xml', $result->type());
        $this->assertSame([
            'Cache-Control' => 'no-cache, no-store, must-revalidate'
        ], $result->headers());
        $this->assertFileNotExists($protected->mediaRoot());

        $app->impersonate(null);

        $result = Media::link($app->site(), $nomedia->mediaHash(), $nomedia->filename());
        $this->assertSame(200, $result->code());

        $this->expectException('Kirby\Http\Exceptions\NextRouteException');
        $result = Media::link($app->site(), $protected->mediaHash(), $protected->filename());
    }

    public function testPublish()
    {
        $filename  = 'test.jpg';
        $hash      = crc32($filename);
        $directory = $this->fixtures . '/media/pages/projects';

        touch($src = $this->fixtures . '/test.jpg');

        Dir::make($versionA = $directory . '/' . $hash . '-1234');
        Dir::make($versionB = $directory . '/' . $hash . '-5678');

        $this->assertTrue(Media::publish($src, $dest = $versionB . '/test.jpg'));

        // the file should be copied
        $this->assertTrue(is_dir($versionB));
        $this->assertTrue(is_file($dest));

        // older versions should be removed
        $this->assertFalse(is_dir($versionA));
    }

    public function testUnpublish()
    {
        $filename  = 'test.jpg';
        $hash      = crc32($filename);
        $directory = $this->fixtures . '/media';

        Dir::make($versionA = $directory . '/' . $hash . '-1234');
        Dir::make($versionB = $directory . '/' . $hash . '-5678');

        $this->assertTrue(is_dir($versionA));
        $this->assertTrue(is_dir($versionB));

        Media::unpublish($directory, $filename);

        $this->assertFalse(is_dir($versionA));
        $this->assertFalse(is_dir($versionB));
    }

    public function testUnpublishAndIgnore()
    {
        $filename  = 'test.jpg';
        $hash      = crc32($filename);
        $directory = $this->fixtures . '/media';

        Dir::make($versionA = $directory . '/' . $hash . '-1234');
        Dir::make($versionB = $directory . '/' . $hash . '-5678');

        $this->assertTrue(is_dir($versionA));
        $this->assertTrue(is_dir($versionB));

        Media::unpublish($directory, $filename, $versionA);

        $this->assertTrue(is_dir($versionA));
        $this->assertFalse(is_dir($versionB));
    }

    public function testUnpublishNonExistingDirectory()
    {
        $directory = $this->fixtures . '/does-not-exist';

        $this->assertTrue(Media::unpublish($directory, 'something.jpg'));
    }
}
