<?php

namespace App\Filament\Resources\Aftercares\RelationManagers;

use App\Enums\ActivityChannel;
use App\Filament\Resources\Aftercares\Concerns\HasAftercareActions;
use App\Models\Base\Aftercare;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The interaction trail of one relationship. Interactions are immutable, so
 * there is no edit or delete action (D-059).
 */
class AftercareActivitiesRelationManager extends RelationManager
{
    use HasAftercareActions {
        recordActivityAction as baseRecordActivityAction;
    }

    protected static string $relationship = 'activities';

    protected static ?string $title = 'التفاعلات';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('recorder'))
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('الوقت')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('channel')
                    ->label('الوسيلة')
                    ->badge()
                    ->formatStateUsing(fn (ActivityChannel $state): string => $state->getLabel()),

                TextColumn::make('result')
                    ->label('النتيجة')
                    ->wrap(),

                TextColumn::make('referral_details')
                    ->label('إحالة أو فرصة')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('deal_id')
                    ->label('الصفقة')
                    ->prefix('#')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('recorder.name')
                    ->label('سجّله')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->headerActions([
                $this->activityAction(),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->emptyStateHeading('لا توجد تفاعلات بعد')
            ->emptyStateDescription('سجّل أول تفاعل لتظهر العلاقة في المتابعة.');
    }

    /**
     * The action form was built for a table row; here the record is the
     * relationship that owns the relation.
     */
    protected function activityAction(): Action
    {
        $owner = $this->getOwnerRecord();

        return $this->baseRecordActivityAction()
            ->record($owner instanceof Aftercare ? $owner : null);
    }
}
