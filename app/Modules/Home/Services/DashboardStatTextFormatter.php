<?php

declare(strict_types=1);

namespace App\Modules\Home\Services;

class DashboardStatTextFormatter
{
    private const STOPWORDS = [
        'a', 'al', 'alla', 'alle', 'con', 'da', 'de', 'dei', 'del', 'della', 'delle', 'di', 'e',
        'gli', 'i', 'il', 'in', 'la', 'le', 'lo', 'nei', 'nel', 'nella', 'nelle', 'non', 'su', 'tra',
    ];

    /**
     * @return array{label: string, subtitle: ?string}
     */
    public function format(?string $label, ?string $subtitle): array
    {
        $cleanLabel = $this->cleanText($label);
        $cleanSubtitle = $this->cleanText($subtitle);

        if ($cleanSubtitle === '') {
            return ['label' => $cleanLabel, 'subtitle' => null];
        }

        if ($this->normalize($cleanSubtitle) === $this->normalize($cleanLabel)) {
            return ['label' => $cleanLabel, 'subtitle' => null];
        }

        $cleanSubtitle = $this->trimRepeatedLeadingWords($cleanLabel, $cleanSubtitle);
        $cleanSubtitle = $this->dropRepeatedTimeWindowAlreadyInLabel($cleanLabel, $cleanSubtitle);
        $cleanSubtitle = $this->simplifyRepeatedTimeWindow($cleanLabel, $cleanSubtitle);
        $cleanSubtitle = $this->simplifyRepeatedTotalLabel($cleanLabel, $cleanSubtitle);
        $cleanSubtitle = $this->cleanText($cleanSubtitle);

        if ($cleanSubtitle === '' || $this->isContainedInLabel($cleanLabel, $cleanSubtitle)) {
            return ['label' => $cleanLabel, 'subtitle' => null];
        }

        return ['label' => $cleanLabel, 'subtitle' => $cleanSubtitle];
    }

    private function trimRepeatedLeadingWords(string $label, string $subtitle): string
    {
        $labelTokens = $this->significantTokens($label);
        if ($labelTokens === []) {
            return $subtitle;
        }

        $subtitleWords = preg_split('/\s+/u', trim($subtitle)) ?: [];
        $dropCount = 0;

        foreach ($subtitleWords as $word) {
            $normalizedWord = $this->normalizeToken($word);
            if ($normalizedWord === '' || !in_array($normalizedWord, $labelTokens, true)) {
                break;
            }
            $dropCount++;
        }

        if ($dropCount === 0) {
            return $subtitle;
        }

        return implode(' ', array_slice($subtitleWords, $dropCount));
    }

    private function dropRepeatedTimeWindowAlreadyInLabel(string $label, string $subtitle): string
    {
        if (
            preg_match('/\((\d+)\s*gg\)/iu', $label, $labelMatches) === 1
            && preg_match('/^nei prossimi\s+(\d+)\s+giorni$/iu', $subtitle, $subtitleMatches) === 1
            && $labelMatches[1] === $subtitleMatches[1]
        ) {
            return '';
        }

        return $subtitle;
    }

    private function simplifyRepeatedTimeWindow(string $label, string $subtitle): string
    {
        if (!preg_match('/prossim/i', $label)) {
            return $subtitle;
        }

        if (preg_match('/^nei prossimi\s+(\d+)\s+giorni$/iu', $subtitle, $matches) === 1) {
            return 'entro ' . $matches[1] . ' giorni';
        }

        if (preg_match('/^nel prossimo\s+giorno$/iu', $subtitle) === 1) {
            return 'entro 1 giorno';
        }

        return $subtitle;
    }

    private function simplifyRepeatedTotalLabel(string $label, string $subtitle): string
    {
        $headToken = $this->firstSignificantToken($label);
        if ($headToken === null) {
            return $subtitle;
        }

        $pattern = '/^totale\s+' . preg_quote($headToken, '/') . '\s*:/iu';
        return preg_replace($pattern, 'Totale:', $subtitle) ?? $subtitle;
    }

    private function isContainedInLabel(string $label, string $subtitle): bool
    {
        $normalizedLabel = $this->normalize($label);
        $normalizedSubtitle = $this->normalize($subtitle);

        if ($normalizedSubtitle === '') {
            return true;
        }

        return str_contains(' ' . $normalizedLabel . ' ', ' ' . $normalizedSubtitle . ' ');
    }

    /**
     * @return list<string>
     */
    private function significantTokens(string $text): array
    {
        $tokens = [];

        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $token) {
            $normalizedToken = $this->normalizeToken($token);
            if ($normalizedToken === '' || in_array($normalizedToken, self::STOPWORDS, true) || is_numeric($normalizedToken)) {
                continue;
            }
            $tokens[] = $normalizedToken;
        }

        return array_values(array_unique($tokens));
    }

    private function firstSignificantToken(string $text): ?string
    {
        $tokens = $this->significantTokens($text);
        return $tokens[0] ?? null;
    }

    private function cleanText(?string $text): string
    {
        $cleanText = trim((string) $text);
        return preg_replace('/\s+/u', ' ', $cleanText) ?? $cleanText;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower($this->cleanText($text));
        $text = preg_replace('/[()\[\],.:;!?]+/u', ' ', $text) ?? $text;
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function normalizeToken(string $token): string
    {
        return trim($this->normalize($token), '-');
    }
}
