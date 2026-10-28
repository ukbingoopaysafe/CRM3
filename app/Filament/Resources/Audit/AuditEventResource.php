<?php

namespace App\Filament\Resources\Audit;

use App\Enums\Department;
use App\Models\AuditEvent;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Append-only trail (D-061, D-071). Read-only by construction: no create,
 * edit or delete exists, and the query is scoped to what the actor may see.
 */
class AuditEventResource extends Resource
{
    protected static ?string $model = AuditEvent::class;

    protected static ?string $slug = 'audit';

    protected static string|UnitEnum|null $navigationGroup = 'الإدارة';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $modelLabel = 'حدث تدقيق';

    protected static ?string $pluralModelLabel = 'سجل التدقيق';

    protected static ?int $navigationSort = 20;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['actor'])
                ->visibleTo(auth()->user())
                ->latest('id'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('الوقت')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('event_type')
                    ->label('الحدث')
                    ->badge()
                    ->formatStateUsing(fn (string $state, AuditEvent $record): string => $record->summary()),

                TextColumn::make('department')
                    ->label('القسم')
                    ->badge()
                    ->placeholder('عام')
                    ->toggleable(),

                TextColumn::make('actor.name')
                    ->label('الفاعل')
                    ->placeholder('—'),

                TextColumn::make('auditable_type')
                    ->label('السجل')
                    ->formatStateUsing(fn (?string $state, AuditEvent $record): string => $state === null
                        ? '—'
                        : class_basename($state).' #'.$record->auditable_id),

                TextColumn::make('reason')
                    ->label('السبب')
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('event_type')
                    ->label('نوع الحدث')
                    ->options([
                        'created' => 'إنشاء',
                        'updated' => 'تعديل',
                        'archived' => 'أرشفة',
                        'restored' => 'استعادة',
                        'deleted' => 'حذف',
                        'stage_changed' => 'تغيير المرحلة',
                        'status_changed' => 'تغيير الحالة',
                        'reassigned' => 'إعادة تعيين',
                        'activity_logged' => 'تسجيل تواصل',
                        'deal_created' => 'إنشاء صفقة',
                    ]),

                SelectFilter::make('department')
                    ->label('القسم')
                    ->options(Department::options())
                    ->visible(fn (): bool => auth()->user()?->isManager() ?? false),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->emptyStateHeading('لا توجد أحداث بعد')
            ->emptyStateDescription('تُسجل الأحداث تلقائيًا عند إنشاء أو تعديل أي سجل.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditEvents::route('/'),
        ];
    }
}
