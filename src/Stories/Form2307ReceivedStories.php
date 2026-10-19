<?php

namespace Josephdlmd\FilamentStorybookPh\Stories;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Model;
use Josephdlmd\FilamentStorybook\Documents\DocumentStatus;
use Josephdlmd\FilamentStorybook\Stories;

class Form2307ReceivedStories extends Stories
{
    public function title(): string
    {
        return 'Actions/Mark 2307 received';
    }

    public function description(): string
    {
        return 'Recording that a customer\'s BIR Form 2307 has arrived: a gray "Mark 2307 received" button on the View page of a payment the customer withheld tax from, until it is marked. It asks for the date the 2307 arrived (today or earlier) and keeps it with the payment, which then leaves the list of 2307s to collect (`reports-tax-withheld-by-customers`); the withholding is in the SAWT of the quarter it was paid either way. Basis: the owner\'s business standards (2026-10-18: customer withholding claimed in the quarter paid, with each 2307 collected to support it); Carbon button (one primary action; this one is gray); Carbon date picker.';
    }

    public function records(): array
    {
        return [1 => ['number' => '004516', 'status' => DocumentStatus::Posted, 'tax_withheld' => '27.31', 'vat_withheld' => '0.00', 'received_2307_on' => null]];
    }

    public function default(): Action
    {
        return Action::make('markForm2307Received')
            ->label('Mark 2307 received')
            ->color('gray')
            ->visible(fn (?Model $record): bool => $record?->status === DocumentStatus::Posted && blank($record?->received_2307_on)
                && (bccomp((string) $record->tax_withheld, '0', 2) === 1 || bccomp((string) $record->vat_withheld, '0', 2) === 1))
            ->modalHeading(fn (?Model $record): string => "Mark 2307 received for receipt {$record?->number}")
            ->modalDescription('Its withholding can then be claimed on the return for the quarter it was paid in.')
            ->schema([
                DatePicker::make('received_2307_on')
                    ->label('Date received')
                    ->default(now()->toDateString())
                    ->maxDate(now()->toDateString())
                    ->maxWidth('xs')
                    ->required()
                    ->validationMessages(['before_or_equal' => 'Date received must be today or earlier']),
            ])
            ->action(function (Model $record, array $data, Action $action): void {
                $record->update(['received_2307_on' => $data['received_2307_on']]);
                $action->success();
            })
            ->successNotificationTitle(fn (Model $record): string => "2307 for receipt {$record->number} marked received");
    }
}
