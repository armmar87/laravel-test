@extends('layouts.admin')

@section('title', 'Pending Reservations')

@section('content')

<div class="mt-6 mb-4 flex items-center justify-between">
    <div>
        <h2 class="text-2xl font-bold text-gray-800">Pending Reservations</h2>
        <p class="text-sm text-gray-500 mt-1">
            Showing {{ $reservations->firstItem() }}–{{ $reservations->lastItem() }}
            of {{ $reservations->total() }} reservations
        </p>
    </div>
    <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-3 py-1 rounded-full">
        {{ $reservations->total() }} pending
    </span>
</div>

@if($reservations->isEmpty())
    <div class="bg-white rounded-lg shadow p-12 text-center text-gray-400">
        <div class="text-5xl mb-3">🎉</div>
        <p class="text-lg font-medium">No pending reservations</p>
        <p class="text-sm mt-1">All reservations have been processed.</p>
    </div>
@else
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left font-semibold text-gray-600 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-3 text-left font-semibold text-gray-600 uppercase tracking-wider">User</th>
                    <th class="px-6 py-3 text-left font-semibold text-gray-600 uppercase tracking-wider">Book</th>
                    <th class="px-6 py-3 text-left font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left font-semibold text-gray-600 uppercase tracking-wider">Reserved At</th>
                    <th class="px-6 py-3 text-left font-semibold text-gray-600 uppercase tracking-wider">Expires At</th>
                    <th class="px-6 py-3 text-right font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($reservations as $reservation)
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4 font-mono text-gray-500">#{{ $reservation->id }}</td>

                    <td class="px-6 py-4">
                        <div class="font-medium text-gray-800">{{ $reservation->user->name }}</div>
                        <div class="text-gray-400 text-xs">{{ $reservation->user->email }}</div>
                    </td>

                    <td class="px-6 py-4">
                        <div class="font-medium text-gray-800">{{ $reservation->book->title }}</div>
                        <div class="text-gray-400 text-xs">{{ $reservation->book->author }}</div>
                    </td>

                    <td class="px-6 py-4">
                        <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2.5 py-0.5 rounded-full capitalize">
                            {{ $reservation->status->value }}
                        </span>
                    </td>

                    <td class="px-6 py-4 text-gray-500">
                        {{ $reservation->created_at->format('Y-m-d H:i') }}
                    </td>

                    <td class="px-6 py-4 text-gray-500">
                        @if($reservation->expires_at)
                            <span class="{{ $reservation->expires_at->isPast() ? 'text-red-500 font-semibold' : '' }}">
                                {{ $reservation->expires_at->format('Y-m-d H:i') }}
                            </span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>

                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">

                            {{-- Confirm button --}}
                            <form method="POST"
                                  action="{{ route('admin.web.reservations.confirm', $reservation->id) }}"
                                  onsubmit="return confirm('Confirm reservation #{{ $reservation->id }}?')">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                        class="bg-green-600 hover:bg-green-500 text-white text-xs font-semibold px-3 py-1.5 rounded transition">
                                    ✓ Confirm
                                </button>
                            </form>

                            {{-- Cancel button --}}
                            <form method="POST"
                                  action="{{ route('admin.web.reservations.cancel', $reservation->id) }}"
                                  onsubmit="return confirm('Cancel reservation #{{ $reservation->id }} and restore stock?')">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                        class="bg-red-600 hover:bg-red-500 text-white text-xs font--semibold px-3 py-1.5 rounded transition">
                                    ✕ Cancel
                                </button>
                            </form>

                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($reservations->hasPages())
        <div class="mt-4">
            {{ $reservations->links() }}
        </div>
    @endif
@endif

@endsection

