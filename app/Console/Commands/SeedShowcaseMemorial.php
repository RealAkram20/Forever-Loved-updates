<?php

namespace App\Console\Commands;

use App\Models\GalleryCategory;
use App\Models\Memorial;
use App\Models\Post;
use App\Models\Reseller;
use App\Models\StoryChapter;
use App\Models\SubscriptionPlan;
use App\Models\Tribute;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The showcase memorial a reseller shows a family who is deciding.
 *
 * Written as a command rather than a seeder because it has to run once, by hand, on a live
 * container whose database already has real memorials in it — `db:seed` invites somebody to
 * run the whole seeder set against production. It is safe to run twice: the memorial is
 * matched on its slug and every child row is rebuilt, so a second run leaves the same
 * memorial rather than a second copy of it.
 *
 * The reseller is resolved by name, not id. Locally "Uganda Funeral Services Ltd" is row 1
 * of faker data; on live it is whatever id it happens to have, and hardcoding 1 would have
 * attached this to a different company's site.
 */
class SeedShowcaseMemorial extends Command
{
    protected $signature = 'memorial:showcase
        {--reseller=Uganda Funeral Services : Reseller name to attach to, matched as a prefix}
        {--owner= : Email of the account that should own it; defaults to the reseller owner}
        {--slug=wilson-ssekandi-mubiru : Memorial slug}
        {--private : Create it hidden, so it can be checked before anyone can reach it}';

    protected $description = 'Create or refresh the showcase memorial for a reseller';

    public function handle(): int
    {
        $reseller = $this->resolveReseller();

        if (! $reseller) {
            return self::FAILURE;
        }

        $owner = $this->resolveOwner($reseller);

        if (! $owner) {
            return self::FAILURE;
        }

        // The free plan caps chapters at 3 and this memorial has 5. Without an unlimited
        // plan attached the extra chapters exist in the database and are refused by the
        // page, which looks like the seeding half-failed.
        $plan = $this->resolvePlan();

        if (! $plan) {
            $this->warn('No unlimited subscription plan found — chapters and gallery may be capped by the free plan.');
        }

        $memorial = DB::transaction(function () use ($reseller, $owner, $plan) {
            $memorial = $this->upsertMemorial($reseller, $owner, $plan);

            $this->rebuildFamily($memorial);
            $this->rebuildEducation($memorial);
            $this->rebuildAffiliations($memorial);
            $this->rebuildGalleryCategories($memorial);

            $chapters = $this->rebuildChapters($memorial);

            // Both feeds are posts. Clearing once here rather than inside each builder so a
            // re-run cannot drop the life events and then fail before the tributes land.
            $memorial->posts()->delete();
            $memorial->tributes()->delete();

            $this->rebuildLifeEvents($memorial, $chapters);
            $this->rebuildTributes($memorial);

            return $memorial;
        });

        $this->newLine();
        $this->info("Showcase memorial ready: {$memorial->full_name}");
        $this->table(['Field', 'Value'], [
            ['Memorial id', $memorial->id],
            ['Slug', $memorial->slug],
            ['Reseller', "{$reseller->name} (id {$reseller->id})"],
            ['Owner', "{$owner->name} (id {$owner->id})"],
            ['Plan', $plan?->name ?? 'none attached'],
            ['Public', $memorial->is_public ? 'yes' : 'no — pass without --private to publish'],
            ['Chapters', $memorial->storyChapters()->count()],
            ['Life events', $memorial->posts()->whereNull('tribute_type')->count()],
            ['Tributes', $memorial->posts()->whereNotNull('tribute_type')->count()],
            ['Gallery categories', $memorial->galleryCategories()->count()],
        ]);

        $this->newLine();
        $this->warn('No photos were attached. Profile, cover and gallery images still need uploading.');

        return self::SUCCESS;
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

        // Attaching a showcase to the wrong funeral home's public site is not something to
        // guess at, so an ambiguous name stops rather than taking the first row.
        if ($matches->count() > 1) {
            $this->error("\"{$name}\" matches {$matches->count()} resellers: ".$matches->pluck('name')->implode(', '));
            $this->line('Pass a longer --reseller= value.');

            return null;
        }

        return $matches->first();
    }

