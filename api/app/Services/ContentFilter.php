<?php

namespace App\Services;

/**
 * Weigert vrije tekst met persoonsgegevens, persoonsbeschrijvingen of scheldwoorden.
 * Er wordt niets stilzwijgend weggestript: de melder krijgt te horen wat er mis is.
 */
class ContentFilter
{
    /** @var array<string, string> reden => regex */
    private const PATTERNS = [
        'telefoonnummer' => '/(?:\+31|0031|0)[\s\-]?[1-9](?:[\s\-]?\d){8}/',
        'e-mailadres' => '/[\w.+\-]+@[\w\-]+\.[\w.\-]+/',
        'link' => '/(?:https?:\/\/|www\.)\S+/i',
        'kenteken' => '/\b(?:[A-Z]{2}-\d{2}-\d{2}|\d{2}-\d{2}-[A-Z]{2}|\d{2}-[A-Z]{2}-\d{2}|[A-Z]{2}-\d{2}-[A-Z]{2}|[A-Z]{2}-[A-Z]{2}-\d{2}|\d{2}-[A-Z]{2}-[A-Z]{2}|\d{2}-[A-Z]{3}-\d|\d-[A-Z]{3}-\d{2}|[A-Z]{2}-\d{3}-[A-Z]|[A-Z]-\d{3}-[A-Z]{2}|[A-Z]{3}-\d{2}-[A-Z])\b/i',
        'adres (postcode met huisnummer)' => '/\b\d{4}\s?[A-Z]{2}\s?\d{1,4}[a-z]?\b/i',
        'gebruikersnaam' => '/(?:^|\s)@\w{2,}/',
    ];

    /**
     * @return string[] lijst met redenen; leeg = tekst is toegestaan
     */
    public function violations(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        $reasons = [];

        foreach (self::PATTERNS as $reason => $pattern) {
            if (preg_match($pattern, $text)) {
                $reasons[] = "bevat een {$reason}";
            }
        }

        $normalised = mb_strtolower($text);

        foreach (config('veiligonderweg.content_filter.person_terms', []) as $term) {
            if ($this->containsWord($normalised, $term)) {
                $reasons[] = 'beschrijft een persoon (afkomst, uiterlijk of signalement); beschrijf de situatie, niet de persoon';
                break;
            }
        }

        foreach (config('veiligonderweg.content_filter.profanity', []) as $term) {
            if ($this->containsWordPrefix($normalised, $term)) {
                $reasons[] = 'bevat een scheldwoord';
                break;
            }
        }

        return array_values(array_unique($reasons));
    }

    public function isAllowed(?string $text): bool
    {
        return $this->violations($text) === [];
    }

    private function containsWord(string $haystack, string $term): bool
    {
        return (bool) preg_match('/(?<![\p{L}\p{N}])'.preg_quote(mb_strtolower($term), '/').'(?![\p{L}\p{N}])/u', $haystack);
    }

    /** Scheldwoorden ook in samenstellingen (kankerlijer, tyfushond) afvangen. */
    private function containsWordPrefix(string $haystack, string $term): bool
    {
        return (bool) preg_match('/(?<![\p{L}\p{N}])'.preg_quote(mb_strtolower($term), '/').'/u', $haystack);
    }
}
