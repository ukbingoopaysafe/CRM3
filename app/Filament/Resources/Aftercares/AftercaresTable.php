<?php

namespace App\Filament\Resources\Aftercares;

use App\Models\Base\Aftercare;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The continuing relationships of one department. There are no states, so the
 * list is ordered by the only operational field: the next follow-up (D-059).
 */
final class AftercaresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['customer', 'owner']))
            ->columns([
                TextColumn::make('customer.name')
                    ->label('العميل')
                    ->description(fn (Aftercare $record): ?string => $record->customer?->phone)
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('owner.name')
                    ->label('المسؤول')
                    ->placeholder('—'),

                TextColumn::make('next_follow_up_at')
                    ->label('المتابعة القادمة')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('بلا موعد')
                    ->color(fn (Aftercare $record): string => $record->next_follow_up_at?->isPast() ? 'danger' : 'gray')
                    ->sortable(),

                TextColumn::make('activities_count')
                    ->label('التفاعلات')
                    ->counts('activities')
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label('بدأت في')
                    ->date('Y-m-d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('follow_up')
                    ->label('موعد المتابعة')
                    ->options([
                        'overdue' => 'متأخرة',
                        'today' => 'اليوم',
                        'week' => 'خلال ٧ أيام',
                        'later' => 'بعد ذلك',
                        'none' => 'بلا موعد',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'overdue' => $query->overdue(),
                        'today' => $query->dueToday(),
                        'week' => $query->dueWithinSevenDays(),
                        'later' => $query->dueLater(),
                        'none' => $query->whereNull('next_follow_up_at'),
                        default => $query,
                    }),

                SelectFilter::make('owner_user_id')
                    ->label('المسؤول')
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make()->label('عرض'),
            ])
            ->defaultSort('next_follow_up_at')
            ->striped()
            ->emptyStateHeading('لا توجد سجلات ما بعد الصفقة')
            ->emptyStateDescription('يبدأ السجل مع أول صفقة ناجحة لعميل معروف، أو عند بدء العلاقة من سجل العميل.');
    }
}
