<?php

namespace App\Services;

use App\Models\Memorial;

class TemplateBioGeneratorService
{
    private const TYPO_MAP = [
        'bussinessman' => 'businessman',
        'bussiness' => 'business',
        'buisness' => 'business',
        'entrepeneur' => 'entrepreneur',
        'entreprenuer' => 'entrepreneur',
        'inventer' => 'inventor',
        'invetor' => 'investor',
        'cofounder' => 'co-founder',
        'co founded' => 'co-founded',
        'cofounded' => 'co-founded',
        'teh' => 'the',
        'taht' => 'that',
        'adn' => 'and',
        'nad' => 'and',
        'waas' => 'was',
        'wsa' => 'was',
        'recieved' => 'received',
        'acheivement' => 'achievement',
        'succesful' => 'successful',
        'sucessful' => 'successful',
        'profesional' => 'professional',
        'famouse' => 'famous',
        'devlopment' => 'development',
        'devloped' => 'developed',
        'techonology' => 'technology',
        'attented' => 'attended',
        'makere' => 'Makerere',
        'kawemple' => 'Kawempe',
        'nakasero' => 'Nakasero',
    ];

    private function isValidChild($child, string $deceasedName): bool
    {
        $childName = is_array($child) ? ($child['child_name'] ?? $child) : $child;
        return $childName && strcasecmp(trim((string) $childName), trim($deceasedName)) !== 0;
    }

    private function stripDuplicateNationality(?string $text, string $nationality): string
    {
        if (!$text || !$nationality) {
            return $text ?? '';
        }
        $nat = preg_quote(trim($nationality), '/');
        $stripped = preg_replace('/^' . $nat . '\s+/i', '', trim($text));
        return $stripped ?: $text;
    }

    private function fixNameCase(?string $text): string
    {
        if ($text === null || trim($text) === '') {
            return '';
        }
        return ucwords(strtolower(trim($text)));
    }

    private function polishText(?string $text): string
    {
        if ($text === null || trim($text) === '') {
            return '';
        }
        $text = trim($text);
        foreach (self::TYPO_MAP as $typo => $correct) {
            $text = preg_replace('/\b' . preg_quote($typo, '/') . '\b/i', $correct, $text);
        }
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\s+([.,;:!?])/', '$1', $text);
        return trim($text);
    }

