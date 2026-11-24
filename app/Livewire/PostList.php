<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Post;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class PostList extends Component
{
    use WithPagination;

    #[Url()]
    public $sort = 'desc';

    #[Url()]
    public $search = '';

    #[Url()]
    public $category = '';

    #[Url()]
    public $popular = false;

    protected function normalizeSort(mixed $value): string
    {
        return in_array($value, ['asc', 'desc'], true)
            ? $value
            : 'desc';
    }

    protected function normalizeSearch(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return mb_substr(trim($value), 0, 200);
    }

    protected function normalizeCategory(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $value = trim($value);

        if (mb_strlen($value) > 255) {
            return '';
        }

        return $value;
    }

    protected function normalizePopular(mixed $value): bool
    {
        if (! is_bool($value) && ! is_string($value) && ! is_int($value)) {
            return false;
        }

        return filter_var(
            $value,
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function mount(): void
    {
        $this->normalizeFilters();
    }

    protected function normalizeFilters(): void
    {
        $this->sort = $this->normalizeSort($this->sort);
        $this->search = $this->normalizeSearch($this->search);
        $this->category = $this->normalizeCategory($this->category);
        $this->popular = $this->normalizePopular($this->popular);
    }

    public function updatedSort(): void
    {
        $this->sort = $this->normalizeSort($this->sort);
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->search = $this->normalizeSearch($this->search);
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->category = $this->normalizeCategory($this->category);
        $this->resetPage();

        unset($this->activeCategory);
    }

    public function updatedPopular(): void
    {
        $this->popular = $this->normalizePopular($this->popular);
        $this->resetPage();
    }

    public function setSort($sort): void
    {
        $this->sort = $this->normalizeSort($sort);
        $this->resetPage();
    }

    #[On('search')]
    public function updateSearch($search): void
    {
        $this->search = $this->normalizeSearch($search);
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->category = '';

        $this->resetPage();

        unset($this->activeCategory);
        unset($this->posts);
    }

    #[Computed()]
    public function posts()
    {
        $sort = $this->normalizeSort($this->sort);
        $search = $this->normalizeSearch($this->search);
        $category = $this->normalizeCategory($this->category);
        $popular = $this->normalizePopular($this->popular);

        return Post::published()
            ->with('author', 'categories')
            ->when($category !== '' && $this->activeCategory, function ($query) use ($category) {
                $query->withCategory($category);
            })
            ->when($popular, function ($query) {
                $query->popular();
            })
            ->search($search)
            ->orderBy('published_at', $sort)
            ->paginate(3);
    }

    #[Computed()]
    public function activeCategory()
    {
        $category = $this->normalizeCategory($this->category);

        if ($category === '') {
            return null;
        }

        return Category::where('slug', $category)->first();
    }

    public function render()
    {
        $this->normalizeFilters();

        return view('livewire.post-list');
    }
}
