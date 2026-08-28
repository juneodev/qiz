<?php

namespace App\Enums;

enum QuizStatus: string
{
    case Waiting = 'waiting';
    case Live = 'live';
    case Finished = 'finished';
}
