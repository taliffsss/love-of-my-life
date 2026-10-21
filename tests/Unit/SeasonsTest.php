<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use App\Services\Designs;
use App\Services\Seasons;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SeasonsTest extends TestCase
{
    private function at(string $date, bool $always = false): Seasons
    {
        return new Seasons(['halloween' => [10, 31], 'christmas' => [12, 25], 'valentine' => [2, 14]], 14, $always, 'UTC', fn () => new \DateTimeImmutable($date));
    }

    /** @return array<string,array{string,string,bool}> */
    public static function windows(): array
    {
        return [
            'halloween, 15 days before' => ['halloween', '2026-10-16', false],
            'halloween, 14 days before' => ['halloween', '2026-10-17', true],
            'halloween, the day' => ['halloween', '2026-10-31 23:59', true],
            'halloween, the day after' => ['halloween', '2026-11-01', false],
            'christmas opens 11 Dec' => ['christmas', '2026-12-11', true],
            'christmas, 10 Dec' => ['christmas', '2026-12-10', false],
            'christmas, Boxing Day' => ['christmas', '2026-12-26', false],
            'valentine opens 31 Jan' => ['valentine', '2027-01-31', true],
            'valentine, 30 Jan' => ['valentine', '2027-01-30', false],
            'valentine, the day' => ['valentine', '2027-02-14', true],
            'valentine, in July' => ['valentine', '2027-07-01', false],
        ];
    }

    #[DataProvider('windows')]
    public function testASeasonalTemplateIsOfferedFromTwoWeeksBeforeItsDay(string $layout, string $date, bool $open): void
    {
        $this->assertSame($open, $this->at($date)->isOpen($layout));
    }

    public function testEverydayTemplatesAreAlwaysOffered(): void
    {
        $seasons = $this->at('2026-07-01');
        foreach (['classic', 'envelope', 'stars'] as $layout) {
            $this->assertTrue($seasons->isOpen($layout));
        }
        $this->assertSame(10, count($seasons->offered(Designs::LAYOUTS)), 'ten everyday templates in July');
        $this->assertSame(11, count($this->at('2026-10-20')->offered(Designs::LAYOUTS)), 'and Halloween in late October');
        $this->assertSame(13, count($this->at('2026-07-01', always: true)->offered(Designs::LAYOUTS)));
    }

    public function testTheLandingLookFollowsTheSeasonThatIsOn(): void
    {
        $this->assertSame('halloween', $this->at('2026-10-20')->theme());
        $this->assertSame('default', $this->at('2026-11-01')->theme(), 'after Halloween: back to the default look');
        $this->assertSame('default', $this->at('2026-12-10')->theme());
        $this->assertSame('christmas', $this->at('2026-12-11')->theme());
        $this->assertSame('christmas', $this->at('2026-12-25')->theme());
        $this->assertSame('default', $this->at('2026-12-26')->theme());
        $this->assertSame('valentine', $this->at('2027-02-14')->theme());
        $this->assertSame('default', $this->at('2027-02-15')->theme());
        $this->assertSame('default', $this->at('2027-07-01')->theme());
    }

    public function testItSaysWhenAClosedTemplateOpens(): void
    {
        $this->assertSame('2026-12-11', $this->at('2026-10-20')->opensOn('christmas')->format('Y-m-d'));
        $this->assertSame('2027-01-31', $this->at('2026-12-26')->opensOn('valentine')->format('Y-m-d'));
        $this->assertSame('2027-10-17', $this->at('2026-11-01')->opensOn('halloween')->format('Y-m-d'));
        $this->assertNull($this->at('2026-10-20')->opensOn('classic'));
    }
}