    /**
     * Who the memorial belongs to.
     *
     * An explicit --owner is asked for by email rather than id because the id differs
     * between this database and live, and the whole point of naming the account is to be
     * sure which one it lands on.
     */
    private function resolveOwner(Reseller $reseller): ?User
    {
        $email = trim((string) $this->option('owner'));

        if ($email === '') {
            $owner = User::find($reseller->owner_user_id);

            if (! $owner) {
                $this->error("Reseller \"{$reseller->name}\" has no owner user (owner_user_id={$reseller->owner_user_id}).");
                $this->line('Pass --owner=someone@example.com, or set the reseller owner first.');

                return null;
            }

            return $owner;
        }

        $owner = User::where('email', $email)->first();

        if (! $owner) {
            $this->error("No user with email \"{$email}\".");

            return null;
        }

        // A user already tenanted to a different reseller is not a naming slip to work
        // around — creating here would put this memorial on the wrong company's site,
        // which is the same mistake resolving the reseller by name exists to prevent.
        if ($owner->reseller_id !== null && (int) $owner->reseller_id !== (int) $reseller->id) {
            $this->error("\"{$email}\" belongs to reseller id {$owner->reseller_id}, not {$reseller->id} ({$reseller->name}).");

            return null;
        }

        if ($owner->reseller_id === null) {
            $this->warn("\"{$email}\" is not tenanted to any reseller; attaching the memorial to {$reseller->name} explicitly.");
        }

        return $owner;
    }

    private function resolvePlan(): ?SubscriptionPlan
    {
        // -1 is this codebase's "unlimited" (PlanFeatures::isUnlimited). Chosen by capability
        // rather than by name so a renamed plan on live still resolves.
        return SubscriptionPlan::where('max_chapters', -1)->orderBy('price')->first()
            ?? SubscriptionPlan::orderByDesc('max_chapters')->first();
    }

    private function upsertMemorial(Reseller $reseller, User $owner, ?SubscriptionPlan $plan): Memorial
    {
        return Memorial::updateOrCreate(
            ['slug' => (string) $this->option('slug')],
            [
                'user_id' => $owner->id,
                'reseller_id' => $reseller->id,

                'title' => 'Mr.',
                'full_name' => 'Wilson Ssekandi Mubiru',
                'first_name' => 'Wilson',
                'middle_name' => 'Ssekandi',
                'last_name' => 'Mubiru',
                'gender' => 'male',
                'relationship' => 'Father',
                'nationality' => 'Ugandan',

                'primary_profession' => 'Secondary school teacher',
                'notable_title' => 'Deputy Headteacher, Nansana Progressive Secondary School',
                'active_year_start' => 1996,
                'active_year_end' => 2025,

                'short_description' => 'A mathematics teacher for twenty-nine years, and Deputy Headteacher for the last thirteen. He taught in the same school long enough to teach the children of his own former students.',

                'known_for' => 'Patience with the students everybody else had given up on, and for the ledger in which he wrote down every shilling he was ever trusted with.',

                'date_of_birth' => '1970-07-04',
                'birth_year' => 1970,
                'birth_month' => 7,
                'birth_day' => 4,
                'birth_city' => 'Nazigo',
                'birth_state' => 'Kayunga District',
                'birth_country' => 'Uganda',

                'date_of_passing' => '2025-09-21',
                'death_year' => 2025,
                'death_month' => 9,
                'death_day' => 21,
                'death_city' => 'Kira',
                'death_state' => 'Wakiso District',
                'death_country' => 'Uganda',

                'biography' => $this->biography(),
                'major_achievements' => $this->achievements(),
                'more_details' => $this->moreDetails(),

                'theme' => 'premium',
                'plan' => Memorial::PLAN_PAID,
                'subscription_plan_id' => $plan?->id,
                'completion_status' => Memorial::COMPLETION_COMPLETED,
                'status' => Memorial::STATUS_ACTIVE,
                'is_public' => ! $this->option('private'),
            ]
        );
    }

