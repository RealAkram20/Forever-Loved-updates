<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Models\Memorial;
use App\Models\Post;
use App\Models\Reseller;
use App\Models\Tribute;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Copies a memorial, with its photographs, onto another site.
 *
 * Built for the showcase: one memorial gets filled in properly — five chapters, a gallery,
 * a life feed, tributes from a dozen names — and then wants to exist on the platform's own
 * site as well as a reseller's, so a family deciding on either can see what a finished one
 * looks like. `memorial:showcase` writes that content from scratch but attaches no
 * photographs, and the photographs are most of why the finished one is convincing.
 *
 * Two things this deliberately does not copy, because they belong to the original and not to
 * the copy:
 *
 *  - Views and shares. They are that page's analytics; carrying them over would open the
 *    duplicate claiming an audience it has never had.
 *  - Collaborators and subscribers. Both are real people who asked for something about one
 *    specific memorial. Copying a subscriber row would sign somebody up to a second
 *    memorial's notifications without them ever asking, which is the kind of mistake you
 *    only discover by sending the email.
 *
 * Everything with an id that something else points at is remapped rather than copied
 * verbatim: chapters before the posts that sit in them, gallery categories before the media
 * filed under them, posts before the tributes that were migrated out of them. share_id is
 * regenerated rather than copied — it is unique, and it is what a shared link resolves.
 */
class DuplicateMemorial extends Command
{
    protected $signature = 'memorial:duplicate
        {source : Slug of the memorial to copy}
        {--to-platform : Attach the copy to the platform rather than to a reseller}
        {--reseller= : Reseller name to attach the copy to, matched as a prefix}
        {--slug= : Slug for the copy; defaults to the source slug with a suffix}
        {--owner= : Email of the account that should own the copy}
        {--private : Create it hidden, so it can be checked before anyone can reach it}';

    protected $description = 'Copy a memorial, its content and its photographs onto another site';

    public function handle(): int
    {
        $source = Memorial::where('slug', $this->argument('source'))->first();

        if (! $source) {
            $this->error("No memorial with slug \"{$this->argument('source')}\".");

            return self::FAILURE;
        }

        if (! $this->option('to-platform') && ! $this->option('reseller')) {
            $this->error('Say where it is going: --to-platform, or --reseller="Some Funeral Home".');

            return self::FAILURE;
        }

        $reseller = $this->option('to-platform') ? null : $this->resolveReseller();

        if (! $this->option('to-platform') && ! $reseller) {
            return self::FAILURE;
        }

        $owner = $this->resolveOwner($reseller);

        if (! $owner) {
            return self::FAILURE;
        }

        $slug = $this->resolveSlug($source);

        if (! $slug) {
            return self::FAILURE;
        }

        $this->line("Copying <info>{$source->full_name}</info> ({$source->slug}) → <info>{$slug}</info>");

        $copy = DB::transaction(fn () => $this->copyMemorial($source, $reseller, $owner, $slug));

        $this->newLine();
        $this->info('Copied.');
        $this->table(['Field', 'Value'], [
            ['Memorial id', $copy->id],
            ['Slug', $copy->slug],
            ['Site', $reseller ? "{$reseller->name} (id {$reseller->id})" : 'the platform'],
            ['Owner', "{$owner->name} <{$owner->email}>"],
            ['Public', $copy->is_public ? 'yes' : 'no — re-run without --private to publish'],
            ['Chapters', $copy->storyChapters()->count()],
            ['Posts', $copy->posts()->count()],
            ['Tributes', $copy->tributes()->count()],
            ['Gallery categories', $copy->galleryCategories()->count()],
            ['Media files', $copy->media()->count()],
            ['Profile photo', $copy->profile_photo_path ? 'copied' : 'none on the source'],
            ['Cover photo', $copy->cover_photo_path ? 'copied' : 'none on the source'],
        ]);

        $this->newLine();
        $this->line('Not copied, deliberately: views, shares, collaborators and subscribers.');

        return self::SUCCESS;
    }

