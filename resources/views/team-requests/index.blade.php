<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Team Requests') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-4 text-sm text-green-800 bg-green-100 rounded-md">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-500">
                            <tr>
                                <th class="px-6 py-3 font-medium">{{ __('Employee') }}</th>
                                <th class="px-6 py-3 font-medium">{{ __('Type') }}</th>
                                <th class="px-6 py-3 font-medium">{{ __('Dates') }}</th>
                                <th class="px-6 py-3 font-medium">{{ __('Days') }}</th>
                                <th class="px-6 py-3 font-medium">{{ __('Reason') }}</th>
                                <th class="px-6 py-3 font-medium">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-gray-900">
                            @forelse ($leaveRequests as $leaveRequest)
                                <tr>
                                    <td class="px-6 py-4">{{ $leaveRequest->user->name }}</td>
                                    <td class="px-6 py-4">{{ $leaveRequest->leaveType->name }}</td>
                                    <td class="px-6 py-4">
                                        {{ $leaveRequest->start_date->format('d M Y') }} - {{ $leaveRequest->end_date->format('d M Y') }}
                                    </td>
                                    <td class="px-6 py-4">{{ $leaveRequest->days }}</td>
                                    <td class="px-6 py-4">{{ $leaveRequest->reason }}</td>
                                    <td class="px-6 py-4">
                                        {{-- One form per row; each button's formaction picks the route, so the note is shared. --}}
                                        <form method="POST" action="{{ route('team-requests.approve', $leaveRequest) }}" class="space-y-2">
                                            @csrf
                                            {{-- On a validation failure Laravel flashes the submitted input, so old('leave_request_id') tells us which row was submitted. --}}
                                            <input type="hidden" name="leave_request_id" value="{{ $leaveRequest->id }}">
                                            @php($submittedThisRow = (int) old('leave_request_id') === $leaveRequest->id)
                                            <textarea name="review_note" rows="2" maxlength="1000" placeholder="{{ __('Note (optional)') }}" class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">{{ $submittedThisRow ? old('review_note') : '' }}</textarea>
                                            @if ($submittedThisRow)
                                                <x-input-error :messages="$errors->get('review_note')" />
                                            @endif
                                            <div class="flex gap-2">
                                                <x-primary-button formaction="{{ route('team-requests.approve', $leaveRequest) }}">
                                                    {{ __('Approve') }}
                                                </x-primary-button>
                                                <x-danger-button formaction="{{ route('team-requests.reject', $leaveRequest) }}">
                                                    {{ __('Reject') }}
                                                </x-danger-button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                        {{ __('No pending requests from your team.') }}
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
