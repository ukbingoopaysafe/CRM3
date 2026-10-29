<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

/**
 * A list page costs the same whether it shows one page of rows or six. The
 * budgets are the numbers recorded in CONSTRAINTS.md: lower them when a screen
 * gets cheaper, and never raise one to make a change pass.
 */
final class QueryBudgetTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    /** @var array<string, int> */
    private const BUDGETS = [
        '/admin' => 25,
        '/admin/rental/requests' => 10,
        '/admin/rental/customers' => 6,
        '/admin/rental/reassignments' => 4,
        '/admin/team' => 6,
    ];

    public function test_a_list_page_does_not_query_once_per_row(): void
    {
        $manager = $this->manager();
        $this->addRequests(12);
        $small = $this->countQueries($manager);

        $this->addRequests(48);
        $large = $this->countQueries($manager);

        foreach (self::BUDGETS as $uri => $budget) {
            $this->assertSame(
                $small[$uri],
                $large[$uri],
                $uri.' asks the database more often with more rows, which is a per-row query.',
            );

            $this->assertLessThanOrEqual(
                $budget,
                $large[$uri],
                $uri.' runs '.$large[$uri].' queries, over its budget of '.$budget.'.',
            );
        }
    }

    private function addRequests(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->makeRequest(Department::Rental);
        }
    }

    /**
     * @return array<string, int>
     */
    private function countQueries(User $manager): array
    {
        $counts = [];

        foreach (array_keys(self::BUDGETS) as $uri) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($manager)->get($uri)->assertOk();
            $counts[$uri] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }

        return $counts;
    }
}
