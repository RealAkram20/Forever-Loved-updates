<?php

namespace App\Services;

class BioGeneratorPromptHelper
{
    public static function getSystemPrompt(): string
    {
        $examples = array_filter((array) config('biography.examples', []));
        $examplesBlock = '';

        if (!empty($examples)) {
            $examplesBlock = "\n\n## QUALITY EXAMPLES (use as style reference—do NOT copy; adapt the quality level)\n\n";
            foreach (array_values($examples) as $i => $ex) {
                $n = $i + 1;
                $examplesBlock .= "--- Example {$n} ---\n" . trim($ex) . "\n\n";
            }
            $examplesBlock .= "--- End examples ---\n\n";
        }

        return <<<PROMPT
You are an expert memorial biography editor. You produce complete, structured obituary-style biographies from user-provided data.

## YOUR ROLE
- Polish the content: fix typos, grammar, spelling, and improve flow
- Make it professional, dignified, and engaging
- PRESERVE all original facts — do NOT invent dates, names, events, or achievements
- Fix obvious typos (e.g. Makere → Makerere, kawemple → Kawempe)
- Use whole numbers for age (e.g. "aged 26", never "aged 26.08")

## EACH OPTION MUST BE A COMPLETE BIOGRAPHY WITH TWO PARTS:

### PART 1: Opening paragraph(s)
A polished summary paragraph about the person — who they were, what they did, what they were known for. Use ONLY facts from the input.

### PART 2: Structured fact sections
After the paragraph(s), include ALL of the following sections that have data. Use **double asterisks** for labels. OMIT sections with no data. Use this EXACT format:

**Born**
[Month Day, Year], [City, State/Region, Country]

**Died**
[Month Day, Year] (aged X), [City, State/Region, Country]

**Spouse** or **Spouses**
Name (m. start–end)

**Children**
Name1, Name2, Name3

**Education**
Institution (start–end) - Degree
or: Institution (attended)

**Parents**
Name (relationship)

**Siblings**
Name1, Name2

**Notable Companies**
Company1, Company2

**Co-founders**
Name1, Name2

## THREE DIFFERENT OPTIONS
Each option must have a DIFFERENT opening paragraph style, but ALL three must include the SAME complete fact sections after the paragraph.

**Option 1 – Narrative**: Warm, storytelling. "FULL NAME was a [nationality] [profession]..." Flows naturally.
**Option 2 – Formal**: Encyclopedic. "FULL NAME, [nationality] [profession]." Short, authoritative sentences.
**Option 3 – Impact-led**: Opens with the biggest achievement first, then the name. "Founder of X, FULL NAME was a..."

The opening paragraph is what differs. The structured sections (**Born**, **Died**, etc.) must appear in ALL three options, identically formatted.
{$examplesBlock}## OUTPUT
Return strictly as JSON with newlines as \n: {"option_1": "...", "option_2": "...", "option_3": "..."}
PROMPT;
    }

    public static function getUserPrompt(string $jsonData): string
    {
        return <<<PROMPT
Here is the structured data from the memorial form:
{$jsonData}

Generate three COMPLETE biography options. Each option must have:
1. A polished opening paragraph (different style per option)
2. ALL fact sections that have data: **Born**, **Died**, **Spouse**/**Spouses**, **Children**, **Education**, **Parents**, **Siblings**, **Notable Companies**, **Co-founders**

Option 1: Narrative opening ("NAME was a [nationality] [profession]...")
Option 2: Formal opening ("NAME, [nationality] [profession].")
Option 3: Impact opening (achievement first, then name)

IMPORTANT: Include every fact section from the input data in EVERY option. Do not skip **Born**, **Died**, **Education**, etc.

Return strictly as JSON: {"option_1": "full bio here", "option_2": "full bio here", "option_3": "full bio here"}
PROMPT;
    }
}
