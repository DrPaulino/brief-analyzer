<?php

declare(strict_types=1);

namespace Tests;

use App\BriefService;
use App\InputException;
use App\InvalidOutputException;
use PHPUnit\Framework\TestCase;

final class BriefServiceTest extends TestCase
{
    private const VALID = '{"summary":"Black Friday campaign","objective":"sales","audience":null,'
        . '"deliverables":[{"platform":"tiktok","format":"video","specs":null,"copy_notes":null,"deadline":"2026-11-25"}],'
        . '"missing_info":["What is the media budget?"],"confidence":"medium"}';

    public function testValidOutputIsReturned(): void
    {
        $llm = new FakeLlm([self::VALID]);
        $result = (new BriefService($llm))->analyze('Need a TikTok video for Black Friday');

        $this->assertSame('Black Friday campaign', $result['brief']['summary']);
        $this->assertSame('tiktok', $result['brief']['deliverables'][0]['platform']);
        $this->assertFalse($result['meta']['retried']);
        $this->assertSame(1, $llm->calls);
    }

    public function testStripsMarkdownFences(): void
    {
        $llm = new FakeLlm(["```json\n" . self::VALID . "\n```"]);
        $result = (new BriefService($llm))->analyze('Need a TikTok video');

        $this->assertSame('sales', $result['brief']['objective']);
    }

    public function testRetriesOnceWhenFirstOutputIsInvalid(): void
    {
        $llm = new FakeLlm(['not json at all', self::VALID]);
        $result = (new BriefService($llm))->analyze('Need a TikTok video');

        $this->assertTrue($result['meta']['retried']);
        $this->assertSame(2, $llm->calls);
    }

    public function testThrowsWhenRetryAlsoFails(): void
    {
        $this->expectException(InvalidOutputException::class);

        (new BriefService(new FakeLlm(['{}', '{}'])))->analyze('Need a TikTok video');
    }

    public function testRejectsUnknownPlatform(): void
    {
        $bad = str_replace('"tiktok"', '"myspace"', self::VALID);

        $this->expectException(InvalidOutputException::class);

        (new BriefService(new FakeLlm([$bad, $bad])))->analyze('Need a video');
    }

    public function testRejectsImpossibleDate(): void
    {
        $bad = str_replace('2026-11-25', '2026-02-31', self::VALID);

        $this->expectException(InvalidOutputException::class);

        (new BriefService(new FakeLlm([$bad, $bad])))->analyze('Need a video');
    }

    public function testRejectsEmptyInputWithoutCallingTheLlm(): void
    {
        $llm = new FakeLlm([self::VALID]);

        try {
            (new BriefService($llm))->analyze('   ');
            $this->fail('Expected InputException');
        } catch (InputException) {
            $this->assertSame(0, $llm->calls);
        }
    }

    public function testRejectsTooLongInput(): void
    {
        $this->expectException(InputException::class);

        (new BriefService(new FakeLlm([self::VALID])))->analyze(str_repeat('a', 2001));
    }
}