    public function generateStructured(Memorial $memorial): string
    {
        $memorial->load(['children', 'spouses', 'parents', 'siblings', 'education', 'notableCompanies', 'coFounders']);

        $fullName = trim(implode(' ', array_filter([
            $memorial->first_name,
            $memorial->middle_name,
            $memorial->last_name,
        ]))) ?: $memorial->full_name ?: 'Unknown';

        $nationality = $this->polishText($memorial->nationality ?? '');
        $profession = $this->polishText($memorial->primary_profession ?: $memorial->short_description ?: $memorial->designation ?: '');
        $profession = $this->stripDuplicateNationality($profession, $nationality);
        $knownFor = $this->polishText($memorial->known_for ?? '');
        $majorAchievements = $this->polishText($memorial->major_achievements ?? '');
        $notableTitle = $this->polishText($memorial->notable_title ?? '');

        $parts = [];
        $rolePart = trim("{$nationality} {$profession}");
        $rolePart = preg_replace('/\b(\w+)\s+\1\b/i', '$1', $rolePart);
        $intro = $fullName . ' was ' . ($rolePart ? "a {$rolePart}" : 'a notable figure') . '.';
        $extra = array_filter([$notableTitle, $knownFor, $majorAchievements]);
        if (!empty($extra)) {
            $sentences = array_map(fn ($s) => trim($s, '. ') . (str_ends_with(trim($s), '.') ? '' : '.'), $extra);
            $intro .= ' ' . implode(' ', $sentences);
        }
        $parts[] = trim(preg_replace('/\.\s*\.+/', '.', $intro));

        if ($memorial->more_details) {
            $parts[] = '';
            $parts[] = $memorial->more_details;
        }

        $birthParts = array_filter([
            $memorial->date_of_birth?->format('F j, Y'),
            trim(implode(', ', array_filter([$memorial->birth_city, $memorial->birth_state, $memorial->birth_country]))),
        ]);
        if (!empty($birthParts)) {
            $parts[] = '';
            $parts[] = '**Born**';
            $parts[] = implode(', ', $birthParts);
        }

        $ageAtDeath = ($memorial->date_of_birth && $memorial->date_of_passing)
            ? (int) $memorial->date_of_birth->diffInYears($memorial->date_of_passing)
            : null;
        $deathParts = array_filter([
            $memorial->date_of_passing
                ? $memorial->date_of_passing->format('F j, Y') . ($ageAtDeath ? " (aged {$ageAtDeath})" : '')
                : null,
            trim(implode(', ', array_filter([$memorial->death_city, $memorial->death_state, $memorial->death_country]))),
        ]);
        if (!empty($deathParts)) {
            $parts[] = '';
            $parts[] = '**Died**';
            $parts[] = implode(', ', $deathParts);
        }

        $deceasedName = $fullName;
        $childrenList = $memorial->children
            ->filter(fn ($c) => $c->child_name && strcasecmp(trim($c->child_name), $deceasedName) !== 0)
            ->pluck('child_name')
            ->filter()
            ->implode(', ');
        if ($childrenList) {
            $parts[] = '';
            $parts[] = '**Children**';
            $parts[] = $childrenList;
        }

        $spousesList = $memorial->spouses->map(function ($s) {
            $years = array_filter([$s->marriage_start_year, $s->marriage_end_year]);
            $yearStr = !empty($years) ? ' (m. ' . implode('–', $years) . ')' : '';
            return $s->spouse_name . $yearStr;
        })->filter()->implode("\n");
        if ($spousesList) {
            $parts[] = '';
            $parts[] = '**Spouse' . ($memorial->spouses->count() > 1 ? 's' : '') . '**';
            $parts[] = $spousesList;
        }

        $educationList = $memorial->education->map(function ($e) {
            $years = array_filter([$e->start_year, $e->end_year]);
            $yearStr = !empty($years) ? ' (' . implode('–', $years) . ')' : ' (attended)';
            $degree = trim($e->degree ?? '');
            return $e->institution_name . $yearStr . ($degree ? " - {$degree}" : '');
        })->filter()->implode("\n");
        if ($educationList) {
            $parts[] = '';
            $parts[] = '**Education**';
            $parts[] = $educationList;
        }

        $parentsList = $memorial->parents->map(fn ($p) => $p->parent_name . ($p->relationship_type ? " ({$p->relationship_type})" : ''))->filter()->implode(', ');
        if ($parentsList) {
            $parts[] = '';
            $parts[] = '**Parents**';
            $parts[] = $parentsList;
        }

        $siblingsList = $memorial->siblings->pluck('sibling_name')->filter()->implode(', ');
        if ($siblingsList) {
            $parts[] = '';
            $parts[] = '**Siblings**';
            $parts[] = $siblingsList;
        }

        $companiesList = $memorial->notableCompanies->pluck('company_name')->filter()->implode(', ');
        if ($companiesList) {
            $parts[] = '';
            $parts[] = '**Notable Companies**';
            $parts[] = $companiesList;
        }

        $coFoundersList = $memorial->coFounders->pluck('name')->filter()->implode(', ');
        if ($coFoundersList) {
            $parts[] = '';
            $parts[] = '**Co-founders**';
            $parts[] = $coFoundersList;
        }

        return implode("\n", $parts);
    }

    public function generate(array $structuredData): array
    {
        return [
            'option_1' => $this->buildFromStructuredData($structuredData, 'narrative'),
            'option_2' => $this->buildFromStructuredData($structuredData, 'formal'),
            'option_3' => $this->buildFromStructuredData($structuredData, 'impact'),
        ];
    }

