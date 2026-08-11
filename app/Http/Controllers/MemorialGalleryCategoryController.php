<?php

namespace App\Http\Controllers;

use App\Helpers\PlanLimitsHelper;
use App\Models\GalleryCategory;
use App\Models\Memorial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The family's own divisions of the gallery — "School Life", "The Farm", "Childhood".
 *
 * Writable by whoever may edit the memorial: its owner, a platform admin, reseller staff of
 * the same tenant, and an accepted collaborator with the editor role. That is the same
 * audience as every other piece of memorial curation, resolved through the one helper that
 * knows the answer, Memorial::canBeEditedBy().
 *
 * There is no index action. The gallery is server-rendered, so the page already has the
 * list by the time anything here could be asked for it.
 */
class MemorialGalleryCategoryController extends Controller
{
    public function store(Request $request, string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (! $this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Sold as "Photo and Video Albums". Only creation is gated: a memorial that drops to
        // a plan without albums keeps the ones it has, and its visitors keep browsing them,
        // rather than having the family's filing quietly undone by a lapsed subscription.
        if (! PlanLimitsHelper::canUseAlbums($memorial)) {
            return response()->json([
                'error' => 'Photo and video albums are not included in this memorial\'s current plan.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        $name = trim($validated['name']);

        if ($name === '') {
            return response()->json(['error' => 'Give the category a name.'], 422);
        }

        if ($memorial->galleryCategories()->count() >= GalleryCategory::MAX_PER_MEMORIAL) {
            return response()->json([
                'error' => 'You can have up to '.GalleryCategory::MAX_PER_MEMORIAL.' categories.',
            ], 422);
        }

        if ($this->nameTaken($memorial, $name)) {
            return response()->json(['error' => 'There is already a category called that.'], 422);
        }

        $category = $memorial->galleryCategories()->create([
            'name' => $name,
            // New categories land at the end rather than the top, so adding one never
            // rearranges the row a visitor was already reading.
            'sort_order' => ((int) $memorial->galleryCategories()->max('sort_order')) + 1,
        ]);

        return response()->json([
            'success' => true,
            'category' => $this->format($category),
        ], 201);
    }

    public function update(Request $request, string $slug, int $categoryId): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (! $this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $category = $memorial->galleryCategories()->findOrFail($categoryId);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:60'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $changes = [];

        if (isset($validated['name'])) {
            $name = trim($validated['name']);

            if ($name === '') {
                return response()->json(['error' => 'Give the category a name.'], 422);
            }

            if ($this->nameTaken($memorial, $name, $category->id)) {
                return response()->json(['error' => 'There is already a category called that.'], 422);
            }

            $changes['name'] = $name;
        }

        if (isset($validated['sort_order'])) {
            $changes['sort_order'] = $validated['sort_order'];
        }

        if ($changes) {
            $category->update($changes);
        }

        return response()->json([
            'success' => true,
            'category' => $this->format($category),
        ]);
    }

    /**
     * Deleting a category empties a shelf, it does not burn what was on it: the foreign key
     * is nullOnDelete, so its photos fall back to unfiled and keep their files.
     */
    public function destroy(string $slug, int $categoryId): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        if (! $this->canEdit($memorial)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $category = $memorial->galleryCategories()->findOrFail($categoryId);
        $released = $category->media()->count();
        $category->delete();

        return response()->json([
            'success' => true,
            'unfiled_count' => $released,
        ]);
    }

    /**
     * Case-insensitive, because "School life" and "School Life" are the same shelf to
     * everyone except the database.
     */
    private function nameTaken(Memorial $memorial, string $name, ?int $ignoreId = null): bool
    {
        return $memorial->galleryCategories()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }

    private function format(GalleryCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'sort_order' => $category->sort_order,
        ];
    }

    private function canEdit(Memorial $memorial): bool
    {
        return $memorial->canBeEditedBy(auth()->user());
    }
}
