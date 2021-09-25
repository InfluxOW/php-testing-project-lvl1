<?php

declare(strict_types=1);

namespace Hexlet\PageLoader\Tests;

use DiDom\Document;
use DiDom\Element;
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
    private const TEST_SITE_ORIGINAL = 'ru-hexlet-io-original.html';
    private const TEST_SITE_FIXTURE = 'ru-hexlet-io.html';
    private const TEST_SITE_FILES_DIRECTORY = 'ru-hexlet-io_files';

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

        $this->assertFalse($this->directory->hasChild(self::TEST_SITE_FIXTURE));

        PageLoader::download(self::TEST_SITE_URL, $this->path, $this->client);

        $this->assertTrue($this->directory->hasChild(self::TEST_SITE_FIXTURE));

        $expected = FileUtils::get(self::getFixtureFullPath(self::TEST_SITE_FIXTURE));
        $actual = FileUtils::get($this->directory->getChild(self::TEST_SITE_FIXTURE)->url());
        $this->assertEquals(str_replace(PHP_EOL, '', $expected), str_replace(PHP_EOL, '', $actual));
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

    /**
     * @param string ...$fixturePath
     */
    private static function getFixtureFullPath(...$fixturePath): string
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
        return FileUtils::get(self::getFixtureFullPath(...$fixturePath));
    }

    private function addMockAnswer(?string $content, int $responseCode = 200): void
    {
        $response = new Response($responseCode, [], $content);
        $this->mock->append($response);
    }

    private function mockPage(): void
    {
        $originalPageContent = $this->getFixtureContent(self::TEST_SITE_ORIGINAL);

        $this->addMockAnswer($originalPageContent);
        $this->mockImages($originalPageContent);
    }

    private function mockImages(string $pageContent): void
    {
        collect((new Document($pageContent))->find('img'))->map(function (Element $element) {
            $src = $element->getAttribute('src');
            if ($src) {
                $fileName = basename($src);
                $content = $this->getFixtureContent(self::TEST_SITE_FILES_DIRECTORY, $fileName);
                $this->addMockAnswer($content);
            }
            return $element;
        });
    }
}
