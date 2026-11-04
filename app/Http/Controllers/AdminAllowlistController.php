<?php

namespace App\Http\Controllers;

use App\Repositories\AdminAllowlistRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AdminAllowlistController extends Controller
{
    public function __construct(private readonly AdminAllowlistRepository $allowlist) {}

    public function index(): InertiaResponse
    {
        return Inertia::render('Admin/Allowlist', [
            'entries' => $this->allowlist->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'emp_no' => ['required', 'string', 'max:50'],
            'label'  => ['nullable', 'string', 'max:255'],
        ], [
            'emp_no.required' => 'رقم الموظف مطلوب.',
            'emp_no.max'      => 'رقم الموظف لا يجب أن يتجاوز 50 حرفاً.',
        ]);

        $addedBy = Auth::guard('admin')->user()?->name ?? 'admin';

        $this->allowlist->add(
            empNo:   trim($data['emp_no']),
            label:   $data['label'] ?? null,
            addedBy: $addedBy,
        );

        return redirect()->route('admin.allowlist')
            ->with('ok', 'تمت إضافة رقم الموظف إلى قائمة المصرح لهم.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->allowlist->remove($id);

        return redirect()->route('admin.allowlist')
            ->with('ok', 'تم حذف رقم الموظف من قائمة المصرح لهم.');
    }
}
