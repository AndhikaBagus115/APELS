<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModuleResource\Pages;
use App\Models\Module;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ModuleResource extends Resource
{
    protected static ?string $model = Module::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-book-open';
    protected static \UnitEnum|string|null $navigationGroup = 'Konten';
    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'dosen']) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        $pathKeys = array_keys(config('learning_paths', []));
        $pathOptions = array_combine($pathKeys, array_map(
            fn ($key) => config("learning_paths.{$key}.label", $key), $pathKeys
        ));

        return $schema->components([
            Forms\Components\TextInput::make('title')->required()->maxLength(255),
            Forms\Components\Textarea::make('description')->maxLength(5000)->rows(3),
            Forms\Components\Select::make('path_key')->label('Learning Path')
                ->options($pathOptions)->required()->searchable(),
            Forms\Components\Select::make('level')
                ->options(['basic'=>'Basic','intermediate'=>'Intermediate','advanced'=>'Advanced','professional'=>'Professional'])
                ->required(),
            Forms\Components\Select::make('tag')
                ->options(['basic'=>'Basic','advanced'=>'Advanced'])->required(),
            Forms\Components\TextInput::make('order_index')->numeric()->default(0)->minValue(0),
            Forms\Components\KeyValue::make('content')->label('Content (JSON)'),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->sortable()->limit(50),
                Tables\Columns\TextColumn::make('path_key')->label('Path')->badge(),
                Tables\Columns\TextColumn::make('level')->badge(),
                Tables\Columns\TextColumn::make('tag'),
                Tables\Columns\TextColumn::make('order_index')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d M Y')->sortable(),
            ])
            ->defaultSort('order_index')
            ->filters([
                Tables\Filters\SelectFilter::make('level')
                    ->options(['basic'=>'Basic','intermediate'=>'Intermediate','advanced'=>'Advanced','professional'=>'Professional']),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListModules::route('/'),
            'create' => Pages\CreateModule::route('/create'),
            'edit'   => Pages\EditModule::route('/{record}/edit'),
        ];
    }
}
