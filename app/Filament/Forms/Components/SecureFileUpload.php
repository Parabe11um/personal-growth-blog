<?php

namespace App\Filament\Forms\Components;

use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class SecureFileUpload extends FileUpload
{
    protected function ensurePanelAccess(): void
    {
        $panel = Filament::getPanel('admin');

        $user = Auth::guard(
            $panel->getAuthGuard()
        )->user();

        abort_unless(
            $user instanceof FilamentUser
                && $user->canAccessPanel($panel),
            403
        );
    }

    public function isExistingFileAuthorized(string $path): bool
    {
        $record = $this->getRecord();

        if (! $record || ! $record->exists) {
            return false;
        }

        $original = $record->getRawOriginal(
            $this->getName()
        );

        return is_string($original)
            && $original !== ''
            && $original === $path;
    }

    protected function uploadValidator(
        TemporaryUploadedFile $file
    ): \Illuminate\Contracts\Validation\Validator {
        return Validator::make(
            ['image' => $file],
            [
                'image' => [
                    'required',
                    'file',
                    'image',
                    'mimetypes:image/jpeg,image/png,image/webp',
                    'extensions:jpg,jpeg,png,webp',
                    'max:5120',
                ],
            ]
        );
    }

    protected function ensureAuthorizedFileState(): void
    {
        $this->ensurePanelAccess();

        $state = $this->getState() ?? [];

        abort_unless(
            is_array($state) && count($state) <= 1,
            403
        );

        foreach ($state as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                continue;
            }

            abort_unless(
                is_string($file)
                    && $this->isExistingFileAuthorized($file),
                403
            );
        }
    }

    public function getValidationRules(): array
    {
        return [
            'bail',
            'array',
            function (
                string $attribute,
                mixed $value,
                Closure $fail
            ): void {
                $this->ensurePanelAccess();

                if ($value === null) {
                    return;
                }

                if (! is_array($value) || count($value) > 1) {
                    $fail('Invalid file upload state.');

                    return;
                }

                foreach ($value as $file) {
                    if ($file instanceof TemporaryUploadedFile) {
                        $validator = $this->uploadValidator($file);

                        if ($validator->fails()) {
                            $fail($validator->errors()->first());

                            return;
                        }

                        continue;
                    }

                    if (
                        ! is_string($file)
                        || ! $this->isExistingFileAuthorized($file)
                    ) {
                        $fail('Unauthorized file reference.');

                        return;
                    }
                }
            },
            ...parent::getValidationRules(),
        ];
    }

    public function getUploadedFiles(): ?array
    {
        $this->ensureAuthorizedFileState();

        return parent::getUploadedFiles();
    }

    public function removeUploadedFile(
        string $fileKey
    ): string | TemporaryUploadedFile | null {
        $this->ensureAuthorizedFileState();

        return parent::removeUploadedFile($fileKey);
    }

    public function saveUploadedFiles(): void
    {
        $this->ensureAuthorizedFileState();

        foreach ($this->getState() ?? [] as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                $this->uploadValidator($file)->validate();
            }
        }

        parent::saveUploadedFiles();
    }
}
