<?php

namespace App\Http\Requests;

use AulaX\Tenancy\Application\Files\ImageUpload;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An uploaded logo or signature (SPEC-006 INS-R04.3). `mimetypes` reads the
 * content, not the name; ImageUpload checks it again and decodes it (S-09).
 * A signature also names who signs and their title.
 */
class InstitutionImageRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'file' => ['required', 'file', 'max:'.(ImageUpload::MAX_BYTES / 1024), 'mimetypes:image/png,image/jpeg'],
        ];

        if ($this->route('role') !== null) {
            $rules['signer_name'] = ['required', 'string', 'max:120'];
            $rules['signer_title'] = ['required', 'string', 'max:120'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $invalid = 'Sube una imagen PNG o JPEG de hasta 1 MB.';

        return [
            'file.required' => $invalid,
            'file.file' => $invalid,
            'file.max' => $invalid,
            'file.mimetypes' => $invalid,
        ];
    }

    /**
     * The image, judged by its content.
     */
    public function upload(): ImageUpload
    {
        return ImageUpload::fromContents((string) $this->file('file')->get());
    }
}
