<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CrudController;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** The system administrators themselves. */
class AdminController extends CrudController
{
    protected string $model = Admin::class;

    protected string $route = 'admin.admins';

    protected string $layout = 'layouts.admin';

    protected string $title = 'Administrators';

    protected string $singular = 'Administrator';

    protected string $icon = 'user-shield';

    protected array $search = ['name', 'email'];

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Full name', 'rules' => ['required', 'string', 'max:150']],
            ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'rules' => ['required', 'email', 'max:255', Rule::unique('admins')->ignore($record?->id)]],
            ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'rules' => [$record ? 'nullable' : 'required', Password::min(8)], 'help' => $record ? 'Leave empty to keep the password.' : 'At least 8 characters'],
            ['name' => 'is_active', 'label' => 'May sign in', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function columns(): array
    {
        return [
            'Full name' => fn (Admin $a) => $a->name,
            'Email address' => fn (Admin $a) => $a->email,
            'May sign in' => fn (Admin $a) => $a->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        $data['email'] = strtolower($data['email']);
        if ($record && $record->id === auth('admin')->id() && ! $data['is_active']) {
            $data['is_active'] = true; // nobody locks himself out
        }

        return $data;
    }

    protected function deleteProblem(Model $record): ?string
    {
        return $record->id === auth('admin')->id() ? __('You cannot delete yourself.') : null;
    }
}
