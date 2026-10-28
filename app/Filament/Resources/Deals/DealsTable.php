<?php

namespace App\Filament\Resources\Deals;

use App\Enums\Currency;
use App\Models\Base\Deal;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The closed agreements of one department. Value and currency share a cell so
 * the number is never read without its unit.
 */
final class DealsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['customer', 'broker', 'creator']))
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->width('70px'),

                TextColumn::make('request_id')
                    ->label('الطلب')
                    ->prefix('#')
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('العميل')
                    ->placeholder('غير معروف')
                    ->description(fn (Deal $record): ?string => $record->customer?->phone)
                    ->weight('medium'),

                TextColumn::make('broker.name')
                    ->label('الوسيط')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('deal_date')
                    ->label('تاريخ الصفقة')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('agreed_total_value')
                    ->label('القيمة')
                    ->state(fn (Deal $record): string => number_format((float) $record->agreed_total_value, 2).' '.$record->currency->value)
                    ->sortable(),

                TextColumn::make('commissions_count')
                    ->label('بنود العمولة')
                    ->counts('commissions')
                    ->alignCenter(),

                TextColumn::make('unit_code')
                    ->label('كود الوحدة')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('creator.name')
                    ->label('سجّلها')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('currency')
                    ->label('العملة')
                    ->options(Currency::options()),
            ])
            ->recordActions([
                ViewAction::make()->label('عرض'),
            ])
            ->defaultSort('deal_date', 'desc')
            ->striped()
            ->emptyStateHeading('لا توجد صفقات بعد')
            ->emptyStateDescription('تُسجل الصفقة من طلب مغلق نتيجته ناجحة.');
    }
}
