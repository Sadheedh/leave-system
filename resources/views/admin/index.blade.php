<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Admin') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-500">
                            <tr>
                                <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                                <th class="px-6 py-3 font-medium">{{ __('Email') }}</th>
                                <th class="px-6 py-3 font-medium">{{ __('Role') }}</th>
                                <th class="px-6 py-3 font-medium">{{ __('Manager') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-gray-900">
                            @foreach ($users as $user)
                                <tr>
                                    <td class="px-6 py-4">{{ $user->name }}</td>
                                    <td class="px-6 py-4">{{ $user->email }}</td>
                                    <td class="px-6 py-4">{{ ucfirst($user->role) }}</td>
                                    <td class="px-6 py-4">{{ $user->manager?->name ?? __('None') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-500">
                            <tr>
                                <th class="px-6 py-3 font-medium">{{ __('Leave type') }}</th>
                                <th class="px-6 py-3 font-medium">{{ __('Default days per year') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-gray-900">
                            @forelse ($leaveTypes as $leaveType)
                                <tr>
                                    <td class="px-6 py-4">{{ $leaveType->name }}</td>
                                    <td class="px-6 py-4">{{ $leaveType->default_days }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="px-6 py-8 text-center text-gray-500">
                                        {{ __('No leave types yet.') }}
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
