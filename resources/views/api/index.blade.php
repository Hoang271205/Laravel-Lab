<x-user-layout>
    <x-slot name="page_title">
        
            {{ __('API Tokens') }}
        
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            @livewire('api-token-manager')
        </div>
    </div>
</x-user-layout>
