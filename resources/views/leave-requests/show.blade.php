<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Leave Request') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
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

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 space-y-4 text-sm text-gray-900">
                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-y-3">
                    <dt class="text-gray-500">{{ __('Employee') }}</dt>
                    <dd class="sm:col-span-2">{{ $leaveRequest->user->name }}</dd>

                    <dt class="text-gray-500">{{ __('Type') }}</dt>
                    <dd class="sm:col-span-2">{{ $leaveRequest->leaveType->name }}</dd>

                    <dt class="text-gray-500">{{ __('Dates') }}</dt>
                    <dd class="sm:col-span-2">{{ $leaveRequest->start_date->format('d M Y') }} - {{ $leaveRequest->end_date->format('d M Y') }}</dd>

                    <dt class="text-gray-500">{{ __('Days') }}</dt>
                    <dd class="sm:col-span-2">{{ $leaveRequest->days }}</dd>

                    <dt class="text-gray-500">{{ __('Reason') }}</dt>
                    <dd class="sm:col-span-2">{{ $leaveRequest->reason }}</dd>

                    <dt class="text-gray-500">{{ __('Status') }}</dt>
                    <dd class="sm:col-span-2">
                        <span @class([
                            'inline-flex px-2 py-1 text-xs font-semibold rounded-full',
                            'bg-yellow-100 text-yellow-800' => $leaveRequest->status === 'pending',
                            'bg-green-100 text-green-800' => $leaveRequest->status === 'approved',
                            'bg-red-100 text-red-800' => $leaveRequest->status === 'rejected',
                            'bg-gray-100 text-gray-800' => $leaveRequest->status === 'cancelled',
                        ])>
                            {{ ucfirst($leaveRequest->status) }}
                        </span>
                    </dd>

                    {{-- Review details only exist after a manager has decided. --}}
                    @if ($leaveRequest->reviewer)
                        <dt class="text-gray-500">{{ __('Reviewed by') }}</dt>
                        <dd class="sm:col-span-2">
                            {{ $leaveRequest->reviewer->name }}
                            @if ($leaveRequest->reviewed_at)
                                <span class="text-gray-500">({{ $leaveRequest->reviewed_at->format('d M Y H:i') }})</span>
                            @endif
                        </dd>
                    @endif

                    @if ($leaveRequest->review_note)
                        <dt class="text-gray-500">{{ __('Review note') }}</dt>
                        <dd class="sm:col-span-2">{{ $leaveRequest->review_note }}</dd>
                    @endif
                </dl>

                {{-- The button is only a convenience; the route and policy are the real protection. --}}
                @can('cancel', $leaveRequest)
                    <form method="POST" action="{{ route('leave-requests.cancel', $leaveRequest) }}">
                        @csrf
                        <x-danger-button>{{ __('Cancel Request') }}</x-danger-button>
                    </form>
                @endcan
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-sm text-gray-900">
                <h3 class="font-semibold text-gray-800 mb-3">{{ __('History') }}</h3>
                <ul class="divide-y divide-gray-200">
                    @foreach ($leaveRequest->events as $event)
                        <li class="py-3">
                            <div class="text-gray-500">{{ $event->created_at->format('d M Y H:i') }}</div>
                            <div>
                                {{ $event->user->name }}:
                                {{ $event->from_status ?? 'created' }} -> {{ $event->to_status }}
                            </div>
                            @if ($event->note)
                                <div class="text-gray-500">{{ $event->note }}</div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
