<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteLayout;
use App\Services\SiteLayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SiteLayoutController extends Controller
{
    public function edit(string $key): View
    {
        if ($key !== SiteLayout::KEY_VISITOR_HOME) {
            abort(404);
        }

        $layout = SiteLayout::query()->firstOrCreate(
            ['key' => $key],
            [
                'version' => 1,
                'json' => json_encode(app(SiteLayoutService::class)->defaultHomeDocument(), JSON_THROW_ON_ERROR),
                'published_at' => now(),
            ]
        );

        $initialDocument = json_decode((string) $layout->json, true);
        if (! is_array($initialDocument)) {
            $initialDocument = app(SiteLayoutService::class)->defaultHomeDocument();
        }

        $layoutJson = json_encode(
            $initialDocument,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        return view('pages.settings.site-layout.edit', [
            'title' => 'Homepage layout',
            'layoutKey' => $key,
            'layout' => $layout,
            'layoutJson' => $layoutJson,
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse|JsonResponse
    {
        if ($key !== SiteLayout::KEY_VISITOR_HOME) {
            abort(404);
        }

        $request->validate([
            'json' => 'required|string',
        ]);

        try {
            $normalized = app(SiteLayoutService::class)->validateDocumentFromJson($request->input('json'));
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
            }

            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['json' => $e->getMessage()])->withInput();
        }

        $layout = SiteLayout::query()->firstOrCreate(['key' => $key]);

        $layout->update([
            'version' => $normalized['version'],
            'json' => json_encode($normalized, JSON_THROW_ON_ERROR),
            'published_at' => now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()
            ->route('settings.site-layout.edit', $key)
            ->with('success', 'Layout saved and published.');
    }
}
