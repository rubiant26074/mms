<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MachineController extends Controller
{
    public function index(Request $request): View
    {
        $machines = Machine::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%' . $request->string('search') . '%';
                $query->where(function ($sub) use ($term): void {
                    $sub->where('machine_code', 'like', $term)
                        ->orWhere('machine_name', 'like', $term)
                        ->orWhere('process_type', 'like', $term)
                        ->orWhere('location', 'like', $term);
                });
            })
            ->orderBy('machine_code')
            ->get();

        return view('admin.machines.index', compact('machines'));
    }

    public function create(): View
    {
        return view('admin.machines.form', ['machine' => new Machine(['status' => 'active']), 'isEdit' => false]);
    }

    public function edit(Machine $machine): View
    {
        return view('admin.machines.form', ['machine' => $machine, 'isEdit' => true]);
    }

    public function store(Request $request): RedirectResponse
    {
        Machine::query()->create($this->validated($request));

        return redirect()->route('admin.machines.index')->with('success', 'Data Mesin tersimpan!');
    }

    public function update(Request $request, Machine $machine): RedirectResponse
    {
        $machine->update($this->validated($request, $machine));

        return redirect()->route('admin.machines.index')->with('success', 'Data Mesin tersimpan!');
    }

    public function destroy(Machine $machine): RedirectResponse
    {
        $machine->delete();

        return redirect()->route('admin.machines.index')->with('success', 'Mesin berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Machine $machine = null): array
    {
        return $request->validate([
            'machine_code' => ['required', 'string', 'max:20', Rule::unique('machines', 'machine_code')->ignore($machine)],
            'machine_name' => ['required', 'string', 'max:100'],
            'process_type' => ['required', 'string', 'max:100'],
            'status' => ['required', Rule::in(['active', 'maintenance', 'broken'])],
            'location' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
