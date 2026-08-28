<?php

namespace App\Http\Controllers;

use App\Events\DisplayAttachmentUpdated;
use App\Models\Display;
use App\Models\Quiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DisplaysController extends Controller
{
    public function index(Request $request): Response
    {
        $displays = $request->user()
            ->displays()
            ->with('displayable')
            ->latest()
            ->get()
            ->map(fn (Display $display) => $this->hostPayload($display))
            ->values();

        return Inertia::render('Displays/Index', [
            'displays' => $displays,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Displays/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $display = $request->user()->displays()->create([
            'name' => $validated['name'],
        ]);

        return redirect()->route('displays.edit', $display);
    }

    public function edit(Request $request, Display $display): Response
    {
        $this->authorizeOwner($request, $display);

        $display->load('displayable');

        $quizzes = $request->user()
            ->quizzes()
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn (Quiz $quiz) => [
                'id' => $quiz->id,
                'title' => $quiz->title,
            ])
            ->values();

        return Inertia::render('Displays/Edit', [
            'display' => $this->hostPayload($display),
            'quizzes' => $quizzes,
        ]);
    }

    public function update(Request $request, Display $display): RedirectResponse
    {
        $this->authorizeOwner($request, $display);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'quiz_id' => [
                'nullable',
                'integer',
                Rule::exists('quizzes', 'id')->where('user_id', $request->user()->id),
            ],
        ]);

        $previousType = $display->displayable_type;
        $previousId = $display->displayable_id;

        $display->name = $validated['name'];

        if (! empty($validated['quiz_id'])) {
            $quiz = Quiz::query()
                ->where('id', $validated['quiz_id'])
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            $display->displayable()->associate($quiz);
        } else {
            $display->displayable()->dissociate();
        }

        $display->save();

        if ($previousType !== $display->displayable_type || (int) $previousId !== (int) $display->displayable_id) {
            event(new DisplayAttachmentUpdated(
                $display->uuid,
                $display->displayable_id ? 'attached' : 'detached',
            ));
        }

        return redirect()->route('displays.edit', $display);
    }

    public function destroy(Request $request, Display $display): RedirectResponse
    {
        $this->authorizeOwner($request, $display);

        $display->delete();

        return redirect()->route('displays.index');
    }

    protected function authorizeOwner(Request $request, Display $display): void
    {
        abort_if($display->user_id !== $request->user()?->id, 404);
    }

    /**
     * @return array{id: int, uuid: string, name: string, url: string, quiz_id: int|null, quiz_title: string|null}
     */
    protected function hostPayload(Display $display): array
    {
        $displayable = $display->displayable;
        $quiz = $displayable instanceof Quiz ? $displayable : null;

        return [
            'id' => $display->id,
            'uuid' => $display->uuid,
            'name' => $display->name,
            'url' => $display->url(),
            'quiz_id' => $quiz?->id,
            'quiz_title' => $quiz?->title,
        ];
    }
}
