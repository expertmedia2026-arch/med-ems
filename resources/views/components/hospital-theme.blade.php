@php
    $primary = $hospital->primary_color ?: '#0f766e';
    $secondary = $hospital->secondary_color ?: '#1d4ed8';
    $accent = $hospital->accent_color ?: $secondary;
    $highlight = $hospital->highlight_color ?: $primary;
@endphp
<style>
    :root {
        --hospital-primary: {{ $primary }};
        --hospital-secondary: {{ $secondary }};
        --hospital-accent: {{ $accent }};
        --hospital-highlight: {{ $highlight }};
    }

    .bg-teal-50 { background-color: color-mix(in srgb, var(--hospital-primary) 7%, white) !important; }
    .bg-teal-50\/50 { background-color: color-mix(in srgb, var(--hospital-primary) 5%, transparent) !important; }
    .bg-teal-50\/60 { background-color: color-mix(in srgb, var(--hospital-primary) 6%, transparent) !important; }
    .bg-teal-100 { background-color: color-mix(in srgb, var(--hospital-primary) 14%, white) !important; }
    .bg-teal-200 { background-color: color-mix(in srgb, var(--hospital-primary) 24%, white) !important; }
    .bg-teal-600 { background-color: color-mix(in srgb, var(--hospital-primary) 94%, black) !important; }
    .bg-teal-700 { background-color: var(--hospital-primary) !important; }
    .bg-teal-800 { background-color: color-mix(in srgb, var(--hospital-primary) 86%, black) !important; }
    .bg-teal-900 { background-color: color-mix(in srgb, var(--hospital-primary) 72%, black) !important; }
    .text-teal-50 { color: color-mix(in srgb, var(--hospital-primary) 5%, white) !important; }
    .text-teal-100 { color: color-mix(in srgb, var(--hospital-primary) 15%, white) !important; }
    .text-teal-200 { color: color-mix(in srgb, var(--hospital-primary) 28%, white) !important; }
    .text-teal-700 { color: var(--hospital-primary) !important; }
    .text-teal-800 { color: color-mix(in srgb, var(--hospital-primary) 86%, black) !important; }
    .text-teal-900 { color: color-mix(in srgb, var(--hospital-primary) 68%, black) !important; }
    .text-teal-950 { color: color-mix(in srgb, var(--hospital-primary) 58%, black) !important; }
    .text-teal-100\/50 { color: color-mix(in srgb, var(--hospital-primary) 8%, white) !important; }
    .border-teal-100 { border-color: color-mix(in srgb, var(--hospital-primary) 15%, white) !important; }
    .border-teal-200 { border-color: color-mix(in srgb, var(--hospital-primary) 28%, white) !important; }
    .border-teal-500 { border-color: color-mix(in srgb, var(--hospital-primary) 65%, white) !important; }
    .border-teal-600 { border-color: color-mix(in srgb, var(--hospital-primary) 82%, white) !important; }
    .border-teal-700 { border-color: var(--hospital-primary) !important; }
    .border-teal-800 { border-color: color-mix(in srgb, var(--hospital-primary) 82%, black) !important; }
    .ring-teal-600 { --tw-ring-color: var(--hospital-primary) !important; }
    .ring-teal-200 { --tw-ring-color: color-mix(in srgb, var(--hospital-primary) 28%, white) !important; }
    .focus\:ring-teal-600:focus { --tw-ring-color: var(--hospital-primary) !important; }
    .focus\:border-teal-600:focus { border-color: var(--hospital-primary) !important; }
    .hover\:bg-teal-50:hover { background-color: color-mix(in srgb, var(--hospital-primary) 7%, white) !important; }
    .hover\:bg-teal-50\/50:hover { background-color: color-mix(in srgb, var(--hospital-primary) 4%, white) !important; }
    .hover\:bg-teal-100:hover { background-color: color-mix(in srgb, var(--hospital-primary) 14%, white) !important; }
    .hover\:bg-teal-700:hover { background-color: var(--hospital-primary) !important; }
    .hover\:bg-teal-800:hover { background-color: color-mix(in srgb, var(--hospital-primary) 86%, black) !important; }
    .hover\:bg-teal-900:hover { background-color: color-mix(in srgb, var(--hospital-primary) 72%, black) !important; }
    .hover\:text-teal-700:hover { color: var(--hospital-primary) !important; }
    .hover\:text-teal-900:hover { color: color-mix(in srgb, var(--hospital-primary) 68%, black) !important; }
    .file\:bg-teal-50::file-selector-button { background-color: color-mix(in srgb, var(--hospital-primary) 7%, white) !important; }
    .file\:text-teal-700::file-selector-button { color: var(--hospital-primary) !important; }

    .bg-blue-50 { background-color: color-mix(in srgb, var(--hospital-secondary) 7%, white) !important; }
    .bg-blue-100 { background-color: color-mix(in srgb, var(--hospital-secondary) 14%, white) !important; }
    .bg-blue-700 { background-color: var(--hospital-secondary) !important; }
    .border-blue-100 { border-color: color-mix(in srgb, var(--hospital-secondary) 15%, white) !important; }
    .text-blue-700 { color: var(--hospital-secondary) !important; }
    .text-blue-800 { color: color-mix(in srgb, var(--hospital-secondary) 86%, black) !important; }
    .text-blue-900 { color: color-mix(in srgb, var(--hospital-secondary) 68%, black) !important; }
    .text-blue-950 { color: color-mix(in srgb, var(--hospital-secondary) 58%, black) !important; }
    .border-blue-200 { border-color: color-mix(in srgb, var(--hospital-secondary) 28%, white) !important; }
    .border-blue-300 { border-color: color-mix(in srgb, var(--hospital-secondary) 42%, white) !important; }
    .border-blue-700 { border-color: var(--hospital-secondary) !important; }
    .border-blue-800 { border-color: color-mix(in srgb, var(--hospital-secondary) 82%, black) !important; }
    .hover\:bg-blue-800:hover { background-color: color-mix(in srgb, var(--hospital-secondary) 86%, black) !important; }

    .bg-purple-50 { background-color: color-mix(in srgb, var(--hospital-accent) 7%, white) !important; }
    .bg-purple-100 { background-color: color-mix(in srgb, var(--hospital-accent) 14%, white) !important; }
    .bg-purple-700 { background-color: var(--hospital-accent) !important; }
    .text-purple-700 { color: var(--hospital-accent) !important; }
    .text-purple-900 { color: color-mix(in srgb, var(--hospital-accent) 68%, black) !important; }
    .border-purple-200 { border-color: color-mix(in srgb, var(--hospital-accent) 28%, white) !important; }

    .bg-indigo-50 { background-color: color-mix(in srgb, var(--hospital-highlight) 7%, white) !important; }
    .bg-indigo-100 { background-color: color-mix(in srgb, var(--hospital-highlight) 14%, white) !important; }
    .bg-indigo-700 { background-color: var(--hospital-highlight) !important; }
    .text-indigo-700 { color: var(--hospital-highlight) !important; }
    .text-indigo-900 { color: color-mix(in srgb, var(--hospital-highlight) 68%, black) !important; }
    .border-indigo-200 { border-color: color-mix(in srgb, var(--hospital-highlight) 28%, white) !important; }
    .border-indigo-400 { border-color: color-mix(in srgb, var(--hospital-highlight) 55%, white) !important; }
    .text-indigo-600 { color: var(--hospital-highlight) !important; }
    .text-indigo-800 { color: color-mix(in srgb, var(--hospital-highlight) 82%, black) !important; }
    .focus\:border-indigo-500:focus { border-color: var(--hospital-highlight) !important; }
    .focus\:border-indigo-700:focus { border-color: var(--hospital-highlight) !important; }
    .focus\:ring-indigo-500:focus { --tw-ring-color: var(--hospital-highlight) !important; }
</style>
