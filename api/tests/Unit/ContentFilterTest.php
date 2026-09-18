<?php

namespace Tests\Unit;

use App\Services\ContentFilter;
use Tests\TestCase;

class ContentFilterTest extends TestCase
{
    private ContentFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new ContentFilter;
    }

    public function test_allows_situational_descriptions(): void
    {
        $this->assertTrue($this->filter->isAllowed('Lantaarns in de tunnel zijn kapot, het is er pikdonker.'));
        $this->assertTrue($this->filter->isAllowed('Werd gevolgd op de brug, voelde me onveilig.'));
        $this->assertTrue($this->filter->isAllowed(null));
        $this->assertTrue($this->filter->isAllowed(''));
    }

    public function test_rejects_personal_data(): void
    {
        $this->assertNotEmpty($this->filter->violations('Bel me op 06-12345678'));
        $this->assertNotEmpty($this->filter->violations('mail naar jan@example.com'));
        $this->assertNotEmpty($this->filter->violations('kenteken AB-12-CD gezien'));
        $this->assertNotEmpty($this->filter->violations('woont op 1012 AB 5'));
        $this->assertNotEmpty($this->filter->violations('zie www.example.com'));
        $this->assertNotEmpty($this->filter->violations('het was @pietje'));
    }

    public function test_rejects_person_descriptions(): void
    {
        $violations = $this->filter->violations('Signalement: man met donkere jas');
        $this->assertNotEmpty($violations);
        $this->assertStringContainsString('persoon', $violations[0]);

        $this->assertNotEmpty($this->filter->violations('een marokkaanse jongen liep hier'));
        $this->assertNotEmpty($this->filter->violations('Getinte man op scooter'));
    }

    public function test_rejects_profanity_including_compounds(): void
    {
        $this->assertNotEmpty($this->filter->violations('die kankerlijer weer'));
        $this->assertNotEmpty($this->filter->violations('wat een KLOOTZAK'));
        $this->assertTrue($this->filter->isAllowed('de lantaarnpalen in het park zijn nieuw'));
    }
}
