<div>
    <x-filament::modal
        id="profile-slideover"
        :visible="$isOpen"
        width="md"
        slide-over
        sticky-header
        display-classes="block"
    >
        <x-slot name="heading">
            Edit Profile
        </x-slot>

        <x-slot name="description">
            Update your personal information and password.
        </x-slot>

        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-6 flex justify-end gap-3">
                <x-filament::button
                    color="gray"
                    x-on:click="$wire.set('isOpen', false)"
                >
                    Cancel
                </x-filament::button>

                <x-filament::button type="submit">
                    Save Changes
                </x-filament::button>
            </div>
        </form>
    </x-filament::modal>
</div>
