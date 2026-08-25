<?php

namespace App\Http\Controllers\Api;

use App\Enums\ChatMessageStatus;
use App\Enums\ChatSender;
use App\Http\Resources\ChatMessageResource;
use App\Http\Resources\ConversationResource;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Notifier;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * §11 Chat — one conversation per application.
 *
 * This is the REST + polling build the spec offers as the alternative to
 * Firestore. Delivery-status transitions happen server-side when the recipient
 * fetches the thread, so no client has to remember to call anything.
 */
class ChatController extends ApiController
{
    public function __construct(private readonly Notifier $notifier) {}

    /**
     * GET /conversations (§12) — the Conversations screen, for either role.
     *
     * Every application is a thread, whether or not anyone has spoken yet, so
     * the list matches what each side already sees elsewhere: a candidate's
     * threads are their applications, a recruiter's are the applications
     * against their own postings. Threads with traffic float to the top; the
     * rest fall back to most-recently-applied.
     *
     * The preview line and unread badge come back with the row — building this
     * screen from `GET /conversations/{id}/messages` would be one full thread
     * fetch per row.
     */
    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();

        // Which inbox to show, from the tab the app is on — not from
        // `users.role`, which one account now holds both of. A person who
        // hires and job-hunts has two sets of threads and only ever wants to
        // see one of them at a time; without the parameter the server would
        // have to guess, and would guess wrong for half the screens.
        //
        // Defaults to the job-seeking side: that is what an account with no
        // postings has, and it is the tab the app opens on.
        $asRecruiter = $request->query('as') === 'recruiter';
        $otherSide = ($asRecruiter ? ChatSender::Recruiter : ChatSender::Candidate)->opposite();

        $query = $asRecruiter
            ? Application::query()->whereIn('job_posting_id', $user->jobPostings()->select('id'))
            : Application::query()->where('user_id', $user->id);

        $query
            ->with([
                'jobPosting.organisationRecord',
                'conversation' => fn ($conversation) => $conversation
                    ->with('latestMessage')
                    ->withCount(['messages as unread_count' => fn ($message) => $message
                        ->where('sender', $otherSide->value)
                        ->where('status', '!=', ChatMessageStatus::Read->value)]),
            ])
            // Sorting on the child table without dragging every message back.
            ->addSelect(['last_message_at' => Conversation::select('last_message_at')
                ->whereColumn('conversations.application_id', 'applications.id')
                ->limit(1)])
            ->orderByRaw('last_message_at IS NULL')
            ->orderByDesc('last_message_at')
            ->orderByDesc('applied_at');

        $paginator = $query->paginate($this->perPage($request));

        $paginator->setCollection(
            $paginator->getCollection()->map(
                fn (Application $application) => (new ConversationResource($application))
                    ->forRecruiter($asRecruiter)
                    ->resolve(),
            ),
        );

        return ApiResponse::paginated($paginator);
    }

    /** GET /conversations/{applicationId}/messages */
    public function index(Request $request, string $applicationId): JsonResponse
    {
        $application = $this->findApplication($request, $applicationId);
        $conversation = $this->conversation($application);
        $me = $this->sideFor($application, $request->user());

        // Opening the thread marks everything the other party sent as read.
        $conversation->messages()
            ->where('sender', $me->opposite()->value)
            ->whereIn('status', [ChatMessageStatus::Sent->value, ChatMessageStatus::Delivered->value])
            ->update(['status' => ChatMessageStatus::Read->value]);

        $messages = $conversation->messages()->get();

        return ApiResponse::data(ChatMessageResource::collection($messages))
            ->withHeaders(['X-Typing' => $conversation->isTyping($me->opposite()) ? '1' : '0']);
    }

    /** POST /conversations/{applicationId}/messages */
    public function store(Request $request, string $applicationId): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
        ]);

        $application = $this->findApplication($request, $applicationId);
        $conversation = $this->conversation($application);
        $sender = $this->sideFor($application, $request->user());

        $message = $conversation->messages()->create([
            'sender' => $sender->value,
            'text' => trim($validated['text']),
            'sent_at' => now(),
            'status' => ChatMessageStatus::Sent->value,
        ]);

        $conversation->forceFill([
            'last_message_at' => $message->sent_at,
            $sender->value.'_typing' => false,
        ])->save();

        $recipient = $sender === ChatSender::Recruiter
            ? $application->candidate
            : $application->jobPosting->recruiter;

        $this->notifier->newMessage($application, $message, $recipient);

        return ApiResponse::data(new ChatMessageResource($message), null, 201);
    }

    /**
     * GET|POST /conversations/{applicationId}/typing
     *
     * The spec calls out a typing indicator as something ChatService expects.
     * Firestore would give it for free; on REST it needs these two calls.
     */
    public function typing(Request $request, string $applicationId): JsonResponse
    {
        $application = $this->findApplication($request, $applicationId);
        $conversation = $this->conversation($application);
        $me = $this->sideFor($application, $request->user());

        if ($request->isMethod('post')) {
            $conversation->setTyping($me, $request->boolean('typing'));
        }

        return ApiResponse::data([
            'recruiter' => $conversation->isTyping(ChatSender::Recruiter),
            'candidate' => $conversation->isTyping(ChatSender::Candidate),
        ]);
    }

    /**
     * POST /conversations/{applicationId}/viewing
     *
     * Tells the server this side currently has the thread open, so
     * `Notifier::newMessage` can skip the push for whoever is already
     * watching messages arrive live — the chat screen re-sends this on every
     * poll tick while it's open and the flag expires on its own otherwise
     * (see `Conversation::isViewing`), so there's nothing to explicitly turn
     * off when a screen is killed rather than closed.
     */
    public function viewing(Request $request, string $applicationId): JsonResponse
    {
        $application = $this->findApplication($request, $applicationId);
        $conversation = $this->conversation($application);
        $me = $this->sideFor($application, $request->user());

        $conversation->setViewing($me, $request->boolean('viewing'));

        return ApiResponse::data(['ok' => true]);
    }

    private function conversation(Application $application): Conversation
    {
        return $application->conversation()->firstOrCreate([]);
    }

    /**
     * Which end of this thread the caller is standing at.
     *
     * Derived from the application, not from `users.role`: one account now
     * holds both sides of the marketplace, so the account's signup role says
     * nothing about which end of a given conversation this person is. A
     * recruiter who also applies for work would otherwise have had every
     * message they sent as a candidate filed as coming from the recruiter,
     * and their own unread badge counting their own messages.
     *
     * Applying to your own posting is refused at submit time, so no thread
     * has the same user at both ends.
     */
    private function sideFor(Application $application, User $user): ChatSender
    {
        return $application->user_id === $user->id
            ? ChatSender::Candidate
            : ChatSender::Recruiter;
    }

    /** Either party to the application may read and write; nobody else may. */
    private function findApplication(Request $request, string $applicationId): Application
    {
        $user = $request->user();

        $application = Application::with(['jobPosting', 'candidate'])
            ->where('reference', $applicationId)
            ->first();

        $isParticipant = $application && (
            $application->user_id === $user->id
            || $application->jobPosting->user_id === $user->id
        );

        if (! $isParticipant) {
            throw new NotFoundHttpException('That conversation was not found.');
        }

        return $application;
    }
}
