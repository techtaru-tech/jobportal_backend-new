<?php

namespace App\Http\Controllers\Api;

use App\Models\ContentPage;
use App\Models\Faq;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * GET /content
 *
 * The app's Terms, Privacy Policy, About Us and Contact Us screens, plus the
 * Help & Support FAQ list — everything that used to be hardcoded `Text()`
 * widgets in `TermsView`/`PrivacyPolicyView`/`AboutUsView`/`ContactUsView`/
 * `HelpSupportView`, in one payload, mirroring how `GET /config/options`
 * replaced the option lists MockDataProvider used to hardcode.
 *
 * Public and unauthenticated: legal/support copy is shown to guests too
 * (the Terms a guest agrees to at signup, in particular), and none of it is
 * personal.
 *
 * `pages` is keyed by slug, not a list — the app has one named screen per
 * page and wants `pages['terms']`, not "find the one where slug == terms" on
 * every read.
 */
class ContentController extends ApiController
{
    public function __invoke(): JsonResponse
    {
        $pages = ContentPage::all()->keyBy('slug')->map(fn (ContentPage $page) => [
            'slug' => $page->slug,
            'title' => $page->title,
            'body' => $page->body,
            'meta' => (object) ($page->meta ?? []),
            'updated_at' => $page->updated_at?->toIso8601String(),
        ]);

        $faqs = Faq::active()->ordered()->get(['question', 'answer'])
            ->map(fn (Faq $faq) => [
                'question' => $faq->question,
                'answer' => $faq->answer,
            ]);

        return ApiResponse::data([
            'pages' => (object) $pages->all(),
            'faqs' => $faqs->all(),
        ]);
    }
}
