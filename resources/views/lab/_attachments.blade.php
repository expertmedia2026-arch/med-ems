@if ($attachments->isNotEmpty())
    <div x-data="{ open: false, src: '' }" class="mt-3">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($attachments as $file)
                <div class="overflow-hidden rounded-lg border bg-slate-50">
                    @if ($file->is_image)
                        <button type="button" @click="src = @js($file->url); open = true" class="block h-28 w-full">
                            <img src="{{ $file->url }}" alt="{{ $file->original_name }}" loading="lazy" class="h-28 w-full object-cover">
                        </button>
                    @else
                        <a href="{{ $file->url }}" target="_blank" class="flex h-28 items-center justify-center bg-slate-100 text-4xl hover:bg-slate-200">📄</a>
                    @endif

                    <div class="px-2 py-1.5 text-xs">
                        <p class="truncate font-medium" title="{{ $file->original_name }}">{{ $file->original_name }}</p>
                        <p class="text-slate-400">{{ $file->human_size }}</p>

                        @if ($canManage ?? false)
                            <form method="POST" action="{{ route('lab.attachments.destroy', $file) }}"
                                  onsubmit="return confirm('Remove this file?')" class="mt-1">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">Remove</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Full-size viewer --}}
        <div x-show="open" x-cloak @keydown.escape.window="open = false" @click="open = false"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4">
            <img :src="src" alt="" class="max-h-full max-w-full rounded" @click.stop>
            <button type="button" @click="open = false" class="absolute right-5 top-4 text-4xl leading-none text-white">×</button>
        </div>
    </div>
@endif