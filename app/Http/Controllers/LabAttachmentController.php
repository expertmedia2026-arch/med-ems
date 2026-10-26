<?php

namespace App\Http\Controllers;

use App\Models\LabAttachment;
use App\Services\LabAttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LabAttachmentController extends Controller
{
    public function show(Request $request, LabAttachment $attachment, LabAttachmentService $attachments)
    {
        abort_unless($attachments->canView($request->user(), $attachment), 403);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function destroy(Request $request, LabAttachment $attachment, LabAttachmentService $attachments)
    {
        $attachments->delete($attachment, $request->user());

        return back()->with('success', 'Attachment removed.');
    }
}
