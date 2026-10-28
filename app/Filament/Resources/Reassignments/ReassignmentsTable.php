<?php

namespace App\Filament\Resources\Reassignments;

use App\Enums\ReassignmentStatus;
use App\Filament\Resources\Reassignments\Concerns\HasReassignmentDecisionActions;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The transfer requests of one department. Pending rows come first because
 * they are the only ones that need a decision (D-027).
 */
final class ReassignmentsTable
{
    use HasReassignmentDecisionActions;

    public static function configure(Table $table): Table
    {
        $self = new self;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'currentOwner', 'target', 'requester', 'decider',
            ]))
            ->columns([
                TextColumn::make('request_id')
                    ->label('الطلب')
                    ->prefix('#')
                    ->sortable(),

                TextColumn::make('currentOwner.name')
                    ->label('من')
                    ->placeholder('—'),

                TextColumn::make('target.name')
                    ->label('إلى')
                    ->weight('medium'),

                TextColumn::make('reason')
                    ->label('السبب')
                    ->wrap()
                    ->limit(80),

                TextColumn::make('requester.name')
                    ->label('مقدم الطلب')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('قُدم في')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('decision_note')
                    ->label('ملاحظة القرار')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(ReassignmentStatus::options())
                    ->default(ReassignmentStatus::Pending->value),

                SelectFilter::make('requested_by')
                    ->label('مقدم الطلب')
                    ->relationship('requester', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                $self->approveAction(),
                $self->rejectAction(),
                $self->cancelAction(),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->emptyStateHeading('لا توجد طلبات إعادة تعيين')
            ->emptyStateDescription('تُقدم الطلبات من صفحة الطلب أو مباشرة من المدير.');
    }
}
