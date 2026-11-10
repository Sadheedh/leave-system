<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('New Leave Request') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('leave-requests.store') }}" class="p-6 space-y-6">
                    @csrf

                    <div>
                        <x-input-label for="leave_type_id" :value="__('Leave type')" />
                        <select id="leave_type_id" name="leave_type_id" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">{{ __('Select a leave type') }}</option>
                            @foreach ($leaveTypes as $leaveType)
                                <option value="{{ $leaveType->id }}" @selected((int) old('leave_type_id') === $leaveType->id)>{{ $leaveType->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('leave_type_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="start_date" :value="__('Start date')" />
                        <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full" :value="old('start_date')" :min="now()->toDateString()" required />
                        <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="end_date" :value="__('End date')" />
                        <x-text-input id="end_date" name="end_date" type="date" class="mt-1 block w-full" :value="old('end_date')" :min="now()->toDateString()" required />
                        <x-input-error :messages="$errors->get('end_date')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="reason" :value="__('Reason')" />
                        <textarea id="reason" name="reason" rows="4" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('reason') }}</textarea>
                        <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>{{ __('Submit Request') }}</x-primary-button>
                        <a href="{{ route('leave-requests.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
