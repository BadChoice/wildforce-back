<?php

use App\Models\BodyProgressPhotoSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Lazy] class extends Component {
    #[Locked]
    public User $user;

    public function mount(User $user): void
    {
        abort_unless(auth()->user()?->canViewUserData($user->getKey()), 403);

        $this->user = $user;
    }

    /**
     * @return Collection<int, BodyProgressPhotoSession>
     */
    #[Computed]
    public function sessions(): Collection
    {
        return $this->user->bodyProgressPhotoSessions()
            ->whereNotNull('front_photo_path')
            ->latest()
            ->get();
    }
};
?>

@placeholder
    <section>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach (range(1, 4) as $index)
                <div class="aspect-[3/4] animate-pulse rounded-lg bg-zinc-100 dark:bg-zinc-800"></div>
            @endforeach
        </div>
    </section>
@endplaceholder

<section aria-labelledby="body-progress-photos-heading">
    <div class="mb-5">
        <flux:heading id="body-progress-photos-heading" size="sm">{{ __('Progress photos') }}</flux:heading>
        <flux:text variant="subtle">{{ __('Front photos taken over time.') }}</flux:text>
    </div>

    @if ($this->sessions->isEmpty())
        <x-dashboard.placeholder-tab :title="__('No progress photos yet')" :description="__('Front photos uploaded by this user will appear here.')" />
    @else
        <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($this->sessions as $session)
                @php($photoUrl = route('body-progress-photos.show', ['bodyProgressPhotoSession' => $session, 'angle' => 'front']))
                <li wire:key="body-progress-photo-{{ $session->id }}">
                    <a href="{{ $photoUrl }}" target="_blank" rel="noopener" class="block">
                        <img src="{{ $photoUrl }}" alt="{{ __('Front photo from :date', ['date' => $session->created_at->format('d/m/Y')]) }}" loading="lazy" class="aspect-[3/4] w-full rounded-lg bg-zinc-100 object-cover dark:bg-zinc-800" />
                    </a>
                    <flux:text variant="subtle" class="mt-2 text-center">{{ $session->created_at->format('d/m/Y') }}</flux:text>
                </li>
            @endforeach
        </ul>
    @endif
</section>
