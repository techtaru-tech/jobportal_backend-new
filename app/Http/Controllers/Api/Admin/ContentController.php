<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\ContentPage;
use App\Models\Faq;
use App\Services\AdminAuditor;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Support & Legal — the admin side of `GET /content` (§ ContentController).
 *
 * Two kinds of content, one screen's worth of editing each:
 *
 *  - **Pages** (`content_pages`): Terms, Privacy Policy, About Us, Contact
 *    Us. Fixed set, by design — the app has one named screen per page, so
 *    "add a new page" would need an app release regardless of what this API
 *    allowed. [SLUGS] is the single source of truth for which four exist.
 *  - **FAQs** (`faqs`): an open-ended list for the Help & Support screen —
 *    add, edit, retire, and reorder freely, the same shape as option-list
 *    items.
 *
 * Every write is audited, the same as `organisation.verify` or
 * `option_list.update` — this is public-facing legal copy, and six months
 * from now "who changed the Privacy Policy and when" should be answerable
 * without grepping deploy logs.
 */
class ContentController extends ApiController
{
    /** The only pages the app knows how to render — see the class docblock. */
    public const SLUGS = ['terms', 'privacy', 'about', 'contact'];

    public function __construct(private readonly AdminAuditor $auditor) {}

    // ── pages ────────────────────────────────────────────────────────────

    /** GET /admin/content/pages */
    public function pages(): JsonResponse
    {
        $bySlug = ContentPage::all()->keyBy('slug');

        // Every slug in the list, even one no row exists for yet (a fresh
        // database before `ContentSeeder` runs) — the admin screen always
        // shows all four editable cards rather than however many happen to
        // have rows.
        $pages = collect(self::SLUGS)->map(fn (string $slug) => $this->pageRow(
            $bySlug->get($slug) ?? new ContentPage(['slug' => $slug, 'title' => ucfirst($slug)]),
        ));

        return ApiResponse::data($pages->all());
    }

    /** GET /admin/content/pages/{slug} */
    public function showPage(string $slug): JsonResponse
    {
        return ApiResponse::data($this->pageRow($this->findPage($slug)));
    }

    /**
     * PATCH /admin/content/pages/{slug}
     *
     * `meta` is only ever read by the `contact` page today, but this accepts
     * it for any slug rather than special-casing one — the validation is the
     * same either way, and a future page with structured fields costs
     * nothing extra here.
     */
    public function updatePage(Request $request, string $slug): JsonResponse
    {
        if (! in_array($slug, self::SLUGS, true)) {
            throw new NotFoundHttpException('That page does not exist.');
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:150'],
            'body' => ['nullable', 'string', 'max:20000'],
            'meta' => ['nullable', 'array'],
        ]);

        $page = ContentPage::firstOrNew(['slug' => $slug], ['title' => ucfirst($slug)]);
        $before = ['title' => $page->title, 'body' => $page->body, 'meta' => $page->meta];

        $page->fill($validated)->save();

        $changes = AdminAuditor::diff($before, [
            'title' => $page->title,
            'body' => $page->body,
            'meta' => $page->meta,
        ]);

        if ($changes !== []) {
            $this->auditor->log(
                action: 'content.page.update',
                summary: "Updated the {$page->title} page",
                subjectType: 'ContentPage',
                subjectId: $slug,
                changes: $changes,
            );
        }

