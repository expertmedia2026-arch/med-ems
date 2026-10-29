<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreatePlatformHospitalRequest;
use App\Http\Requests\Api\V1\SetPlatformAdminStatusRequest;
use App\Http\Requests\Api\V1\SetPlatformHospitalStatusRequest;
use App\Http\Requests\Api\V1\StorePlatformAdminRequest;
use App\Http\Requests\Api\V1\StorePlatformHospitalAdminRequest;
use App\Http\Requests\Api\V1\UpdatePlatformHospitalConfigurationRequest;
use App\Http\Resources\Api\V1\PlatformHospitalResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Hospital;
use App\Models\User;
use App\Services\GlobalAdminHospitalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Password;

/**
 * Provides platform-level hospital, module, administrator, and notification API operations.
 */
class GlobalAdminApiController extends Controller
{
    /** Return aggregate counts for the global-admin dashboard. */
    public function dashboard(GlobalAdminHospitalService $hospitals): array
    {
        return $hospitals->dashboard();
    }

    /** Return the module catalog and dependency requirements used by onboarding forms. */
    public function modules(GlobalAdminHospitalService $hospitals): array
    {
        return ['modules' => $hospitals->moduleCatalog()];
    }

    /** List all hospitals with their enabled modules and aggregate counts. */
    public function hospitals(Request $request, GlobalAdminHospitalService $service)
    {
        return PlatformHospitalResource::collection($service->hospitals($request->user()));
    }

    /** Return one hospital's platform configuration. */
    public function showHospital(
        Request $request,
        Hospital $hospital,
        GlobalAdminHospitalService $service,
    ): PlatformHospitalResource {
        return new PlatformHospitalResource($service->findHospital($hospital, $request->user()));
    }

    /**
     * Create a hospital and send its initial administrator a password setup link.
     */
    public function createHospital(
        CreatePlatformHospitalRequest $request,
        GlobalAdminHospitalService $service,
    ): JsonResponse {
        $result = $service->createHospital($request->validated(), $request->user());
        $adminEmail = $result['admin_email'];
        $setupLinkSent = $adminEmail !== null
            && Password::sendResetLink(['email' => $adminEmail]) === Password::RESET_LINK_SENT;

        return response()->json([
            'message' => $setupLinkSent
                ? 'Hospital created; the administrator password setup link was sent.'
                : ($adminEmail === null
                    ? 'Hospital created; a super-admin must add its first administrator.'
                    : 'Hospital created, but the password setup email could not be sent.'),
            'data' => [
                'hospital' => (new PlatformHospitalResource($result['hospital']))->resolve($request),
                'admin_email' => $adminEmail,
                'setup_link_sent' => $setupLinkSent,
            ],
        ], 201);
    }

    /** Add a hospital administrator and send them a password setup link. */
    public function createHospitalAdmin(
        StorePlatformHospitalAdminRequest $request,
        Hospital $hospital,
        GlobalAdminHospitalService $service,
    ): JsonResponse {
        $admin = $service->createHospitalAdmin($hospital, $request->validated(), $request->user());
        $setupLinkSent = Password::sendResetLink(['email' => $admin->email]) === Password::RESET_LINK_SENT;

        return response()->json([
            'message' => $setupLinkSent
                ? 'Hospital administrator created; the password setup link was sent.'
                : 'Hospital administrator created, but the password setup email could not be sent.',
            'data' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'setup_link_sent' => $setupLinkSent,
            ],
        ], 201);
    }

    /** Replace a hospital's enabled modules and publish its next admission workflow version. */
    public function updateHospitalConfiguration(
        UpdatePlatformHospitalConfigurationRequest $request,
        Hospital $hospital,
        GlobalAdminHospitalService $service,
    ): PlatformHospitalResource {
        return new PlatformHospitalResource(
            $service->updateConfiguration($hospital, $request->validated(), $request->user()),
        );
    }

    /** Set the hospital's active state to the explicit value in the request body. */
    public function setHospitalStatus(
        SetPlatformHospitalStatusRequest $request,
        Hospital $hospital,
        GlobalAdminHospitalService $service,
    ): PlatformHospitalResource {
        return new PlatformHospitalResource(
            $service->setHospitalActive($hospital, $request->boolean('is_active'), $request->user()),
        );
    }

    /** List platform administrators without exposing passwords or tokens. */
    public function platformAdmins()
    {
        $admins = User::query()
            ->where('is_global_admin', true)
            ->whereNull('hospital_id')
            ->orderBy('name')
            ->get();

        return $admins->map(fn (User $admin) => [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'is_active' => (bool) $admin->is_active,
            'account_type' => $admin->isPlatformSuperAdmin() ? 'global_admin' : 'sub_admin',
        ]);
    }

    /** Create an additional platform administrator with a supplied password. */
    public function createPlatformAdmin(StorePlatformAdminRequest $request): JsonResponse
    {
        $admin = new User;
        $admin->name = $request->validated('name');
        $admin->email = $request->validated('email');
        $admin->password = $request->validated('password');
        $admin->role = Role::Admin;
        $admin->hospital_id = null;
        $admin->department_id = null;
        $admin->is_global_admin = true;
        $admin->is_platform_super_admin = false;
        $admin->is_active = true;
        $admin->email_verified_at = now();
        $admin->save();

        return response()->json([
            'message' => 'Platform administrator created.',
            'data' => array_merge((new UserResource($admin))->resolve($request), [
                'account_type' => 'sub_admin',
            ]),
        ], 201);
    }

    /** Activate or deactivate a platform administrator, preventing self-deactivation. */
    public function setPlatformAdminStatus(
        SetPlatformAdminStatusRequest $request,
        User $user,
    ): UserResource {
        abort_unless($user->is_global_admin && $user->hospital_id === null, 404);
        abort_if(
            (int) $request->user()->id === (int) $user->id && ! $request->boolean('is_active'),
            422,
            'You cannot deactivate your own platform admin account.',
        );

        $user->update(['is_active' => $request->boolean('is_active')]);

        return new UserResource($user->refresh());
    }

    /** Return the current platform administrator's recent notifications. */
    public function notifications(Request $request): array
    {
        if ($request->has('unread_only')) {
            $request->merge(['unread_only' => $request->boolean('unread_only')]);
        }

        $filters = $request->validate([
            'unread_only' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $user = $request->user();
        $query = $user->notifications()->latest();

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate($filters['per_page'] ?? 20);

        return [
            'unread_count' => $user->unreadNotifications()->count(),
            'items' => $notifications->getCollection()->map(fn (DatabaseNotification $notification) => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? 'Notification',
                'message' => $notification->data['message'] ?? '',
                'url' => $notification->data['url'] ?? null,
                'created_at' => $notification->created_at?->toIso8601String(),
                'read_at' => $notification->read_at?->toIso8601String(),
            ])->values(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ];
    }

    /** Mark one of the authenticated platform administrator's notifications as read. */
    public function markNotificationRead(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return response()->json(['message' => 'Notification marked as read.']);
    }

    /** Mark all unread notifications belonging to the authenticated user as read. */
    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
