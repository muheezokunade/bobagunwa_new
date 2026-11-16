<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Media;
use App\Models\PrivateFile;
use App\Models\User;
use App\Support\Audit;
use App\Support\Tokens;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

/**
 * Secure upload handling.
 *
 * Public images: content-sniffed, dimension-limited and re-encoded with GD,
 * which discards metadata and anything appended to the image. Stored under
 * public/media with random names; never executable.
 *
 * Private files (payment evidence, member documents): stored outside the
 * webroot and served only through authorised controllers as downloads.
 * PDFs containing active content (JavaScript, launch actions, embedded files)
 * are refused.
 */
class UploadService
{
    private const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function storePublicImage(UploadedFile $file, ?string $alt, User $user): Media
    {
        [$binary, $mime, $width, $height] = $this->reencodeImage($file, 1800);
        $dir = date('Y/m');
        $name = strtolower(Tokens::generate(12)).'.'.self::IMAGE_TYPES[$mime];
        $target = public_path("media/{$dir}");
        File::ensureDirectoryExists($target, 0755);
        file_put_contents("{$target}/{$name}", $binary);

        $media = Media::create([
            'path' => "{$dir}/{$name}", 'original_name' => mb_substr($file->getClientOriginalName(), 0, 190), 'mime' => $mime,
            'width' => $width, 'height' => $height, 'size_bytes' => strlen($binary), 'alt_text' => $alt, 'uploaded_by' => $user->id,
        ]);
        Audit::record('media.uploaded', 'Image uploaded: '.$media->original_name, $media);

        return $media;
    }

    public function storePrivate(UploadedFile $file, string $purpose, User $user): PrivateFile
    {
        $maxKb = (int) config('boba.uploads.document_max_kb');
        if (! $file->isValid() || $file->getSize() > $maxKb * 1024) {
            throw new DomainRuleException('Upload a file smaller than '.intdiv($maxKb, 1024).' MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if ($mime === 'application/pdf') {
            $binary = (string) file_get_contents($file->getRealPath());
            $this->assertSafePdf($binary);
            $ext = 'pdf';
        } elseif (isset(self::IMAGE_TYPES[$mime])) {
            [$binary, $mime] = $this->reencodeImage($file, 2400);
            $ext = self::IMAGE_TYPES[$mime];
        } else {
            throw new DomainRuleException('Upload a PDF, JPEG or PNG file.');
        }
        $path = date('Y/m').'/'.strtolower(Tokens::generate(16)).'.'.$ext;
        $full = storage_path('app/private/files/'.$path);
        File::ensureDirectoryExists(dirname($full), 0750);
        file_put_contents($full, $binary);
        @chmod($full, 0640);

        $record = PrivateFile::create([
            'purpose' => $purpose, 'path' => $path, 'original_name' => mb_substr($file->getClientOriginalName(), 0, 190),
            'mime' => $mime, 'size_bytes' => strlen($binary), 'sha256' => hash('sha256', $binary), 'uploaded_by' => $user->id,
        ]);
        Audit::record('file.uploaded', "Private file stored ({$purpose})", $record);

        return $record;
    }

    public function privatePath(PrivateFile $file): string
    {
        return storage_path('app/private/files/'.$file->path);
    }

    public function assertSafePdf(string $binary): void
    {
        if (! str_starts_with($binary, '%PDF-')) {
            throw new DomainRuleException('The file is not a valid PDF.');
        }
        if (preg_match('#/(JavaScript|JS|Launch|EmbeddedFile|OpenAction|AA|RichMedia|XFA)\b#', $binary)) {
            throw new DomainRuleException('This PDF contains active content (scripts, embedded files or automatic actions) and cannot be accepted. Print it to a plain PDF and upload again.');
        }
    }

    /** @return array{0: string, 1: string, 2: int, 3: int} */
    private function reencodeImage(UploadedFile $file, int $maxEdge): array
    {
        $maxKb = (int) config('boba.uploads.image_max_kb');
        if (! $file->isValid() || $file->getSize() > $maxKb * 1024) {
            throw new DomainRuleException('Upload an image smaller than '.intdiv($maxKb, 1024).' MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if (! isset(self::IMAGE_TYPES[$mime])) {
            throw new DomainRuleException('Upload a JPEG or PNG image.');
        }
        $info = @getimagesize($file->getRealPath());
        $limit = (int) config('boba.uploads.image_max_pixels');
        if (! $info || $info[0] < 1 || $info[1] < 1 || $info[0] > $limit || $info[1] > $limit) {
            throw new DomainRuleException('The image could not be read or is too large.');
        }
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $source) {
            throw new DomainRuleException('The image could not be processed.');
        }
        [$w, $h] = [$info[0], $info[1]];
        $scale = min(1, $maxEdge / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $canvas = imagecreatetruecolor($nw, $nh);
        if ($mime === 'image/png') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
        }
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        $mime === 'image/png' ? imagepng($canvas, null, 7) : imagejpeg($canvas, null, 84);
        $binary = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);

        return [$binary, $mime, $nw, $nh];
    }
}
