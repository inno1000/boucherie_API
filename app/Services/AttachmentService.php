<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttachmentService
{
    private const MAX_PER_ENTITY = 3;

    private const ALLOWED_MIMES = [
        'audio/webm',
        'audio/ogg',
        'audio/mpeg',
        'audio/mp4',
        'audio/wav',
        'audio/x-wav',
        'video/webm',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/heic',
        'image/heif',
    ];

    public function storeUpload(UploadedFile $file, User $user): Attachment
    {
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'file' => ['Format de fichier non supporté.'],
            ]);
        }

        $extension = $file->getClientOriginalExtension() ?: 'webm';
        $path      = sprintf(
            'attachments/%d/%s.%s',
            $user->id,
            (string) Str::uuid(),
            $extension,
        );

        Storage::disk('local')->putFileAs(
            dirname($path),
            $file,
            basename($path),
        );

        return Attachment::create([
            'user_id'       => $user->id,
            'disk'          => 'local',
            'path'          => $path,
            'original_name' => $file->getClientOriginalName() ?: 'recording.webm',
            'mime_type'     => $mime,
            'size_bytes'    => (int) $file->getSize(),
        ]);
    }

    /**
     * @param  array<int, string>  $attachmentIds
     */
    public function linkTo(Model $model, array $attachmentIds, User $user): void
    {
        $ids = array_values(array_unique(array_filter($attachmentIds)));
        if ($ids === []) {
            return;
        }

        if (count($ids) > self::MAX_PER_ENTITY) {
            throw ValidationException::withMessages([
                'attachment_ids' => ['Maximum '.self::MAX_PER_ENTITY.' fichiers audio par enregistrement.'],
            ]);
        }

        $attachments = Attachment::query()
            ->whereIn('id', $ids)
            ->where('user_id', $user->id)
            ->whereNull('attachable_id')
            ->get();

        if ($attachments->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'attachment_ids' => ['Un ou plusieurs fichiers sont invalides ou déjà utilisés.'],
            ]);
        }

        Attachment::query()
            ->whereIn('id', $ids)
            ->update([
                'attachable_type' => $model->getMorphClass(),
                'attachable_id'   => $model->getKey(),
            ]);
    }

    public function findForStream(string $id): Attachment
    {
        return Attachment::query()->findOrFail($id);
    }
}
