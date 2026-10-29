<?php

namespace App\Filament\Resources\Requests\Concerns;

use App\Enums\Department;
use App\Enums\Outcome;
use App\Filament\Resources\Rental\Deals\DealResource as RentalDealResource;
use App\Filament\Resources\Resale\Deals\DealResource as ResaleDealResource;
use App\Models\Base\Request;
use Filament\Actions\Action;

/**
 * The only doorway from a request to a deal. It appears once the request is
 * closed successfully and has no deal yet, and it opens the deal form with the
 * request preselected (D-047, D-058).
 */
trait HasDealAction
{
    protected function dealAction(): Action
    {
        return Action::make('openDeal')
            ->label('تسجيل الصفقة')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (Request $record): bool => $record->isClosed()
                && $record->outcome === Outcome::Successful
                && $record->deal === null)
            ->url(fn (Request $record): string => self::dealResourceFor($record)::getUrl('create', [
                'request_id' => $record->getKey(),
            ]));
    }

    /**
     * @return class-string
     */
    private static function dealResourceFor(Request $record): string
    {
        return $record::department() === Department::Rental
            ? RentalDealResource::class
            : ResaleDealResource::class;
    }
}
