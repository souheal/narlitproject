<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fills out the Explore feed with demo stories.
 *
 * The category rows on /explore only ever show the articles that exist for
 * that category, so a database with one story per category renders a row
 * with a single lonely card. This tops every category up to TARGET_PER_CATEGORY.
 *
 * Idempotent: it only inserts what is missing, and never touches or removes
 * articles that are already there.
 */
class DemoArticlesSeeder extends Seeder
{
    private const TARGET_PER_CATEGORY = 5;

    /** @var array<string, list<array{0:string,1:string}>> title + excerpt per category */
    private const STORIES = [
        'Environment' => [
            ['Wetlands reborn: how a coastal town rebuilt 12 acres', 'A grassroots effort turned degraded shoreline into a living barrier against storm surges.'],
            ['The river that came back to life', 'Ten years of cleanup turned a dumping ground into a swimming spot again.'],
            ['Rooftop forests cooling a concrete district', 'Volunteers planted 4,000 saplings above the city and dropped street temperatures.'],
            ['Solar wells for a drying valley', 'Twelve pumps now draw clean water without a litre of diesel.'],
            ['The seed bank guarding 300 local crops', 'Farmers are reclaiming varieties that vanished from the market decades ago.'],
            ['Turning fishing nets into playground floors', 'A coastal co-op recycles the gear that used to choke the reef.'],
        ],
        'Food Security' => [
            ['The kitchen feeding 900 kids a week', 'A co-op kitchen turned cafeteria surplus into a summer lifeline for families.'],
            ['A grocery where the price tag follows your income', 'The shelves are the same; the checkout adapts to what each family can pay.'],
            ['Rescuing a tonne of bread every night', 'Bakeries now route unsold loaves to shelters instead of skips.'],
            ['School gardens that outgrew the schoolyard', 'What started as a science lesson now supplies the lunch line.'],
            ['The bus that brings a market to food deserts', 'Three neighbourhoods without a grocer get fresh produce twice a week.'],
            ['Freezers that keep a harvest from spoiling', 'Shared cold storage let smallholders stop selling at a loss.'],
        ],
        'Education' => [
            ['Night classes that cut dropout rates by half', 'Volunteer teachers rebuilt a school schedule around the families it serves.'],
            ['Reading circles in the refugee camp', 'How a library of 200 donated books became a community anchor.'],
            ['The tutors are 16 and they are very good', 'Older students now coach younger ones, and both sides gained a year of maths.'],
            ['A laptop lab built from donated office machines', 'Refurbished hardware put a full computing class within reach.'],
            ['Teaching mothers to read alongside their children', 'Two generations share one classroom, and neither wants to miss it.'],
            ['The radio lessons that reached the valleys', 'When the road closed for winter, the broadcast kept the term going.'],
        ],
        'Health' => [
            ['A clinic on wheels reaches the last mile', 'Two vans, six nurses, and a route map that changed rural care.'],
            ['Midwives closing a two-hour gap to the hospital', 'Local training put skilled birth attendants in nine villages.'],
            ['The pharmacy that tracks refills by text', 'A simple reminder system lifted treatment adherence sharply.'],
            ['Eye camps that handed back a trade', 'Cataract surgeries returned dozens of craftspeople to work.'],
            ['Peer counsellors on the night shift', 'Trained volunteers answer the calls that arrive after midnight.'],
            ['Cold chains for a vaccine run', 'Solar fridges keep doses viable on a three-day journey.'],
        ],
    ];

    public function run(): void
    {
        $organizationId = DB::table('organization_profiles')
            ->where('verification_status', 'approved')
            ->value('id');

        if ($organizationId === null) {
            $this->command?->warn('DemoArticlesSeeder: no approved organization found — skipping.');

            return;
        }

        $created = 0;

        foreach (self::STORIES as $category => $stories) {
            $existing = DB::table('articles')
                ->where('category', $category)
                ->where('status', 'published')
                ->whereNull('deleted_at')
                ->count();

            foreach ($stories as [$title, $excerpt]) {
                if ($existing >= self::TARGET_PER_CATEGORY) {
                    break;
                }

                $slug = Str::slug($title);

                if (DB::table('articles')->where('slug', $slug)->exists()) {
                    continue;
                }

                DB::table('articles')->insert([
                    'public_id'               => (string) Str::uuid(),
                    'organization_profile_id' => $organizationId,
                    'title'                   => $title,
                    'slug'                    => $slug,
                    'excerpt'                 => $excerpt,
                    'content'                 => $this->content($title, $excerpt),
                    'category'                => $category,
                    'status'                  => 'published',
                    'published_at'            => now()->subDays(random_int(1, 20)),
                    'read_time'               => random_int(3, 8),
                    'total_reads'             => random_int(40, 400),
                    'total_unique_reads'      => random_int(30, 300),
                    'total_reading_seconds'   => 0,
                    'total_points_generated'  => 0,
                    'created_at'              => now(),
                    'updated_at'              => now(),
                ]);

                $existing++;
                $created++;
            }
        }

        $this->command?->info("DemoArticlesSeeder: {$created} article(s) created.");
    }

    private function content(string $title, string $excerpt): string
    {
        $paragraphs = [
            $excerpt,
            'It began with a handful of people who were tired of waiting for someone else to act. They mapped the problem street by street, counted what was actually missing, and started with the part they could fund themselves.',
            'The first months were unglamorous: permits, spreadsheets, and a lot of conversations with neighbours who had heard promises before. What changed minds was not a pitch but a pilot small enough to see and finish.',
            'Funding arrived in pieces — a municipal grant, a local business covering materials, and donations that rarely topped a few dollars each. Every figure was posted publicly, which turned out to matter more than the amounts.',
            'Today the work runs on a rhythm the community set itself. The numbers are modest against the scale of the need, but they are real, they are verified, and they grow each season.',
            'What the team says they learned is simple enough to travel: start where you already have trust, measure honestly, and make it easy for the next person to join.',
        ];

        return "<h2>{$title}</h2>\n" . implode("\n", array_map(
            static fn (string $p): string => "<p>{$p}</p>",
            $paragraphs
        ));
    }
}