    private function buildFromStructuredData(array $d, string $style = 'narrative'): string
    {
        $name = $this->polishText($d['full_name'] ?? '') ?: 'Unknown';
        $nationality = $this->polishText($d['nationality'] ?? '');
        $profession = $this->polishText($d['occupation'] ?? $d['primary_profession'] ?? $d['short_description'] ?? '');
        $profession = $this->stripDuplicateNationality($profession, $nationality);
        $knownFor = $this->polishText($d['known_for'] ?? '');
        $majorAchievements = $this->polishText($d['major_achievements'] ?? '');
        $notableTitle = $this->polishText($d['notable_title'] ?? '');
        $birthDate = $d['birth_date'] ?? null;
        $deathDate = $d['death_date'] ?? null;
        $ageAtDeath = $d['age_at_death'] ?? null;
        $birthPlace = $this->polishText($d['birth_place'] ?? '');
        $deathPlace = $this->polishText($d['death_place'] ?? '');
        $children = $d['children'] ?? [];
        $spouses = $d['spouses'] ?? [];
        $education = $d['education'] ?? [];
        $parents = $d['parents'] ?? [];
        $siblings = $d['siblings'] ?? [];
        $companies = $d['companies'] ?? [];
        $coFounders = $d['co_founders'] ?? [];

        $rolePart = trim("{$nationality} {$profession}");
        $rolePart = preg_replace('/\b(\w+)\s+\1\b/i', '$1', $rolePart);
        $extra = array_filter([$notableTitle, $knownFor, $majorAchievements]);
        $extraSentences = !empty($extra)
            ? array_map(fn ($s) => trim($s, '. ') . (str_ends_with(trim($s), '.') ? '' : '.'), $extra)
            : [];

        $intro = match ($style) {
            'narrative' => $this->buildNarrativeIntro($name, $rolePart, $extraSentences),
            'formal' => $this->buildFormalIntro($name, $rolePart, $extraSentences),
            'impact' => $this->buildImpactIntro($name, $rolePart, $extraSentences, $d),
            default => $name . ' was ' . ($rolePart ? "a {$rolePart}" : 'a notable figure') . '.',
        };
        $parts = [trim(preg_replace('/\.\s*\.+/', '.', $intro))];

        $birthStr = trim(implode(', ', array_filter([$birthDate, $birthPlace])));
        if ($birthStr) {
            $parts[] = '';
            $parts[] = '**Born**';
            $parts[] = $birthStr;
        }
        $deathDateStr = $deathDate;
        if ($deathDate && $ageAtDeath !== null) {
            $deathDateStr = $deathDate . ' (aged ' . (int) $ageAtDeath . ')';
        }
        $deathStr = trim(implode(', ', array_filter([$deathDateStr, $deathPlace])));
        if ($deathStr) {
            $parts[] = '';
            $parts[] = '**Died**';
            $parts[] = $deathStr;
        }
        $validChildren = array_filter($children, fn ($c) => $this->isValidChild($c, $name));
        if (!empty($validChildren)) {
            $parts[] = '';
            $parts[] = '**Children**';
            $names = array_map(fn ($c) => is_array($c) ? ($c['child_name'] ?? $c) : $c, $validChildren);
            $parts[] = implode(', ', array_map(fn ($n) => $this->fixNameCase($this->polishText($n)), $names));
        }
        if (!empty($spouses)) {
            $parts[] = '';
            $parts[] = '**Spouse' . (count($spouses) > 1 ? 's' : '') . '**';
            $spouseLines = array_map(function ($s) {
                $n = $this->fixNameCase($this->polishText(is_array($s) ? ($s['name'] ?? $s['spouse_name'] ?? '') : $s));
                $start = $s['marriage_start_year'] ?? $s['start_year'] ?? null;
                $end = $s['marriage_end_year'] ?? $s['end_year'] ?? null;
                $years = array_filter([$start, $end]);
                return $n . (!empty($years) ? ' (m. ' . implode('–', $years) . ')' : '');
            }, $spouses);
            $parts[] = implode("\n", $spouseLines);
        }
        if (!empty($education)) {
            $parts[] = '';
            $parts[] = '**Education**';
            $eduLines = array_map(function ($e) {
                $inst = is_array($e) ? ($e['institution'] ?? $e['institution_name'] ?? '') : $e;
                $start = $e['start_year'] ?? null;
                $end = $e['end_year'] ?? null;
                $degree = $e['degree'] ?? null;
                $years = array_filter([$start, $end]);
                $yearStr = !empty($years) ? ' (' . implode('–', $years) . ')' : ' (attended)';
                return $this->polishText($inst) . $yearStr . ($degree ? ' - ' . $this->polishText($degree) : '');
            }, $education);
            $parts[] = implode("\n", $eduLines);
        }
        if (!empty($parents)) {
            $parts[] = '';
            $parts[] = '**Parents**';
            $names = array_map(fn ($p) => is_array($p) ? ($p['parent_name'] ?? $p) : $p, $parents);
            $parts[] = implode(', ', array_map(fn ($n) => $this->fixNameCase($this->polishText($n)), $names));
        }
        if (!empty($siblings)) {
            $parts[] = '';
            $parts[] = '**Siblings**';
            $names = array_map(fn ($s) => is_array($s) ? ($s['sibling_name'] ?? $s) : $s, $siblings);
            $parts[] = implode(', ', array_map(fn ($n) => $this->fixNameCase($this->polishText($n)), $names));
        }
        if (!empty($companies)) {
            $parts[] = '';
            $parts[] = '**Notable Companies**';
            $names = array_map(fn ($c) => is_array($c) ? ($c['company_name'] ?? $c) : $c, $companies);
            $parts[] = implode(', ', array_map(fn ($n) => $this->polishText($n), $names));
        }
        if (!empty($coFounders)) {
            $parts[] = '';
            $parts[] = '**Co-founders**';
            $names = array_map(fn ($c) => is_array($c) ? ($c['name'] ?? $c) : $c, $coFounders);
            $parts[] = implode(', ', array_map(fn ($n) => $this->polishText($n), $names));
        }
        return implode("\n", $parts);
    }

