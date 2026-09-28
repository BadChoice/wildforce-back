<x-layouts::app :title="__('Exercises')">
    <livewire:exercises.catalog :user="auth()->user()" />
</x-layouts::app>
