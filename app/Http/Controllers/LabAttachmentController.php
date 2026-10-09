<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Models\LabAttachment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LabAttachmentController extends Controller
{
    public function show(Request $request, LabAttachment $attachment)
    {
        abort_unless($this->canView($request->user(), $attachment), 403);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response($attachment->path, $attachment->original_name, [
            'Content-Type'           => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, max-age=3600',
        ]);
    }

    public function destroy(LabAttachment $attachment)
    {
        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        return back()->with('success', 'Attachment removed.');
    }

    private function canView(User $user, LabAttachment $attachment): bool
    {
        if ($user->hasRole(Role::Admin, Role::Lab)) {
            return true;
        }

        if ($user->hasRole(Role::Doctor)) {
            $request = $attachment->labRequest()->with('visit')->first();

            // Own requests, or any finished visit (same rule as the previous-visit page)
            return $request->requested_by === $user->id
                || $request->visit->status === VisitStatus::Completed;
        }

        return false;
    }
}