    private function buildNarrativeIntro(string $name, string $rolePart, array $extraSentences): string
    {
        $base = $name . ' was ' . ($rolePart ? "a {$rolePart}" : 'a notable figure') . '.';
        if (!empty($extraSentences)) {
            $base .= ' ' . implode(' ', $extraSentences);
        }
        return trim(preg_replace('/\.\s*\.+/', '.', $base));
    }

    private function buildFormalIntro(string $name, string $rolePart, array $extraSentences): string
    {
        $role = $rolePart ?: 'notable figure';
        $intro = $name . ', ' . $role . '.';
        if (!empty($extraSentences)) {
            $phrases = [];
            foreach ($extraSentences as $s) {
                $s = trim($s, '. ');
                if ($s) $phrases[] = $s;
            }
            $joined = implode('; ', $phrases);
            $intro .= ' ' . $joined . (str_ends_with($joined, '.') ? '' : '.');
        }
        return $intro;
    }

    private function buildImpactIntro(string $name, string $rolePart, array $extraSentences, array $d): string
    {
        $companies = $d['companies'] ?? [];
        $firstCompany = '';
        if (!empty($companies) && is_array($companies[0] ?? null)) {
            $firstCompany = trim($companies[0]['company_name'] ?? '');
        }
        $knownFor = $this->polishText($d['known_for'] ?? '');
        $majorAchievements = $this->polishText($d['major_achievements'] ?? '');

        $achievementLead = '';
        if ($firstCompany) {
            $achievementLead = "Founder of {$firstCompany}";
        } elseif ($knownFor) {
            $firstSentence = preg_split('/[.!?]\s+/', trim($knownFor), 2)[0] ?? '';
            $achievementLead = trim($firstSentence, '. ');
        } elseif ($majorAchievements) {
            $firstSentence = preg_split('/[.!?]\s+/', trim($majorAchievements), 2)[0] ?? '';
            $achievementLead = trim($firstSentence, '. ');
        }

        if ($achievementLead) {
            $base = $achievementLead . ', ' . $name . ' was ' . ($rolePart ? "a {$rolePart}" : 'a notable figure') . '.';
            return $base . (!empty($extraSentences) ? ' ' . implode(' ', $extraSentences) : '');
        }

        return $name . ' was ' . ($rolePart ? "a {$rolePart}" : 'a notable figure') . '.' . (!empty($extraSentences) ? ' ' . implode(' ', $extraSentences) : '');
    }
}
