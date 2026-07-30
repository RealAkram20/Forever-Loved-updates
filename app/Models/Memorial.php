<?php

namespace App\Models;

use App\Helpers\StorageHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Memorial extends Model
{
    use HasFactory;

    /**
     * A memorial belongs to whatever tenant its owner belongs to.
     *
     * Enforced here rather than at each call site because two of the three call sites
     * missed it: Reseller\DashboardController::storeMemorial() stamped reseller_id, while
     * MemorialController::store() and MemorialSignupController never did. A reseller's own
     * client creating a memorial through the normal flow therefore produced an untenanted
     * record — absent from the reseller's list and analytics, uncounted against their tier
     * allowance and storage cap, unreachable on their own subdomain, and handed out with a
     * platform URL instead of their white-labeled one.
     *
     * A creating hook rather than a fourth explicit assignment, so the next creation path
     * cannot reintroduce the same gap. Anything that sets reseller_id itself wins.
     */
    protected static function booted(): void
    {
        static::creating(function (self $memorial) {
            // "Said nothing about it" — not "said null". A caller passing an explicit null
            // means a platform-owned memorial and is left alone: a direct user with existing
            // memorials can later be attached to a reseller, and those older memorials stay
            // the platform's. Treating the two cases alike would silently reassign them.
            if (array_key_exists('reseller_id', $memorial->getAttributes()) || $memorial->user_id === null) {
                return;
            }

            // value() rather than loading the relation: this runs on every insert, and the
            // owner is frequently not loaded at this point.
            $resellerId = User::whereKey($memorial->user_id)->value('reseller_id');

            if ($resellerId !== null) {
                $memorial->reseller_id = $resellerId;
                // Left alone once set, so a rollover can still be reversed by restore().
                $memorial->original_reseller_id ??= $resellerId;
            }
        });
    }

    // Dashboard routes (/memorials/{memorial}/...) bind by slug so URLs read
    // as the person's name instead of a numeric id. Public /{slug} routes
    // resolve slugs explicitly and are unaffected.
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $fillable = [
        'user_id',
        'reseller_id',
        'original_reseller_id',
        'slug',
        'title',
        'full_name',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'relationship',
        'short_description',
        'nationality',
        'primary_profession',
        'notable_title',
        'more_details',
        'major_achievements',
        'known_for',
        'active_year_start',
        'active_year_end',
        'date_of_birth',
        'date_of_passing',
        'birth_year',
        'birth_month',
        'birth_day',
        'birth_city',
        'birth_state',
        'birth_country',
        'death_year',
        'death_month',
        'death_day',
        'death_city',
        'death_state',
        'death_country',
        'biography',
        'theme',
        'plan',
        'subscription_plan_id',
        'user_subscription_id',
        'completion_status',
        'background_music',
        'profile_photo_path',
        'profile_photo_size',
        'is_public',
        'status',
        'visitor_count',
        'expires_at',
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DEACTIVATED = 'deactivated';

    public const STATUS_SUSPENDED = 'suspended';

    public const COMPLETION_PENDING = 'pending';

    public const COMPLETION_COMPLETED = 'completed';

    public const PLAN_FREE = 'free';

    public const PLAN_PAID = 'paid';

    /** Theme display names for design themes (extend as needed). */
    public static function getThemeDisplayName(?string $theme): string
    {
        return match ($theme) {
            'free' => 'Classic',
            'premium' => 'Premium',
            'classic' => 'Classic',
            'modern' => 'Modern',
            'garden' => 'Garden',
            default => ucfirst($theme ?? 'Classic'),
        };
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'date_of_passing' => 'date',
            'is_public' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Fold the "Other" relationship option and its free-text companion field into the
     * single value stored on the memorial. Picking "Other" without typing anything
     * stores nothing rather than the useless literal "Other".
     */
    public static function resolveRelationship(?string $selected, ?string $custom = null): ?string
    {
        $selected = trim((string) $selected);
        $custom = trim((string) $custom);

        if ($selected === 'Other') {
            return $custom !== '' ? $custom : null;
        }

        return $selected !== '' ? $selected : null;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function userSubscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'user_subscription_id');
    }

    /**
     * Get the public URL for the memorial's profile photo.
     */
    public function getProfilePhotoUrlAttribute(): ?string
    {
        return StorageHelper::publicUrl($this->profile_photo_path);
    }

    /**
     * Calculate profile completion percentage (0–100) based on filled fields.
     */
    public function getCompletionPercentageAttribute(): int
    {
        $sectionScores = [];

        // Section 1: Identity (first_name, last_name required; nationality, profession, gender, description optional)
        $identityChecks = array_filter([
            ! empty(trim($this->first_name ?? '')),
            ! empty(trim($this->last_name ?? '')),
            ! empty(trim($this->nationality ?? '')),
            ! empty(trim($this->primary_profession ?? '')) || ! empty(trim($this->short_description ?? '')),
        ]);
        $sectionScores[] = count($identityChecks) / 4;

        // Section 2: Biography Summary (known_for, achievements, companies)
        $bioChecks = array_filter([
            ! empty(trim($this->known_for ?? '')) || ! empty(trim($this->major_achievements ?? '')),
            $this->relationLoaded('notableCompanies') ? $this->notableCompanies->isNotEmpty() : $this->notableCompanies()->exists(),
        ]);
        $sectionScores[] = count($bioChecks) / 2;

        // Section 3: Birth (date + place)
        $birthChecks = array_filter([
            $this->date_of_birth !== null,
            ! empty(trim($this->birth_city ?? '')) || ! empty(trim($this->birth_country ?? '')),
        ]);
        $sectionScores[] = count($birthChecks) / 2;

        // Section 4: Death (date + place)
        $deathChecks = array_filter([
            $this->date_of_passing !== null,
            ! empty(trim($this->death_city ?? '')) || ! empty(trim($this->death_country ?? '')),
        ]);
        $sectionScores[] = count($deathChecks) / 2;

        // Section 5: Family (any family member added)
        $hasFamily = ($this->relationLoaded('spouses') ? $this->spouses->isNotEmpty() : $this->spouses()->exists())
            || ($this->relationLoaded('children') ? $this->children->isNotEmpty() : $this->children()->exists())
            || ($this->relationLoaded('parents') ? $this->parents->isNotEmpty() : $this->parents()->exists())
            || ($this->relationLoaded('siblings') ? $this->siblings->isNotEmpty() : $this->siblings()->exists());
        $sectionScores[] = $hasFamily ? 1.0 : 0.0;

        // Section 6: Education (any education entry added)
        $hasEducation = $this->relationLoaded('education') ? $this->education->isNotEmpty() : $this->education()->exists();
        $sectionScores[] = $hasEducation ? 1.0 : 0.0;

        $total = count($sectionScores);
        $sum = array_sum($sectionScores);

        return $total > 0 ? (int) round(($sum / $total) * 100) : 0;
    }

    public function tributes(): HasMany
    {
        return $this->hasMany(Tribute::class);
    }

    public function views()
    {
        return $this->hasMany(MemorialView::class);
    }

    public function shares()
    {
        return $this->hasMany(MemorialShare::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(MemorialSubscription::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    /**
     * The address this memorial should be shared at.
     *
     * For a reseller-owned memorial that is their verified custom domain, or failing that
     * their subdomain — never the platform's apex. The white-label routes existed and
     * worked, but nothing in the product ever generated one, so every share link, canonical
     * URL and notification handed families the platform's brand instead of the funeral
     * home's. A subdomain nobody is ever given is not a white-label.
     */
    public function publicUrl(): string
    {
        $reseller = $this->reseller;

        if (! $reseller) {
            return route('memorial.public', ['slug' => $this->slug], true);
        }

        // Reseller::publicUrlForSlug() picks the verified custom domain over the subdomain,
        // and degrades to the path-based /r/{slug} route in environments that cannot serve
        // either. route() on the host-based routes would happily generate an address for a
        // host this deployment never answers on.
        return $reseller->publicUrlForSlug($this->slug);
    }

    /**
     * Bytes this memorial accounts for: its gallery media plus the profile photo, which
     * has no media row of its own.
     *
     * This is *referenced* size, not disk size. Replacing a profile photo currently leaves
     * the old file behind, so actual disk use can exceed this — an orphan-cleanup job would
     * be needed before this number could be treated as a billing input rather than a guide.
     */
    public function storageBytes(): int
    {
        return (int) $this->media()->sum('size') + (int) $this->profile_photo_size;
    }

    public function storyChapters(): HasMany
    {
        return $this->hasMany(StoryChapter::class)->orderBy('sort_order');
    }

    public function notableCompanies(): HasMany
    {
        return $this->hasMany(MemorialNotableCompany::class)->orderBy('sort_order');
    }

    public function coFounders(): HasMany
    {
        return $this->hasMany(MemorialCoFounder::class)->orderBy('sort_order');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MemorialChild::class);
    }

    public function spouses(): HasMany
    {
        return $this->hasMany(MemorialSpouse::class);
    }

    public function parents(): HasMany
    {
        return $this->hasMany(MemorialParent::class);
    }

    public function siblings(): HasMany
    {
        return $this->hasMany(MemorialSibling::class);
    }

    public function education(): HasMany
    {
        return $this->hasMany(MemorialEducation::class);
    }

    public function collaborators(): HasMany
    {
        return $this->hasMany(MemorialCollaborator::class);
    }

    /**
     * Check if a user can edit this memorial (owner, collaborator with editor role, admin, or
     * reseller staff managing their own tenant's memorial).
     */
    public function canBeEditedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->user_id === $user->id) {
            return true;
        }

        if ($user->hasRole(['admin', 'super-admin'])) {
            return true;
        }

        if ($this->isManagedByResellerStaff($user)) {
            return true;
        }

        return $this->collaborators()
            ->where('user_id', $user->id)
            ->where('role', 'editor')
            ->whereNotNull('accepted_at')
            ->exists();
    }

    /**
     * Reseller staff manage every memorial under their own tenant, regardless of which
     * end-client user actually owns it. Shared by canBeEditedBy() and MemorialPolicy so the
     * rule lives in exactly one place.
     */
    public function isManagedByResellerStaff(User $user): bool
    {
        return $this->reseller_id !== null
            && $user->reseller_id === $this->reseller_id
            && $user->hasRole('reseller');
    }

    /**
     * Who may manage this memorial's *team* — invite, re-role, or remove collaborators.
     * Deliberately narrower than canBeEditedBy(): the owner, a platform admin, and reseller
     * staff for their own tenant can manage the team, but a plain editor collaborator cannot,
     * so an invited contributor can help run the memorial without silently building their own
     * roster of further editors.
     */
    public function canManageTeam(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->user_id === $user->id
            || $user->hasRole(['admin', 'super-admin'])
            || $this->isManagedByResellerStaff($user);
    }

    public function galleryMedia()
    {
        $usedInPosts = DB::table('post_media')->pluck('media_id')->toArray();

        return $this->media()
            ->whereIn('type', ['photo', 'video'])
            ->when(! empty($usedInPosts), fn ($q) => $q->whereNotIn('id', $usedInPosts));
    }

    /**
     * Get first_name, falling back to parsing full_name for legacy records.
     */
    public function getFirstNameAttribute(?string $value): string
    {
        if ($value) {
            return $value;
        }
        $parts = $this->parseFullName();

        return $parts['first'] ?? '';
    }

    /**
     * Get middle_name, falling back to parsing full_name for legacy records.
     */
    public function getMiddleNameAttribute(?string $value): ?string
    {
        if ($value !== null) {
            return $value;
        }
        $parts = $this->parseFullName();

        return $parts['middle'] ?? null;
    }

    /**
     * Get last_name, falling back to parsing full_name for legacy records.
     */
    public function getLastNameAttribute(?string $value): string
    {
        if ($value) {
            return $value;
        }
        $parts = $this->parseFullName();

        return $parts['last'] ?? '';
    }

    protected function parseFullName(): array
    {
        $fullName = $this->attributes['full_name'] ?? '';
        $parts = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($parts)) {
            return ['first' => '', 'middle' => null, 'last' => ''];
        }
        if (count($parts) === 1) {
            return ['first' => $parts[0], 'middle' => null, 'last' => ''];
        }

        return [
            'first' => $parts[0],
            'middle' => count($parts) > 2 ? implode(' ', array_slice($parts, 1, -1)) : null,
            'last' => $parts[count($parts) - 1],
        ];
    }

    public function getBirthYearAttribute(?int $value): ?int
    {
        return $value ?? $this->date_of_birth?->year;
    }

    public function getBirthMonthAttribute(?int $value): ?int
    {
        return $value ?? $this->date_of_birth?->month;
    }

    public function getBirthDayAttribute(?int $value): ?int
    {
        return $value ?? $this->date_of_birth?->day;
    }

    public function getDeathYearAttribute(?int $value): ?int
    {
        return $value ?? $this->date_of_passing?->year;
    }

    public function getDeathMonthAttribute(?int $value): ?int
    {
        return $value ?? $this->date_of_passing?->month;
    }

    public function getDeathDayAttribute(?int $value): ?int
    {
        return $value ?? $this->date_of_passing?->day;
    }

    /**
     * Get age at death (computed from date_of_birth and date_of_passing).
     */
    public function getAgeAtDeathAttribute(): ?int
    {
        $birth = $this->date_of_birth;
        $death = $this->date_of_passing;
        if (! $birth || ! $death) {
            return null;
        }

        return (int) abs($birth->diffInYears($death));
    }

    /**
     * Get birth-death year range (e.g. "1990-2026").
     */
    public function getBirthDeathYearsAttribute(): ?string
    {
        $birth = $this->birth_year ?? $this->date_of_birth?->year;
        $death = $this->death_year ?? $this->date_of_passing?->year;
        if (! $birth && ! $death) {
            return null;
        }

        return trim(($birth ?? '').'-'.($death ?? ''));
    }

    /**
     * Get unique contributor users (those who left tributes).
     */
    public function contributors()
    {
        return $this->tributes()
            ->whereNotNull('user_id')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->values();
    }
}
