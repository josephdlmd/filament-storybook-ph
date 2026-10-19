<?php

namespace Josephdlmd\FilamentStorybookPh\Stories;

use Filament\Forms\Components\TextInput;
use Josephdlmd\FilamentStorybook\Stories;

class TinInputStories extends Stories
{
    public function title(): string
    {
        return 'Forms/TIN';
    }

    public function description(): string
    {
        return 'Every Philippine Tax Identification Number a user types: the 9-digit TIN plus its 3-digit branch code, `000` for the head office. One field, an example in the hint and nothing more, so it fits under the field (Carbon forms: helper text no longer than the input), any spaces, dashes or dots accepted, and stored as `000-000-000-000`. On a resource form, add `->unique(ignoreRecord: true)` so each TIN is registered once; it compares the stored form, since the TIN is put in that form for checking as well as for saving. Basis: USWDS text input 2026 (one field, not split into sections; no placeholder); W3C WAI forms tutorial (forgiving of separators); GOV.UK hint wording ("For example, …"); USWDS\'s input mask failed WCAG 2.1 AA in its own tests, so no mask. The stored format is the owner\'s business standard.';
    }

    public function records(): array
    {
        return [1 => ['tin' => '123-456-789-000']];
    }

    public function default(): TextInput
    {
        return TextInput::make('tin')
            ->label('TIN (optional)')
            ->regex('/^[\s.-]*(\d[\s.-]*){12}$/')
            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? implode('-', str_split(preg_replace('/\D/', '', $state), 3)) : null)
            // Checked in the stored form too, so unique() matches "123456789000" against a saved "123-456-789-000".
            ->mutateStateForValidationUsing(fn (?string $state): ?string => preg_match('/^[\s.-]*(\d[\s.-]*){12}$/', (string) $state) ? implode('-', str_split(preg_replace('/\D/', '', $state), 3)) : $state)
            ->helperText('For example, 123-456-789-000')
            ->maxWidth('xs')
            ->validationMessages(['regex' => 'Enter a TIN in the correct format: 12 digits, including the branch code']);
    }

    /** A required TIN says what to do when it is left empty. */
    public function required(): TextInput
    {
        return TextInput::make('tin')
            ->label('TIN')
            ->regex('/^[\s.-]*(\d[\s.-]*){12}$/')
            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? implode('-', str_split(preg_replace('/\D/', '', $state), 3)) : null)
            // Checked in the stored form too, so unique() matches "123456789000" against a saved "123-456-789-000".
            ->mutateStateForValidationUsing(fn (?string $state): ?string => preg_match('/^[\s.-]*(\d[\s.-]*){12}$/', (string) $state) ? implode('-', str_split(preg_replace('/\D/', '', $state), 3)) : $state)
            ->helperText('For example, 123-456-789-000')
            ->maxWidth('xs')
            ->required()
            ->validationMessages([
                'required' => 'Enter the TIN',
                'regex' => 'Enter a TIN in the correct format: 12 digits, including the branch code',
            ]);
    }
}
