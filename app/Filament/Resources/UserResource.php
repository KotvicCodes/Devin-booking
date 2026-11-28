<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user';

    protected static string|\UnitEnum|null $navigationGroup = 'Správa';

    // Sets the plural and singular names for the resource in the navigation menu
    protected static ?string $modelLabel = 'Administrátor';

    protected static ?string $pluralModelLabel = 'Administrátoři';

    protected static ?string $navigationLabel = 'Správa administrátorů';

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),

                TextInput::make('password')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn ($state) => filled($state))
                    ->maxLength(255),

                Section::make('Systémová oprávnění')
                    ->columnSpanFull()
                    ->description('Toggle the specific actions this staff member is allowed to perform.')
                    ->schema([
                        Toggle::make('is_super_admin')
                            ->label('Super administrátor')
                            ->helperText('Grants absolute access to all system features.')
                            // Only an existing super admin may see or grant the super-admin flag.
                            // The value is also enforced server-side in CreateUser/EditUser so a
                            // crafted request cannot bypass this UI guard.
                            ->visible(fn (): bool => (bool) (auth()->user()?->is_super_admin))
                            ->columnSpanFull(),

                        Toggle::make('can_manage_users')
                            ->label('Může spravovat uživatele'),

                        Toggle::make('can_view_bookings')
                            ->label('Může zobrazit rezervace'),

                        Toggle::make('can_edit_bookings')
                            ->label('Může upravovat rezervace'),

                        Toggle::make('can_manage_financials')
                            ->label('Může spravovat finance'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable(),
                TextColumn::make('email')
                    ->sortable()
                    ->searchable(),
                IconColumn::make('is_super_admin')
                    ->boolean()
                    ->label('Super administrátor'),
                IconColumn::make('can_manage_users')
                    ->boolean()
                    ->label('Správa uživatelů'),
                IconColumn::make('can_view_bookings')
                    ->boolean()
                    ->label('Zobrazení rezervací'),
                IconColumn::make('can_edit_bookings')
                    ->boolean()
                    ->label('Úprava rezervací'),
                IconColumn::make('can_manage_financials')
                    ->boolean()
                    ->label('Správa financí'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(function (User $record): bool {
                        $user = auth()->user();

                        if (! $user) {
                            return false;
                        }

                        if ($record->is_super_admin && ! $user->is_super_admin) {
                            return false;
                        }

                        return true;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