        return ApiResponse::data($this->pageRow($page), 'Saved.');
    }

    // ── faqs ─────────────────────────────────────────────────────────────

    /** GET /admin/content/faqs — every FAQ, active or not, editorial order. */
    public function faqs(): JsonResponse
    {
        $faqs = Faq::ordered()->get()->map(fn (Faq $faq) => $this->faqRow($faq));

        return ApiResponse::data($faqs->all());
    }

    /** POST /admin/content/faqs */
    public function storeFaq(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:200'],
            'answer' => ['required', 'string', 'max:2000'],
        ]);

        $faq = Faq::create([
            ...$validated,
            // New questions land last — an admin reordering a list they can
            // already see is a much better default than guessing where a
            // brand-new one should slot in.
            'sort_order' => (int) Faq::max('sort_order') + 1,
            'is_active' => true,
        ]);

        $this->auditor->log(
            action: 'content.faq.create',
            summary: "Added an FAQ: “{$faq->question}”",
            subjectType: 'Faq',
            subjectId: (string) $faq->id,
        );

        return ApiResponse::data($this->faqRow($faq), 'Added.', 201);
    }

    /** PATCH /admin/content/faqs/{faqId} */
    public function updateFaq(Request $request, int $faqId): JsonResponse
    {
        $faq = $this->findFaq($faqId);

        $validated = $request->validate([
            'question' => ['sometimes', 'string', 'max:200'],
            'answer' => ['sometimes', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $before = ['question' => $faq->question, 'answer' => $faq->answer, 'is_active' => $faq->is_active];

        $faq->fill($validated)->save();

        $changes = AdminAuditor::diff($before, [
            'question' => $faq->question,
            'answer' => $faq->answer,
            'is_active' => $faq->is_active,
        ]);

        if ($changes !== []) {
            $this->auditor->log(
                action: 'content.faq.update',
                summary: "Updated the FAQ “{$faq->question}”",
                subjectType: 'Faq',
                subjectId: (string) $faq->id,
                changes: $changes,
            );
        }

        return ApiResponse::data($this->faqRow($faq), 'Saved.');
    }

    /**
     * DELETE /admin/content/faqs/{faqId}
     *
     * Hard delete, unlike an option-list item: an FAQ has no other table
     * referencing it, so there is nothing to orphan and no "still in use by
     * N records" caveat to report. Turning `is_active` off is what "keep the
     * text but hide it" is for.
     */
    public function destroyFaq(int $faqId): JsonResponse
    {
        $faq = $this->findFaq($faqId);
        $question = $faq->question;
        $faq->delete();

        $this->auditor->log(
            action: 'content.faq.delete',
            summary: "Removed the FAQ “{$question}”",
            subjectType: 'Faq',
            subjectId: (string) $faqId,
        );

        return ApiResponse::message('Removed.');
    }

    /**
     * PUT /admin/content/faqs/reorder
     *
     * Mirrors `OptionListController::reorder()` — the app renders FAQs in
     * array order and the most commonly asked question belongs first, so
     * that order has to be settable and has to survive a database round trip.
     */
    public function reorderFaqs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => [Rule::exists('faqs', 'id')],
        ]);

        foreach ($validated['ids'] as $index => $id) {
            Faq::whereKey($id)->update(['sort_order' => $index]);
        }

        $this->auditor->log(
            action: 'content.faq.reorder',
            summary: 'Reordered the FAQ list',
            subjectType: 'Faq',
        );

        return ApiResponse::message('Order saved.');
    }

    // ── shared shape ─────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function pageRow(ContentPage $page): array
    {
        return [
            'slug' => $page->slug,
            'title' => $page->title,
            'body' => $page->body,
            'meta' => (object) ($page->meta ?? []),
            'updated_at' => $page->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function faqRow(Faq $faq): array
    {
        return [
            'id' => $faq->id,
            'question' => $faq->question,
            'answer' => $faq->answer,
            'sort_order' => $faq->sort_order,
            'is_active' => $faq->is_active,
        ];
    }

    private function findPage(string $slug): ContentPage
    {
        if (! in_array($slug, self::SLUGS, true)) {
            throw new NotFoundHttpException('That page does not exist.');
        }

        return ContentPage::where('slug', $slug)->firstOr(
            fn () => new ContentPage(['slug' => $slug, 'title' => ucfirst($slug)]),
        );
    }

    private function findFaq(int $faqId): Faq
    {
        $faq = Faq::find($faqId);

        if (! $faq) {
            throw new NotFoundHttpException('That FAQ was not found.');
        }

        return $faq;
    }
}
