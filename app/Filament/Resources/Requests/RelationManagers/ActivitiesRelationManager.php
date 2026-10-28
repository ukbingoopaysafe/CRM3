<?php

namespace App\Filament\Resources\Requests\RelationManagers;

use App\Enums\ActivityChannel;
use App\Filament\Resources\Requests\Concerns\HasRequestLifecycleActions;
use App\Models\Base\Activity;
use App\Models\Base\Request;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The communication trail of one request. Activity records are immutable, so
 * the table offers no edit or delete action (D-055).
 */
class ActivitiesRelationManager extends RelationManager
{
    use HasRequestLifecycleActions {
        activityAction as baseActivityAction;
    }

    protected static string $relationship = 'activities';

    protected static ?string $title = 'سجل التواصل';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('actor')->latest('occurred_at'))
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('وقت التواصل')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('channel')
                    ->label('الوسيلة')
                    ->badge()
                    ->formatStateUsing(fn (ActivityChannel $state): string => $state->getLabel()),

                TextColumn::make('contact_name_snapshot')
                    ->label('جهة الاتصال')
                    ->description(fn (Activity $record): string => $record->contact_party === 'broker' ? 'وسيط' : 'عميل'),

                TextColumn::make('result')
                    ->label('النتيجة')
                    ->wrap(),

                TextColumn::make('next_action')
                    ->label('الخطوة التالية')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('actor.name')
                    ->label('سجّله')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->headerActions([
                $this->activityAction(),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->emptyStateHeading('لا يوجد تواصل مسجل')
            ->emptyStateDescription('سجّل أول تواصل مكتمل مع جهة الاتصال ليظهر هنا.');
    }

    /**
     * The action form was built for a table row; here the record is the
     * request that owns the relation.
     */
    protected function activityAction(): Action
    {
        $owner = $this->getOwnerRecord();

        return $this->baseActivityAction()
            ->record($owner instanceof Request ? $owner : null)
            ->visible($owner instanceof Request && $owner->acceptsActivities());
    }
}
