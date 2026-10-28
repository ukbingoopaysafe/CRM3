<?php

namespace Tests\Concerns;

use App\Enums\Department;
use App\Enums\Governorate;
use App\Models\Area;
use App\Models\Base\Request as BaseRequest;
use App\Models\DepartmentMembership;
use App\Models\Rental;
use App\Models\Resale;
use App\Models\User;
use App\Services\RequestService;

trait CreatesDomainData
{
    protected function manager(): User
    {
        return User::factory()->manager()->create();
    }

    protected function employee(Department ...$departments): User
    {
        $user = User::factory()->employee()->create();

        foreach ($departments ?: [Department::Rental] as $department) {
            DepartmentMembership::create([
                'user_id' => $user->getKey(),
                'department' => $department->value,
            ]);
        }

        return $user->refresh();
    }

    protected function area(Governorate $governorate = Governorate::Cairo, string $name = 'المعادي'): Area
    {
        return Area::firstOrCreate(
            ['name' => $name, 'governorate' => $governorate],
            ['is_active' => true],
        );
    }

    protected function customerClass(Department $department): string
    {
        return $department === Department::Rental ? Rental\Customer::class : Resale\Customer::class;
    }

    protected function brokerClass(Department $department): string
    {
        return $department === Department::Rental ? Rental\Broker::class : Resale\Broker::class;
    }

    protected function requestClass(Department $department): string
    {
        return $department === Department::Rental ? Rental\Request::class : Resale\Request::class;
    }

    protected function makeRequest(Department $department = Department::Rental, array $overrides = []): BaseRequest
    {
        $owner = $overrides['_owner'] ?? $this->employee($department);
        unset($overrides['_owner']);

        $customer = $overrides['_customer'] ?? null;
        unset($overrides['_customer']);

        if ($customer === null && ! array_key_exists('broker_id', $overrides)) {
            $customer = $this->customerClass($department)::create([
                'name' => 'عميل اختبار',
                'phone' => '+2010'.fake()->unique()->numerify('#######'),
            ]);
        }

        // Create the default area only when the test did not supply one, so
        // repeated calls never collide on the unique area name.
        $areaId = $overrides['area_id'] ?? $this->area()->getKey();

        $attributes = array_merge([
            'customer_id' => $customer?->getKey(),
            'owner_user_id' => $owner->getKey(),
            'governorate' => Governorate::Cairo->value,
            'area_id' => $areaId,
            'next_follow_up_at' => now()->addDay(),
        ], $overrides);

        return app(RequestService::class)->create($this->requestClass($department), $attributes, $owner);
    }
}
