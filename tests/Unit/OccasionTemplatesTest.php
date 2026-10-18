<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use App\Services\Designs;
use App\Services\LetterStore;
use App\Services\OccasionTemplates;
use PHPUnit\Framework\TestCase;

final class OccasionTemplatesTest extends TestCase
{
    public function testEveryOccasionHasTenTemplates(): void
    {
        $all = OccasionTemplates::all();
        $this->assertSame(['anniversary', 'wedding', 'partner', 'birthday'], array_keys($all));
        $this->assertSame(array_keys(OccasionTemplates::OCCASIONS), array_keys($all));
        foreach ($all as $occasion => $templates) {
            $this->assertCount(10, $templates, $occasion);
        }
    }

    public function testTemplatesAreValidAndUnique(): void
    {
        $keys = [];
        foreach (OccasionTemplates::all() as $occasion => $templates) {
            $layouts = [];
            foreach ($templates as $t) {
                $this->assertArrayNotHasKey($t['key'], $keys, 'duplicate key ' . $t['key']);
                $keys[$t['key']] = true;
                $this->assertArrayHasKey($t['layout'], Designs::LAYOUTS, $t['key']);
                $this->assertArrayHasKey($t['theme'], Designs::THEMES, $t['key']);
                $this->assertLessThanOrEqual(60, mb_strlen($t['eyebrow']), $t['key']);
                $this->assertNotSame('', $t['question']);
                $this->assertLessThanOrEqual(120, mb_strlen($t['question']), $t['key']);
                $this->assertNotEmpty($t['letter']);
                $this->assertLessThanOrEqual(LetterStore::MAX_PARAGRAPHS, count($t['letter']));
                $this->assertLessThanOrEqual(LetterStore::MAX_CHARS, mb_strlen(implode("\n\n", $t['letter'])));
                $layouts[] = $t['layout'];
            }
            // ten templates, ten different looks
            $this->assertCount(10, array_unique($layouts), $occasion);
        }
    }

    public function testFindReturnsATemplateOrNull(): void
    {
        $this->assertSame('birthday-happy', OccasionTemplates::find('birthday-happy')['key']);
        $this->assertNull(OccasionTemplates::find('nope'));
        $this->assertNull(OccasionTemplates::find(''));
        $this->assertNull(OccasionTemplates::find(null));
    }
}