    private function copyMemorial(Memorial $source, ?Reseller $reseller, User $owner, string $slug): Memorial
    {
        $attributes = collect($source->getAttributes())
            ->except([
                'id', 'created_at', 'updated_at', 'deleted_at',
                'slug', 'reseller_id', 'user_id',
                // Counters belong to the page that earned them.
                'visitor_count', 'view_count', 'share_count',
                // Photographs are moved by hand below, once the copy has an id to file
                // them under.
                'profile_photo_path', 'cover_photo_path',
            ])
            ->all();

        $copy = new Memorial($attributes);
        $copy->forceFill($attributes);
        $copy->slug = $slug;
        $copy->reseller_id = $reseller?->id;
        $copy->user_id = $owner->id;
        $copy->is_public = ! $this->option('private') && (bool) $source->is_public;
        $copy->save();

        $this->copyOwnPhotos($source, $copy);

        // Order matters from here: everything that something else points at is created first,
        // and the maps carry old id => new id so the pointers can be rewritten.
        $categoryMap = $this->copyGalleryCategories($source, $copy);
        $chapterMap = $this->copyChapters($source, $copy);
        $postMap = $this->copyPosts($source, $copy, $chapterMap);

        $this->copyTributes($source, $copy, $postMap);
        $this->copyMedia($source, $copy, $categoryMap);
        $this->copyRelations($source, $copy);

        return $copy->refresh();
    }

    /** The profile portrait and the cover, which live on the memorial row rather than in media. */
    private function copyOwnPhotos(Memorial $source, Memorial $copy): void
    {
        // The size column is named for the photograph, not for the path column — the two are
        // profile_photo_path and profile_photo_size, so deriving one from the other produces
        // `profile_photo_path_size`, which does not exist.
        $photos = [
            'profile' => ['profile_photo_path', 'profile_photo_size'],
            'cover' => ['cover_photo_path', 'cover_photo_size'],
        ];

        foreach ($photos as $folder => [$pathColumn, $sizeColumn]) {
            $path = $source->{$pathColumn};

            if (! $path) {
                continue;
            }

            $new = $this->copyFile($path, "memorials/{$copy->id}/{$folder}");

            if ($new) {
                $copy->{$pathColumn} = $new;
                $copy->{$sizeColumn} = $source->{$sizeColumn};
            }
        }

        $copy->save();
    }

    /**
     * One stored file to the copy's own folder, keeping the extension and taking a fresh
     * random name — two memorials sharing a path would mean deleting one deletes the other's
     * picture too.
     */
    private function copyFile(string $path, string $intoFolder): ?string
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            $this->warn("  missing on disk, skipped: {$path}");

            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $target = $intoFolder.'/'.Str::random(40).($extension ? '.'.$extension : '');

        $disk->copy($path, $target);

