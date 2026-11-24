<?php

namespace App\Livewire;

use Livewire\Component;

class SearchBox extends Component
{
    public $search = '';

    protected function normalizeSearch(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return mb_substr(trim($value), 0, 200);
    }

    public function updatedSearch(): void
    {
        $this->update();
    }

    public function update(): void
    {
        $this->search = $this->normalizeSearch($this->search);

        $this->dispatch(
            'search',
            search: $this->search
        );
    }

    public function render()
    {
        $this->search = $this->normalizeSearch($this->search);

        return view('livewire.search-box');
    }
}
