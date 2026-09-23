<?php

namespace App\Models;

use App\Enums\KycDocumentType;
use App\Enums\KycStatus;
use Database\Factories\KycDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property KycDocumentType $type
 * @property KycStatus $status
 */
#[Fillable(['member_id', 'type', 'document_number', 'file_path'])]
class KycDocument extends Model implements HasMedia
{
    /** @use HasFactory<KycDocumentFactory> */
    use HasFactory, InteractsWithMedia;

    protected function casts(): array
    {
        return [
            'type' => KycDocumentType::class,
            'status' => KycStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents')->useDisk('local');
        $this->addMediaCollection('photo')->singleFile()->useDisk('local');
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Admin, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }
}
