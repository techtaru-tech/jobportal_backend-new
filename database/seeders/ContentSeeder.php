<?php

namespace Database\Seeders;

use App\Models\ContentPage;
use App\Models\Faq;
use Illuminate\Database\Seeder;

/**
 * Seeds today's hardcoded Terms/Privacy/About/Contact copy and FAQ list —
 * the exact text that used to live in `TermsView`, `PrivacyPolicyView`,
 * `AboutUsView`, `ContactUsView` and `HelpSupportView`'s Dart source, moved
 * here so day one of `GET /content` reads identically to what the app
 * already showed. See the `content_pages` migration for why this table has
 * no other fallback.
 *
 * Idempotent — `updateOrCreate`, like `AdminSeeder` — so re-running this
 * (e.g. after a fresh migrate) doesn't fail on the unique `slug`/duplicate
 * questions, and *doesn't* clobber an admin's edits on a second run in an
 * environment where that matters: swap to `firstOrCreate` there if this ever
 * needs to run against a production database. For local dev it stays
 * `updateOrCreate` so this seeder is also the tool for "reset content to
 * defaults" during development.
 *
 *   php artisan db:seed --class=ContentSeeder
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->pages();
        $this->faqs();

        $this->command?->info('Content pages and FAQs seeded.');
    }

    private function pages(): void
    {
        ContentPage::updateOrCreate(['slug' => 'terms'], [
            'title' => 'Terms of Use',
            'body' => <<<'TEXT'
                These Terms govern your use of Inthes. By creating an account or browsing job listings, you agree to them.

                ## 1. Your account
                You may use Inthes as a job seeker, a recruiter, or both, from the same account. You are responsible for the accuracy of the information you add to your profile and for keeping your login secure.

                ## 2. Job seekers
                Applying to a job shares the relevant parts of your profile with that job's recruiter — never with anyone else, and never before you tap Apply. Smart Apply only asks for information a specific job actually requires.

                ## 3. Recruiters
                Job postings must be genuine, currently open roles at a real organisation. Recruiters may not use candidate contact details or profile information for anything other than evaluating that candidate for the posted role.

                ## 4. Subscriptions
                Paid plans renew automatically for the billing period shown at checkout until cancelled. You can switch back to the Free plan at any time from My Plan in your profile; downgrades take effect at the end of the current billing period.

                ## 5. Conduct
                Discriminatory, misleading, or abusive listings, messages, or profile content are not allowed and may result in account suspension.

                ## 6. Liability
                Inthes connects job seekers and recruiters but is not a party to any employment relationship, offer, or agreement made between them.

                ## Contact
                Questions about these Terms? Reach us from Contact Us in your profile.
                TEXT,
        ]);

        ContentPage::updateOrCreate(['slug' => 'privacy'], [
            'title' => 'Privacy Policy',
            'body' => <<<'TEXT'
                This Privacy Policy explains what Inthes collects and how it's used, for both job seekers and recruiters.

                ## What we collect
                Job seekers: the profile details you add (name, contact, education, experience, skills, resume) and your application history.

                Recruiters: your company profile (name, industry, contact details) and the jobs you post.

                ## How your profile is shared
                A job seeker's profile is shared with an employer only when that job seeker applies — never before, and never with any other employer. A recruiter's company profile is visible to any job seeker viewing that recruiter's postings.

                ## How we use your data
                To match job seekers with relevant listings, to let recruiters review applicants for their own postings, and to run Smart Apply — filling in only the fields a given job actually asks for.

                ## Storage
                Your profile and application data are stored securely and kept for as long as your account is active.

                ## Your choices
                You can edit or remove most profile fields at any time from your Profile tab, and request account deletion via Contact Us.

                ## Contact
                For any data request or privacy question, reach us from Contact Us in your profile.
                TEXT,
        ]);

        ContentPage::updateOrCreate(['slug' => 'about'], [
            'title' => 'About Us',
            'body' => <<<'TEXT'
                Inthes is built for one job: getting healthcare professionals hired faster, without the paperwork.

                ## Why we exist
                Hospitals, clinics, diagnostic labs, and nursing homes hire constantly, but the process is usually slowed down by long forms and manual screening on both sides. Inthes fixes that with Smart Apply — a candidate fills in their profile once, and every application after that is a single tap.

                ## For job seekers
                Browse every healthcare opening as a guest, build your profile at your own pace, and apply to the roles that fit your qualification, experience, and preferred location — with recruiters only seeing your details once you apply.

                ## For recruiters
                Post a role in minutes, tell Smart Apply exactly which details matter for that job, and review applicants who already meet your requirements — no unqualified inbox to sort through.

                ## Where we're headed
                We're growing Inthes city by city across India's healthcare sector, with the same principle throughout: less friction, faster hiring, for both sides of the table.
                TEXT,
        ]);

        ContentPage::updateOrCreate(['slug' => 'contact'], [
            'title' => 'Contact Us',
            'body' => "Have a question about your account, an application, or a job posting? We're here to help.",
            'meta' => [
                'email' => 'support@inthes.app',
                'phone' => '+919876543210',
                'phone_display' => '+91 98765 43210',
                'hours' => 'Mon–Sat, 9 AM – 7 PM IST',
            ],
        ]);
    }

    private function faqs(): void
    {
        $faqs = [
            [
                'question' => 'How does Smart Apply work?',
                'answer' => "When you apply, Smart Apply only asks for the profile fields that job actually requires, using what's already in your profile wherever possible — so most applications are a single tap.",
            ],
            [
                'question' => 'Who can see my profile?',
                'answer' => "Nobody, until you apply to a job — your profile is then shared only with that job's recruiter, never anyone else.",
            ],
            [
                'question' => 'How do I switch between job seeker and recruiter?',
                'answer' => "Use the Job Seeker / Recruiter switch on the Home tab — it's the same account either way, just a different view.",
            ],
            [
                'question' => 'How do I cancel a paid plan?',
                'answer' => 'Open My Plan in your Profile tab and choose the Free plan — it takes effect at the end of your current billing period.',
            ],
            [
                'question' => 'How do I delete my account?',
                'answer' => "Contact support using the button below and we'll take care of it.",
            ],
        ];

        foreach ($faqs as $index => $faq) {
            Faq::updateOrCreate(
                ['question' => $faq['question']],
                ['answer' => $faq['answer'], 'sort_order' => $index, 'is_active' => true],
            );
        }
    }
}
