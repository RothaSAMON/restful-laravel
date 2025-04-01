<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class S3Services
{
    protected string $disk = 's3';

    private function sliceOldPath(string $path): string
    {
        $newPath = explode(".com/", $path)[1];
        return $newPath;
    }

    public function upload(UploadedFile $file, string $directory = '', ?string $filename = null): string
    {
        if (!$file->isValid()) {
            throw new \Exception('Invalid file upload.');
        }

        $originalNameSlice = explode(".", $file->getClientOriginalName())[0];
        $filename = $filename ?? $originalNameSlice . "-" . uniqid() . '.' . $file->getClientOriginalExtension();
        $directory = trim($directory, '/');
        $path = $file->store($directory, $this->disk);

        if (!$path) {
            throw new \Exception('File upload failed or returned an empty path.');
        }

        return Storage::disk($this->disk)->url($path);
    }

    public function delete(string $path): bool
    {
        $newPath = $this->sliceOldPath($path);
        return Storage::disk($this->disk)->delete($newPath);
    }

    public function update(UploadedFile $file, string $oldPath, string $directory = '', ?string $filename = null): string
    {
        $this->delete($oldPath);
        return $this->upload($file, $directory, $filename);
    }
}