<?php

use App\Enums\QuizStatus;
use App\Events\DisplayAttachmentUpdated;
use App\Models\Answer;
use App\Models\Display;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

function makeDisplayQuiz(?User $owner = null, int $questionCount = 2): Quiz
{
    $owner ??= User::factory()->create();
    $quiz = Quiz::factory()->for($owner)->create();

    for ($i = 1; $i <= $questionCount; $i++) {
        $question = Question::factory()->for($quiz)->create([
            'text' => "Question {$i}",
            'order' => $i,
        ]);

        Answer::factory()->for($question)->create([
            'text' => 'Bonne réponse',
            'order' => 1,
            'is_correct' => true,
        ]);
        Answer::factory()->for($question)->create([
            'text' => 'Mauvaise réponse',
            'order' => 2,
            'is_correct' => false,
        ]);
    }

    return $quiz->fresh(['questions.answers', 'user']);
}

test('the public display shows an idle screen when nothing is attached', function () {
    $display = Display::factory()->create(['name' => 'Salle principale']);

    $this->get(route('displays.show', $display->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Display/Show')
            ->where('display.name', 'Salle principale')
            ->where('display.uuid', $display->uuid)
            ->where('quiz', null)
        );
});

test('the public display shows a waiting lobby when a quiz is attached', function () {
    $quiz = makeDisplayQuiz();
    $display = Display::factory()->for($quiz->user)->create(['name' => 'Salle principale']);
    $display->displayable()->associate($quiz);
    $display->save();

    $this->get(route('displays.show', $display->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Display/Show')
            ->where('display.name', 'Salle principale')
            ->where('quiz.status', 'waiting')
            ->where('quiz.title', $quiz->title)
            ->where('quiz.answerClosesAt', null)
            ->where('quiz.answerDurationSeconds', 20)
            ->where('quiz.correctAnswerIds', [])
            ->has('quiz.qrSvg')
            ->has('quiz.joinUrl')
            ->where('quiz.recap', null)
        );
});

test('the public display stays up when the attached quiz has no questions', function () {
    $quiz = Quiz::factory()->create();
    $display = Display::factory()->for($quiz->user)->create();
    $display->displayable()->associate($quiz);
    $display->save();

    $this->get(route('displays.show', $display->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Display/Show')
            ->where('quiz.title', $quiz->title)
            ->where('quiz.total', 0)
        );
});

test('the public display hides correct answers while the window is open', function () {
    $quiz = makeDisplayQuiz();
    $quiz->update([
        'status' => QuizStatus::Live,
        'current_question_index' => 0,
        'question_started_at' => now(),
    ]);
    $display = Display::factory()->for($quiz->user)->create(['name' => 'Salle principale']);
    $display->displayable()->associate($quiz);
    $display->save();

    $this->get(route('displays.show', $display->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Display/Show')
            ->where('quiz.correctAnswerIds', [])
            ->missing('quiz.questions.0.answers.0.is_correct')
        );
});

test('the public display reveals the correct answers after the timer', function () {
    $quiz = makeDisplayQuiz();
    $quiz->update([
        'status' => QuizStatus::Live,
        'current_question_index' => 0,
        'question_started_at' => now(),
    ]);
    $display = Display::factory()->for($quiz->user)->create(['name' => 'Salle principale']);
    $display->displayable()->associate($quiz);
    $display->save();
    $correctId = $quiz->questions->first()->answers->firstWhere('is_correct', true)->id;

    $this->travel(21)->seconds();

    $this->get(route('displays.show', $display->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Display/Show')
            ->where('quiz.correctAnswerIds', [$correctId])
            ->missing('quiz.questions.0.answers.0.is_correct')
            ->where('quiz.recap', null)
        );
});

test('the public display shows a recap when the quiz is finished', function () {
    $quiz = makeDisplayQuiz();
    $quiz->update([
        'status' => QuizStatus::Finished,
        'current_question_index' => 1,
        'question_started_at' => null,
    ]);
    $display = Display::factory()->for($quiz->user)->create(['name' => 'Salle principale']);
    $display->displayable()->associate($quiz);
    $display->save();
    $firstCorrect = $quiz->questions->first()->answers->firstWhere('is_correct', true);

    $this->get(route('displays.show', $display->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Display/Show')
            ->where('quiz.recap.total', 2)
            ->where('quiz.recap.questions.0.text', $quiz->questions->first()->text)
            ->where('quiz.recap.questions.0.correctTexts.0', $firstCorrect->text)
            ->missing('quiz.recap.questions.0.selectedAnswerId')
        );
});

test('the former quiz projection url no longer exists', function () {
    $quiz = Quiz::factory()->create();

    $this->get('/quiz/'.$quiz->uuid)->assertNotFound();
});

test('guests cannot manage displays', function () {
    $this->get(route('displays.index'))->assertRedirect(route('login'));
});

test('the owner can create a display', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->post(route('displays.store'), ['name' => 'Salle principale'])
        ->assertRedirect();

    $this->assertDatabaseHas('displays', [
        'user_id' => $owner->id,
        'name' => 'Salle principale',
    ]);
});

test('the owner can attach a quiz to a display', function () {
    Event::fake([DisplayAttachmentUpdated::class]);

    $owner = User::factory()->create();
    $quiz = Quiz::factory()->for($owner)->create();
    $display = Display::factory()->for($owner)->create(['name' => 'Salle 1']);

    $this->actingAs($owner)
        ->put(route('displays.update', $display), [
            'name' => 'Salle 1',
            'quiz_id' => $quiz->id,
        ])
        ->assertRedirect(route('displays.edit', $display));

    expect($display->fresh()->displayable_id)->toBe($quiz->id)
        ->and($display->fresh()->displayable_type)->toBe(Quiz::class);

    Event::assertDispatched(DisplayAttachmentUpdated::class);
});

test('a display cannot be attached to another users quiz', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $display = Display::factory()->for($owner)->create(['name' => 'Salle 1']);
    $foreignQuiz = Quiz::factory()->for($other)->create();

    $this->actingAs($owner)
        ->from(route('displays.edit', $display))
        ->put(route('displays.update', $display), [
            'name' => 'Salle 1',
            'quiz_id' => $foreignQuiz->id,
        ])
        ->assertRedirect(route('displays.edit', $display))
        ->assertSessionHasErrors('quiz_id');

    expect($display->fresh()->displayable_id)->toBeNull();
});

test('another user cannot edit a display', function () {
    $display = Display::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other)
        ->get(route('displays.edit', $display))
        ->assertNotFound();
});

test('the quiz console lists attached displays', function () {
    $owner = User::factory()->create();
    $quiz = Quiz::factory()->for($owner)->create();
    $display = Display::factory()->for($owner)->create(['name' => 'Salle principale']);
    $display->displayable()->associate($quiz);
    $display->save();

    $question = Question::factory()->for($quiz)->create(['text' => 'Capitale de la France ?', 'order' => 1]);
    Answer::factory()->for($question)->create(['text' => 'Paris', 'order' => 1, 'is_correct' => true]);
    Answer::factory()->for($question)->create(['text' => 'Lyon', 'order' => 2, 'is_correct' => false]);

    $this->actingAs($owner)
        ->get(route('quizzes.console', $quiz))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Quizzes/Console')
            ->has('displays', 1)
            ->where('displays.0.name', 'Salle principale')
            ->has('availableDisplays', 1)
            ->where('availableDisplays.0.name', 'Salle principale')
            ->has('questions', 1)
            ->where('questions.0.text', 'Capitale de la France ?')
            ->where('questions.0.answers.0.is_correct', true)
            ->where('questions.0.answers.1.is_correct', false)
        );
});

test('the quiz console lists participants with scores', function () {
    $owner = User::factory()->create();
    $quiz = makeDisplayQuiz($owner, 2);
    $quiz->update([
        'status' => QuizStatus::Finished,
        'current_question_index' => 1,
    ]);

    $leader = \App\Models\Participant::factory()->for($quiz)->create(['nickname' => 'Alex']);
    $other = \App\Models\Participant::factory()->for($quiz)->create(['nickname' => 'Sam']);

    $firstCorrect = $quiz->questions->first()->answers->firstWhere('is_correct', true);
    $secondCorrect = $quiz->questions->last()->answers->firstWhere('is_correct', true);
    $secondWrong = $quiz->questions->last()->answers->firstWhere('is_correct', false);

    $leader->answers()->create([
        'question_id' => $quiz->questions->first()->id,
        'answer_id' => $firstCorrect->id,
    ]);
    $leader->answers()->create([
        'question_id' => $quiz->questions->last()->id,
        'answer_id' => $secondCorrect->id,
    ]);
    $other->answers()->create([
        'question_id' => $quiz->questions->first()->id,
        'answer_id' => $firstCorrect->id,
    ]);
    $other->answers()->create([
        'question_id' => $quiz->questions->last()->id,
        'answer_id' => $secondWrong->id,
    ]);

    $this->actingAs($owner)
        ->get(route('quizzes.console', $quiz))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Quizzes/Console')
            ->has('participants', 2)
            ->where('participants.0.nickname', 'Alex')
            ->where('participants.0.score', 2)
            ->where('participants.0.score_total', 2)
            ->where('participants.1.nickname', 'Sam')
            ->where('participants.1.score', 1)
        );
});

test('the owner can attach a display from the quiz console', function () {
    Event::fake([DisplayAttachmentUpdated::class]);

    $owner = User::factory()->create();
    $quiz = Quiz::factory()->for($owner)->create();
    $display = Display::factory()->for($owner)->create(['name' => 'Salle 1']);

    $this->actingAs($owner)
        ->from(route('quizzes.console', $quiz))
        ->put(route('quizzes.display', $quiz), [
            'display_id' => $display->id,
        ])
        ->assertRedirect(route('quizzes.console', $quiz));

    expect($display->fresh()->displayable_id)->toBe($quiz->id)
        ->and($display->fresh()->displayable_type)->toBe(Quiz::class);

    Event::assertDispatched(DisplayAttachmentUpdated::class);
});

test('the owner can detach a display from the quiz console', function () {
    Event::fake([DisplayAttachmentUpdated::class]);

    $owner = User::factory()->create();
    $quiz = Quiz::factory()->for($owner)->create();
    $display = Display::factory()->for($owner)->create(['name' => 'Salle 1']);
    $display->displayable()->associate($quiz);
    $display->save();

    $this->actingAs($owner)
        ->from(route('quizzes.console', $quiz))
        ->put(route('quizzes.display', $quiz), [
            'display_id' => null,
        ])
        ->assertRedirect(route('quizzes.console', $quiz));

    expect($display->fresh()->displayable_id)->toBeNull();

    Event::assertDispatched(DisplayAttachmentUpdated::class);
});

test('a display belonging to another user cannot be attached from the console', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $quiz = Quiz::factory()->for($owner)->create();
    $foreignDisplay = Display::factory()->for($other)->create();

    $this->actingAs($owner)
        ->from(route('quizzes.console', $quiz))
        ->put(route('quizzes.display', $quiz), [
            'display_id' => $foreignDisplay->id,
        ])
        ->assertRedirect(route('quizzes.console', $quiz))
        ->assertSessionHasErrors('display_id');

    expect($foreignDisplay->fresh()->displayable_id)->toBeNull();
});
