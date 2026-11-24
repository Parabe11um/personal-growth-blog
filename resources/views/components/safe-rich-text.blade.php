@props(['html' => null])

{!! \Illuminate\Support\Str::sanitizeHtml((string) ($html ?? '')) !!}
