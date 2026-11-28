<?php

namespace App\Filament\Pages;

// Aliases the Spatie settings class to prevent a naming collision with the Filament page class
use App\Settings\GeneralSettings as BlueprintSettings;
use Filament\Forms;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

// The class name must exactly match the file name (GeneralSettings.php) for Laravel's autoloader to find it
class GeneralSettings extends SettingsPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Obecné nastavení';

    protected static ?string $title = 'Obecné nastavení';

    protected static string|\UnitEnum|null $navigationGroup = 'Správa';

    protected static ?int $navigationSort = 100;

    // Binds the visual form directly to the aliased Spatie blueprint class
    protected static string $settings = BlueprintSettings::class;

    // Evaluates access permissions before rendering the page or placing the link in the sidebar
    public static function canAccess(): bool
    {
        return auth()->check();
    }

    // Constructs the form layout and defines the input fields for the settings page
    public function form(Schema $form): Schema
    {
        $readOnly = ! $this->canEdit();

        return $form
            ->schema([
                Section::make('Cenové úrovně')
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\TextInput::make('graduate_price')
                            ->numeric()
                            ->required()
                            ->suffix('Kč')
                            ->label('Cena absolvent')
                            ->disabled($readOnly),

                        Forms\Components\TextInput::make('student_price')
                            ->numeric()
                            ->required()
                            ->suffix('Kč')
                            ->label('Cena student')
                            ->disabled($readOnly),

                        Forms\Components\TextInput::make('child_price')
                            ->numeric()
                            ->required()
                            ->suffix('Kč')
                            ->label('Cena dítě')
                            ->disabled($readOnly),

                        Forms\Components\TextInput::make('external_price')
                            ->numeric()
                            ->required()
                            ->suffix('Kč')
                            ->label('Cena cizí')
                            ->disabled($readOnly),

                        Forms\Components\TextInput::make('dog_price')
                            ->numeric()
                            ->required()
                            ->suffix('Kč')
                            ->label('Cena pes')
                            ->disabled($readOnly),

                        Forms\Components\TextInput::make('wood_price')
                            ->numeric()
                            ->required()
                            ->suffix('Kč')
                            ->label('Cena dřevo')
                            ->disabled($readOnly),

                    ])->columns(6),

                Section::make('Bankovní údaje')
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\TextInput::make('bank_account_number')
                            ->required()
                            ->label('Číslo účtu')
                            ->columnSpan(2)
                            ->disabled($readOnly),

                        Forms\Components\TextInput::make('bank_code')
                            ->required()
                            ->label('Kód banky')
                            ->prefix('/')
                            ->disabled($readOnly),
                    ])->columns(3),
                Section::make('Obecné')
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\TextInput::make('bed_capacity')
                            ->numeric()
                            ->required()
                            ->suffix('lůžek')
                            ->label('Kapacita')
                            ->disabled($readOnly),

                        Forms\Components\TextInput::make('pending_window')
                            ->numeric()
                            ->required()
                            ->suffix('dní')
                            ->label('Okno na zaplacení záruky')
                            ->disabled($readOnly),

                        Forms\Components\TextInput::make('deposit_percentage')
                            ->numeric()
                            ->suffix('%')
                            ->required()
                            ->label('Záruka procenta')
                            ->disabled($readOnly),
                    ])->columns(3),
            ]);
    }

    public function canEdit(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->is_super_admin || $user?->can_manage_financials);
    }
}
