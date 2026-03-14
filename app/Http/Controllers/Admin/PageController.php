<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function index()
    {
        $pages = Page::orderBy('title')->get();

        return view('pages.settings.pages.index', [
            'title' => 'Manage Pages',
            'pages' => $pages,
        ]);
    }

    public function edit(string $slug)
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        return view('pages.settings.pages.edit', [
            'title' => "Edit {$page->title}",
            'page' => $page,
        ]);
    }

    public function update(Request $request, string $slug)
    {
        $page = Page::where('slug', $slug)->firstOrFail();

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'meta_description' => 'nullable|string|max:500',
            'is_published' => 'boolean',
        ]);

        $page->update([
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'meta_description' => $request->input('meta_description'),
            'is_published' => $request->boolean('is_published', true),
        ]);

        Page::clearSlugCache($slug);

        return redirect()->route('settings.pages.edit', $slug)
            ->with('success', "{$page->title} updated successfully.");
    }
}
