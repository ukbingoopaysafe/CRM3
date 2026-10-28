<?php

namespace App\Services;

use App\Enums\Governorate;
use App\Models\Area;
use App\Models\User;
use App\Support\ArabicText;
use App\Support\Authorization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manages the single company-wide area directory (D-081) with the archive
 * lifecycle from D-067 and duplicate prevention from D-032.
 */
class AreaService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, governorate: Governorate|string}  $data
     */
    public function create(array $data, User $actor): Area
    {
        Authorization::requireManager($actor);

        return DB::transaction(function () use ($data, $actor) {
            $governorate = $data['governorate'] instanceof Governorate
                ? $data['governorate']
                : Governorate::from($data['governorate']);

            $this->assertNameAvailable($data['name'], $governorate);

            $area = Area::create([
                'name' => trim($data['name']),
                'governorate' => $governorate,
                'is_active' => true,
            ]);

            $this->audit->record('created', $area, null, [
                'name' => $area->name,
                'governorate' => $governorate->value,
            ], actor: $actor);

            return $area;
        });
    }

    /**
     * @param  array{name?: string, governorate?: Governorate|string, is_active?: bool}  $data
     */
    public function update(Area $area, array $data, User $actor): Area
    {
        Authorization::requireManager($actor);

        return DB::transaction(function () use ($area, $data, $actor) {
            $original = $area->only(['name', 'governorate', 'is_active']);

            $governorate = isset($data['governorate'])
                ? ($data['governorate'] instanceof Governorate
                    ? $data['governorate']
                    : Governorate::from($data['governorate']))
                : $area->governorate;

            $name = $data['name'] ?? $area->name;

            $this->assertNameAvailable($name, $governorate, $area->getKey());

            $area->fill([
                'name' => trim($name),
                'governorate' => $governorate,
                'is_active' => $data['is_active'] ?? $area->is_active,
            ])->save();

            $this->audit->record('updated', $area, null, [
                'before' => $original,
                'after' => $area->only(['name', 'governorate', 'is_active']),
            ], actor: $actor);

            return $area;
        });
    }

    public function archive(Area $area, User $actor): Area
    {
        Authorization::requireManager($actor);

        if ($area->isArchived()) {
            return $area;
        }

        $area->forceFill([
            'archived_at' => now(),
            'is_active' => false,
        ])->save();

        $this->audit->record('archived', $area, null, ['name' => $area->name], actor: $actor);

        return $area;
    }

    public function restore(Area $area, User $actor): Area
    {
        Authorization::requireManager($actor);

        if (! $area->isArchived()) {
            return $area;
        }

        $this->assertNameAvailable($area->name, $area->governorate, $area->getKey());

        $area->forceFill([
            'archived_at' => null,
            'is_active' => true,
        ])->save();

        $this->audit->record('restored', $area, null, ['name' => $area->name], actor: $actor);

        return $area;
    }

    /**
     * @throws ValidationException
     */
    public function assertNameAvailable(string $name, Governorate $governorate, ?int $ignoreId = null): void
    {
        $needle = ArabicText::normalize($name);

        $duplicate = Area::query()
            ->where('governorate', $governorate->value)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get()
            ->first(fn (Area $area) => ArabicText::normalize($area->name) === $needle);

        if ($duplicate !== null) {
            throw ValidationException::withMessages([
                'name' => 'توجد منطقة مطابقة الاسم داخل المحافظة نفسها.',
            ]);
        }
    }

    /**
     * Areas selectable for a new request: active, not archived, and inside
     * the chosen governorate (D-033, D-042).
     *
     * @return Collection<int, Area>
     */
    public function selectableFor(Governorate $governorate)
    {
        return Area::query()
            ->selectable()
            ->forGovernorate($governorate)
            ->orderBy('name')
            ->get();
    }
}
