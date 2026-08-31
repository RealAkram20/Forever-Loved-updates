<?php

namespace App\Console\Commands;

use App\Models\GalleryCategory;
use App\Models\Media;
use App\Models\Memorial;
use App\Models\MemorialChild;
use App\Models\MemorialCoFounder;
use App\Models\MemorialEducation;
use App\Models\MemorialNotableCompany;
use App\Models\MemorialParent;
use App\Models\MemorialSibling;
use App\Models\MemorialSpouse;
use App\Models\Post;
use App\Models\Reseller;
use App\Models\StoryChapter;
use App\Models\Tribute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Copy a memorial, with its content, onto another reseller's site.
 *
 *   php artisan memorials:duplicate wilson-ssekandi-mubiru --to=a-plus --dry-run
 *   php artisan memorials:duplicate wilson-ssekandi-mubiru --to=a-plus
 *
 * Built because there is no other way to do it: nothing in the admin moves or copies a
 * memorial between tenants, and the alternative is retyping a life by hand.
 *
 * The tenant is named, never numbered. Live and local ids do not correspond — a reseller that
 * is 1 here is 4 there — so an id typed from a local lookup would silently land somebody's
 * memorial on the wrong funeral home's site. Slug first, then exact name.
 *
 * Everything happens in one transaction. A memorial half-copied is worse than none: the page
 * would be live, public and missing the parts that make it a memorial.
 */
class MemorialsDuplicateCommand extends Command
{
    protected $signature = 'memorials:duplicate
        {source : Slug or id of the memorial to copy}
        {--to= : Slug or name of the reseller to copy it to}
        {--slug= : Slug for the copy (default: the source slug suffixed with the reseller)}
        {--dry-run : Report what would be copied and change nothing}';

    protected $description = 'Copy a memorial and its content onto another reseller\'s site';

    /**
     * What is deliberately left behind, and why. Printed on every run, because a copy that
     * silently omits things is a copy nobody can trust.
     *
     * @var array<string, string>
     */
    private const EXCLUDED = [
        'subscriptions' => 'real people asked to hear about *that* memorial; copying them would post updates about a page they never followed',
        'views and shares' => 'analytics — copying them would invent traffic this page has not had',
        'collaborators' => 'an edit grant, and these are people from the source tenant; it would hand them rights on another funeral home\'s site',
    ];

