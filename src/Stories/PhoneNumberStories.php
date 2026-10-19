<?php

namespace Josephdlmd\FilamentStorybookPh\Stories;

use Closure;
use Filament\Forms\Components\TextInput;
use Josephdlmd\FilamentStorybook\Stories;
use Josephdlmd\FilamentStorybookPh\Contacts\PhoneNumber;

class PhoneNumberStories extends Stories
{
    public function title(): string
    {
        return 'Forms/Philippine phone number';
    }

    public function description(): string
    {
        return 'The phone field of `forms-contact-details` for Philippine numbers: typed any way, with spaces, dashes or brackets and with or without +63, checked and stored as +63 917 123 4567 by `PhoneNumber` (call it from the package). Basis: GOV.UK telephone numbers pattern (2026: accept any format); the +63 format is the owner\'s business standard (2026-10-12).';
    }

    public function examples(): array
    {
        return [PhoneNumber::class];
    }

    public function records(): array
    {
        return [1 => ['phone' => '+63 917 123 4567']];
    }

    /** A phone number: typed any way, stored as +63. */
    public function default(): TextInput
    {
        return TextInput::make('phone')
            ->label('Phone number')
            ->tel()
            ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                if (filled($value) && PhoneNumber::format($value) === null) {
                    $fail('Enter a phone number, like 0917 123 4567 or (02) 8123 4567');
                }
            }])
            ->dehydrateStateUsing(fn (?string $state): ?string => PhoneNumber::format($state))
            ->helperText('For example, 0917 123 4567')
            ->maxWidth('xs')
            ->required()
            ->validationMessages([
                'required' => 'Enter a phone number',
            ]);
    }
}
