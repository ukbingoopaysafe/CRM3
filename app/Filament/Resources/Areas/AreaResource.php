<?php

namespace App\Filament\Resources\Areas;

use App\Enums\Governorate;
use App\Filament\Concerns\ReportsDomainErrors;
use App\Models\Area;
use App\Models\User;
use App\Services\AreaService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * The single shared area directory (D-081). Creating needs the explicit
 * "manage areas" grant; archiving and restoring are manager-only (D-032,
 * D-067).
 */
class AreaResource extends Resource
{
    use ReportsDomainErrors;

    protected static ?string $model = Area::class;

    protected static ?string $slug = 'areas';

    protected static string|UnitEnum|null $navigationGroup = 'الإدارة';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $modelLabel = 'منطقة';

    protected static ?string $pluralModelLabel = 'المناطق';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManageAreas() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', Area::class) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات المنطقة')
                ->description('المنطقة تُدار مرة واحدة لصالح القسمين، وتُربط بالمحافظة قبل اختيارها في أي طلب.')
                ->columns(2)
                ->components([
                    TextInput::make('name')
                        ->label('اسم المنطقة')
                        ->required()
                        ->maxLength(120)
                        ->autofocus(),

                    Select::make('governorate')
                        ->label('المحافظة')
                        ->options(Governorate::options())
                        ->required()
                        ->selectablePlaceholder(false),

                    Toggle::make('is_active')
                        ->label('متاحة للاختيار في الطلبات الجديدة')
                        ->default(true)
                        ->helperText('إيقافها يخفيها من قوائم الطلبات الجديدة دون أرشفتها.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $self = new self;

        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['rentalRequests', 'resaleRequests']))
            ->columns([
                TextColumn::make('name')
                    ->label('المنطقة')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('governorate')
                    ->label('المحافظة')
                    ->badge()
                    ->sortable(),

                TextColumn::make('rental_requests_count')
                    ->label('طلبات الإيجار')
                    ->alignCenter(),

                TextColumn::make('resale_requests_count')
                    ->label('طلبات إعادة البيع')
                    ->alignCenter(),

                TextColumn::make('is_active')
                    ->label('متاحة')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'نعم' : 'لا')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),

                TextColumn::make('archived_at')
                    ->label('الأرشفة')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'مؤرشفة' : 'نشطة')
                    ->color(fn ($state): string => $state ? 'warning' : 'success'),

                TextColumn::make('created_at')
                    ->label('أُضيفت في')
                    ->date('Y-m-d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('governorate')
                    ->label('المحافظة')
                    ->options(Governorate::options()),

                SelectFilter::make('archived')
                    ->label('السجل')
                    ->options([
                        'active' => 'النشطة',
                        'archived' => 'المؤرشفة',
                    ])
                    ->default('active')
                    ->query(fn ($query, array $data) => match ($data['value'] ?? 'active') {
                        'archived' => $query->whereNotNull('archived_at'),
                        'active' => $query->whereNull('archived_at'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('تعديل')
                    ->visible(fn (Area $record): bool => $self->actor()?->can('update', $record) ?? false),

                Action::make('archive')
                    ->label('أرشفة')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('أرشفة المنطقة')
                    ->modalDescription('تختفي من قوائم الطلبات الجديدة، وتبقى ظاهرة في الطلبات القديمة. لا يمكن حذفها نهائيًا.')
                    ->visible(fn (Area $record): bool => ! $record->isArchived()
                        && ($self->actor()?->can('archive', $record) ?? false))
                    ->action(function (Area $record) use ($self): void {
                        $self->run(fn () => app(AreaService::class)->archive($record, $self->actor()));
                    }),

                Action::make('restore')
                    ->label('استعادة')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Area $record): bool => $record->isArchived()
                        && ($self->actor()?->can('restore', $record) ?? false))
                    ->action(function (Area $record) use ($self): void {
                        $self->run(fn () => app(AreaService::class)->restore($record, $self->actor()));
                    }),
            ])
            ->defaultSort('name')
            ->striped()
            ->emptyStateHeading('دليل المناطق فارغ')
            ->emptyStateDescription('أضف المناطق التشغيلية وربطها بالمحافظة لتظهر في الطلبات.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAreas::route('/'),
            'create' => Pages\CreateArea::route('/create'),
            'edit' => Pages\EditArea::route('/{record}/edit'),
        ];
    }

    private function actor(): ?User
    {
        return auth()->user();
    }

    private function run(\Closure $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            $this->reportDomainError($exception);
        }
    }
}
