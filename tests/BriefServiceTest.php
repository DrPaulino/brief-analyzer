<?php

declare(strict_types=1);

namespace Tests;

use App\BriefService;
use App\InputException;
use App\InvalidOutputException;
use PHPUnit\Framework\TestCase;

final class BriefServiceTest extends TestCase
{
    // The deadline is far in the future so these tests do not depend on today's date.
    private const VALID = '{"summary":"Black Friday campaign","objective":"sales","audience":null,'
        . '"deliverables":[{"platform":"tiktok","format":"video","specs":null,"copy_notes":null,"deadline":"2099-12-31"}],'
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
        $bad = str_replace('2099-12-31', '2099-02-31', self::VALID);

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

    public function testRejectsUnsupportedLanguage(): void
    {
        $this->expectException(InputException::class);

        (new BriefService(new FakeLlm([self::VALID])))->analyze('Need a video', 'fr');
    }

    public function testConfidenceIsComputedNotTrustedFromTheModel(): void
    {
        // The model claims "high", but the audience is missing and a question is open.
        $forced = str_replace('"confidence":"medium"', '"confidence":"high"', self::VALID);
        $result = (new BriefService(new FakeLlm([$forced])))->analyze('Need a TikTok video');

        $this->assertSame('medium', $result['brief']['confidence']);
    }

    public function testConfidenceIsHighWhenTheBriefIsComplete(): void
    {
        $complete = str_replace('"audience":null', '"audience":"Runners aged 18 to 30"', self::VALID);
        $result = (new BriefService(new FakeLlm([$complete])))->analyze('Need a TikTok video');

        $this->assertSame('high', $result['brief']['confidence']);
    }

    public function testConfidenceIsLowWhenManyQuestionsAreOpen(): void
    {
        $vague = str_replace(
            '["What is the media budget?"]',
            '["Question 1?","Question 2?","Question 3?"]',
            self::VALID
        );
        $result = (new BriefService(new FakeLlm([$vague])))->analyze('Need a TikTok video');

        $this->assertSame('low', $result['brief']['confidence']);
    }

    public function testConfidenceIsLowWithoutDeliverables(): void
    {
        $empty = '{"summary":"Vague request","objective":null,"audience":null,'
            . '"deliverables":[],"missing_info":[],"confidence":"high"}';
        $result = (new BriefService(new FakeLlm([$empty])))->analyze('Make some ads');

        $this->assertSame('low', $result['brief']['confidence']);
    }

    public function testPastDeadlineIsRemoved(): void
    {
        $past = str_replace('2099-12-31', '2020-01-01', self::VALID);
        $result = (new BriefService(new FakeLlm([$past])))->analyze('Need a TikTok video');

        $this->assertNull($result['brief']['deliverables'][0]['deadline']);
    }

    public function testFutureDeadlineIsKept(): void
    {
        $result = (new BriefService(new FakeLlm([self::VALID])))->analyze('Need a TikTok video');

        $this->assertSame('2099-12-31', $result['brief']['deliverables'][0]['deadline']);
    }

    public function testRejectsOverlongSummaryAsPossibleLeak(): void
    {
        $leak = str_replace('Black Friday campaign', str_repeat('rule text ', 80), self::VALID);

        $this->expectException(InvalidOutputException::class);

        (new BriefService(new FakeLlm([$leak, $leak])))->analyze('Need a TikTok video');
    }
}
