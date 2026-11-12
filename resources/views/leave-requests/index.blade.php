<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Requests') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-4 text-sm text-green-800 bg-green-100 rounded-md">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="p-4 text-sm text-red-800 bg-red-100 rounded-md">
                    {{ session('error') }}
                </div>
            @endif

            <div class="flex justify-end">
                <a href="{{ route('leave-requests.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    {{ __('New Request') }}
                </a>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-500">
                            <tr>
                                <th class="px-6 py-3 font-medium">{{ __('Type') }}</th>
                                <th class="px-6 py-3 font-medium">{{ __('Dates') }}</th>
                                <th class="px-6 py-3 font-medium">{{ __('Days') }}</th>
                                <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                                <th class="px-6 py-3 font-medium">{{ __('Submitted') }}</th>
                                <th class="px-6 py-3 font-medium">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-gray-900">
                            @forelse ($leaveRequests as $leaveRequest)
                                <tr>
                                    <td class="px-6 py-4">{{ $leaveRequest->leaveType->name }}</td>
                                    <td class="px-6 py-4">
                                        {{ $leaveRequest->start_date->format('d M Y') }} - {{ $leaveRequest->end_date->format('d M Y') }}
                                        <div class="text-gray-500">{{ $leaveRequest->reason }}</div>
                                    </td>
                                    <td class="px-6 py-4">{{ $leaveRequest->days }}</td>
                                    <td class="px-6 py-4">
                                        <span @class([
                                            'inline-flex px-2 py-1 text-xs font-semibold rounded-full',
                                            'bg-yellow-100 text-yellow-800' => $leaveRequest->status === 'pending',
                                            'bg-green-100 text-green-800' => $leaveRequest->status === 'approved',
                                            'bg-red-100 text-red-800' => $leaveRequest->status === 'rejected',
                                            'bg-gray-100 text-gray-800' => $leaveRequest->status === 'cancelled',
                                        ])>
                                            {{ ucfirst($leaveRequest->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">{{ $leaveRequest->created_at->format('d M Y') }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <a href="{{ route('leave-requests.show', $leaveRequest) }}" class="text-indigo-600 hover:text-indigo-900 font-medium">{{ __('View') }}</a>
                                            {{-- The button is only a convenience; the route and policy are the real protection. --}}
                                            @can('cancel', $leaveRequest)
                                                <form method="POST" action="{{ route('leave-requests.cancel', $leaveRequest) }}">
                                                    @csrf
                                                    <button type="submit" class="text-red-600 hover:text-red-900 font-medium">{{ __('Cancel') }}</button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                        {{ __('You have not made any leave requests yet.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
