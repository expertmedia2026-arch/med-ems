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

/**
 * Exposes administrative API operations for departments, consultation types, staff, and hospital settings.
 */
class AdminController extends Controller
{
    /**
     * Delegate department listing to DepartmentService and return a DepartmentResource collection.
     */
    public function departments(DepartmentService $service)
    {
        return DepartmentResource::collection($service->index());
    }

    /**
     * Pass validated department fields to DepartmentService and return the created DepartmentResource.
     */
    public function createDepartment(SaveDepartmentRequest $request, DepartmentService $service): DepartmentResource
    {
        return new DepartmentResource($service->create($request->validated()));
    }

    /**
     * Pass validated department fields and the bound department to DepartmentService; return its updated resource.
     */
    public function updateDepartment(SaveDepartmentRequest $request, Department $department, DepartmentService $service): DepartmentResource
    {
        return new DepartmentResource($service->update($department, $request->validated()));
    }

    /**
     * Check deletion eligibility and delegate removal to DepartmentService; return an empty 204 response.
     */
    public function deleteDepartment(Department $department, DepartmentService $service)
    {
        abort_unless($service->canDelete($department), 409, 'Remove staff assignments, pending invitations, and consultation types before deleting this department.');
        $service->delete($department);

        return response()->noContent();
    }

    /**
     * Delegate consultation-type listing to ConsultationTypeService and return a resource collection.
     */
    public function consultationTypes(ConsultationTypeService $service)
    {
        return ConsultationTypeResource::collection($service->index());
    }

    /**
     * Pass validated consultation-type fields to ConsultationTypeService and return the created resource.
     */
    public function createConsultationType(SaveConsultationTypeRequest $request, ConsultationTypeService $service): ConsultationTypeResource
    {
        return new ConsultationTypeResource($service->create($request->validated()));
    }

    /**
     * Update the bound consultation type with validated fields through ConsultationTypeService and return its resource.
     */
    public function updateConsultationType(SaveConsultationTypeRequest $request, ConsultationType $consultationType, ConsultationTypeService $service): ConsultationTypeResource
    {
        return new ConsultationTypeResource($service->update($consultationType, $request->validated()));
    }

    /**
     * Pass validated staff filters and the authenticated user to StaffService; return staff resources with department and invitation metadata.
     */
    public function staff(StaffIndexRequest $request, StaffService $service)
    {
        $data = $service->indexData($request->validated(), $request->user());

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

    /**
     * Create staff from validated fields through StaffService and return the new user resource with its department.
     */
    public function createStaff(StoreStaffRequest $request, StaffService $service): UserResource
    {
        return new UserResource($service->create($request->validated())->load('department'));
    }

    /**
     * Update the bound staff member from validated fields through StaffService and return the refreshed user resource.
     */
    public function updateStaff(UpdateStaffRequest $request, User $staff, StaffService $service): UserResource
    {
        return new UserResource($service->update($staff, $request->validated(), $request->user())->load('department'));
    }

    /**
     * Delegate the authenticated administrator's staff-status toggle to StaffService and return the updated user resource.
     */
    public function toggleStaff(Request $request, User $staff, StaffService $service): UserResource
    {
        return new UserResource($service->toggle($staff, $request->user())->load('department'));
    }

    /**
     * Issue an invitation using validated email, role, and optional department fields; return invitation id, delivery status, and message.
     */
    public function inviteStaff(StoreStaffInvitationRequest $request, StaffInvitationService $invitations): array
    {
        $data = $request->validated();
        $result = $invitations->issue($data['email'], Role::from($data['role']), $data['department_id'] ?? null, $request->user());

        return [
            'id' => $result['invitation']->id,
            'email' => $result['invitation']->email,
            'sent' => $result['sent'],
            'message' => $result['message'],

        ];
    }

    /**
     * Ask StaffInvitationService to resend the bound invitation and return its delivery status and message.
     */
    public function resendStaffInvitation(Request $request, StaffInvitation $invitation, StaffInvitationService $invitations): array
    {
        $result = $invitations->resend($invitation, $request->user());

        return ['sent' => $result['sent'], 'message' => $result['message']];
    }

    /**
     * Revoke the bound invitation through StaffInvitationService and return an empty 204 response.
     */
    public function revokeStaffInvitation(StaffInvitation $invitation, StaffInvitationService $invitations)
    {
        $invitations->revoke($invitation);

        return response()->noContent();
    }

    /** Return the authenticated hospital's settings, including its delivery fee schedule. */
    public function hospitalSettings(HospitalSettingsService $service): array
    {
        $setting = $service->current();

        return $this->settingsPayload($setting);
    }

    /** Validate and save the hospital settings submitted by an authorized API admin. */
    public function updateHospitalSettings(UpdateHospitalSettingsRequest $request, HospitalSettingsService $service): array
    {
        $setting = $service->update(
            $request->validated(),
            $request->file('logo'),
            $request->boolean('remove_logo'),
            $request->file('administrator_signature'),
            $request->boolean('remove_administrator_signature'),
        );

        return $this->settingsPayload($setting);
    }

    /** Keep the API settings response shape consistent for reads and updates. */
    private function settingsPayload(HospitalSetting $setting): array
    {
        return [
            'name' => $setting->name,
            'address' => $setting->address,
            'phone' => $setting->phone,
            'email' => $setting->email,
            'website' => $setting->website,
            'logo_url' => $setting->logo_url,
            'has_administrator_signature' => filled($setting->administrator_signature_path),
            'receipt_footer' => $setting->receipt_footer,
            'registration_fee' => (float) $setting->registration_fee,
            'emergency_fee' => (float) $setting->emergency_fee,
            'mortuary_intake_fee' => (float) $setting->mortuary_intake_fee,
            'mortuary_daily_storage_fee' => (float) $setting->mortuary_daily_storage_fee,
            'state_mortuary_tax_daily_fee' => (float) $setting->state_mortuary_tax_daily_fee,
            'maternity_vaginal_delivery_fee' => (float) $setting->maternity_vaginal_delivery_fee,
            'maternity_assisted_delivery_fee' => (float) $setting->maternity_assisted_delivery_fee,
            'maternity_cesarean_delivery_fee' => (float) $setting->maternity_cesarean_delivery_fee,
            'maternity_other_delivery_fee' => (float) $setting->maternity_other_delivery_fee,
            'maternity_delivery_pack_fee' => (float) $setting->maternity_delivery_pack_fee,
            'payment_gateway' => $setting->payment_gateway ?: 'paystack',
            'paystack_public_key' => $setting->paystack_public_key,
            'has_paystack_secret_key' => filled($setting->getRawOriginal('paystack_secret_key')),
            'primary_color' => $setting->primary_color ?: '#0f766e',
            'secondary_color' => $setting->secondary_color ?: '#1d4ed8',
            'accent_color' => $setting->accent_color,
            'highlight_color' => $setting->highlight_color,
        ];
    }
}
