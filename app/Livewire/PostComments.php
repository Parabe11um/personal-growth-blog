<?php

namespace App\Livewire;

use App\Models\Post;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class PostComments extends Component
{
    use WithPagination;

    #[Locked]
    public Post $post;

    #[Rule('required|string|min:3|max:200')]
    public string $comment = '';

    public function postComment()
    {
        abort_unless(auth()->check(), 403);

        $post = Post::published()
            ->whereKey($this->post->getKey())
            ->firstOrFail();

        $this->comment = trim($this->comment);

        $this->validateOnly('comment');

        $key = 'post-comment:' . auth()->id();

        $created = RateLimiter::attempt(
            $key,
            10,
            function () use ($post): bool {
                $post->comments()->create([
                    'comment' => $this->comment,
                    'user_id' => auth()->id(),
                ]);

                return true;
            },
            60
        );

        if (! $created) {
            $this->addError(
                'comment',
                'Слишком много комментариев. Попробуйте немного позже.'
            );

            return;
        }

        $this->reset('comment');
        $this->resetPage();

        unset($this->comments);
    }

    #[Computed()]
    public function comments()
    {
        return Post::published()
            ->whereKey($this->post->getKey())
            ->firstOrFail()
            ->comments()
            ->with('user')
            ->latest()
            ->paginate(5);
    }

    public function render()
    {
        return view('livewire.post-comments');
    }
}
