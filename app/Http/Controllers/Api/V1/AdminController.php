<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveConsultationTypeRequest;
use App\Http\Requests\SaveDepartmentRequest;
use App\Http\Requests\StaffIndexRequest;
use App\Http\Requests\StoreStaffInvitationRequest;
use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateHospitalSettingsRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Http\Resources\Api\V1\ConsultationTypeResource;
use App\Http\Resources\Api\V1\DepartmentResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\ConsultationType;
use App\Models\Department;
use App\Models\HospitalSetting;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Services\ConsultationTypeService;
use App\Services\DepartmentService;
use App\Services\HospitalSettingsService;
use App\Services\StaffInvitationService;
use App\Services\StaffService;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function departments(DepartmentService $service)
    {
        return DepartmentResource::collection($service->index());
    }

    public function createDepartment(SaveDepartmentRequest $request, DepartmentService $service): DepartmentResource
    {
        return new DepartmentResource($service->create($request->validated()));
    }

    public function updateDepartment(SaveDepartmentRequest $request, Department $department, DepartmentService $service): DepartmentResource
    {
        return new DepartmentResource($service->update($department, $request->validated()));
    }

    public function deleteDepartment(Department $department, DepartmentService $service)
    {
        abort_unless($service->canDelete($department), 409, 'Remove staff assignments, pending invitations, and consultation types before deleting this department.');
        $service->delete($department);

        return response()->noContent();
    }

    public function consultationTypes(ConsultationTypeService $service)
    {
        return ConsultationTypeResource::collection($service->index());
    }

    public function createConsultationType(SaveConsultationTypeRequest $request, ConsultationTypeService $service): ConsultationTypeResource
    {
        return new ConsultationTypeResource($service->create($request->validated()));
    }

    public function updateConsultationType(SaveConsultationTypeRequest $request, ConsultationType $consultationType, ConsultationTypeService $service): ConsultationTypeResource
    {
        return new ConsultationTypeResource($service->update($consultationType, $request->validated()));
    }

    public function staff(StaffIndexRequest $request, StaffService $service)
    {
        $data = $service->indexData($request->validated());

        return UserResource::collection($data['staff'])->additional([
            'departments' => DepartmentResource::collection($data['departments']),
            'pending_invitations' => $data['invitations']->map(fn ($invitation) => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'department' => $invitation->department?->name,
                'expires_at' => $invitation->expires_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function createStaff(StoreStaffRequest $request, StaffService $service): UserResource
    {
        return new UserResource($service->create($request->validated())->load('department'));
    }

    public function updateStaff(UpdateStaffRequest $request, User $staff, StaffService $service): UserResource
    {
        return new UserResource($service->update($staff, $request->validated(), $request->user())->load('department'));
    }

    public function toggleStaff(Request $request, User $staff, StaffService $service): UserResource
    {
        return new UserResource($service->toggle($staff, $request->user())->load('department'));
    }

    public function inviteStaff(StoreStaffInvitationRequest $request, StaffInvitationService $invitations): array
    {
        $data = $request->validated();
        $result = $invitations->issue($data['email'], Role::from($data['role']), $data['department_id'] ?? null, $request->user());

        return [
            'id' => $result['invitation']->id,
            'email' => $result['invitation']->email,
            'sent' => $result['sent'],
            'message' => $result['message'],
            'invitation_url' => $result['link'],
        ];
    }

    public function resendStaffInvitation(Request $request, StaffInvitation $invitation, StaffInvitationService $invitations): array
    {
        $result = $invitations->resend($invitation, $request->user());

        return ['sent' => $result['sent'], 'message' => $result['message'], 'invitation_url' => $result['link']];
    }

    public function revokeStaffInvitation(StaffInvitation $invitation, StaffInvitationService $invitations)
    {
        $invitations->revoke($invitation);

        return response()->noContent();
    }

    public function hospitalSettings(HospitalSettingsService $service): array
    {
        $setting = $service->current();

        return $this->settingsPayload($setting);
    }

    public function updateHospitalSettings(UpdateHospitalSettingsRequest $request, HospitalSettingsService $service): array
    {
        $setting = $service->update($request->validated(), $request->file('logo'), $request->boolean('remove_logo'));

        return $this->settingsPayload($setting);
    }

    private function settingsPayload(HospitalSetting $setting): array
    {
        return [
            'name' => $setting->name,
            'address' => $setting->address,
            'phone' => $setting->phone,
            'email' => $setting->email,
            'website' => $setting->website,
            'logo_url' => $setting->logo_url,
            'receipt_footer' => $setting->receipt_footer,
            'registration_fee' => (float) $setting->registration_fee,
            'paystack_public_key' => $setting->paystack_public_key,
            'has_paystack_secret_key' => filled($setting->getRawOriginal('paystack_secret_key')),
        ];
    }
}
