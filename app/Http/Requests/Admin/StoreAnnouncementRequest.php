<?php

namespace App\Http\Requests\Admin;

use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by can:send-announcements on the route
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...self::segmentRules(),
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'channels' => ['nullable', 'array'],
            'channels.*' => [Rule::in(array_keys(Announcement::CHANNELS))],
        ];
    }

    /**
     * Shared with the recipient-count preview.
     *
     * @return array<string, mixed>
     */
    public static function segmentRules(): array
    {
        return [
            'audience' => ['required', Rule::in(array_keys(Announcement::AUDIENCES))],
            'min_rank_id' => ['nullable', 'integer', 'exists:ranks,id'],
            'package_id' => ['nullable', 'integer', 'exists:packages,id'],
        ];
    }
}