    /**
     * Written by his eldest daughter, because that is who fills this in. The relationship
     * field on the memorial says "Father"; a biography in an encyclopaedia voice would
     * contradict it.
     */
    private function biography(): string
    {
        return <<<'TEXT'
My name is Sylvia. Wilson Ssekandi Mubiru was my father, and I am writing this because I am the eldest and my mother asked me to. He would have found the whole idea embarrassing, so I am just going to write what is true.

He was born in Nazigo, Kayunga, on 4 July 1970, the fourth of seven. His father grew maize and his mother sold silverfish at the market. He was the one they decided to educate. He never let any of us forget that it was a decision somebody made for him, and that it cost his brothers and sisters something.

He went to Ndejje for O and A level and to Makerere in 1992 for a BSc with Education. He came out a mathematics teacher in 1995 and he stayed one for thirty years. He started at Nazigo Community Secondary School, moved to Nansana Progressive in 2004 to head the mathematics department, and became Deputy Headteacher there in 2012. He was still Deputy Headteacher the week he died. He taught long enough that in his last years he was teaching the children of people he had taught in the nineties, and he found this funnier than anybody else did.

He married my mother, Florence, in 1995 at St. Andrew's in Nansana. I was born the following year.

I want to say something about my sister Grace. She was born in 1999 and she was with us eight months. My father did not talk about her, not once, not in twenty-six years. But her photograph was in the front of the ledger he carried, and every one of us saw it at some point and somehow all of us knew not to ask.

The ledger is the thing I keep thinking about. It was a hard-backed book and he wrote down every payment he ever made or was trusted with — our school fees, the SACCO he was treasurer of for fourteen years, the money the staff collected when somebody was bereaved. Fourteen years of teachers' savings and not one shilling was ever in question. When people say he was a responsible man, that book is what they mean.

We rented in Nansana until 2011. He bought a plot in Kira and built the house slowly, room by room, on a SACCO loan he cleared in 2021. Four bedrooms. He drove the same white Corolla for nineteen years and refused every suggestion that he change it. He said it started every morning, which was all he had ever asked of it.

He paid our fees and he paid for two of our cousins as well, and he never mentioned it to any of us. We found that out from the ledger too.

He was a church warden at St. Andrew's. He sang loudly and very badly. He drank tea with too much sugar. He watched the news at nine and told everyone to be quiet. He marked exercise books at the dining table until eleven at night for thirty years and he never once complained about it where we could hear.

He fell ill in July. He was in and out of hospital through August. He died at home in Kira on the morning of 21 September 2025, with my mother beside him. He was 55, which everyone keeps saying is too young, and they are right.

The last thing I want to write is this. When he died, we did not have to argue about anything. He had left a will, he had left instructions, and he had paid into a plan years ago so that we would not be making decisions about money in the same week we were burying him. At the time we thought he was being morbid. I understand now that it was the last considerate thing he did for us, and it was entirely him.

Daddy, you always said check your working. We are trying.

— Sylvia
TEXT;
    }

    private function achievements(): string
    {
        return <<<'TEXT'
Taught mathematics for twenty-nine years, from 1996 until the term he died.
Head of the Mathematics Department at Nansana Progressive Secondary School, 2004 to 2012.
Deputy Headteacher at the same school from 2012 to 2025.
BSc with Education, Makerere University, 1995 — the first in his family to attend university.
Postgraduate Diploma in Education Management, Kyambogo University, 2010, studied part-time while teaching a full timetable.
Treasurer of the Bulemeezi Teachers' SACCO for fourteen years, with accounts that balanced every single year.
Built the family house in Kira room by room and cleared the loan on it in 2021.
Put his four children through school, and two of his brother's children alongside them.
Church warden at St. Andrew's Church of Uganda, Nansana, for eleven years.
Left a written will, funeral instructions and a paid-up plan, so that his family had nothing to arrange in the week they buried him.
TEXT;
    }

    private function moreDetails(): string
    {
        return <<<'TEXT'
Languages: Luganda and English.
Faith: Church of Uganda — St. Andrew's, Nansana, where he was a warden.
Clan: Mmamba (Lungfish).
Home village: Nazigo, Kayunga District.
Known as: "Mr. Mubiru" to thirty years of students, "Ssekandi" at home, "Teacher" to the neighbours.
The car: a white Toyota Corolla he bought in 2006 and drove for nineteen years.
Always had: a red pen in his shirt pocket and the ledger in his bag.
Loved: strong tea with too much sugar, the nine o'clock news, and marking at the dining table.
Could not stand: lateness, guesswork, and anyone rounding a figure to make it easier.
TEXT;
    }

