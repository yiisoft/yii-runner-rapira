<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Runner\Rapira\Tests\Feature;

use Rapira\Http\FormField;
use Rapira\Http\Multipart;
use Rapira\Http\UploadedFile;
use Rapira\Mode;
use Rapira\Sdk\Testing\Double\FakeRuntime;
use Rapira\Sdk\Testing\Double\Http\FakeExchange;
use Rapira\Sdk\Testing\Double\Http\FakeHttpDispatcher;
use Testo\Assert;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Yii\Runner\Rapira\RapiraApplicationRunner;
use Yiisoft\Yii\Runner\Rapira\Tests\Feature\Support\RequestParity\SetCookieAction;

use function dirname;
use function explode;
use function file_put_contents;
use function json_decode;
use function strlen;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use function array_key_exists;

use const JSON_THROW_ON_ERROR;
use const UPLOAD_ERR_OK;

/**
 * What a Yii application observes of a request in Dispatcher mode, where the PSR-7 request is built
 * from the host's exchange instead of the SAPI. Every expectation is what the same request would show
 * under PHP-FPM.
 */
#[Test]
final class DispatcherRequestParityTest
{
    private FakeRuntime $runtime;

    /** @var list<string> */
    private array $tempFiles = [];

    #[BeforeTest]
    public function setUp(): void
    {
        $this->runtime = (new FakeRuntime(Mode::Dispatcher))->install();
    }

    #[AfterTest]
    public function tearDown(): void
    {
        FakeRuntime::reset();
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }
    }

    public function formPostWithMixedCaseContentTypeIsParsed(): void
    {
        $exchange = FakeExchange::for(
            '/probe',
            'POST',
            ['content-type' => ['Application/X-WWW-Form-Urlencoded; charset=UTF-8']],
            'name=Alice&tags%5B%5D=a',
        );

        $probe = $this->probe($exchange);

        Assert::same($probe['parsedBody'], ['name' => 'Alice', 'tags' => ['a']]);
    }

    public function cookieSetByTheApplicationComesBackIdentical(): void
    {
        $set = FakeExchange::for('/cookie');
        $this->serve($set);
        $pair = explode(';', (string) $set->header('set-cookie'), 2)[0];

        $probe = $this->probe(FakeExchange::for('/probe', headers: ['cookie' => [$pair]]));

        Assert::same($probe['cookies'], [SetCookieAction::NAME => SetCookieAction::VALUE]);
    }

    public function firstOfDuplicateCookiesWins(): void
    {
        $exchange = FakeExchange::for('/probe', headers: ['cookie' => ['theme=dark; theme=light']]);

        $probe = $this->probe($exchange);

        Assert::same($probe['cookies'], ['theme' => 'dark']);
    }

    public function cookiesFromSeveralHeaderFieldsAreAllSeen(): void
    {
        $exchange = FakeExchange::for('/probe', headers: ['cookie' => ['theme=dark', 'lang=en']]);

        $probe = $this->probe($exchange);

        Assert::same($probe['cookies'], ['theme' => 'dark', 'lang' => 'en']);
    }

    public function serverParamsCarryTheRawQueryAndNoScriptLocation(): void
    {
        $exchange = FakeExchange::for('/probe?filter=a%20b&tags[]=x');

        $server = $this->probe($exchange)['server'];

        Assert::same($server['QUERY_STRING'] ?? null, 'filter=a%20b&tags[]=x');
        // Dispatcher mode has no docroot to place a script in; a URL prefix is configured, not derived.
        Assert::false(array_key_exists('SCRIPT_NAME', $server));
        Assert::false(array_key_exists('PHP_SELF', $server));
    }

    public function generatedUrlsMatchThoseUnderFpm(): void
    {
        $exchange = FakeExchange::for('/urls?filter=a%20b');

        $this->serve($exchange);

        Assert::same(json_decode($exchange->getBody(), true, flags: JSON_THROW_ON_ERROR), [
            'relative' => '/items/42?page=2',
            'absolute' => 'http://localhost:8080/items/42?page=2',
            'fromCurrent' => '/urls?filter=a+b&sort=name',
        ]);
    }

    public function multipartFileReachesTheActionNextToAField(): void
    {
        $contents = "line one\nline two\n";
        $exchange = FakeExchange::for('/probe', 'POST', [
            'content-type' => ['multipart/form-data; boundary=parity'],
        ], new Multipart(
            fields: [new FormField('title', 'Quarterly report', [])],
            files: [new UploadedFile(
                name: 'attachment',
                clientFilename: 'report.txt',
                clientMediaType: 'text/plain',
                headers: [],
                tmpPath: $this->tempFile($contents),
                size: strlen($contents),
            )],
        ));

        $probe = $this->probe($exchange);

        Assert::same($probe['parsedBody'], ['title' => 'Quarterly report']);
        Assert::same($probe['files'], [
            'attachment' => [
                'name' => 'report.txt',
                'type' => 'text/plain',
                'size' => strlen($contents),
                'error' => UPLOAD_ERR_OK,
                'contents' => $contents,
            ],
        ]);
    }

    public function urlencodedPutBodyIsParsed(): void
    {
        $exchange = FakeExchange::for(
            '/probe',
            'PUT',
            ['content-type' => ['application/x-www-form-urlencoded']],
            'name=Alice',
        );

        $probe = $this->probe($exchange);

        Assert::same($probe['parsedBody'], ['name' => 'Alice']);
    }

    public function urlencodedBodyIsParsedWhateverTheMethod(): void
    {
        // A body is read by its framing, never by the method name.
        foreach (['GET', 'QUERY'] as $method) {
            $exchange = FakeExchange::for(
                '/probe',
                $method,
                ['content-type' => ['application/x-www-form-urlencoded']],
                'name=Alice',
            );

            Assert::same($this->probe($exchange)['parsedBody'], ['name' => 'Alice']);
        }
    }

    /**
     * The request as {@see Support\RequestParity\ProbeAction} saw it.
     *
     * @return array<string, mixed>
     */
    private function probe(FakeExchange $exchange): array
    {
        $this->serve($exchange);

        Assert::same($exchange->status, 200);
        /** @var array<string, mixed> */
        return json_decode($exchange->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function serve(FakeExchange ...$exchanges): void
    {
        $this->runtime->dispatcher = new FakeHttpDispatcher(...$exchanges);

        (new RapiraApplicationRunner(
            rootPath: dirname(__DIR__) . '/Support',
            debug: true,
            environment: 'request-parity',
        ))->run();
    }

    private function tempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'parity');
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }
}
