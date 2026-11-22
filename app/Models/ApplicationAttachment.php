<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A supporting document uploaded with a membership application. The file
 * itself is a PrivateFile (outside the webroot); only officers who review
 * applications can download it, and every download is audited.
 */
class ApplicationAttachment extends Model
{
    public const UPDATED_AT = null;

    /** kind => [label, required, multiple, images only] — the association's list of 22 Nov 2026. */
    public const KINDS = [
        'birth_certificate' => ['Birth certificate', true, false, false],
        'lg_certificate' => ['Certificate of local government of origin', true, false, false],
        'first_school_certificate' => ['First School Leaving Certificate', true, false, false],
        'secondary_school_certificate' => ['Secondary school certificate', true, false, false],
        'other_certificate' => ['Other certificates', false, true, false],
        'referee_letter' => ['Referee letters (with the referees’ contact details)', true, true, false],
        'passport_photo' => ['Passport photograph', true, false, true],
        'full_photo' => ['Full-length photograph', true, false, true],
    ];

    public const MAX_MULTIPLE = 5;

    protected $guarded = ['id'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(MembershipApplication::class, 'membership_application_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(PrivateFile::class, 'private_file_id');
    }

    public function label(): string
    {
        return self::KINDS[$this->kind][0] ?? $this->kind;
    }
}
