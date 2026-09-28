<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaveAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by can:manage-admins on the route
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $target = $this->route('admin');
        $editing = $target instanceof Admin;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($editing ? $target->id : null)],
            'role' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'admin')],
            // New accounts get a password from whoever creates them; on edit it's an optional reset.
            'password' => [$editing ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'is_active' => [$editing ? 'required' : 'prohibited', 'boolean'],
        ];
    }
}
