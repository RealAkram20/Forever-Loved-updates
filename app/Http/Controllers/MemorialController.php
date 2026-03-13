<?php

namespace App\Http\Controllers;

use App\Helpers\PlanLimitsHelper;
use App\Models\Memorial;
use App\Services\ClaudeBioGeneratorService;
use App\Services\GeminiBioGeneratorService;
use App\Services\NotificationService;
use App\Services\OpenAIBioGeneratorService;
use App\Services\TemplateBioGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MemorialController extends Controller
{
    /**
     * AJAX memorial search — returns JSON for live search dropdowns.
     * Context: 'public' (default) = only public memorials; 'admin' = own + granted; 'super_admin' = all.
     * Private memorials (is_public=false) never appear in public search.
     */
    public function search(Request $request): JsonResponse
    {
        $query = trim($request->input('q', ''));
        $context = $request->input('context', 'public');
        $user = $request->user();

        if (mb_strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $base = Memorial::query()
            ->where(function ($q) use ($query) {
                $q->where('full_name', 'like', "%{$query}%")
                  ->orWhere('first_name', 'like', "%{$query}%")
                  ->orWhere('last_name', 'like', "%{$query}%")
                  ->orWhere('primary_profession', 'like', "%{$query}%")
                  ->orWhere('nationality', 'like', "%{$query}%")
                  ->orWhere('birth_city', 'like', "%{$query}%")
                  ->orWhere('death_city', 'like', "%{$query}%")
                  ->orWhere('known_for', 'like', "%{$query}%");
            });

        if ($context === 'super_admin' && $user?->hasRole('super-admin')) {
            $base->whereIn('status', [Memorial::STATUS_ACTIVE, Memorial::STATUS_DEACTIVATED, Memorial::STATUS_SUSPENDED]);
        } elseif ($context === 'admin' && $user?->hasRole(['admin', 'super-admin'])) {
            if ($user->hasRole('super-admin')) {
                $base->whereIn('status', [Memorial::STATUS_ACTIVE, Memorial::STATUS_DEACTIVATED, Memorial::STATUS_SUSPENDED]);
            } else {
                $base->where('user_id', $user->id);
            }
        } else {
            $base->where('is_public', true)
                 ->where('status', Memorial::STATUS_ACTIVE)
                 ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
        }

        $memorials = $base
            ->select(['id', 'slug', 'full_name', 'primary_profession', 'profile_photo_path', 'birth_year', 'death_year', 'date_of_birth', 'date_of_passing'])
            ->orderByRaw("CASE WHEN full_name LIKE ? THEN 0 ELSE 1 END", ["{$query}%"])
            ->orderBy('full_name')
            ->limit(8)
            ->get();

        $results = $memorials->map(fn (Memorial $m) => [
            'slug' => $m->slug,
            'name' => $m->full_name,
            'profession' => $m->primary_profession,
            'photo' => $m->profile_photo_url,
            'years' => $m->birth_death_years,
            'url' => route('memorial.public', $m->slug),
        ]);

        return response()->json(['results' => $results]);
    }

    /**
     * Display memorials: admin sees all with admin table; regular users see their own with user table.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->hasRole(['admin', 'super-admin']);

        $memorials = $isAdmin
            ? Memorial::with(['owner', 'media', 'tributes' => fn ($q) => $q->with('user')->whereNotNull('user_id')])
                ->latest()
                ->paginate(10)
            : $user->memorials()->latest()->paginate(10);

        return view('pages.memorials.index', [
            'title' => $isAdmin ? 'All Memorials' : 'My Memorials',
            'memorials' => $memorials,
            'isAdmin' => $isAdmin,
        ]);
    }

    /**
     * Show the form for creating a new memorial.
     */
    public function create()
    {
        return view('pages.memorials.create', [
            'title' => 'Create Memorial',
        ]);
    }

    /**
     * Store a newly created memorial.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'relationship' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'primary_profession' => ['nullable', 'string', 'max:255'],
            'notable_title' => ['nullable', 'string', 'max:255'],
            'more_details' => ['nullable', 'string', 'max:2000'],
            'major_achievements' => ['nullable', 'string', 'max:2000'],
            'known_for' => ['nullable', 'string', 'max:500'],
            'active_year_start' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'active_year_end' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'date_of_birth' => ['nullable', 'date'],
            'date_of_passing' => ['nullable', 'date'],
            'birth_city' => ['nullable', 'string', 'max:255'],
            'birth_state' => ['nullable', 'string', 'max:255'],
            'birth_country' => ['nullable', 'string', 'max:255'],
            'death_city' => ['nullable', 'string', 'max:255'],
            'death_state' => ['nullable', 'string', 'max:255'],
            'death_country' => ['nullable', 'string', 'max:255'],
            'cause_of_death' => ['nullable', 'string', 'max:255'],
            'biography' => ['nullable', 'string', 'max:50000'],
            'theme' => ['required', Rule::in(['free', 'premium', 'classic', 'modern', 'garden'])],
            'plan' => ['nullable', Rule::in(['free', 'paid'])],
            'companies' => ['nullable', 'array'],
            'companies.*.company_name' => ['nullable', 'string', 'max:255'],
            'co_founders' => ['nullable', 'array'],
            'co_founders.*.name' => ['nullable', 'string', 'max:255'],
            'children' => ['nullable', 'array'],
            'children.*.child_name' => ['nullable', 'string', 'max:255'],
            'children.*.birth_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'spouses' => ['nullable', 'array'],
            'spouses.*.spouse_name' => ['nullable', 'string', 'max:255'],
            'spouses.*.marriage_start_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'spouses.*.marriage_end_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'parents' => ['nullable', 'array'],
            'parents.*.parent_name' => ['nullable', 'string', 'max:255'],
            'parents.*.relationship_type' => ['nullable', Rule::in(['biological', 'adoptive'])],
            'siblings' => ['nullable', 'array'],
            'siblings.*.sibling_name' => ['nullable', 'string', 'max:255'],
            'education' => ['nullable', 'array'],
            'education.*.institution_name' => ['nullable', 'string', 'max:255'],
            'education.*.start_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'education.*.end_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'education.*.degree' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['cause_of_death_private'] = $request->boolean('cause_of_death_private');
        $validated['plan'] = $validated['plan'] ?? ($validated['theme'] === 'premium' ? 'paid' : 'free');
        $validated['completion_status'] = Memorial::COMPLETION_PENDING;
        $validated['full_name'] = trim(implode(' ', array_filter([
            $validated['first_name'],
            $validated['middle_name'] ?? '',
            $validated['last_name'],
        ])));
        $validated['title'] = 'In Loving Memory of ' . $validated['full_name'];
        $validated['slug'] = static::generateUniqueSlug($validated['full_name']);
        $validated['is_public'] = $request->boolean('is_public', true);

        if (!empty($validated['cause_of_death']) && trim($validated['cause_of_death']) !== '' && $validated['cause_of_death'] !== 'No designation') {
            $validated['designation'] = $validated['cause_of_death'];
        }

        $bio = trim($validated['biography'] ?? '');
        $validated['biography'] = $bio ?: null;

        $scalarData = collect($validated)->except(['companies', 'co_founders', 'children', 'spouses', 'parents', 'siblings', 'education'])->toArray();

        $memorial = DB::transaction(function () use ($scalarData, $validated) {
            $memorial = Memorial::create($scalarData);
            static::syncMemorialRelations($memorial, $validated);
            return $memorial;
        });

        if (empty($memorial->biography)) {
            try {
                $bioService = app(TemplateBioGeneratorService::class);
                $biography = $bioService->generateStructured($memorial);
                if ($biography && trim($biography) !== '') {
                    $memorial->update(['biography' => $biography]);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('memorial.create.preparing', ['slug' => $memorial->slug]);
    }

    /**
     * Display the specified memorial.
     */
    public function show(Memorial $memorial)
    {
        $this->authorize('view', $memorial);

        return view('pages.memorials.show', [
            'title' => $memorial->title,
            'memorial' => $memorial,
        ]);
    }

    /**
     * Show the form for editing the specified memorial.
     */
    public function edit(Memorial $memorial)
    {
        $this->authorize('update', $memorial);

        $aiProvider = $this->getActiveAiProvider();

        return view('pages.memorials.edit', [
            'title' => 'Edit Memorial',
            'memorial' => $memorial,
            'aiEnabled' => $aiProvider !== null,
            'aiProviderName' => $aiProvider,
            'quotaInfo' => PlanLimitsHelper::getQuotaInfo($memorial),
        ]);
    }

    /**
     * Update the specified memorial.
     */
    public function update(Request $request, Memorial $memorial)
    {
        $this->authorize('update', $memorial);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'relationship' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'primary_profession' => ['nullable', 'string', 'max:255'],
            'notable_title' => ['nullable', 'string', 'max:255'],
            'more_details' => ['nullable', 'string', 'max:2000'],
            'major_achievements' => ['nullable', 'string', 'max:2000'],
            'known_for' => ['nullable', 'string', 'max:500'],
            'active_year_start' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'active_year_end' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'date_of_birth' => ['nullable', 'date'],
            'date_of_passing' => ['nullable', 'date'],
            'birth_city' => ['nullable', 'string', 'max:255'],
            'birth_state' => ['nullable', 'string', 'max:255'],
            'birth_country' => ['nullable', 'string', 'max:255'],
            'death_city' => ['nullable', 'string', 'max:255'],
            'death_state' => ['nullable', 'string', 'max:255'],
            'death_country' => ['nullable', 'string', 'max:255'],
            'cause_of_death' => ['nullable', 'string', 'max:255'],
            'cause_of_death_private' => ['nullable', 'boolean'],
            'theme' => ['required', Rule::in(['free', 'premium', 'classic', 'modern', 'garden'])],
            'plan' => ['nullable', Rule::in(['free', 'paid'])],
            'companies' => ['nullable', 'array'],
            'companies.*.company_name' => ['nullable', 'string', 'max:255'],
            'co_founders' => ['nullable', 'array'],
            'co_founders.*.name' => ['nullable', 'string', 'max:255'],
            'children' => ['nullable', 'array'],
            'children.*.child_name' => ['nullable', 'string', 'max:255'],
            'children.*.birth_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'spouses' => ['nullable', 'array'],
            'spouses.*.spouse_name' => ['nullable', 'string', 'max:255'],
            'spouses.*.marriage_start_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'spouses.*.marriage_end_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'parents' => ['nullable', 'array'],
            'parents.*.parent_name' => ['nullable', 'string', 'max:255'],
            'parents.*.relationship_type' => ['nullable', Rule::in(['biological', 'adoptive'])],
            'siblings' => ['nullable', 'array'],
            'siblings.*.sibling_name' => ['nullable', 'string', 'max:255'],
            'education' => ['nullable', 'array'],
            'education.*.institution_name' => ['nullable', 'string', 'max:255'],
            'education.*.start_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'education.*.end_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'education.*.degree' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['full_name'] = trim(implode(' ', array_filter([
            $validated['first_name'],
            $validated['middle_name'] ?? '',
            $validated['last_name'],
        ])));
        $validated['title'] = 'In Loving Memory of ' . $validated['full_name'];
        $validated['is_public'] = $request->boolean('is_public', true);
        $validated['cause_of_death_private'] = $request->boolean('cause_of_death_private');

        if (array_key_exists('cause_of_death', $validated)) {
            $cause = $validated['cause_of_death'];
            if (!empty($cause) && trim($cause) !== '' && $cause !== 'No designation') {
                $validated['designation'] = $cause;
            } elseif ($cause === 'No designation' || (empty($cause) && !$memorial->designation)) {
                $validated['designation'] = null;
            }
        }

        if ($memorial->full_name !== $validated['full_name']) {
            $validated['slug'] = static::generateUniqueSlug($validated['full_name'], $memorial->id);
        }

        $memorial->update(collect($validated)->except(['companies', 'co_founders', 'children', 'spouses', 'parents', 'siblings', 'education'])->toArray());

        static::syncMemorialRelations($memorial, $validated);

        if ($request->expectsJson()) {
            $memorial->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Memorial updated successfully.',
                'completion_percentage' => $memorial->completion_percentage,
            ]);
        }

        return redirect()->route('memorials.index')
            ->with('status', 'Memorial updated successfully.');
    }

    /**
     * Update a single section of the memorial (AJAX, no redirect).
     */
    public function updateSection(Request $request, Memorial $memorial): JsonResponse
    {
        $this->authorize('update', $memorial);

        $validated = $request->validate([
            'section' => ['required', 'string', 'in:identity,biography_summary,birth,death,family,education,settings'],
            'data' => ['required', 'array'],
        ]);

        $section = $validated['section'];
        $data = $validated['data'];

        $rules = $this->getSectionValidationRules($section);
        $sectionData = validator($data, $rules)->validate();

        DB::transaction(function () use ($memorial, $section, $sectionData) {
            if ($section === 'identity') {
                $identityData = collect($sectionData)->only([
                    'first_name', 'middle_name', 'last_name', 'short_description', 'nationality',
                    'primary_profession', 'notable_title', 'gender', 'relationship'
                ])->toArray();
                $fullName = trim(implode(' ', array_filter([
                    $sectionData['first_name'] ?? '',
                    $sectionData['middle_name'] ?? '',
                    $sectionData['last_name'] ?? '',
                ])));
                $memorial->update(array_merge($identityData, [
                    'full_name' => $fullName,
                    'title' => 'In Loving Memory of ' . $fullName,
                ]));
            } elseif ($section === 'biography_summary') {
                $memorial->update(collect($sectionData)->only([
                    'major_achievements', 'known_for', 'active_year_start', 'active_year_end'
                ])->toArray());
                static::syncMemorialRelations($memorial, [
                    'companies' => $sectionData['companies'] ?? [],
                    'co_founders' => $sectionData['co_founders'] ?? [],
                ]);
            } elseif ($section === 'birth') {
                $memorial->update(collect($sectionData)->only([
                    'date_of_birth', 'birth_city', 'birth_state', 'birth_country'
                ])->toArray());
            } elseif ($section === 'death') {
                $cause = $sectionData['cause_of_death'] ?? null;
                $cleanCause = $cause && trim((string) $cause) !== '' ? trim($cause) : null;
                $updateData = [
                    'date_of_passing' => $sectionData['date_of_passing'] ?? null,
                    'death_city' => $sectionData['death_city'] ?? null,
                    'death_state' => $sectionData['death_state'] ?? null,
                    'death_country' => $sectionData['death_country'] ?? null,
                    'cause_of_death' => $cleanCause,
                    'cause_of_death_private' => !empty($sectionData['cause_of_death_private']),
                ];
                if ($cleanCause && $cleanCause !== 'No designation') {
                    $updateData['designation'] = $cleanCause;
                } elseif ($cleanCause === 'No designation' || (!$cleanCause && !$memorial->designation)) {
                    $updateData['designation'] = null;
                }
                $memorial->update($updateData);
            } elseif ($section === 'family') {
                static::syncMemorialRelations($memorial, [
                    'children' => $sectionData['children'] ?? [],
                    'spouses' => $sectionData['spouses'] ?? [],
                    'parents' => $sectionData['parents'] ?? [],
                    'siblings' => $sectionData['siblings'] ?? [],
                ]);
            } elseif ($section === 'education') {
                static::syncMemorialRelations($memorial, [
                    'education' => $sectionData['education'] ?? [],
                ]);
            } elseif ($section === 'settings') {
                $memorial->update([
                    'theme' => $sectionData['theme'] ?? 'free',
                    'plan' => $sectionData['plan'] ?? 'free',
                    'is_public' => !empty($sectionData['is_public']),
                ]);
            }
        });

        $memorial->refresh();

        return response()->json([
            'success' => true,
            'completion_percentage' => $memorial->completion_percentage,
        ]);
    }

    /**
     * Update individual fields of the memorial (auto-save, no section required).
     */
    public function updateFields(Request $request, Memorial $memorial): JsonResponse
    {
        $this->authorize('update', $memorial);

        $input = $request->validate(['fields' => ['required', 'array']])['fields'];

        $allRules = [
            'first_name' => ['nullable', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'primary_profession' => ['nullable', 'string', 'max:255'],
            'notable_title' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'relationship' => ['nullable', 'string', 'max:255'],
            'major_achievements' => ['nullable', 'string', 'max:2000'],
            'known_for' => ['nullable', 'string', 'max:500'],
            'active_year_start' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'active_year_end' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'date_of_birth' => ['nullable', 'date'],
            'date_of_passing' => ['nullable', 'date'],
            'birth_city' => ['nullable', 'string', 'max:255'],
            'birth_state' => ['nullable', 'string', 'max:255'],
            'birth_country' => ['nullable', 'string', 'max:255'],
            'death_city' => ['nullable', 'string', 'max:255'],
            'death_state' => ['nullable', 'string', 'max:255'],
            'death_country' => ['nullable', 'string', 'max:255'],
            'cause_of_death' => ['nullable', 'string', 'max:255'],
            'cause_of_death_private' => ['nullable'],
            'is_public' => ['nullable'],
            'theme' => ['nullable', Rule::in(['free', 'premium', 'classic', 'modern', 'garden'])],
            'plan' => ['nullable', Rule::in(['free', 'paid'])],
            'companies' => ['nullable', 'array'],
            'companies.*.company_name' => ['nullable', 'string', 'max:255'],
            'co_founders' => ['nullable', 'array'],
            'co_founders.*.name' => ['nullable', 'string', 'max:255'],
            'children' => ['nullable', 'array'],
            'children.*.child_name' => ['nullable', 'string', 'max:255'],
            'children.*.birth_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'spouses' => ['nullable', 'array'],
            'spouses.*.spouse_name' => ['nullable', 'string', 'max:255'],
            'spouses.*.marriage_start_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'spouses.*.marriage_end_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'parents' => ['nullable', 'array'],
            'parents.*.parent_name' => ['nullable', 'string', 'max:255'],
            'parents.*.relationship_type' => ['nullable', Rule::in(['biological', 'adoptive'])],
            'siblings' => ['nullable', 'array'],
            'siblings.*.sibling_name' => ['nullable', 'string', 'max:255'],
            'education' => ['nullable', 'array'],
            'education.*.institution_name' => ['nullable', 'string', 'max:255'],
            'education.*.start_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'education.*.end_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'education.*.degree' => ['nullable', 'string', 'max:255'],
        ];

        $rules = array_intersect_key($allRules, $input);
        $relationKeys = ['companies', 'co_founders', 'children', 'spouses', 'parents', 'siblings', 'education'];
        foreach ($relationKeys as $rel) {
            if (isset($input[$rel])) {
                foreach ($allRules as $key => $rule) {
                    if (str_starts_with($key, $rel . '.')) {
                        $rules[$key] = $rule;
                    }
                }
            }
        }

        $validated = validator($input, $rules)->validate();

        $scalarData = collect($validated)->except($relationKeys)->toArray();
        $relationData = collect($validated)->only($relationKeys)->toArray();

        DB::transaction(function () use ($memorial, $scalarData, $relationData) {
            if (!empty($scalarData)) {
                if (isset($scalarData['first_name']) || isset($scalarData['middle_name']) || isset($scalarData['last_name'])) {
                    $fullName = trim(implode(' ', array_filter([
                        $scalarData['first_name'] ?? $memorial->first_name ?? '',
                        $scalarData['middle_name'] ?? $memorial->middle_name ?? '',
                        $scalarData['last_name'] ?? $memorial->last_name ?? '',
                    ])));
                    $scalarData['full_name'] = $fullName;
                    $scalarData['title'] = 'In Loving Memory of ' . $fullName;
                }
                if (array_key_exists('cause_of_death_private', $scalarData)) {
                    $scalarData['cause_of_death_private'] = !empty($scalarData['cause_of_death_private']);
                }
                if (array_key_exists('is_public', $scalarData)) {
                    $scalarData['is_public'] = !empty($scalarData['is_public']);
                }
                if (array_key_exists('cause_of_death', $scalarData)) {
                    $cause = $scalarData['cause_of_death'];
                    $cleanCause = $cause && trim((string) $cause) !== '' ? trim($cause) : null;
                    $scalarData['cause_of_death'] = $cleanCause;
                    if ($cleanCause && $cleanCause !== 'No designation') {
                        $scalarData['designation'] = $cleanCause;
                    } elseif ($cleanCause === 'No designation' || (!$cleanCause && !$memorial->designation)) {
                        $scalarData['designation'] = null;
                    }
                }
                $memorial->update($scalarData);
            }
            if (!empty($relationData)) {
                static::syncMemorialRelations($memorial, $relationData);
            }
        });

        $memorial->refresh();

        return response()->json([
            'success' => true,
            'completion_percentage' => $memorial->completion_percentage,
        ]);
    }

    protected function getSectionValidationRules(string $section): array
    {
        $base = [
            'first_name' => ['nullable', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'primary_profession' => ['nullable', 'string', 'max:255'],
            'notable_title' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'relationship' => ['nullable', 'string', 'max:255'],
            'major_achievements' => ['nullable', 'string', 'max:2000'],
            'known_for' => ['nullable', 'string', 'max:500'],
            'active_year_start' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'active_year_end' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'date_of_birth' => ['nullable', 'date'],
            'date_of_passing' => ['nullable', 'date'],
            'birth_city' => ['nullable', 'string', 'max:255'],
            'birth_state' => ['nullable', 'string', 'max:255'],
            'birth_country' => ['nullable', 'string', 'max:255'],
            'death_city' => ['nullable', 'string', 'max:255'],
            'death_state' => ['nullable', 'string', 'max:255'],
            'death_country' => ['nullable', 'string', 'max:255'],
            'cause_of_death' => ['nullable', 'string', 'max:255'],
            'cause_of_death_private' => ['nullable'],
            'theme' => ['nullable', Rule::in(['free', 'premium', 'classic', 'modern', 'garden'])],
            'plan' => ['nullable', Rule::in(['free', 'paid'])],
            'is_public' => ['nullable'],
            'companies' => ['nullable', 'array'],
            'companies.*.company_name' => ['nullable', 'string', 'max:255'],
            'co_founders' => ['nullable', 'array'],
            'co_founders.*.name' => ['nullable', 'string', 'max:255'],
            'children' => ['nullable', 'array'],
            'children.*.child_name' => ['nullable', 'string', 'max:255'],
            'children.*.birth_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'spouses' => ['nullable', 'array'],
            'spouses.*.spouse_name' => ['nullable', 'string', 'max:255'],
            'spouses.*.marriage_start_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'spouses.*.marriage_end_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'parents' => ['nullable', 'array'],
            'parents.*.parent_name' => ['nullable', 'string', 'max:255'],
            'parents.*.relationship_type' => ['nullable', Rule::in(['biological', 'adoptive'])],
            'siblings' => ['nullable', 'array'],
            'siblings.*.sibling_name' => ['nullable', 'string', 'max:255'],
            'education' => ['nullable', 'array'],
            'education.*.institution_name' => ['nullable', 'string', 'max:255'],
            'education.*.start_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'education.*.end_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'education.*.degree' => ['nullable', 'string', 'max:255'],
        ];

        $sectionFields = [
            'identity' => ['first_name', 'middle_name', 'last_name', 'short_description', 'nationality', 'primary_profession', 'notable_title', 'gender', 'relationship'],
            'biography_summary' => ['major_achievements', 'known_for', 'active_year_start', 'active_year_end', 'companies', 'companies.*.company_name', 'co_founders', 'co_founders.*.name'],
            'birth' => ['date_of_birth', 'birth_city', 'birth_state', 'birth_country'],
            'death' => ['date_of_passing', 'death_city', 'death_state', 'death_country', 'cause_of_death', 'cause_of_death_private'],
            'family' => ['children', 'children.*.child_name', 'children.*.birth_year', 'spouses', 'spouses.*.spouse_name', 'spouses.*.marriage_start_year', 'spouses.*.marriage_end_year', 'parents', 'parents.*.parent_name', 'parents.*.relationship_type', 'siblings', 'siblings.*.sibling_name'],
            'education' => ['education', 'education.*.institution_name', 'education.*.start_year', 'education.*.end_year', 'education.*.degree'],
            'settings' => ['theme', 'plan', 'is_public'],
        ];

        $keys = $sectionFields[$section] ?? [];
        $flat = [];
        foreach ($keys as $k) {
            if (isset($base[$k])) {
                $flat[$k] = $base[$k];
            }
        }
        return $flat;
    }

    /**
     * Generate template-based biography from structured fields.
     * Accepts optional form_data to use current unsaved form values.
     */
    public function generateTemplateBiography(Request $request, Memorial $memorial): JsonResponse
    {
        $this->authorize('update', $memorial);

        if ($request->has('form_data') && is_array($request->form_data)) {
            $this->applyFormDataToMemorial($memorial, $request->form_data);
            $memorial->refresh();
        }

        $memorial->load(['notableCompanies', 'coFounders', 'children', 'spouses', 'parents', 'siblings', 'education']);

        $service = app(TemplateBioGeneratorService::class);
        $biography = $service->generateStructured($memorial);

        return response()->json([
            'biography' => $biography,
        ]);
    }

    /**
     * Generate biography summaries via OpenAI or Gemini (or template fallback).
     * Accepts optional form_data to use current unsaved form values.
     */
    public function generateBiography(Request $request, Memorial $memorial): JsonResponse
    {
        $this->authorize('update', $memorial);

        $aiCheck = PlanLimitsHelper::canUseAiBio($memorial);
        if (!$aiCheck['allowed']) {
            return response()->json(['message' => $aiCheck['reason']], 422);
        }

        if ($request->has('form_data') && is_array($request->form_data)) {
            $this->applyFormDataToMemorial($memorial, $request->form_data);
            $memorial->refresh();
        }

        $memorial->load(['notableCompanies', 'coFounders', 'children', 'spouses', 'parents', 'siblings', 'education']);
        $structuredData = GeminiBioGeneratorService::buildStructuredDataFromMemorial($memorial);

        $aiProvider = $this->getActiveAiProvider();

        if (!$aiProvider) {
            return response()->json([
                'message' => 'No AI provider is enabled. Please enable OpenAI, Claude, or Gemini in your configuration.',
            ], 422);
        }

        $service = match ($aiProvider) {
            'ChatGPT' => app(OpenAIBioGeneratorService::class),
            'Claude AI' => app(ClaudeBioGeneratorService::class),
            'Google Gemini' => app(GeminiBioGeneratorService::class),
        };
        $noCache = $request->boolean('no_cache') || $request->boolean('fresh');

        try {
            $options = $service->generate($structuredData, $memorial->id, $noCache);
        } catch (\Throwable $e) {
            $userMessage = $this->parseAiErrorMessage($e->getMessage());
            return response()->json([
                'message' => $userMessage,
            ], 422);
        }

        $o1 = trim($options['option_1'] ?? '');
        $o2 = trim($options['option_2'] ?? '');
        $o3 = trim($options['option_3'] ?? '');

        if (!$o1 && !$o2 && !$o3) {
            return response()->json([
                'message' => 'AI returned empty results. Please add more details and try again.',
            ], 422);
        }

        PlanLimitsHelper::incrementAiBioUsage($memorial->user_id);

        return response()->json([
            'ai_provider' => $aiProvider,
            'option_1' => $o1,
            'option_2' => $o2,
            'option_3' => $o3,
        ]);
    }

    /**
     * Apply form data to memorial (for generate endpoints). Validates and updates.
     */
    protected function getActiveAiProvider(): ?string
    {
        if (config('services.openai.enabled') && config('services.openai.api_key')) {
            return 'ChatGPT';
        }
        if (config('services.anthropic.enabled') && config('services.anthropic.api_key')) {
            return 'Claude AI';
        }
        if (config('services.gemini.enabled') && config('services.gemini.api_key')) {
            return 'Google Gemini';
        }
        return null;
    }

    protected function parseAiErrorMessage(string $message): string
    {
        if (str_starts_with($message, 'AI_AUTH_ERROR:')) {
            return 'AI authentication failed. The API key may be invalid or expired. Template suggestions are shown instead.';
        }
        if (str_starts_with($message, 'AI_NO_CREDITS:')) {
            return 'Your AI account has no remaining credits. Please top up your API billing. Template suggestions are shown instead.';
        }
        if (str_starts_with($message, 'AI_RATE_LIMIT:')) {
            return 'AI rate limit reached. Please wait a moment and try again.';
        }
        if (str_starts_with($message, 'AI_MODEL_ERROR:')) {
            return 'The configured AI model is unavailable. Please check your settings. Template suggestions are shown instead.';
        }
        if (str_starts_with($message, 'AI_OVERLOADED:')) {
            return 'The AI service is temporarily overloaded. Please try again in a few seconds.';
        }
        if (str_starts_with($message, 'AI_API_ERROR:')) {
            return 'AI generation encountered an error. Template suggestions are shown instead.';
        }
        return 'AI generation failed. Template suggestions are shown instead.';
    }

    protected function applyFormDataToMemorial(Memorial $memorial, array $data): void
    {
        $rules = [
            'first_name' => ['nullable', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'relationship' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'primary_profession' => ['nullable', 'string', 'max:255'],
            'notable_title' => ['nullable', 'string', 'max:255'],
            'more_details' => ['nullable', 'string', 'max:2000'],
            'major_achievements' => ['nullable', 'string', 'max:2000'],
            'known_for' => ['nullable', 'string', 'max:500'],
            'active_year_start' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'active_year_end' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'date_of_birth' => ['nullable', 'date'],
            'date_of_passing' => ['nullable', 'date'],
            'birth_city' => ['nullable', 'string', 'max:255'],
            'birth_state' => ['nullable', 'string', 'max:255'],
            'birth_country' => ['nullable', 'string', 'max:255'],
            'death_city' => ['nullable', 'string', 'max:255'],
            'death_state' => ['nullable', 'string', 'max:255'],
            'death_country' => ['nullable', 'string', 'max:255'],
            'cause_of_death' => ['nullable', 'string', 'max:255'],
            'companies' => ['nullable', 'array'],
            'companies.*.company_name' => ['nullable', 'string', 'max:255'],
            'co_founders' => ['nullable', 'array'],
            'co_founders.*.name' => ['nullable', 'string', 'max:255'],
            'children' => ['nullable', 'array'],
            'children.*.child_name' => ['nullable', 'string', 'max:255'],
            'children.*.birth_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'spouses' => ['nullable', 'array'],
            'spouses.*.spouse_name' => ['nullable', 'string', 'max:255'],
            'spouses.*.marriage_start_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'spouses.*.marriage_end_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'parents' => ['nullable', 'array'],
            'parents.*.parent_name' => ['nullable', 'string', 'max:255'],
            'parents.*.relationship_type' => ['nullable', Rule::in(['biological', 'adoptive'])],
            'siblings' => ['nullable', 'array'],
            'siblings.*.sibling_name' => ['nullable', 'string', 'max:255'],
            'education' => ['nullable', 'array'],
            'education.*.institution_name' => ['nullable', 'string', 'max:255'],
            'education.*.start_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'education.*.end_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'education.*.degree' => ['nullable', 'string', 'max:255'],
        ];
        $validated = validator($data, $rules)->validate();

        $scalar = collect($validated)->except(['companies', 'co_founders', 'children', 'spouses', 'parents', 'siblings', 'education'])->toArray();
        $scalar['cause_of_death_private'] = !empty($data['cause_of_death_private']);
        $fullName = trim(implode(' ', array_filter([
            $validated['first_name'] ?? '',
            $validated['middle_name'] ?? '',
            $validated['last_name'] ?? '',
        ])));
        $scalar['full_name'] = $fullName ?: $memorial->full_name;
        $scalar['title'] = 'In Loving Memory of ' . $scalar['full_name'];

        $memorial->update($scalar);
        static::syncMemorialRelations($memorial, $validated);
    }

    /**
     * Save the user-selected biography.
     */
    public function saveBiography(Request $request, Memorial $memorial): JsonResponse
    {
        $this->authorize('update', $memorial);

        $validated = $request->validate([
            'biography' => ['nullable', 'string', 'max:50000'],
        ]);

        $memorial->update(['biography' => trim($validated['biography'] ?? '') ?: null]);

        return response()->json(['success' => true]);
    }

    /**
     * Remove the specified memorial.
     */
    public function destroy(Memorial $memorial)
    {
        $this->authorize('delete', $memorial);

        $memorial->delete();

        return redirect()->route('memorials.index')
            ->with('status', 'Memorial deleted successfully.');
    }

    /**
     * Update memorial status (deactivate, suspend, delete) - admin only.
     */
    public function updateStatus(Request $request, Memorial $memorial)
    {
        if (!$request->user()?->hasRole(['admin', 'super-admin'])) {
            abort(403);
        }

        $validated = $request->validate([
            'action' => ['required', 'in:activate,deactivate,suspend,delete'],
        ]);

        switch ($validated['action']) {
            case 'activate':
                $memorial->update(['status' => Memorial::STATUS_ACTIVE, 'is_public' => true]);
                NotificationService::notifyMemorialStatusChange($memorial, 'active');
                return back()->with('status', 'Memorial activated.');
            case 'deactivate':
                $memorial->update(['status' => Memorial::STATUS_DEACTIVATED, 'is_public' => false]);
                NotificationService::notifyMemorialStatusChange($memorial, 'deactivated');
                return back()->with('status', 'Memorial deactivated.');
            case 'suspend':
                $memorial->update(['status' => Memorial::STATUS_SUSPENDED, 'is_public' => false]);
                NotificationService::notifyMemorialStatusChange($memorial, 'suspended');
                return back()->with('status', 'Memorial suspended.');
            case 'delete':
                $memorial->delete();
                return back()->with('status', 'Memorial deleted.');
        }

        return back()->with('error', 'Invalid action.');
    }

    /**
     * Sync relational data (companies, co-founders, children, spouses, parents, siblings, education).
     * Only syncs relations that exist as keys in $validated (allows partial updates).
     */
    protected static function syncMemorialRelations(Memorial $memorial, array $validated): void
    {
        if (array_key_exists('companies', $validated)) {
            $memorial->notableCompanies()->delete();
            foreach (array_filter($validated['companies'] ?? [], fn ($c) => !empty(trim($c['company_name'] ?? ''))) as $i => $c) {
                $memorial->notableCompanies()->create(['company_name' => trim($c['company_name']), 'sort_order' => $i]);
            }
        }

        if (array_key_exists('co_founders', $validated)) {
            $memorial->coFounders()->delete();
            foreach (array_filter($validated['co_founders'] ?? [], fn ($c) => !empty(trim($c['name'] ?? ''))) as $i => $c) {
                $memorial->coFounders()->create(['name' => trim($c['name']), 'sort_order' => $i]);
            }
        }

        if (array_key_exists('children', $validated)) {
            $memorial->children()->delete();
            foreach (array_filter($validated['children'] ?? [], fn ($c) => !empty(trim($c['child_name'] ?? ''))) as $c) {
                $memorial->children()->create([
                    'child_name' => trim($c['child_name']),
                    'birth_year' => !empty($c['birth_year']) ? (int) $c['birth_year'] : null,
                ]);
            }
        }

        if (array_key_exists('spouses', $validated)) {
            $memorial->spouses()->delete();
            foreach (array_filter($validated['spouses'] ?? [], fn ($c) => !empty(trim($c['spouse_name'] ?? ''))) as $c) {
                $memorial->spouses()->create([
                    'spouse_name' => trim($c['spouse_name']),
                    'marriage_start_year' => !empty($c['marriage_start_year']) ? (int) $c['marriage_start_year'] : null,
                    'marriage_end_year' => !empty($c['marriage_end_year']) ? (int) $c['marriage_end_year'] : null,
                ]);
            }
        }

        if (array_key_exists('parents', $validated)) {
            $memorial->parents()->delete();
            foreach (array_filter($validated['parents'] ?? [], fn ($c) => !empty(trim($c['parent_name'] ?? ''))) as $c) {
                $memorial->parents()->create([
                    'parent_name' => trim($c['parent_name']),
                    'relationship_type' => in_array($c['relationship_type'] ?? '', ['biological', 'adoptive']) ? $c['relationship_type'] : 'biological',
                ]);
            }
        }

        if (array_key_exists('siblings', $validated)) {
            $memorial->siblings()->delete();
            foreach (array_filter($validated['siblings'] ?? [], fn ($c) => !empty(trim($c['sibling_name'] ?? ''))) as $c) {
                $memorial->siblings()->create(['sibling_name' => trim($c['sibling_name'])]);
            }
        }

        if (array_key_exists('education', $validated)) {
            $memorial->education()->delete();
            foreach (array_filter($validated['education'] ?? [], fn ($c) => !empty(trim($c['institution_name'] ?? ''))) as $c) {
                $memorial->education()->create([
                    'institution_name' => trim($c['institution_name']),
                    'start_year' => !empty($c['start_year']) ? (int) $c['start_year'] : null,
                    'end_year' => !empty($c['end_year']) ? (int) $c['end_year'] : null,
                    'degree' => !empty($c['degree']) ? trim($c['degree']) : null,
                ]);
            }
        }
    }

    /**
     * Generate a URL slug from full name (e.g. "Miiro Rio Akram" -> "miiro-rio-akram").
     * Ensures uniqueness by appending a suffix if needed.
     */
    protected static function generateUniqueSlug(string $fullName, ?int $excludeMemorialId = null): string
    {
        $baseSlug = Str::slug($fullName);
        $slug = $baseSlug;
        $suffix = 0;

        while (
            Memorial::where('slug', $slug)
                ->when($excludeMemorialId, fn ($q) => $q->where('id', '!=', $excludeMemorialId))
                ->exists()
        ) {
            $suffix++;
            $slug = $baseSlug . '-' . $suffix;
        }

        return $slug;
    }
}
