<?php

use App\Models\DiagnosticResult;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

/**
 * Lecturer Reports — laporan berkala Diagnostic Result (Req 22.1-22.3).
 * Read-only untuk dosen.
 */
new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public ?int $filterUserId = null;
    public ?string $filterFrom = null;
    public ?string $filterUntil = null;

    public function updatedFilterUserId(): void { $this->resetPage(); }
    public function updatedFilterFrom(): void { $this->resetPage(); }
    public function updatedFilterUntil(): void { $this->resetPage(); }

    public function with(): array
    {
        $query = DiagnosticResult::with('user')
            ->orderByDesc('created_at');

        if ($this->filterUserId) {
            $query->where('user_id', $this->filterUserId);
        }
        if ($this->filterFrom) {
            $query->whereDate('created_at', '>=', $this->filterFrom);
        }
        if ($this->filterUntil) {
            $query->whereDate('created_at', '<=', $this->filterUntil);
        }

        return [
            'results'  => $query->paginate(20),
            'students' => User::role('mahasiswa')->orderBy('name')->get(['id', 'name']),
        ];
    }
}; ?>

<div class="max-w-5xl mx-auto py-8 px-4">
    <h1 class="text-2xl font-bold mb-6">Laporan Diagnostic Test</h1>

    {{-- Filters (Req 22.3) --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 bg-white p-4 rounded-lg shadow">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Mahasiswa</label>
            <select wire:model.live="filterUserId" class="w-full rounded border-gray-300 text-sm">
                <option value="">Semua</option>
                @foreach ($students as $student)
                    <option value="{{ $student->id }}">{{ $student->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Dari Tanggal</label>
            <input type="date" wire:model.live="filterFrom" class="w-full rounded border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Sampai Tanggal</label>
            <input type="date" wire:model.live="filterUntil" class="w-full rounded border-gray-300 text-sm">
        </div>
    </div>

    {{-- Results table (Req 22.2) --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Mahasiswa</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600">Speaking</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600">Grammar</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600">Vocabulary</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600">Overall</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-600">Attempt</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Tanggal</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($results as $result)
                    <tr>
                        <td class="px-4 py-3">{{ $result->user?->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-center">{{ $result->speaking }}</td>
                        <td class="px-4 py-3 text-center">{{ $result->grammar }}</td>
                        <td class="px-4 py-3 text-center">{{ $result->vocabulary }}</td>
                        <td class="px-4 py-3 text-center font-medium">{{ number_format($result->overall, 1) }}</td>
                        <td class="px-4 py-3 text-center">{{ $result->attempt }}</td>
                        <td class="px-4 py-3">{{ $result->created_at->format('d M Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">Belum ada data.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $results->links() }}
    </div>
</div>
