<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DiagnosticResultResource\Pages;
use App\Models\DiagnosticResult;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class DiagnosticResultResource extends Resource
{
    protected static ?string $model = DiagnosticResult::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static \UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'dosen']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('user_id')->relationship('user', 'name')->required()->searchable(),
            Forms\Components\TextInput::make('speaking')->numeric()->minValue(0)->maxValue(100),
            Forms\Components\TextInput::make('grammar')->numeric()->minValue(0)->maxValue(100),
            Forms\Components\TextInput::make('vocabulary')->numeric()->minValue(0)->maxValue(100),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Mahasiswa')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('speaking')->sortable(),
                Tables\Columns\TextColumn::make('grammar')->sortable(),
                Tables\Columns\TextColumn::make('vocabulary')->sortable(),
                Tables\Columns\TextColumn::make('overall')->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state, 1)),
                Tables\Columns\TextColumn::make('attempt')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')->relationship('user', 'name')->label('Mahasiswa')->searchable(),
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Dari'),
                        Forms\Components\DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()->visible(fn () => auth()->user()?->hasRole('admin')),
                Tables\Actions\DeleteAction::make()->visible(fn () => auth()->user()?->hasRole('admin')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDiagnosticResults::route('/'),
        ];
    }
}
