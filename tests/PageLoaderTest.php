<?php

declare(strict_types=1);

namespace Hexlet\PageLoader\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Hexlet\PageLoader\Http\Client;
use Hexlet\PageLoader\Http\ClientInterface;
use Hexlet\PageLoader\PageLoader;
use Hexlet\PageLoader\Utils\FileUtils;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

class PageLoaderTest extends TestCase
{
    private const TEMP_FILES_DIRECTORY = 'tmp';
    private const TEST_SITE_URL = 'https://ru.hexlet.io/';
    private const TEST_SITE_FIXTURE_NAME = 'ru.hexlet.io';
    private const TEST_SITE_RESULT_FILENAME = 'ru-hexlet-io.html';

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
        $expectedContent = $this->getFixtureContent(self::TEST_SITE_FIXTURE_NAME);

        $this->addMockAnswer($expectedContent);

        $this->assertFalse($this->directory->hasChildren());

        PageLoader::download(self::TEST_SITE_URL, $this->path, $this->client);

        $this->assertTrue($this->directory->hasChildren());
        $this->assertTrue($this->directory->hasChild(self::TEST_SITE_RESULT_FILENAME));
        $this->assertStringEqualsFile($this->directory->getChild(self::TEST_SITE_RESULT_FILENAME)->url(), $expectedContent);
    }

    public function testPageDownloadIncorrectDirectoryError(): void
    {
        $this->expectException(RuntimeException::class);

        PageLoader::download(self::TEST_SITE_URL, $this->path . '/wrong_directory', $this->client);
    }

    /**
     * @dataProvider httpErrorDataProvider
     */
    public function testPageDownloadHttpError(int $responseCode): void
    {
        $this->expectException(ClientExceptionInterface::class);

        $this->addMockAnswer(null, $responseCode);

        PageLoader::download(self::TEST_SITE_URL, $this->path, $this->client);
    }

    public function httpErrorDataProvider(): array
    {
        return [[400], [401], [403], [404], [500], [502], [503]];
    }

    private function getFixtureContent(string $fixtureName): string
    {
        $parts = [__DIR__, 'fixtures', $fixtureName];
        $path = realpath(implode('/', $parts));

        if ($path) {
            return FileUtils::get($path);
        }

        throw new RuntimeException();
    }

    private function addMockAnswer(?string $content, int $responseCode = 200): void
    {
        $response = new Response($responseCode, [], $content);
        $this->mock->append($response);
    }
}