        return $target;
    }

    /** @return array<int, int> old id => new id */
    private function copyGalleryCategories(Memorial $source, Memorial $copy): array
    {
        $map = [];

        foreach ($source->galleryCategories()->orderBy('id')->get() as $category) {
            $new = $category->replicate(['memorial_id']);
            $new->memorial_id = $copy->id;
            $new->save();
            $map[$category->id] = $new->id;
        }

        return $map;
    }

    /** @return array<int, int> old id => new id */
    private function copyChapters(Memorial $source, Memorial $copy): array
    {
        $map = [];

        foreach ($source->storyChapters()->orderBy('id')->get() as $chapter) {
            $new = $chapter->replicate(['memorial_id']);
            $new->memorial_id = $copy->id;
            $new->save();
            $map[$chapter->id] = $new->id;
        }

        return $map;
    }

    /**
     * The life feed and the written tributes, which are both posts.
     *
     * share_id is left unset so the model mints a fresh one: it is unique and it is what a
     * shared link resolves, so a copied one would send anybody who followed it to the
     * original's chapter on the original's site.
     *
     * @param  array<int, int>  $chapterMap
     * @return array<int, int> old id => new id
     */
    private function copyPosts(Memorial $source, Memorial $copy, array $chapterMap): array
    {
        $map = [];

        foreach ($source->posts()->orderBy('id')->get() as $post) {
            $new = $post->replicate(['memorial_id', 'share_id', 'story_chapter_id']);
            $new->memorial_id = $copy->id;
            $new->share_id = null;
            $new->story_chapter_id = $chapterMap[$post->story_chapter_id] ?? null;
            $new->save();
            $map[$post->id] = $new->id;
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $postMap
     */
    private function copyTributes(Memorial $source, Memorial $copy, array $postMap): void
    {
        foreach ($source->tributes()->orderBy('id')->get() as $tribute) {
            $new = $tribute->replicate(['memorial_id', 'share_id', 'visitor_token', 'migrated_post_id']);
            $new->memorial_id = $copy->id;
            $new->share_id = null;
            // Identifies one anonymous browser as the author of that tribute, on that
            // memorial. Carried over it would let whoever laid the original edit the copy.
            $new->visitor_token = null;
            $new->migrated_post_id = $postMap[$tribute->migrated_post_id] ?? null;
            $new->save();
        }
    }

    /** @param  array<int, int>  $categoryMap */
    private function copyMedia(Memorial $source, Memorial $copy, array $categoryMap): void
    {
        foreach ($source->media()->orderBy('id')->get() as $media) {
            // Kept in the same sub-folder it was in, so posts, gallery and the rest stay
            // told apart on disk as well as in the database.
            $folder = basename(dirname($media->path));
            $path = $this->copyFile($media->path, "memorials/{$copy->id}/{$folder}");

            if (! $path) {
                continue;
            }

            $new = $media->replicate(['memorial_id', 'path', 'gallery_category_id']);
            $new->memorial_id = $copy->id;
            $new->path = $path;
            $new->gallery_category_id = $categoryMap[$media->gallery_category_id] ?? null;
            $new->save();
        }
    }

    /** Family, education and working life — plain child rows with nothing pointing at them. */
    private function copyRelations(Memorial $source, Memorial $copy): void
    {
        foreach (['children', 'spouses', 'parents', 'siblings', 'education', 'notableCompanies', 'coFounders'] as $relation) {
            foreach ($source->{$relation}()->orderBy('id')->get() as $row) {
                $new = $row->replicate(['memorial_id']);
                $new->memorial_id = $copy->id;
                $new->save();
            }
        }
    }

    private function resolveReseller(): ?Reseller
    {
        $name = (string) $this->option('reseller');
        $matches = Reseller::where('name', 'like', $name.'%')->get();

        if ($matches->isEmpty()) {
            $this->error("No reseller matching \"{$name}\".");
            $this->line('Available: '.Reseller::pluck('name')->implode(', '));

            return null;
        }

        // Publishing somebody's memorial onto the wrong funeral home's site is not a thing to
        // guess at, so an ambiguous name stops rather than taking the first row.
        if ($matches->count() > 1) {
            $this->error("\"{$name}\" matches {$matches->count()}: ".$matches->pluck('name')->implode(', '));

            return null;
        }

        return $matches->first();
    }

    private function resolveOwner(?Reseller $reseller): ?User
    {
        if ($email = $this->option('owner')) {
            $user = User::where('email', $email)->first();

            if (! $user) {
                $this->error("No account with email \"{$email}\".");
            }

            return $user;
        }

        // A reseller's copy belongs to that reseller's own account; the platform's belongs to
        // an administrator, since there is no tenant owner to fall back on.
        $owner = $reseller
            ? $reseller->owner
            : User::role(['super-admin', 'admin'])->orderBy('id')->first();

        if (! $owner) {
            $this->error('Could not work out who should own the copy. Pass --owner=email.');
        }

        return $owner;
    }

    private function resolveSlug(Memorial $source): ?string
    {
        $slug = $this->option('slug') ?: $source->slug.'-'.Str::random(6);
        $slug = Str::slug($slug);

        // Slugs are unique across every site, not per tenant — the copy cannot share the
        // original's even though the two sit on different domains.
        if (Memorial::where('slug', $slug)->exists()) {
            $this->error("A memorial with slug \"{$slug}\" already exists. Pass a different --slug.");

            return null;
        }

        return $slug;
    }
}
