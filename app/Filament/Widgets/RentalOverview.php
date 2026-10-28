<?php

namespace App\Filament\Widgets;

use App\Enums\Department;
use App\Services\SummaryMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The five rental indicators approved in D-057. The widget only appears for
 * users who work in the rental department and never mixes resale numbers.
 */
class RentalOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'مؤشرات الإيجار';

    public static function canView(): bool
    {
        return auth()->user()?->belongsToDepartment(Department::Rental) ?? false;
    }

    protected function getStats(): array
    {
        $metrics = app(SummaryMetrics::class)->for(Department::Rental);

        return [
            Stat::make('طلبات نشطة', $metrics['active'])
                ->description('قيد المتابعة الآن')
                ->color('success')
                ->icon('heroicon-o-clipboard-document-list'),

            Stat::make('متابعات متأخرة', $metrics['overdue'])
                ->description('تجاوزت موعدها')
                ->color($metrics['overdue'] > 0 ? 'danger' : 'gray')
                ->icon('heroicon-o-exclamation-triangle'),

            Stat::make('متابعات اليوم', $metrics['due_today'])
                ->description('حتى نهاية اليوم')
                ->color('warning')
                ->icon('heroicon-o-clock'),

            Stat::make('بلا عميل معروف', $metrics['without_customer'])
                ->description('مرتبطة بوسيط فقط')
                ->color('gray')
                ->icon('heroicon-o-user'),

            Stat::make('متوقفة مؤقتًا', $metrics['paused'])
                ->description('بانتظار الاستئناف')
                ->color('info')
                ->icon('heroicon-o-pause-circle'),
        ];
    }
}
