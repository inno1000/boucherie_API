<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVersementStatutRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'motif_rejet' => ['nullable', 'string', 'max:1000'],
            'attachment_ids'   => ['sometimes', 'array', 'max:3'],
            'attachment_ids.*' => ['uuid', 'exists:attachments,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! str_contains($this->path(), '/rejeter')) {
                return;
            }

            $motif = trim((string) $this->input('motif_rejet', ''));
            $ids = $this->input('attachment_ids', []);

            if ($motif === '' && (! is_array($ids) || $ids === [])) {
                $validator->errors()->add(
                    'motif_rejet',
                    'Indiquez un motif de rejet ou joignez un enregistrement vocal.',
                );
            }
        });
    }
}