    public function handle(): int
    {
        $source = $this->resolveMemorial((string) $this->argument('source'));

        if (! $source) {
            $this->error('No memorial matches "'.$this->argument('source').'".');

            return self::FAILURE;
        }

        $target = $this->resolveReseller((string) $this->option('to'));

        if (! $target) {
            $this->error('No reseller matches "'.$this->option('to').'". Pass a slug or an exact name.');

            return self::FAILURE;
        }

        if ($source->reseller_id === $target->id) {
            $this->error("\"{$source->full_name}\" already belongs to {$target->name}.");

            return self::FAILURE;
        }

        $slug = $this->uniqueSlug((string) ($this->option('slug') ?: $source->slug.'-'.$target->slug));

        // The owner becomes the receiving tenant's owner. Left alone, a memorial on A-Plus's
        // site would be owned by a family member who signed up with somebody else — they would
        // see it in their dashboard, and the tenant hosting it could not edit it.
        $owner = $target->owner_user_id;

        if (! $owner) {
            $this->error("{$target->name} has no owner user, so the copy would have nobody to belong to.");

            return self::FAILURE;
        }

        $counts = $this->sourceCounts($source);

        $this->line('');
        $this->line("  <options=bold>{$source->full_name}</>  →  <options=bold>{$target->name}</>");
        $this->line("  /{$source->slug}  →  /{$slug}");
        $this->line('');

        $this->table(
            ['Copies', 'Rows'],
            collect($counts)->map(fn ($n, $k) => [$k, $n])->values()->all()
        );

        foreach (self::EXCLUDED as $what => $why) {
            $this->line("  <fg=yellow>left behind</> {$what} — {$why}");
        }

        $this->line('');

        if ($this->option('dry-run')) {
            $this->info('Dry run. Nothing was written.');

            return self::SUCCESS;
        }

        $copyId = DB::transaction(fn () => $this->copy($source, $target, $slug, $owner));

        $this->info("Copied. New memorial id {$copyId} at /{$slug}.");
        $this->line('Its images point at the same stored files as the original, which are never');
        $this->line('deleted when a memorial is, so neither copy can take the other\'s pictures away.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function sourceCounts(Memorial $source): array
    {
        return [
            'gallery categories' => $source->galleryCategories()->count(),
            'story chapters' => $source->storyChapters()->count(),
            'media' => $source->media()->count(),
            'posts (stories and comments)' => $source->posts()->count(),
            'tributes' => $source->tributes()->count(),
            'family' => $source->children()->count() + $source->spouses()->count()
                + $source->parents()->count() + $source->siblings()->count(),
            'education' => $source->education()->count(),
            'companies and co-founders' => $source->notableCompanies()->count() + $source->coFounders()->count(),
        ];
    }

    private function copy(Memorial $source, Reseller $target, string $slug, int $owner): int
    {
        $copy = $source->replicate([
            // Counted, not copied: the duplicate has been seen by nobody.
            'visitor_count',
            // Subscription state belongs to whoever paid for the original. Carried over, the
            // copy would ride somebody else's plan and expire with it.
            'subscription_plan_id',
            'user_subscription_id',
            'expires_at',
        ]);

        $copy->slug = $slug;
        $copy->reseller_id = $target->id;
        $copy->original_reseller_id = $target->id;
        $copy->user_id = $owner;
        $copy->visitor_count = 0;
        $copy->save();

        // Memorial::created() seeds three starter gallery categories on every new memorial.
        // That is right for a memorial somebody is starting and wrong for a copy, which ends
        // up with Childhood, Family & Friends and Milestones *plus* whatever the original
        // had — seven categories became ten on the first run of this. Cleared before the
        // source's are copied, so the copy mirrors the original rather than adding to it.
        if ($source->galleryCategories()->exists()) {
            $copy->galleryCategories()->delete();
        }

        // Categories and chapters first: media and posts point at them by id, and those ids
        // have to be the *copy's* or the new page would file its photographs under the
        // original's albums.
        $categoryMap = $this->copyChildren($source->galleryCategories, $copy, GalleryCategory::class);
        $chapterMap = $this->copyChildren($source->storyChapters, $copy, StoryChapter::class);

        foreach ($source->media as $row) {
            $new = $row->replicate();
            $new->memorial_id = $copy->id;
            $new->user_id = $owner;
            $new->gallery_category_id = $categoryMap[$row->gallery_category_id] ?? null;
            $new->save();
        }

        // Posts keep their author. These are somebody's words about the person who died, and
        // reattributing them to the tenant's owner would put the funeral home's name on a
        // family's story.
        $postMap = [];

        foreach ($source->posts as $row) {
            $new = $row->replicate(['share_id']);
            $new->memorial_id = $copy->id;
            $new->story_chapter_id = $chapterMap[$row->story_chapter_id] ?? null;
            $new->share_id = null;
            $new->save();
            $postMap[$row->id] = $new->id;
        }

        foreach ($source->tributes as $row) {
            $new = $row->replicate(['share_id']);
            $new->memorial_id = $copy->id;
            $new->share_id = null;
            // Points at a post that was migrated into a tribute. Remapped, or dropped when the
            // post it names is not part of this copy.
            $new->migrated_post_id = $postMap[$row->migrated_post_id] ?? null;
            $new->save();
        }

        foreach ([
            MemorialChild::class => $source->children,
            MemorialSpouse::class => $source->spouses,
            MemorialParent::class => $source->parents,
            MemorialSibling::class => $source->siblings,
            MemorialEducation::class => $source->education,
            MemorialNotableCompany::class => $source->notableCompanies,
            MemorialCoFounder::class => $source->coFounders,
        ] as $class => $rows) {
            $this->copyChildren($rows, $copy, $class);
        }

        return $copy->id;
    }

    /**
     * Replicate a set of rows onto the copy, returning old id => new id.
     *
     * @param  iterable<int, \Illuminate\Database\Eloquent\Model>  $rows
     * @return array<int, int>
     */
    private function copyChildren(iterable $rows, Memorial $copy, string $class): array
    {
        $map = [];

        foreach ($rows as $row) {
            $new = $row->replicate();
            $new->memorial_id = $copy->id;
            $new->save();
            $map[$row->id] = $new->id;
        }

        return $map;
    }

    private function resolveMemorial(string $value): ?Memorial
    {
        return Memorial::where('slug', $value)
            ->when(ctype_digit($value), fn ($q) => $q->orWhere('id', (int) $value))
            ->first();
    }

    /** Slug first, then an exact name. Never an id — see the class docblock. */
    private function resolveReseller(string $value): ?Reseller
    {
        if ($value === '') {
            return null;
        }

        return Reseller::where('slug', $value)->first()
            ?? Reseller::where('name', $value)->first();
    }

    /** Memorial slugs are unique across the whole install, not per tenant. */
    private function uniqueSlug(string $base): string
    {
        $base = Str::slug($base) ?: 'memorial';
        $slug = $base;
        $n = 2;

        while (Memorial::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
