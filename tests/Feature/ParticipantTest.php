<?php

use App\Enums\QuizStatus;
use App\Events\ParticipantJoined;
use App\Models\Answer;
use App\Models\Participant;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

function makeQuizWithQuestions(?User $owner = null, int $questionCount = 2): Quiz
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

    return $quiz->fresh(['questions.answers']);
}

beforeEach(function () {
    Event::fake([ParticipantJoined::class]);
});

test('a visitor can join a quiz with a nickname and receives a cookie', function () {
    $quiz = makeQuizWithQuestions();

    $response = $this->post(route('quiz.join.store', $quiz->uuid), [
        'nickname' => 'Alex',
    ]);

    $response->assertRedirect(route('quiz.participate', $quiz->uuid));
    $response->assertCookie(Participant::COOKIE);

    $this->assertDatabaseHas('participants', [
        'quiz_id' => $quiz->id,
        'nickname' => 'Alex',
    ]);

    Event::assertDispatched(ParticipantJoined::class);
});

test('a nickname already taken on the quiz is rejected', function () {
    $quiz = makeQuizWithQuestions();
    Participant::factory()->for($quiz)->create(['nickname' => 'Alex']);

    $this->from(route('quiz.join', $quiz->uuid))
        ->post(route('quiz.join.store', $quiz->uuid), [
            'nickname' => 'alex',
        ])
        ->assertRedirect(route('quiz.join', $quiz->uuid))
        ->assertSessionHasErrors('nickname');

    expect(Participant::where('quiz_id', $quiz->id)->count())->toBe(1);
});

test('an existing participant cookie skips a second registration', function () {
    $quiz = makeQuizWithQuestions();
    $participant = Participant::factory()->for($quiz)->create(['nickname' => 'Alex']);

    $this->withCookie(Participant::COOKIE, $participant->token)
        ->post(route('quiz.join.store', $quiz->uuid), [
            'nickname' => 'Other',
        ])
        ->assertRedirect(route('quiz.participate', $quiz->uuid));

    expect(Participant::where('quiz_id', $quiz->id)->count())->toBe(1);
});

