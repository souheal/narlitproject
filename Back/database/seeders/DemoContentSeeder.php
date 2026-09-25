<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $orgRoleId = DB::table('roles')->where('name', 'organization')->value('id')
            ?? DB::table('roles')->insertGetId([
                'name' => 'organization',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $orgs = [
            [
                'user' => ['full_name' => 'Habitat Voices', 'email' => 'author@habitatvoices.org', 'phone' => '+15550000101'],
                'name' => 'Habitat Voices',
                'website' => 'https://habitatvoices.example.org',
                'category' => 'Environment',
            ],
            [
                'user' => ['full_name' => 'Meals For All', 'email' => 'author@mealsforall.org', 'phone' => '+15550000102'],
                'name' => 'Meals For All',
                'website' => 'https://mealsforall.example.org',
                'category' => 'Food Security',
            ],
            [
                'user' => ['full_name' => 'Bright Futures Fund', 'email' => 'author@brightfutures.org', 'phone' => '+15550000103'],
                'name' => 'Bright Futures Fund',
                'website' => 'https://brightfutures.example.org',
                'category' => 'Education',
            ],
            [
                'user' => ['full_name' => 'Refuge Bridge', 'email' => 'author@refugebridge.org', 'phone' => '+15550000104'],
                'name' => 'Refuge Bridge',
                'website' => 'https://refugebridge.example.org',
                'category' => 'Refugees',
            ],
        ];

        $orgIds = [];

        foreach ($orgs as $entry) {
            $userId = DB::table('users')->where('email', $entry['user']['email'])->value('id');

            if ($userId === null) {
                $userId = DB::table('users')->insertGetId([
                    'public_id' => (string) Str::uuid(),
                    'role_id' => $orgRoleId,
                    'full_name' => $entry['user']['full_name'],
                    'username' => Str::slug($entry['user']['full_name']),
                    'email' => $entry['user']['email'],
                    'phone' => $entry['user']['phone'],
                    'password' => Hash::make('Org@Narlit2026!'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'first_login_mfa_completed_at' => now(),
                    'failed_login_attempts' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $orgId = DB::table('organization_profiles')->where('user_id', $userId)->value('id');

            if ($orgId === null) {
                $orgId = DB::table('organization_profiles')->insertGetId([
                    'public_id' => (string) Str::uuid(),
                    'user_id' => $userId,
                    'organization_name' => $entry['name'],
                    'website' => $entry['website'],
                    'landline' => '+15550000000',
                    'tax_id' => '99-'.random_int(1000000, 9999999),
                    'certificate_file' => 'demo/seed-cert.pdf',
                    'irs_verified' => true,
                    'verification_status' => 'approved',
                    'reviewed_at' => now()->subDays(random_int(30, 90)),
                    'payouts_enabled' => true,
                    'charges_enabled' => true,
                    'metadata' => json_encode([
                        'category' => $entry['category'],
                        'mission_statement' => 'Empowering communities through storytelling.',
                    ]),
                    'created_at' => now()->subDays(random_int(60, 120)),
                    'updated_at' => now(),
                ]);
            }

            $orgIds[$entry['name']] = ['id' => $orgId, 'category' => $entry['category']];
        }

        $articles = [
            // Published + featured
            [
                'org' => 'Habitat Voices', 'status' => 'published', 'featured' => true,
                'title' => 'How a coastal town rebuilt its wetlands from scratch',
                'excerpt' => 'A grassroots effort transformed 12 acres of degraded shoreline into a living barrier against storm surges.',
                'category' => 'Environment',
                'body' => "Three years ago, the shoreline at Bay Ridge looked nothing like it does today. Residents watched storm after storm eat away at the last remaining marshland, and with it, the buffer that kept their homes safe. This is the story of how a handful of neighbors, a marine biologist, and an unlikely alliance with the county turned things around.\n\nIt began with a single grant of \$8,000 — barely enough to hire an ecologist for a season. But that ecologist, Dr. Renata Lin, saw potential where others saw ruin. Her plan was simple in principle: reintroduce native Spartina grass, install oyster reef modules to break wave energy, and let the ecosystem do the heavy lifting.\n\nThree years and dozens of volunteer weekends later, the wetlands are back — and so are the birds, the fish, and the sense of possibility.",
                'days_ago' => 3,
            ],
            [
                'org' => 'Meals For All', 'status' => 'published', 'featured' => true,
                'title' => 'The kitchen that feeds 900 kids a week — for less than \$0.60 a meal',
                'excerpt' => 'Inside a co-op kitchen that turned cafeteria surplus into a lifeline for families during summer break.',
                'category' => 'Food Security',
                'body' => "When school lets out for the summer, roughly one in six American children loses reliable access to meals. In Whitman County, a coalition of parents and teachers refused to accept that math.\n\nWhat they built — quietly, without much attention — is a distributed kitchen network that redirects unused cafeteria stock during the school year into a summer meal program. The results, three summers in, are quietly extraordinary: 900 kids fed per week, at an average cost of \$0.58 per meal.\n\nHere's how they did it, and what other districts are borrowing from the model.",
                'days_ago' => 5,
            ],
            [
                'org' => 'Bright Futures Fund', 'status' => 'published', 'featured' => false,
                'title' => 'What we learned funding 40 first-generation college students',
                'excerpt' => 'The application asked one unusual question — and it changed how we picked scholarship winners.',
                'category' => 'Education',
                'body' => "For years we asked the standard questions: GPA, essays, letters of recommendation. Then one board member suggested we add a question no one else was asking: 'Describe a time you had to figure something out alone.'\n\nThat question changed everything. It surfaced students whose transcripts didn't tell the whole story — the kid who taught himself calculus from library books, the girl who translated for her parents at every parent-teacher conference. Over four years, our completion rate for scholarship recipients rose from 68% to 91%.\n\nThis is what we learned, and what we're doing differently now.",
                'days_ago' => 10,
            ],
            [
                'org' => 'Refuge Bridge', 'status' => 'published', 'featured' => false,
                'title' => 'The forms that stand between refugees and stability',
                'excerpt' => 'A single missing signature can push a resettlement timeline back by nine months. We built a tool to fix that.',
                'category' => 'Refugees',
                'body' => "The paperwork of resettlement is a paper wall. Nine forms, three agencies, four languages — and every one of them requires exact answers on the first try. Miss a checkbox, and you might wait months for another appointment.\n\nOver the past year, our caseworkers logged every rejection they saw. The pattern was clear: 62% of rejections came from just five recurring form-filling mistakes. So we built a checklist app — nothing fancy, just a phone-friendly walkthrough that flags likely mistakes before submission.\n\nSix months in, our rejection rate dropped from 41% to 8%.",
                'days_ago' => 14,
            ],
            [
                'org' => 'Habitat Voices', 'status' => 'published', 'featured' => false,
                'title' => 'Why we stopped counting trees and started counting canopy',
                'excerpt' => 'A shift in how we measure our reforestation projects revealed a mistake we\'d been making for a decade.',
                'category' => 'Environment',
                'body' => "For ten years, every restoration project we ran ended the same way: a photo, a plaque, and a number. 'We planted 4,000 trees.' It felt like impact. It looked like impact. But it wasn't quite the whole story.\n\nWhen a partner NGO asked us to switch to canopy-area measurement instead, we resisted at first. But the first survey humbled us: our 4,000-tree site had only 61% of the canopy we'd projected. Many trees had died, been chewed by deer, or shaded out by faster-growing neighbors.\n\nThe good news: the change in how we measure has changed how we plant. Here's what we're doing differently.",
                'days_ago' => 21,
            ],
            [
                'org' => 'Meals For All', 'status' => 'published', 'featured' => false,
                'title' => 'A grocery store on wheels — 22 stops, 3 counties, 4 years',
                'excerpt' => 'What began as a converted school bus is now the primary grocery source for 1,800 rural households.',
                'category' => 'Food Security',
                'body' => "Food deserts don't always look like empty lots. Sometimes they look like a corner store with fresh produce that costs three times what it does in the suburbs. In Larch County, both realities existed side by side.\n\nOur mobile market started as a converted school bus, driven twice a week by a retired teacher. Four years later, it's a fleet of three refrigerated vans covering 22 stops across three counties. And it's not charity — customers pay what they'd pay anywhere else, made possible by wholesale relationships and a grant that covers fuel.",
                'days_ago' => 30,
            ],
            [
                'org' => 'Bright Futures Fund', 'status' => 'published', 'featured' => false,
                'title' => 'The tutoring model that outperformed a private school',
                'excerpt' => 'For \$400 per student per year, an after-school peer program moved the needle further than we thought possible.',
                'category' => 'Education',
                'body' => "The premise was almost embarrassingly simple: pair 8th graders with 5th graders for an hour a week. No fancy curriculum, no test prep, no professional tutors. Just kids teaching kids.\n\nWhen we finally got the third-party evaluation back, the results made everyone uncomfortable. Fifth graders in the program outperformed peers in a nearby private school in reading comprehension — by a margin large enough that the evaluators re-ran the analysis to make sure.\n\nWhat's the secret? It might be that no one is more motivated to explain fractions clearly than a 13-year-old who wants to look smart.",
                'days_ago' => 42,
            ],
            [
                'org' => 'Refuge Bridge', 'status' => 'published', 'featured' => false,
                'title' => 'What our clients wish employers knew about their resumes',
                'excerpt' => 'Ten years of engineering experience in Damascus doesn\'t translate to a US resume automatically. Here\'s the gap.',
                'category' => 'Refugees',
                'body' => "'I have a PhD. Why do I get rejected from cashier jobs?' It's a question we hear at least once a week.\n\nThe answer, we've learned, has less to do with credentials and more to do with the invisible layer of expectations that resumes are supposed to signal. American employers scan for particular keywords, particular structures, particular ways of framing accomplishments. Miss the code, and even the strongest applicant is invisible.\n\nWe've been running resume workshops for three years, and this year we finally have data on what actually works.",
                'days_ago' => 60,
            ],

            // Pending review (for admin queue)
            [
                'org' => 'Habitat Voices', 'status' => 'pending_review',
                'title' => 'A river returns: two years of restoration in one photo essay',
                'excerpt' => 'We tracked the same 400-meter stretch of the Milton River every month for 24 months.',
                'category' => 'Environment',
                'body' => "Restoration is slow. So slow that it can feel like nothing is happening — until you look back at the timeline and realize a lot has. This photo essay documents 24 months of change along a single stretch of river we've been working on since 2024.\n\nEach photo was taken from the same tripod position, roughly the same time of day, at the beginning of each month. What emerges is a story of patience: the willows that took a full year to establish, the beavers that appeared in month nine and started doing our job for us, the return of trout in month eighteen.",
                'days_ago' => 1,
            ],
            [
                'org' => 'Meals For All', 'status' => 'pending_review',
                'title' => 'The paperwork gap that keeps small farms out of school kitchens',
                'excerpt' => 'A single food-safety certification blocks 70% of local farms from selling to public schools. We\'re trying to change that.',
                'category' => 'Food Security',
                'body' => "There are 340 small farms within a 50-mile radius of the school district we work with. Only 24 of them are certified to sell into public school cafeterias. The reason isn't quality — it's paperwork.\n\nThe certification in question takes 40+ hours to complete, costs \$1,200, and needs annual renewal. For a farm doing \$50,000/year, that's an unreachable barrier.",
                'days_ago' => 2,
            ],
            [
                'org' => 'Bright Futures Fund', 'status' => 'pending_review',
                'title' => 'What happens after the scholarship: a five-year follow-up',
                'excerpt' => 'We tracked 200 scholarship recipients from 2020 to 2025. The results were both hopeful and humbling.',
                'category' => 'Education',
                'body' => "In 2020, we handed out our first cohort of scholarships to 200 first-generation college students. We promised ourselves we'd stay in touch — not to check up on them, but to learn.\n\nFive years later, we've done the work. What we found is a mix of triumph and hard truth: 78% graduated on time. Of those, 91% found relevant work within a year. But the 22% who didn't graduate told us the story we most needed to hear.",
                'days_ago' => 4,
            ],
            [
                'org' => 'Refuge Bridge', 'status' => 'pending_review',
                'title' => 'A language cafe that doubled as a job network',
                'excerpt' => 'What started as informal English practice became the largest employer-refugee matchmaking event in the region.',
                'category' => 'Refugees',
                'body' => "The idea was casual: rent a coffee shop's back room on Tuesday nights, invite refugees to practice English with local volunteers. Free. No pressure.\n\nEighteen months in, something surprising had happened. Volunteers were mentioning job openings at their own companies. Refugees were sharing which employers were welcoming. The room had quietly become a job board.",
                'days_ago' => 6,
            ],

            // Draft (author still working)
            [
                'org' => 'Habitat Voices', 'status' => 'draft',
                'title' => 'Draft: What no one tells you about \'invasive species\'',
                'excerpt' => 'The label \'invasive\' is doing more harm than good in some ecosystems. This is a nuanced take.',
                'category' => 'Environment',
                'body' => "Still working on this piece. The core argument: the binary of native/invasive is oversimplified. Some 'invasive' species are functionally restoring niches that were emptied by extinction. Need to interview 2 more ecologists.",
                'days_ago' => 8,
            ],

            // Rejected
            [
                'org' => 'Refuge Bridge', 'status' => 'rejected',
                'title' => 'Refugee statistics 2024: everything you need to know',
                'excerpt' => 'A rundown of the year\'s numbers, sources, and what they mean for policy.',
                'category' => 'Refugees',
                'body' => "This is a numbers-heavy piece. Draft version had several statistics without cited sources and one figure that turned out to be from a discredited study.",
                'rejection_reason' => 'Several statistics lack primary source citations. The 62% displacement figure appears to be sourced from a 2019 report that has since been retracted. Please replace with UNHCR 2024 data and add citations for the other figures before resubmission.',
                'days_ago' => 12,
            ],

            // Archived (published + soft-deleted)
            [
                'org' => 'Meals For All', 'status' => 'published', 'archived' => true,
                'title' => 'How we handled the 2024 supply chain disruption',
                'excerpt' => 'A retrospective on a bad year — and what the coalition learned from it.',
                'category' => 'Food Security',
                'body' => "In early 2024, three of our regional distributors went out of business within eight weeks. We came very close to shutting down the summer program. This is what we learned about redundancy.",
                'days_ago' => 220,
            ],
        ];

        $created = 0;
        foreach ($articles as $a) {
            $orgId = $orgIds[$a['org']]['id'];
            $publishedAt = null;
            $featuredAt = null;

            if ($a['status'] === 'published') {
                $publishedAt = now()->subDays($a['days_ago']);
                if (! empty($a['featured'])) {
                    $featuredAt = $publishedAt->copy()->addHours(random_int(1, 24));
                }
            }

            $slug = Str::slug($a['title']).'-'.Str::lower(Str::random(6));
            $totalReads = $a['status'] === 'published' ? random_int(20, 1500) : 0;
            $uniqueReads = (int) ($totalReads * 0.75);

            $article = [
                'public_id' => (string) Str::uuid(),
                'organization_profile_id' => $orgId,
                'title' => $a['title'],
                'slug' => $slug,
                'excerpt' => $a['excerpt'],
                'content' => $a['body'],
                'category' => $a['category'],
                'status' => $a['status'],
                'rejection_reason' => $a['rejection_reason'] ?? null,
                'featured_at' => $featuredAt,
                'published_at' => $publishedAt,
                'read_time' => max(1, (int) ceil(str_word_count(strip_tags($a['body'])) / 200)),
                'total_reads' => $totalReads,
                'total_unique_reads' => $uniqueReads,
                'total_reading_seconds' => $totalReads * random_int(60, 240),
                'total_points_generated' => $totalReads * 5,
                'metadata' => json_encode(['seeded' => true]),
                'created_at' => now()->subDays($a['days_ago'] + 2),
                'updated_at' => now()->subDays($a['days_ago']),
            ];

            if (! empty($a['archived'])) {
                $article['deleted_at'] = now()->subDays(30);
            }

            DB::table('articles')->insert($article);
            $created++;
        }

        $this->command->info("Seeded {$created} articles across " . count($orgIds) . " organizations.");
        $this->command->line('Organization login (any of):');
        foreach (array_keys($orgIds) as $name) {
            $email = 'author@'.strtolower(str_replace(' ', '', $name)).'.org';
            $this->command->line("  - {$email} / Org@Narlit2026!");
        }
    }
}
