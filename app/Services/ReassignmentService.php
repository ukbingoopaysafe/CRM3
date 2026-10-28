<?php

namespace App\Services;

use App\Enums\ReassignmentStatus;
use App\Enums\Role;
use App\Models\Base\Reassignment;
use App\Models\Base\Request;
use App\Models\User;
use App\Support\Authorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Employees request a transfer, the department manager decides, and the
 * company manager may execute a direct transfer. The manager is never a
 * target and completed activities keep their original owner (D-070).
 */
class ReassignmentService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function request(Request $request, int $targetUserId, string $reason, User $actor): Reassignment
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'سبب طلب إعادة التعيين مطلوب.',
            ]);
        }

        if ($request->isClosed()) {
            throw ValidationException::withMessages([
                'request_id' => 'لا يمكن إعادة تعيين مسؤول طلب مغلق.',
            ]);
        }

        $target = $this->resolveTarget($targetUserId, $request, $request->owner_user_id);

        if ($request->reassignments()->where('status', ReassignmentStatus::Pending->value)->exists()) {
            throw ValidationException::withMessages([
                'request_id' => 'يوجد طلب إعادة تعيين معلق على هذا الطلب.',
            ]);
        }

        return DB::transaction(function () use ($request, $target, $reason, $actor) {
            $reassignment = $request->reassignments()->create([
                'requested_by' => $actor->getKey(),
                'current_owner_id' => $request->owner_user_id,
                'target_user_id' => $target->getKey(),
                'reason' => $reason,
                'status' => ReassignmentStatus::Pending->value,
            ]);

            $this->audit->record('reassignment_requested', $reassignment, $request::department(), [
                'request_id' => $request->getKey(),
                'from' => $request->owner_user_id,
                'to' => $target->getKey(),
            ], $reason, $actor);

            return $reassignment;
        });
    }

    public function approve(Reassignment $reassignment, User $manager, ?string $note = null): Reassignment
    {
        return $this->decide($reassignment, $manager, ReassignmentStatus::Approved, $note);
    }

    public function reject(Reassignment $reassignment, User $manager, ?string $note = null): Reassignment
    {
        return $this->decide($reassignment, $manager, ReassignmentStatus::Rejected, $note);
    }

    /**
     * The requester may withdraw while the request is still pending.
     */
    public function cancel(Reassignment $reassignment, User $actor): Reassignment
    {
        Authorization::requireSelf($actor, $reassignment->requested_by, 'إلغاء طلب إعادة التعيين من اختصاص مقدمه فقط.');

        if ($reassignment->status !== ReassignmentStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن إلغاء طلب تم البت فيه.',
            ]);
        }

        $reassignment->forceFill([
            'status' => ReassignmentStatus::Cancelled,
            'decided_by' => $actor->getKey(),
            'decided_at' => now(),
        ])->save();

        $this->audit->record('reassignment_cancelled', $reassignment, $reassignment::department(), [
            'request_id' => $reassignment->request_id,
        ], actor: $actor);

        return $reassignment;
    }

    /**
     * Direct manager transfer without an employee request (D-070).
     */
    public function direct(Request $request, int $targetUserId, string $reason, User $manager): Request
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'سبب إعادة التعيين مطلوب.',
            ]);
        }

        if ($request->isClosed()) {
            throw ValidationException::withMessages([
                'request_id' => 'لا يمكن إعادة تعيين مسؤول طلب مغلق.',
            ]);
        }

        $target = $this->resolveTarget($targetUserId, $request, $request->owner_user_id);
        $previousOwner = $request->owner_user_id;

        $this->assertManager($manager);

        return DB::transaction(function () use ($request, $target, $previousOwner, $reason, $manager) {
            $request->forceFill(['owner_user_id' => $target->getKey()])->save();

            $this->audit->record('reassigned', $request, $request::department(), [
                'from' => $previousOwner,
                'to' => $target->getKey(),
                'mode' => 'direct',
            ], $reason, $manager);

            return $request;
        });
    }

    private function decide(Reassignment $reassignment, User $manager, ReassignmentStatus $status, ?string $note): Reassignment
    {
        $this->assertManager($manager);

        if ($reassignment->status !== ReassignmentStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'تم البت في طلب إعادة التعيين بالفعل.',
            ]);
        }

        $request = $reassignment->request;

        if ($request === null || $request->isClosed()) {
            throw ValidationException::withMessages([
                'request_id' => 'الطلب لم يعد قابلًا لإعادة التعيين.',
            ]);
        }

        if ($request->owner_user_id !== $reassignment->current_owner_id) {
            throw ValidationException::withMessages([
                'request_id' => 'تغيّر مسؤول الطلب بعد تقديم الطلب، يجب مراجعته من جديد.',
            ]);
        }

        return DB::transaction(function () use ($reassignment, $request, $manager, $status, $note) {
            $reassignment->forceFill([
                'status' => $status,
                'decided_by' => $manager->getKey(),
                'decided_at' => now(),
                'decision_note' => $note,
            ])->save();

            if ($status === ReassignmentStatus::Approved) {
                $request->forceFill(['owner_user_id' => $reassignment->target_user_id])->save();
            }

            $this->audit->record(
                $status === ReassignmentStatus::Approved ? 'reassigned' : 'reassignment_rejected',
                $request,
                $request::department(),
                [
                    'from' => $reassignment->current_owner_id,
                    'to' => $reassignment->target_user_id,
                    'reassignment_id' => $reassignment->getKey(),
                ],
                $reassignment->reason,
                $manager,
            );

            return $reassignment;
        });
    }

    private function assertManager(User $user): void
    {
        Authorization::requireManager($user, 'قرار إعادة التعيين من اختصاص مدير الشركة فقط.');
    }

    private function resolveTarget(int $targetUserId, Request $request, int $currentOwnerId): User
    {
        $target = User::query()->with('memberships')->find($targetUserId);

        if ($target === null || ! $target->isActive()) {
            throw ValidationException::withMessages([
                'target_user_id' => 'يجب اختيار موظف نشط.',
            ]);
        }

        if ($target->role === Role::Manager) {
            throw ValidationException::withMessages([
                'target_user_id' => 'لا يكون مدير الشركة هدفًا لإعادة التعيين.',
            ]);
        }

        if (! $target->belongsToDepartment($request::department())) {
            throw ValidationException::withMessages([
                'target_user_id' => 'الموظف المستهدف لا يعمل في هذا القسم.',
            ]);
        }

        if ($target->getKey() === $currentOwnerId) {
            throw ValidationException::withMessages([
                'target_user_id' => 'الموظف المستهدف هو المسؤول الحالي بالفعل.',
            ]);
        }

        return $target;
    }
}
