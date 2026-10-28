<?php

namespace App\Filament\Widgets;

use App\Enums\Department;
use App\Services\SummaryMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The five resale indicators approved in D-057, isolated from rental.
 */
class ResaleOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'مؤشرات إعادة البيع';

    public static function canView(): bool
    {
        return auth()->user()?->belongsToDepartment(Department::Resale) ?? false;
    }

    protected function getStats(): array
    {
        $metrics = app(SummaryMetrics::class)->for(Department::Resale);

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

            Stat::make('ما بعد الصفقة: متأخرة', $metrics['aftercare_overdue'])
                ->description('علاقات تجاوزت موعدها')
                ->color($metrics['aftercare_overdue'] > 0 ? 'danger' : 'gray')
                ->icon('heroicon-o-heart'),

            Stat::make('ما بعد الصفقة: اليوم', $metrics['aftercare_due_today'])
                ->description('حتى نهاية اليوم')
                ->color('warning')
                ->icon('heroicon-o-heart'),
        ];
    }
}
