<x-app-layout>
    <div class="px-3 py-6 lg:px-7">
        <div class="bg-white dark:bg-gray-900 shadow-md rounded-xl p-6 sm:p-10 prose max-w-none dark:prose-invert">
            {!! $terms ?? $policy !!}
        </div>
    </div>
</x-app-layout>