    private function rebuildFamily(Memorial $memorial): void
    {
        $memorial->spouses()->delete();
        $memorial->spouses()->create([
            'spouse_name' => 'Florence Nakayiza Mubiru',
            'marriage_start_year' => 1995,
            'marriage_end_year' => 2025,
        ]);

        $memorial->children()->delete();
        foreach ([
            ['Sylvia Nabukenya Mubiru', 1996],
            ['Grace Nakato Mubiru', 1999],
            ['Isaac Ssekandi Mubiru', 2001],
            ['Ronald Kayemba Mubiru', 2005],
            ['Joan Namulindwa Mubiru', 2010],
        ] as [$name, $year]) {
            $memorial->children()->create(['child_name' => $name, 'birth_year' => $year]);
        }

        $memorial->parents()->delete();
        foreach (['Yowana Mubiru', 'Sarah Nakiwala'] as $parent) {
            $memorial->parents()->create(['parent_name' => $parent, 'relationship_type' => 'biological']);
        }

        $memorial->siblings()->delete();
        foreach ([
            'Joyce Namusoke',
            'Stephen Kigongo',
            'Rebecca Nabakka',
            'Fred Ssentamu',
            'Milly Nakato',
            'Anthony Lubowa',
        ] as $sibling) {
            $memorial->siblings()->create(['sibling_name' => $sibling]);
        }
    }

    private function rebuildEducation(Memorial $memorial): void
    {
        $memorial->education()->delete();

        foreach ([
            ['Nazigo Primary School', 1978, 1985, 'Primary Leaving Examination'],
            ['Ndejje Senior Secondary School', 1986, 1991, 'O & A Level'],
            ['Makerere University', 1992, 1995, 'BSc with Education (Mathematics)'],
            ['Kyambogo University', 2008, 2010, 'Postgraduate Diploma in Education Management'],
        ] as [$institution, $start, $end, $degree]) {
            $memorial->education()->create([
                'institution_name' => $institution,
                'start_year' => $start,
                'end_year' => $end,
                'degree' => $degree,
            ]);
        }
    }

    private function rebuildAffiliations(Memorial $memorial): void
    {
        $memorial->notableCompanies()->delete();
        foreach ([
            'Nansana Progressive Secondary School',
            'Nazigo Community Secondary School',
            "Bulemeezi Teachers' SACCO",
            "St. Andrew's Church of Uganda, Nansana",
        ] as $index => $company) {
            $memorial->notableCompanies()->create(['company_name' => $company, 'sort_order' => $index]);
        }

        // He founded nothing. Left empty on purpose — the page has to look right for the
        // majority of people, who also founded nothing.
        $memorial->coFounders()->delete();
    }

    private function rebuildGalleryCategories(Memorial $memorial): void
    {
        // Memorial::created() seeds three defaults. Replacing them rather than adding to
        // them, or a re-run accumulates duplicates against the 20-per-memorial cap.
        $memorial->galleryCategories()->delete();

        foreach ([
            'Early Years',
            'Wedding',
            'Family',
            'The School',
            'Church',
            'The House in Kira',
            'Farewell',
        ] as $index => $name) {
            $memorial->galleryCategories()->create(['name' => $name, 'sort_order' => $index]);
        }
    }

    /** @return array<string, StoryChapter> */
    private function rebuildChapters(Memorial $memorial): array
    {
        $memorial->storyChapters()->delete();

        $chapters = [
            'nazigo' => ['Nazigo', 'The fourth of seven, in a house that could afford to educate one of them. He knew which one he was and what it had cost the others.'],
            'makerere' => ['Makerere', 'Ndejje, then a BSc with Education in 1992. The first in the family to see a university from the inside.'],
            'classroom' => ['The Classroom', 'Nazigo Community, then Nansana Progressive in 2004. Twenty-nine years of mathematics and thirty years of marking at the dining table.'],
            'ledger' => ['The Ledger', "Deputy Headteacher, SACCO treasurer, church warden. Fourteen years of other people's savings and never a shilling in question."],
            'kira' => ['The House in Kira', 'A plot bought in 2011, four bedrooms built room by room, the loan cleared in 2021, and the last two years.'],
        ];

        $created = [];

        foreach ($chapters as $key => [$title, $description]) {
            $created[$key] = $memorial->storyChapters()->create([
                'title' => $title,
                'description' => $description,
                'sort_order' => count($created),
            ]);
        }

        return $created;
    }