test('a participant can submit an answer for the current question', function () {
    $quiz = makeQuizWithQuestions();
    $quiz->update([
        'status' => QuizStatus::Live,
        'current_question_index' => 0,
        'question_started_at' => now(),
    ]);

    $participant = Participant::factory()->for($quiz)->create(['nickname' => 'Alex']);
    $question = $quiz->questions->first();
    $answer = $question->answers->first();

    $this->withCookie(Participant::COOKIE, $participant->token)
        ->post(route('quiz.answers.store', $quiz->uuid), [
            'answer_id' => $answer->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('participant_answers', [
        'participant_id' => $participant->id,
        'question_id' => $question->id,
        'answer_id' => $answer->id,
    ]);
});

test('a participant cannot answer a question that is not current', function () {
    $quiz = makeQuizWithQuestions();
    $quiz->update([
        'status' => QuizStatus::Live,
        'current_question_index' => 0,
        'question_started_at' => now(),
    ]);

    $participant = Participant::factory()->for($quiz)->create(['nickname' => 'Alex']);
    $otherAnswer = $quiz->questions->last()->answers->first();

    $this->withCookie(Participant::COOKIE, $participant->token)
        ->from(route('quiz.participate', $quiz->uuid))
        ->post(route('quiz.answers.store', $quiz->uuid), [
            'answer_id' => $otherAnswer->id,
        ])
        ->assertSessionHasErrors('answer_id');

    $this->assertDatabaseCount('participant_answers', 0);
});

test('a participant cannot answer the same question twice', function () {
    $quiz = makeQuizWithQuestions();
    $quiz->update([
        'status' => QuizStatus::Live,
        'current_question_index' => 0,
        'question_started_at' => now(),
    ]);

    $participant = Participant::factory()->for($quiz)->create(['nickname' => 'Alex']);
    $answer = $quiz->questions->first()->answers->first();

    $this->withCookie(Participant::COOKIE, $participant->token)
        ->post(route('quiz.answers.store', $quiz->uuid), [
            'answer_id' => $answer->id,
        ])
        ->assertRedirect();

    $this->withCookie(Participant::COOKIE, $participant->token)
        ->from(route('quiz.participate', $quiz->uuid))
        ->post(route('quiz.answers.store', $quiz->uuid), [
            'answer_id' => $answer->id,
        ])
        ->assertSessionHasErrors('answer_id');

    $this->assertDatabaseCount('participant_answers', 1);
});

test('a participant cannot answer while the quiz is waiting', function () {
    $quiz = makeQuizWithQuestions();
    $participant = Participant::factory()->for($quiz)->create(['nickname' => 'Alex']);
    $answer = $quiz->questions->first()->answers->first();

    $this->withCookie(Participant::COOKIE, $participant->token)
        ->from(route('quiz.participate', $quiz->uuid))
        ->post(route('quiz.answers.store', $quiz->uuid), [
            'answer_id' => $answer->id,
        ])
        ->assertSessionHasErrors('answer_id');
});

test('the owner can view the participant list', function () {
    $owner = User::factory()->create();
    $quiz = makeQuizWithQuestions($owner);
    Participant::factory()->for($quiz)->create(['nickname' => 'Alex']);

    $this->actingAs($owner)
        ->get(route('quizzes.participants', $quiz))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Quizzes/Participants')
            ->has('participants', 1)
            ->where('participants.0.nickname', 'Alex')
        );
});

test('another user cannot view the participant list', function () {
    $quiz = makeQuizWithQuestions();
    $other = User::factory()->create();

    $this->actingAs($other)
        ->get(route('quizzes.participants', $quiz))
        ->assertNotFound();
});

test('non-owners cannot advance or reset a quiz', function () {
    $quiz = makeQuizWithQuestions();
    $other = User::factory()->create();

    $this->actingAs($other)
        ->post(route('quiz.play.advance', $quiz->uuid))
        ->assertForbidden();

    $this->actingAs($other)
        ->post(route('quiz.play.reset', $quiz->uuid))
        ->assertForbidden();
});

test('the owner can start the quiz from the waiting room', function () {
    $owner = User::factory()->create();
    $quiz = makeQuizWithQuestions($owner);

    $this->actingAs($owner)
        ->post(route('quiz.play.advance', $quiz->uuid))
        ->assertRedirect();

    expect($quiz->fresh()->status)->toBe(QuizStatus::Live)
        ->and($quiz->fresh()->current_question_index)->toBe(0)
        ->and($quiz->fresh()->question_started_at)->not->toBeNull();
});

test('entering a quiz uuid redirects to the join page', function () {
    $quiz = makeQuizWithQuestions();

    $this->post(route('quiz.enter.submit'), [
        'uuid' => $quiz->uuid,
    ])->assertRedirect(route('quiz.join', $quiz->uuid));
});

test('a participant cannot answer after the time has elapsed', function () {
    $quiz = makeQuizWithQuestions();
    $quiz->update([
        'status' => QuizStatus::Live,
        'current_question_index' => 0,
        'question_started_at' => now(),
    ]);

    $participant = Participant::factory()->for($quiz)->create(['nickname' => 'Alex']);
    $answer = $quiz->questions->first()->answers->first();

    $this->travel(21)->seconds();

    $this->withCookie(Participant::COOKIE, $participant->token)
        ->from(route('quiz.participate', $quiz->uuid))
        ->post(route('quiz.answers.store', $quiz->uuid), [
            'answer_id' => $answer->id,
        ])
        ->assertSessionHasErrors(['answer_id' => 'Le temps est écoulé.']);

    $this->assertDatabaseCount('participant_answers', 0);
});

test('the participate page includes the answer window closing time', function () {
    $quiz = makeQuizWithQuestions();
    $quiz->update([
        'status' => QuizStatus::Live,
        'current_question_index' => 0,
        'question_started_at' => now(),
    ]);
    $participant = Participant::factory()->for($quiz)->create(['nickname' => 'Alex']);

    $this->withCookie(Participant::COOKIE, $participant->token)
        ->get(route('quiz.participate', $quiz->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Quiz/Participate')
            ->where('answerDurationSeconds', 20)
            ->where('correctAnswerIds', [])
            ->where('recap', null)
            ->has('answerClosesAt')
            ->missing('question.answers.0.is_correct')
        );
});

test('the participate page reveals the correct answers after the timer', function () {
    $quiz = makeQuizWithQuestions();
    $quiz->update([
        'status' => QuizStatus::Live,
        'current_question_index' => 0,
        'question_started_at' => now(),
    ]);
    $participant = Participant::factory()->for($quiz)->create(['nickname' => 'Alex']);
    $correctId = $quiz->questions->first()->answers->firstWhere('is_correct', true)->id;

    $this->travel(21)->seconds();

    $this->withCookie(Participant::COOKIE, $participant->token)
        ->get(route('quiz.participate', $quiz->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Quiz/Participate')
            ->where('correctAnswerIds', [$correctId])
            ->missing('question.answers.0.is_correct')
        );
});

test('advancing to the next question restarts the answer window', function () {
    $this->freezeTime();
    $owner = User::factory()->create();
    $quiz = makeQuizWithQuestions($owner);

    $this->actingAs($owner)->post(route('quiz.play.advance', $quiz->uuid));
    $firstStart = $quiz->fresh()->question_started_at;

    $this->travel(5)->seconds();

    $this->actingAs($owner)->post(route('quiz.play.advance', $quiz->uuid));

    $fresh = $quiz->fresh();

    expect($fresh->current_question_index)->toBe(1)
        ->and($fresh->question_started_at?->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($fresh->question_started_at?->equalTo($firstStart))->toBeFalse();
});

test('finishing or resetting a quiz clears the answer window', function () {
    $owner = User::factory()->create();
    $quiz = makeQuizWithQuestions($owner, 1);

    $this->actingAs($owner)->post(route('quiz.play.advance', $quiz->uuid));
    expect($quiz->fresh()->question_started_at)->not->toBeNull();

    $this->actingAs($owner)->post(route('quiz.play.advance', $quiz->uuid));
    expect($quiz->fresh()->status)->toBe(QuizStatus::Finished)
        ->and($quiz->fresh()->question_started_at)->toBeNull();

    $this->actingAs($owner)->post(route('quiz.play.reset', $quiz->uuid));
    expect($quiz->fresh()->status)->toBe(QuizStatus::Waiting)
        ->and($quiz->fresh()->question_started_at)->toBeNull();
});

test('the participate page has no recap while the quiz is live', function () {
    $quiz = makeQuizWithQuestions();
    $quiz->update([
        'status' => QuizStatus::Live,
        'current_question_index' => 0,
        'question_started_at' => now(),
    ]);
    $participant = Participant::factory()->for($quiz)->create(['nickname' => 'Alex']);

    $this->withCookie(Participant::COOKIE, $participant->token)
        ->get(route('quiz.participate', $quiz->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Quiz/Participate')
            ->where('recap', null)
            ->where('score.current', 0)
            ->where('score.total', 2)
        );
});

test('the participate page score is zero while the quiz is waiting', function () {
    $quiz = makeQuizWithQuestions();
    $participant = Participant::factory()->for($quiz)->create(['nickname' => 'Alex']);

    $this->withCookie(Participant::COOKIE, $participant->token)
        ->get(route('quiz.participate', $quiz->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Quiz/Participate')
            ->where('score.current', 0)
            ->where('score.total', 2)
        );
});

test('the participate page scores settled questions without spoiling the current one', function () {
    $quiz = makeQuizWithQuestions();
    $quiz->update([
        'status' => QuizStatus::Live,
        'current_question_index' => 1,
        'question_started_at' => now(),
    ]);
    $participant = Participant::factory()->for($quiz)->create(['nickname' => 'Alex']);
    $firstQuestion = $quiz->questions->first();
    $secondQuestion = $quiz->questions->last();
    $correct = $firstQuestion->answers->firstWhere('is_correct', true);
    $secondCorrect = $secondQuestion->answers->firstWhere('is_correct', true);

    $participant->answers()->create([
        'question_id' => $firstQuestion->id,
        'answer_id' => $correct->id,
    ]);
    $participant->answers()->create([
        'question_id' => $secondQuestion->id,
        'answer_id' => $secondCorrect->id,
    ]);

    $this->withCookie(Participant::COOKIE, $participant->token)
        ->get(route('quiz.participate', $quiz->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Quiz/Participate')
            ->where('score.current', 1)
            ->where('score.total', 2)
            ->where('recap', null)
        );
});

test('the participate page does not increment score until the current question is revealed', function () {
    $quiz = makeQuizWithQuestions();
    $quiz->update([
        'status' => QuizStatus::Live,
        'current_question_index' => 0,
        'question_started_at' => now(),
    ]);
    $participant = Participant::factory()->for($quiz)->create(['nickname' => 'Alex']);
    $correct = $quiz->questions->first()->answers->firstWhere('is_correct', true);

    $participant->answers()->create([
        'question_id' => $quiz->questions->first()->id,
        'answer_id' => $correct->id,
    ]);

    $this->withCookie(Participant::COOKIE, $participant->token)
        ->get(route('quiz.participate', $quiz->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Quiz/Participate')
            ->where('score.current', 0)
            ->where('score.total', 2)
        );

    $this->travel(21)->seconds();

    $this->withCookie(Participant::COOKIE, $participant->token)
        ->get(route('quiz.participate', $quiz->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Quiz/Participate')
            ->where('score.current', 1)
            ->where('score.total', 2)
        );
});

test('the participate page shows a recap when the quiz is finished', function () {
    $quiz = makeQuizWithQuestions();
    $quiz->update([
        'status' => QuizStatus::Finished,
        'current_question_index' => 1,
        'question_started_at' => null,
    ]);
    $participant = Participant::factory()->for($quiz)->create(['nickname' => 'Alex']);
    $firstQuestion = $quiz->questions->first();
    $secondQuestion = $quiz->questions->last();
    $correct = $firstQuestion->answers->firstWhere('is_correct', true);
    $wrong = $secondQuestion->answers->firstWhere('is_correct', false);

    $participant->answers()->create([
        'question_id' => $firstQuestion->id,
        'answer_id' => $correct->id,
    ]);
    $participant->answers()->create([
        'question_id' => $secondQuestion->id,
        'answer_id' => $wrong->id,
    ]);

    $this->withCookie(Participant::COOKIE, $participant->token)
        ->get(route('quiz.participate', $quiz->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Quiz/Participate')
            ->where('recap.score', 1)
            ->where('recap.total', 2)
            ->where('score.current', 1)
            ->where('score.total', 2)
            ->where('recap.questions.0.isCorrect', true)
            ->where('recap.questions.0.selectedAnswerId', $correct->id)
            ->where('recap.questions.0.selectedText', $correct->text)
            ->where('recap.questions.1.isCorrect', false)
            ->where('recap.questions.1.selectedText', $wrong->text)
            ->has('recap.questions.0.correctTexts')
        );
});
