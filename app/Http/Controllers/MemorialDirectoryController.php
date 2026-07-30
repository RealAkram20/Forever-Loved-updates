<?php

namespace App\Http\Controllers;

use App\Helpers\SiteShareMetaHelper;
use App\Helpers\ThemeSetting;
use App\Models\Memorial;
use App\Models\Page;
use App\Support\StandardPages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemorialDirectoryController extends Controller
{
    /**
     * Public directory page — grid/list view with filters.
     * Only shows public, active memorials.
     */
    public function index(Request $request): View|JsonResponse
    {
        if ($request->wantsJson() || $request->ajax()) {
            return $this->directoryResults($request);
        }

        $layoutPage = StandardPages::resolve(Page::SLUG_FIND_MEMORIAL, ThemeSetting::siteTenantId());
        if ($layoutPage && $layoutPage->hasLayout()) {
            return view('pages.memorial-directory.page-layout', [
                'title' => $layoutPage->title ?: 'Find Memorial',
                'page' => $layoutPage,
                'widgets' => $layoutPage->layout['widgets'],
                'layoutContext' => [],
                'shareMeta' => SiteShareMetaHelper::forNamedRoute(
                    'Find Memorial',
                    'memorial.directory',
                    [],
                    'Search public memorials by name, location, and more. Honor and discover lives remembered on our platform.'
                ),
            ]);
        }

        return view('pages.memorial-directory.index', [
            'title' => 'Find Memorial',
            'shareMeta' => SiteShareMetaHelper::forNamedRoute(
                'Find Memorial',
                'memorial.directory',
                [],
                'Search public memorials by name, location, and more. Honor and discover lives remembered on our platform.'
            ),
        ]);
    }

    /**
     * AJAX directory results with filters.
     */
    public function directoryResults(Request $request): JsonResponse
    {
        // siteTenantId(), not tenant(): which memorials a directory lists follows whose site
        // it is, never who is looking at it. Keyed off the viewer, a reseller's own staff
        // would see their memorials in the platform's directory.
        $tenantId = ThemeSetting::siteTenantId();

        $query = Memorial::query()
            ->where('is_public', true)
            // Each directory lists the memorials of the site it is served on, and only those.
            //
            // On the platform's host that means ours alone: listing a funeral home's client
            // under our brand — and driving traffic to us rather than to them — is the
            // opposite of white-labelling, which is why this was hardcoded to NULL. But the
            // hardcoding also meant a reseller's own directory would have listed nothing at
            // all, since none of their memorials are platform-owned.
            ->when($tenantId,
                fn ($q) => $q->where('reseller_id', $tenantId),
                fn ($q) => $q->whereNull('reseller_id'),
            )
            ->where('status', Memorial::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));

        $search = trim($request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('primary_profession', 'like', "%{$search}%")
                    ->orWhere('nationality', 'like', "%{$search}%")
                    ->orWhere('birth_city', 'like', "%{$search}%")
                    ->orWhere('death_city', 'like', "%{$search}%")
                    ->orWhere('known_for', 'like', "%{$search}%");
            });
        }

        $gender = $request->input('gender');
        if (in_array($gender, ['male', 'female'])) {
            $query->where('gender', $gender);
        }

        $ageMin = $request->integer('age_min', 0);
        $ageMax = $request->integer('age_max', 120);
        if ($ageMin > 0 || $ageMax < 120) {
            $query->whereNotNull('date_of_birth')
                ->whereNotNull('date_of_passing')
                ->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, date_of_passing) BETWEEN ? AND ?', [$ageMin, $ageMax]);
        }

        $birthYearFrom = $request->integer('birth_year_from', 0);
        $birthYearTo = $request->integer('birth_year_to', 0);
        if ($birthYearFrom > 0) {
            $query->where(function ($q) use ($birthYearFrom) {
                $q->where('birth_year', '>=', $birthYearFrom)
                    ->orWhere(function ($b) use ($birthYearFrom) {
                        $b->whereNotNull('date_of_birth')->whereYear('date_of_birth', '>=', $birthYearFrom);
                    });
            });
        }
        if ($birthYearTo > 0) {
            $query->where(function ($q) use ($birthYearTo) {
                $q->where('birth_year', '<=', $birthYearTo)
                    ->orWhere(function ($b) use ($birthYearTo) {
                        $b->whereNotNull('date_of_birth')->whereYear('date_of_birth', '<=', $birthYearTo);
                    });
            });
        }

        $deathYearFrom = $request->integer('death_year_from', 0);
        $deathYearTo = $request->integer('death_year_to', 0);
        if ($deathYearFrom > 0) {
            $query->where(function ($q) use ($deathYearFrom) {
                $q->where('death_year', '>=', $deathYearFrom)
                    ->orWhere(function ($b) use ($deathYearFrom) {
                        $b->whereNotNull('date_of_passing')->whereYear('date_of_passing', '>=', $deathYearFrom);
                    });
            });
        }
        if ($deathYearTo > 0) {
            $query->where(function ($q) use ($deathYearTo) {
                $q->where('death_year', '<=', $deathYearTo)
                    ->orWhere(function ($b) use ($deathYearTo) {
                        $b->whereNotNull('date_of_passing')->whereYear('date_of_passing', '<=', $deathYearTo);
                    });
            });
        }

        $perPage = (int) $request->input('per_page', 12);
        $perPage = min(max($perPage, 6), 48);
        $memorials = $query
            ->withCount('tributes')
            ->select(['id', 'slug', 'full_name', 'primary_profession', 'profile_photo_path', 'gender', 'visitor_count', 'date_of_birth', 'date_of_passing', 'birth_year', 'death_year'])
            ->orderBy('full_name')
            ->paginate($perPage);

        $items = $memorials->getCollection()->map(fn (Memorial $m) => [
            'slug' => $m->slug,
            'name' => $m->full_name,
            'profession' => $m->primary_profession,
            'photo' => $m->profile_photo_url,
            'gender' => $m->gender,
            'years' => $m->birth_death_years,
            'age_at_death' => $m->age_at_death,
            'visitor_count' => $m->visitor_count ?? 0,
            'tributes_count' => $m->tributes_count ?? 0,
            'url' => $m->publicUrl(),
        ]);

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $memorials->currentPage(),
                'last_page' => $memorials->lastPage(),
                'per_page' => $memorials->perPage(),
                'total' => $memorials->total(),
            ],
        ]);
    }
}
