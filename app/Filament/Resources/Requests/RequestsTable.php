<?php

namespace App\Filament\Resources\Requests;

use App\Enums\ContactParty;
use App\Enums\Department;
use App\Enums\RequestStatus;
use App\Enums\Stage;
use App\Filament\Resources\Requests\Concerns\HasDealAction;
use App\Filament\Resources\Requests\Concerns\HasReassignmentActions;
use App\Filament\Resources\Requests\Concerns\HasRequestLifecycleActions;
use App\Models\Base\Request;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The working list of one department. Stage, status and outcome are shown
 * separately, and the follow-up filter mirrors the four buckets in D-054.
 */
final class RequestsTable
{
    use HasDealAction;
    use HasReassignmentActions;
    use HasRequestLifecycleActions;

    public static function configure(Table $table, Department $department): Table
    {
        $self = new self;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['customer', 'broker', 'owner', 'area']))
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->width('70px'),

                TextColumn::make('contact')
                    ->label('جهة الاتصال')
                    ->state(fn (Request $record): string => $record->contactName() ?? '—')
                    ->description(fn (Request $record): string => $record->contact_party === ContactParty::Broker ? 'وسيط' : 'عميل')
                    ->searchable(
                        condition: true,
                        query: fn (Builder $query, string $search): Builder => $query->search($search),
                    )
                    ->weight('medium'),

                TextColumn::make('stage')
                    ->label('المرحلة')
                    ->badge()
                    ->formatStateUsing(fn (Stage $state, Request $record): string => $state->labelFor($record::department()))
                    ->sortable(),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->sortable(),

                TextColumn::make('outcome')
                    ->label('النتيجة')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('next_follow_up_at')
                    ->label('المتابعة القادمة')
                    // A raw "Y-m-d H:i" string is two numeric runs, and RTL
                    // renders it as "14:19 2026-11-06". An Arabic date reads in
                    // one direction and needs no bidi fixing.
                    ->dateTime('j F Y')
                    ->placeholder('—')
                    ->color(fn (Request $record): string => $record->isActive()
                        && $record->next_follow_up_at?->isPast() ? 'danger' : 'gray')
                    ->sortable(),

                TextColumn::make('owner.name')
                    ->label('المسؤول')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('area.name')
                    ->label('المنطقة')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('governorate')
                    ->label('المحافظة')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('أُنشئ في')
                    ->date('Y-m-d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(RequestStatus::options())
                    ->default(RequestStatus::Active->value),

                SelectFilter::make('stage')
                    ->label('المرحلة')
                    ->options(Stage::options($department))
                    ->multiple(),

                SelectFilter::make('owner_user_id')
                    ->label('المسؤول')
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('follow_up')
                    ->label('موعد المتابعة')
                    ->options([
                        'overdue' => 'متأخرة',
                        'today' => 'اليوم',
                        'week' => 'خلال ٧ أيام',
                        'later' => 'بعد ذلك',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'overdue' => $query->overdue(),
                        'today' => $query->dueToday(),
                        'week' => $query->dueWithinSevenDays(),
                        'later' => $query->dueLater(),
                        default => $query,
                    }),
            ])
            ->recordActions([
                ViewAction::make()->iconButton()->label('عرض')->tooltip('عرض'),
                EditAction::make()->iconButton()->label('تعديل')->tooltip('تعديل'),
                $self->dealAction(),
                // The remaining moves are rare enough to live behind one menu,
                // so the row stays readable with ten possible actions.
                ActionGroup::make([
                    $self->stageAction(),
                    $self->pauseAction(),
                    $self->resumeAction(),
                    $self->closeAction(),
                    $self->requestReassignmentAction(),
                    $self->directReassignmentAction(),
                ])->icon('heroicon-m-ellipsis-horizontal')->tooltip('خيارات أخرى'),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->emptyStateHeading('لا توجد طلبات بعد')
            ->emptyStateDescription('استقبل أول طلب في هذا القسم ليظهر هنا.')
            ->emptyStateIcon('heroicon-o-document-text');
    }
}
