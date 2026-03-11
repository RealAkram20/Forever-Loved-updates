<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Geo\GeoCity;
use App\Models\Geo\GeoCountry;
use App\Models\Geo\GeoState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LocationController extends Controller
{
    public function countries(): JsonResponse
    {
        $countries = Cache::remember('geo_countries', 86400, function () {
            return GeoCountry::orderBy('name')
                ->get(['id', 'name', 'iso2', 'iso3', 'nationality', 'phone_code', 'emoji'])
                ->toArray();
        });

        return response()->json($countries);
    }

    public function states(string $countryCode): JsonResponse
    {
        $countryCode = strtoupper($countryCode);

        $cacheKey = "geo_states_{$countryCode}";
        $data = Cache::remember($cacheKey, 86400, function () use ($countryCode) {
            $states = GeoState::where('country_code', $countryCode)
                ->orderBy('name')
                ->get(['id', 'name', 'country_code', 'type']);

            $typeLabel = $this->resolveTypeLabel($states->pluck('type')->filter()->toArray());

            return [
                'type_label' => $typeLabel,
                'states'     => $states->toArray(),
            ];
        });

        return response()->json($data);
    }

    public function cities(int $stateId, Request $request): JsonResponse
    {
        $query = trim($request->input('q', ''));
        $limit = min((int) $request->input('limit', 50), 100);
        $countryCode = strtoupper(trim($request->input('country_code', '')));

        $builder = GeoCity::where('state_id', $stateId)->orderBy('name');

        if ($query !== '') {
            $builder->where('name', 'like', "{$query}%");
        }

        $cities = $builder->limit($limit)->get(['id', 'name', 'state_id', 'country_code']);

        if ($cities->isEmpty() && $countryCode !== '') {
            $fallback = GeoCity::where('country_code', $countryCode)->orderBy('name');
            if ($query !== '') {
                $fallback->where('name', 'like', "{$query}%");
            }
            $cities = $fallback->limit($limit)->get(['id', 'name', 'state_id', 'country_code']);
        }

        return response()->json(['cities' => $cities->toArray()]);
    }

    private function resolveTypeLabel(array $types): string
    {
        if (empty($types)) {
            return 'State / Region';
        }

        $counts = array_count_values(array_map('strtolower', $types));
        arsort($counts);
        $dominant = array_key_first($counts);

        $labels = [
            'state'        => 'State',
            'province'     => 'Province',
            'district'     => 'District',
            'region'       => 'Region',
            'department'   => 'Department',
            'municipality' => 'Municipality',
            'canton'       => 'Canton',
            'prefecture'   => 'Prefecture',
            'emirate'      => 'Emirate',
            'parish'       => 'Parish',
            'county'       => 'County',
        ];

        return $labels[$dominant] ?? ucfirst($dominant);
    }
}
