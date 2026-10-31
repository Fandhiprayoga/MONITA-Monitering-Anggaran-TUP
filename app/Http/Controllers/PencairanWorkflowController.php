<?php

namespace App\Http\Controllers;

use App\Models\PencairanAttachment;
use App\Models\PencairanPengajuan;
use App\Services\PencairanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PencairanWorkflowController extends Controller
{
    public function __construct(private PencairanService $service) {}

    private function respond(Request $request, array $result, bool $created = false)
    {
        return $request->expectsJson() ? response()->json($result, $created ? 201 : 200)
            : ($created ? redirect($result['redirect_url']) : back())->with('success', 'Pengajuan berhasil disimpan.');
    }

    public function store(Request $request) { return $this->respond($request, $this->service->store($request), true); }
    public function update(Request $request, PencairanPengajuan $pengajuan) { return $this->respond($request, $this->service->update($request, $pengajuan)); }
    public function submit(Request $request, PencairanPengajuan $pengajuan) { return $this->respond($request, $this->service->submit($request, $pengajuan)); }
    public function returnToUser(Request $request, PencairanPengajuan $pengajuan) { return $this->respond($request, $this->service->returnToUser($request, $pengajuan)); }
    public function verify(Request $request, PencairanPengajuan $pengajuan) { return $this->respond($request, $this->service->verify($request, $pengajuan)); }
    public function cancel(Request $request, PencairanPengajuan $pengajuan) { return $this->respond($request, $this->service->cancel($request, $pengajuan)); }
    public function complete(Request $request, PencairanPengajuan $pengajuan) { return $this->respond($request, $this->service->complete($request, $pengajuan)); }

    public function attachment(Request $request, $attachment)
    {
        $attachment = $attachment instanceof PencairanAttachment ? $attachment : PencairanAttachment::findOrFail($attachment);
        $this->service->authorize($request, $attachment->completion->pengajuan);
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404, 'Lampiran tidak tersedia.');
        return $disk->download($attachment->path, $attachment->original_filename,
            ['Content-Type' => $attachment->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}