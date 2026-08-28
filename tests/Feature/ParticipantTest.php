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
        ->and($quiz->fresh()->current_question_index)->toBe(0);
});

test('entering a quiz uuid redirects to the join page', function () {
    $quiz = makeQuizWithQuestions();

    $this->post(route('quiz.enter.submit'), [
        'uuid' => $quiz->uuid,
    ])->assertRedirect(route('quiz.join', $quiz->uuid));
});

test('the public display shows a waiting lobby with a join qr code', function () {
    $quiz = makeQuizWithQuestions();

    $this->get(route('quiz.play.show', $quiz->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Quiz/Play')
            ->where('status', 'waiting')
            ->where('title', $quiz->title)
            ->has('qrSvg')
            ->has('joinUrl')
        );
});
