<?php

namespace App\Filament\Resources\QuestionResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AnswersRelationManager extends RelationManager
{
    protected static string $relationship = 'answers';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Grid::make()
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('text')
                        ->label('Réponse')
                        ->required()
                        ->columnSpan(2),
                    Forms\Components\Toggle::make('is_correct')
                        ->label('Correcte ?')
                        ->inline(false)
                        ->columnSpan(1),
                    Forms\Components\TextInput::make('order')
                        ->label('Ordre')
                        ->numeric()
                        ->default(0),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('text')
            ->columns([
                Tables\Columns\TextColumn::make('text')
                    ->label('Réponse')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_correct')
                    ->label('Correcte')
                    ->boolean(),
                Tables\Columns\TextColumn::make('order')
                    ->label('Ordre')
                    ->sortable(),
            ]);
    }
}