    /**
     * Life events are posts, and the feed orders by created_at — there is no event_date
     * column — so each one is stamped with the date it happened. That is what puts them in
     * order; it also means the feed reads newest first, which for a memorial is right.
     *
     * @param  array<string, StoryChapter>  $chapters
     */
    private function rebuildLifeEvents(Memorial $memorial, array $chapters): void
    {
        $events = [
            ['1970-07-04', 'nazigo', 'Born in Nazigo', 'Nazigo, Kayunga District', 'The fourth of seven, born at home on a Saturday.'],
            ['1985-12-01', 'nazigo', 'Passed his PLE', 'Nazigo Primary School', 'He came top of his class. The family decided he was the one who would go on, and his brothers went to work instead. He carried that his whole life.'],
            ['1986-02-03', 'nazigo', 'First term at Ndejje', 'Ndejje, Luweero District', 'One metal trunk, two shirts and a jerrycan. He kept the trunk. It is still in the store in Kira.'],
            ['1992-08-17', 'makerere', 'Admitted to Makerere University', 'Kampala, Uganda', 'BSc with Education. The first person in the family to go to university. His mother kept the admission letter in her Bible.'],
            ['1995-01-21', 'makerere', 'Graduation', 'Makerere University, Kampala', 'He borrowed a jacket for the photograph. He is wearing it in the picture on the sitting room wall.'],
            ['1995-12-02', 'makerere', 'Married Florence Nakayiza', "St. Andrew's Church of Uganda, Nansana", 'Fifty-two guests. Her aunt made the cake. He was on time, which he mentioned for the next thirty years.'],
            ['1996-02-05', 'classroom', 'First day of teaching', 'Nazigo Community Secondary School', 'Senior One mathematics, sixty-eight students, one blackboard. He said he did not sleep the night before.'],
            ['1996-05-18', 'classroom', 'Sylvia is born', 'Nansana, Wakiso', 'His first. He wrote the date and the time on the inside cover of the ledger.'],
            ['1999-08-11', 'classroom', 'Grace', 'Nansana, Wakiso', 'Their second daughter. She was with them eight months. Her photograph stayed in the front of the ledger for twenty-six years.'],
            ['2004-01-26', 'classroom', 'Head of Mathematics, Nansana Progressive', 'Nansana, Wakiso', 'He moved schools for the department and stayed twenty-one years.'],
            ['2006-06-10', 'ledger', 'The white Corolla', 'Kampala, Uganda', 'Bought second-hand and driven for nineteen years. He refused every suggestion that he replace it. It started every morning, which he said was the entire specification.'],
            ['2010-11-19', 'ledger', 'Postgraduate Diploma, Kyambogo', 'Kyambogo University, Kampala', 'Studied at weekends for two years while teaching a full timetable and marking every night.'],
            ['2011-04-02', 'kira', 'Bought the plot in Kira', 'Kira, Wakiso', 'After sixteen years of renting. He walked the boundary with my mother and did not say anything for a long time.'],
            ['2012-09-03', 'ledger', 'Appointed Deputy Headteacher', 'Nansana Progressive Secondary School', 'He kept teaching two mathematics classes anyway. He said an administrator who does not teach forgets what he is administering.'],
            ['2018-07-14', 'kira', 'The house is finished', 'Kira, Wakiso', 'Four bedrooms, built room by room over seven years. He took every visitor round the back to look at the guttering.'],
            ['2021-03-30', 'kira', 'The loan is cleared', 'Kira, Wakiso', 'He came home with the clearance letter, put it in the ledger, and made tea. That was the celebration.'],
            ['2024-11-08', 'ledger', 'Thirty years at the front of a classroom', 'Nansana, Wakiso', 'The staff bought him a cake. He gave a speech that was four sentences long and then asked whether anyone had marked the Senior Threes.'],
            ['2025-08-29', 'kira', 'His last day at school', 'Nansana Progressive Secondary School', 'He came in to hand over the accounts properly. He would not leave until they balanced.'],
            ['2025-09-21', 'kira', 'Rest in peace', 'Kira, Wakiso', 'He died at home in the morning. My mother was holding his hand. He was 55.'],
        ];

        foreach ($events as $index => [$date, $chapterKey, $title, $location, $content]) {
            $this->createPost($memorial, [
                'story_chapter_id' => $chapters[$chapterKey]->id,
                'title' => $title,
                'content' => $content,
                'location' => $location,
                'sort_order' => $index,
            ], $date.' 09:00:00');
        }
    }

