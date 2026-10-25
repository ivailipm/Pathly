@if ($subjects->isNotEmpty())
    @php($openSubject = request()->route('subject'))

    <nav aria-labelledby="sidebar-subjects-label" class="flex flex-col gap-0.5">
        <x-eyebrow id="sidebar-subjects-label" size="sm" class="px-2.5 pb-1.5">{{ __('Subjects') }}</x-eyebrow>

        @foreach ($subjects as $subject)
            <x-sidebar-link
                :href="route('subjects.show', $subject)"
                :current="$openSubject instanceof \App\Models\Subject && $openSubject->is($subject)"
            >
                <x-monogram :subject="$subject" />
                <span class="truncate">{{ $subject->title }}</span>
            </x-sidebar-link>
        @endforeach
    </nav>
@endif
