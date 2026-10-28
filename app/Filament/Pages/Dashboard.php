<?php

namespace App\Filament\Pages;

use App\Enums\Department;
use App\Filament\Resources\Rental\Customers\CustomerResource as RentalCustomerResource;
use App\Filament\Resources\Rental\Requests\RequestResource as RentalRequestResource;
use App\Filament\Resources\Resale\Customers\CustomerResource as ResaleCustomerResource;
use App\Filament\Resources\Resale\Requests\RequestResource as ResaleRequestResource;
use App\Models\Base\Request;
use App\Models\Rental\Request as RentalRequest;
use App\Models\Resale\Request as ResaleRequest;
use App\Services\SummaryMetrics;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The operations desk: one block per department the signed-in user may work
 * in, never mixed (D-020, D-057). It replaces the generic stats widgets so the
 * page can carry a real hierarchy - hero, metrics, then the two lists that need
 * a decision today.
 */
class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    /**
     * @var array<string, mixed>
     */
    public array $hero = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $boards = [];

    public function mount(): void
    {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        $departments = $user->departmentScope();
        $totals = ['active' => 0, 'overdue' => 0, 'due_today' => 0];

        foreach ($departments as $department) {
            $metrics = app(SummaryMetrics::class)->for($department);

            foreach (array_keys($totals) as $key) {
                $totals[$key] += $metrics[$key];
            }

            $this->boards[] = $this->board($department, $metrics);
        }

        $this->hero = [
            'name' => $user->name,
            'initials' => $this->initials($user->name),
            'role' => $user->isManager() ? 'مدير الشركة' : 'موظف',
            'departments' => implode('، ', array_map(
                fn (Department $department): string => $department->getLabel(),
                $departments,
            )),
            'date' => now()->translatedFormat('l، j F Y'),
            'active' => $totals['active'],
            'overdue' => $totals['overdue'],
            'due_today' => $totals['due_today'],
        ];
    }

    /**
     * The hero greets, so the page does not need a second title above it.
     */
    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * @param  array<string, int>  $metrics
     * @return array<string, mixed>
     */
    private function board(Department $department, array $metrics): array
    {
        [$model, $requestResource, $customerResource] = $department === Department::Rental
            ? [RentalRequest::class, RentalRequestResource::class, RentalCustomerResource::class]
            : [ResaleRequest::class, ResaleRequestResource::class, ResaleCustomerResource::class];

        $newThisWeek = $model::query()->where('created_at', '>=', now()->subDays(7))->count();

        return [
            'key' => $department->value,
            'label' => $department->getLabel(),
            'metrics' => $metrics,
            'new_this_week' => $newThisWeek,
            'overdue' => $this->overdueRows($model, $department, $requestResource),
            'latest' => $this->latestRows($model, $department, $requestResource),
            'urls' => [
                'requests' => $requestResource::getUrl('index'),
                'customers' => $customerResource::getUrl('index'),
                'new_request' => $requestResource::getUrl('create'),
                'new_customer' => $customerResource::getUrl('create'),
            ],
        ];
    }

    /**
     * The four rows a person should act on first, oldest follow-up at the top.
     *
     * @param  class-string<Request>  $model
     * @param  class-string  $resource
     * @return array<int, array<string, mixed>>
     */
    private function overdueRows(string $model, Department $department, string $resource): array
    {
        return $model::query()
            ->overdue()
            ->with(['customer', 'broker'])
            ->orderBy('next_follow_up_at')
            ->limit(4)
            ->get()
            ->map(fn ($request): array => [
                'title' => $request->contactName() ?: 'طلب #'.$request->getKey(),
                'stage' => $request->stage->labelFor($department),
                'late_days' => max(1, (int) now()->startOfDay()->diffInDays($request->next_follow_up_at->startOfDay(), true)),
                'url' => $resource::getUrl('view', ['record' => $request]),
            ])
            ->all();
    }

    /**
     * @param  class-string<Request>  $model
     * @param  class-string  $resource
     * @return array<int, array<string, mixed>>
     */
    private function latestRows(string $model, Department $department, string $resource): array
    {
        return $model::query()
            ->with(['customer', 'broker'])
            ->latest('id')
            ->limit(4)
            ->get()
            ->map(fn ($request): array => [
                'title' => $request->contactName() ?: 'طلب #'.$request->getKey(),
                'stage' => $request->stage->labelFor($department),
                'when' => $request->created_at?->diffForHumans(),
                'url' => $resource::getUrl('view', ['record' => $request]),
            ])
            ->all();
    }

    private function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return mb_substr(implode('', array_map(
            fn (string $word): string => mb_substr($word, 0, 1),
            array_slice($words, 0, 2),
        )), 0, 2);
    }
}
