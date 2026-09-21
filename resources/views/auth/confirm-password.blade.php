<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />

            <div x-data="{ visible: false }" class="relative mt-1">
                <x-text-input id="password" class="block w-full pr-20"
                                x-bind:type="visible ? 'text' : 'password'"
                                name="password"
                                required autocomplete="current-password" />
                <button type="button" x-on:click="visible = ! visible" class="absolute inset-y-0 right-0 px-3 text-sm font-semibold text-blue-800" x-text="visible ? 'Hide' : 'Show'" aria-label="Show or hide password"></button>
            </div>

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex justify-end mt-4">
            <x-primary-button>
                {{ __('Confirm') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
