<?php

declare(strict_types=1);

namespace Hexlet\PageLoader\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Hexlet\PageLoader\Exceptions\IncorrectDirectoryException;
use Hexlet\PageLoader\Exceptions\IncorrectUrlException;
use Hexlet\PageLoader\Http\Client;
use Hexlet\PageLoader\Http\ClientInterface;
use Hexlet\PageLoader\PageLoader;
use Hexlet\PageLoader\Utils\FileUtils;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PageLoaderTest extends TestCase
{
    private const TEMP_FILES_DIRECTORY = 'tmp';
    private const TEST_SITE_URL = 'https://ru.hexlet.io/programs/php';
    private const TEST_SITE_ORIGINAL = 'ru-hexlet-io-programs-php-original.html';
    private const TEST_SITE_FIXTURE = 'ru-hexlet-io-programs-php.html';
    private const TEST_SITE_FILES_DIRECTORY = 'ru-hexlet-io-programs-php_files';

    private string $path;
    private vfsStreamDirectory $directory;

    private MockHandler $mock;
    private ClientInterface $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = vfsStream::setup(self::TEMP_FILES_DIRECTORY);
        $this->path = vfsStream::url(self::TEMP_FILES_DIRECTORY);

        $this->mock = new MockHandler([]);
        $this->client = new Client(new GuzzleClient(['handler' => HandlerStack::create($this->mock)]));
    }

    public function testPageDownloadSuccess(): void
    {
        $this->mockPage();

        $this->assertFalse($this->directory->hasChildren());
        PageLoader::download(self::TEST_SITE_URL, $this->path, $this->client);
        $this->assertTrue($this->directory->hasChild(self::TEST_SITE_FIXTURE));
        $this->assertTrue($this->directory->hasChild(self::TEST_SITE_FILES_DIRECTORY));

        $this->assertFileEquals($this->getFixtureFullPath(self::TEST_SITE_FIXTURE), $this->directory->getChild(self::TEST_SITE_FIXTURE)->url());

        /** @var string[] $files */
        $files = scandir($this->directory->getChild(self::TEST_SITE_FILES_DIRECTORY)->url());
        $resources = array_values(array_diff($files, ['.', '..']));
        collect($resources)->every(function (string $filename) {
            $this->assertFileEquals(
                $this->getFixtureFullPath(self::TEST_SITE_FILES_DIRECTORY, $filename),
                $this->directory->getChild(self::TEST_SITE_FILES_DIRECTORY . '/' . $filename)->url()
            );
        });
    }

    public function testPageDownloadIncorrectDirectoryError(): void
    {
        $this->expectException(IncorrectDirectoryException::class);

        $this->addMockAnswer('HTML');

        PageLoader::download(self::TEST_SITE_URL, $this->path . '/wrong/directory', $this->client);
    }

    /**
     * @dataProvider httpErrorDataProvider
     */
    public function testPageDownloadHttpError(int $responseCode): void
    {
        $this->expectException(IncorrectUrlException::class);

        $this->addMockAnswer('HTML', $responseCode);

        PageLoader::download(self::TEST_SITE_URL, $this->path, $this->client);
    }

    public function httpErrorDataProvider(): array
    {
        return [[400], [401], [403], [404], [500], [502], [503]];
    }

    /**
     * @param string ...$fixturePath
     */
    private function getFixtureFullPath(...$fixturePath): string
    {
        $parts = [__DIR__, 'fixtures', ...$fixturePath];
        $path = realpath(implode('/', $parts));

        if ($path) {
            return $path;
        }

        throw new RuntimeException();
    }

    /**
     * @param string ...$fixturePath
     */
    private function getFixtureContent(...$fixturePath): string
    {
        return FileUtils::get($this->getFixtureFullPath(...$fixturePath));
    }

    private function addMockAnswer(?string $content, int $responseCode = 200): void
    {
        $response = new Response($responseCode, [], $content);
        $this->mock->append($response);
    }

    private function mockPage(): void
    {
        $this->addMockAnswer($this->getFixtureContent(self::TEST_SITE_ORIGINAL));

        /** @var string[] $files */
        $files = scandir($this->getFixtureFullPath(self::TEST_SITE_FILES_DIRECTORY));
        $resources = array_values(array_diff($files, ['.', '..']));
        collect($resources)
            ->sortBy(function (string $filename) {
                return $filename;
            })
            ->each(function (string $filename) {
                $this->addMockAnswer($this->getFixtureContent(self::TEST_SITE_FILES_DIRECTORY, $filename));
            });
    }
}
