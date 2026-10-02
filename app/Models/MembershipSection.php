<?php

namespace App\Models;

use App\Support\RichText;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A text section of the public membership page, written by admins in
 * English and (optionally) Bangla. Bodies are sanitized HTML.
 *
 * @property int $id
 * @property string $title_en
 * @property string|null $title_bn
 * @property string $body_en
 * @property string|null $body_bn
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['title_en', 'title_bn', 'body_en', 'body_bn', 'sort_order', 'is_active'])]
class MembershipSection extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Title in the current language, falling back to English.
     */
    public function localTitle(): string
    {
        return app()->getLocale() === 'bn' && filled($this->title_bn) ? $this->title_bn : $this->title_en;
    }

    /**
     * Body in the current language (falling back to English), as safe HTML.
     */
    public function localBody(): string
    {
        $body = app()->getLocale() === 'bn' && filled(RichText::clean($this->body_bn)) ? $this->body_bn : $this->body_en;

        return (string) RichText::clean($body);
    }
}
