<?php

namespace Josephdlmd\FilamentStorybookPh\Stories;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Josephdlmd\FilamentStorybook\Attributes\Section as ScreenSection;
use Josephdlmd\FilamentStorybook\Stories;

class TaxRegistrationStories extends Stories
{
    public function title(): string
    {
        return 'Forms/Tax registration';
    }

    public function description(): string
    {
        return 'The company\'s tax registration, a section of `forms-settings-page` for an app that keeps Philippine books: its TIN (`forms-tin`), whether it is VAT-registered, and whether BIR has made it a top withholding agent, which decides the codes `forms-document-totals-with-vat` offers. The printout\'s header shows the TIN and the VAT registration (`actions-download-pdf`). Business standards from the owner (2026-10-10 to 2026-10-12).';
    }

    public function records(): array
    {
        return [1 => ['tin' => '123-456-789-000', 'vat' => 'vat', 'top_withholding_agent' => true]];
    }

    /** Add this section to the settings page's form, after the company's name. */
    #[ScreenSection]
    public function default(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Tax registration')
                    ->schema([
                        TextInput::make('tin')
                            ->label('TIN')
                            ->regex('/^[\s.-]*(\d[\s.-]*){12}$/')
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? implode('-', str_split(preg_replace('/\D/', '', $state), 3)) : null)
                            ->helperText('For example, 123-456-789-000')
                            ->maxWidth('xs')
                            ->required()
                            ->validationMessages([
                                'required' => 'Enter the TIN',
                                'regex' => 'Enter a TIN in the correct format: 12 digits, including the branch code',
                            ]),
                        Radio::make('vat')
                            ->label('VAT registration')
                            ->options([
                                'vat' => 'VAT-registered',
                                'non_vat' => 'Non-VAT',
                            ])
                            ->required(),
                        Checkbox::make('top_withholding_agent')
                            ->label('Top withholding agent'),
                    ]),
            ]);
    }
}
