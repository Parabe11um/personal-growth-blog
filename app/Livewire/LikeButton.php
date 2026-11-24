<?php

namespace App\Livewire;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class LikeButton extends Component
{
    #[Reactive]
    public Post $post;

    public function toggleLike()
    {
        if (auth()->guest()) {
            return $this->redirect(route('login'), true);
        }

        $post = Post::published()
            ->whereKey($this->post->getKey())
            ->firstOrFail();

        $userId = auth()->id();

        $key = 'post-like:' . $userId;

        abort_unless(
            RateLimiter::attempt(
                $key,
                30,
                static fn (): bool => true,
                60
            ),
            429
        );

        DB::transaction(function () use ($post, $userId): void {
            $user = User::query()
                ->whereKey($userId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($user->hasLiked($post)) {
                $user->likes()->detach($post->getKey());

                return;
            }

            $user->likes()->attach($post->getKey());
        }, 3);
    }

    public function render()
    {
        return view('livewire.like-button');
    }
}
