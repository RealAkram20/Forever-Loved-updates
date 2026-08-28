<?php

namespace App\Http\Controllers;

use App\Helpers\StorageHelper;
use App\Models\Reseller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Uploads for the page builder.
 *
 * Until now the builder had no upload at all: the Image widget's only control was a text box
 * labelled "Image URL", so putting a picture on a page meant already having that picture
 * hosted somewhere. For a funeral home editing their own About page that is not a workflow,
 * it is a dead end — which is why every page shipped with the artwork the template happened
 * to include and nothing else.
 *
 * Two things this deliberately is not:
 *
 *  - **Not a media library.** One upload, one URL back, straight into the prop. A library is a
 *    bigger surface (folders, reuse, deletion, orphan sweeping) and none of it is needed to
 *    answer "put this photograph on this page".
 *  - **Not shared.** Files land under the uploader's own tenant folder. A reseller's media is
 *    theirs, and a path that could be guessed into another tenant's folder is a cross-tenant
 *    leak in a system whose whole promise is that tenants cannot see each other.
 */
class PageMediaController extends Controller
{
    /**
     * SVG is refused for the reason the Appearance uploader already refuses it: an SVG is a
     * document that can carry script, and these are rendered inside the page a reseller's
     * clients and the families they serve are looking at. Accepting one is stored XSS.
     */
    private const RULES = ['file' => 'required|image|mimes:jpg,jpeg,png,webp,gif|max:4096'];

    public function store(Request $request): JsonResponse
    {
        $request->validate(self::RULES, [
            'file.image' => 'That file is not an image.',
            'file.mimes' => 'Images must be JPG, PNG, WebP or GIF.',
            'file.max' => 'Images must be 4 MB or smaller.',
        ]);

        $path = $request->file('file')->store($this->directoryFor($request), 'public');

        return response()->json([
            'url' => StorageHelper::publicUrl($path),
            'path' => $path,
        ]);
    }

    /**
     * Where this uploader's files live.
     *
     * Keyed off the *user's own* reseller, never a value from the request: the alternative is
     * an endpoint where the folder is chosen by whoever is calling it.
     */
    private function directoryFor(Request $request): string
    {
        $reseller = $request->user()?->reseller;

        return $reseller instanceof Reseller
            ? 'page-media/reseller-'.$reseller->id
            : 'page-media/platform';
    }

    /**
     * Remove a file this uploader put there.
     *
     * Scoped to the caller's own directory, so a path pointing anywhere else — another
     * tenant's folder, a reseller's logo, anything outside page-media — is refused rather
     * than deleted.
     */
    public function destroy(Request $request): JsonResponse
    {
        $path = ltrim(str_replace('\\', '/', (string) $request->input('path')), '/');
        $directory = $this->directoryFor($request);

        if ($path === '' || ! str_starts_with($path, $directory.'/') || str_contains($path, '..')) {
            return response()->json(['message' => 'That file is not yours to remove.'], 403);
        }

        Storage::disk('public')->delete($path);

        return response()->json(['deleted' => true]);
    }
}