    /**
     * A written tribute is a post carrying a marker, plus the gesture row that marker came
     * from — that split is what the 2026_08_08 migration established, and it is what makes
     * "7 candles" and a feed of 7 stories agree with each other.
     *
     * Attribution goes in the title because posts have no guest_name: formatPost() falls
     * back to the memorial's own name, so without this every tribute would appear to have
     * been written by the man being mourned.
     */
    private function rebuildTributes(Memorial $memorial): void
    {
        $tributes = [
            ['2025-09-23', Tribute::TYPE_CANDLE, 'Florence Nakayiza Mubiru — Wife',
                'Thirty years. You brought your salary home the same day it was paid, every month, and you never once asked me to account for a shilling of what I did with it. You wrote everything else in that book but you never wrote that. I do not know how to do the evenings yet. Rest, Ssekandi.'],

            ['2025-09-23', Tribute::TYPE_FLOWER, 'Isaac Ssekandi Mubiru — Son',
                'He made me redo a whole page of long division when I was nine because I had got the right answer by luck. I was furious for a week. I have thought about that page more times in my adult life than I can count.'],

            ['2025-09-24', Tribute::TYPE_FLOWER, 'Joan Namulindwa Mubiru — Daughter',
                'You always had a red pen in your shirt pocket. I used to take it and you would search the entire house and pretend you had no idea where it had gone. I have it now. I am not giving it back.'],

            ['2025-09-24', Tribute::TYPE_PRAYER, 'Stephen Kigongo — Brother',
                'They could only educate one of us and they chose him, and he never once behaved as though that made him better than the rest. He paid for two of my children without telling me and I only found out this week. Sleep well, brother.'],

            ['2025-09-25', Tribute::TYPE_CANDLE, 'Moses Wasswa — Former student, 1998',
                'I failed mathematics twice and I had decided I was stupid. Mr. Mubiru kept me back on Thursdays for a whole term and never once made me feel like a burden. I teach mathematics myself now, in Mbale. Every Thursday I keep somebody back.'],

            ['2025-09-25', Tribute::TYPE_PRAYER, "Harriet Nabbosa — Headteacher, Nansana Progressive Secondary School",
                'In thirteen years as my deputy he never brought me a problem without also bringing me the arithmetic behind it. He handed over the accounts three weeks before he died and they balanced to the shilling. The school has lost the person who knew where everything was.'],

            ['2025-09-26', Tribute::TYPE_CANDLE, "Rev. Paul Ssenyonga — St. Andrew's Church of Uganda, Nansana",
                'Warden for eleven years, back row on the left, every Sunday. He counted the collection after the service and he sang during it, very loudly and not at all well. The church is quieter now in a way I do not like.'],
        ];

        foreach ($tributes as $index => [$date, $type, $title, $content]) {
            $timestamp = $date.' '.sprintf('%02d:00:00', 10 + $index);

            $post = $this->createPost($memorial, [
                'tribute_type' => $type,
                'title' => $title,
                'content' => $content,
                'sort_order' => $index,
            ], $timestamp);

            // Kept so the tally on the page stays true: writing a candle tribute was
            // lighting a candle. migrated_post_id marks the row as spoken for, which is
            // what stops the words being rendered a second time.
            $tribute = $memorial->tributes()->create([
                'user_id' => null,
                'type' => $type,
                'is_approved' => true,
                'migrated_post_id' => $post->id,
            ]);

            $tribute->timestamps = false;
            $tribute->forceFill(['created_at' => $timestamp, 'updated_at' => $timestamp])->save();
        }
    }

    /**
     * created_at is assigned after the insert because Eloquent overwrites it during one,
     * and the feed's ordering is built on it.
     */
    private function createPost(Memorial $memorial, array $attributes, string $timestamp): Post
    {
        $post = $memorial->posts()->create(array_merge([
            'user_id' => null,
            'type' => 'text',
            'is_published' => true,
        ], $attributes));

        $post->timestamps = false;
        $post->forceFill(['created_at' => $timestamp, 'updated_at' => $timestamp])->save();

        return $post;
    }
}
