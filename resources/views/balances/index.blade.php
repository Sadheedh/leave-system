<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Balances') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-500">
                            <tr>
                                <th class="px-6 py-3 font-medium">{{ __('Leave type') }}</th>
                                <th class="px-6 py-3 font-medium">{{ __('Days remaining') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-gray-900">
                            @forelse ($balances as $balance)
                                <tr>
                                    <td class="px-6 py-4">{{ $balance->leaveType->name }}</td>
                                    <td class="px-6 py-4">{{ $balance->days_remaining }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="px-6 py-8 text-center text-gray-500">
                                        {{ __('You have no leave balances for this year.') }}
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
