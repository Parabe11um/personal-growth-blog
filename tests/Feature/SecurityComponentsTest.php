<?php

namespace Tests\Feature;

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Filament\Forms\Components\SecureFileUpload;
use App\Filament\Resources\PostResource\Pages\EditPost;
use App\Filament\Resources\PostResource\Pages\CreatePost;
use App\Livewire\LikeButton;
use App\Livewire\PostComments;
use App\Livewire\PostList;
use App\Livewire\SearchBox;
use App\Models\Post;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityComponentsTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(
        string $role = User::ROLE_USER
    ): User {
        return User::factory()->create([
            'role' => $role,
        ]);
    }

    protected function makePost(array $attributes = []): Post
    {
        return Post::create(array_merge([
            'user_id' => $this->makeUser()->id,
            'title' => 'Functional security article',
            'slug' => 'security-' . Str::random(12),
            'body' => '<p>Safe article content.</p>',
            'image' => 'posts/thumbnails/test.jpg',
            'published_at' => now()->subDay(),
            'featured' => false,
        ], $attributes));
    }

    public function test_guest_cannot_create_comment(): void
    {
        $post = $this->makePost();

        Livewire::test(PostComments::class, [
            'post' => $post,
        ])
            ->set('comment', 'Guest comment')
            ->call('postComment')
            ->assertStatus(403);

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_authenticated_user_can_create_comment(): void
    {
        $user = $this->makeUser();
        $post = $this->makePost();

        $this->actingAs($user);

        Livewire::test(PostComments::class, [
            'post' => $post,
        ])
            ->set('comment', '  Valid comment  ')
            ->call('postComment')
            ->assertHasNoErrors()
            ->assertSet('comment', '');

        $this->assertDatabaseHas('comments', [
            'user_id' => $user->id,
            'post_id' => $post->id,
            'comment' => 'Valid comment',
        ]);
    }

    public function test_whitespace_comment_is_rejected(): void
    {
        $this->actingAs($this->makeUser());

        Livewire::test(PostComments::class, [
            'post' => $this->makePost(),
        ])
            ->set('comment', '     ')
            ->call('postComment')
            ->assertHasErrors(['comment']);

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_short_comment_is_rejected(): void
    {
        $this->actingAs($this->makeUser());

        Livewire::test(PostComments::class, [
            'post' => $this->makePost(),
        ])
            ->set('comment', 'ab')
            ->call('postComment')
            ->assertHasErrors(['comment']);

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_oversized_comment_is_rejected(): void
    {
        $this->actingAs($this->makeUser());

        Livewire::test(PostComments::class, [
            'post' => $this->makePost(),
        ])
            ->set('comment', str_repeat('a', 201))
            ->call('postComment')
            ->assertHasErrors(['comment']);

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_comment_rate_limit_is_enforced(): void
    {
        $user = $this->makeUser();
        $post = $this->makePost();

        $this->actingAs($user);

        $key = 'post-comment:' . $user->id;

        RateLimiter::clear($key);

        try {
            for ($i = 0; $i < 10; $i++) {
                RateLimiter::hit($key, 60);
            }

            Livewire::test(PostComments::class, [
                'post' => $post,
            ])
                ->set('comment', 'Rate limited comment')
                ->call('postComment')
                ->assertHasErrors(['comment']);

            $this->assertDatabaseCount('comments', 0);
        } finally {
            RateLimiter::clear($key);
        }
    }

    public function test_unpublished_article_rejects_comment_submission(): void
    {
        $this->actingAs($this->makeUser());

        $post = $this->makePost();

        $component = Livewire::test(
            PostComments::class,
            ['post' => $post]
        );

        $component->set('comment', 'Valid comment');

        $post->update([
            'published_at' => null,
        ]);

        try {
            $component
                ->call('postComment')
                ->assertStatus(404);
        } catch (ModelNotFoundException $exception) {
            $this->assertSame(
                Post::class,
                $exception->getModel()
            );

            $response = app(
                \Illuminate\Foundation\Exceptions\Handler::class
            )->render(request(), $exception);

            $this->assertSame(404, $response->getStatusCode());
        }

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_guest_like_redirects_to_login(): void
    {
        Livewire::test(LikeButton::class, [
            'post' => $this->makePost(),
        ])
            ->call('toggleLike')
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('post_likes', 0);
    }

    public function test_authenticated_user_can_toggle_like(): void
    {
        $user = $this->makeUser();
        $post = $this->makePost();

        $this->actingAs($user);

        $component = Livewire::test(
            LikeButton::class,
            ['post' => $post]
        );

        $component
            ->call('toggleLike')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('post_likes', [
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);

        $component
            ->call('toggleLike')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('post_likes', [
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_like_rate_limit_is_enforced(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user);

        $key = 'post-like:' . $user->id;

        RateLimiter::clear($key);

        try {
            for ($i = 0; $i < 30; $i++) {
                RateLimiter::hit($key, 60);
            }

            Livewire::test(LikeButton::class, [
                'post' => $this->makePost(),
            ])
                ->call('toggleLike')
                ->assertStatus(429);

            $this->assertDatabaseCount('post_likes', 0);
        } finally {
            RateLimiter::clear($key);
        }
    }

    public function test_unpublished_article_rejects_like(): void
    {
        $this->actingAs($this->makeUser());

        $post = $this->makePost([
            'published_at' => null,
        ]);

        try {
            Livewire::test(LikeButton::class, [
                'post' => $post,
            ])
                ->call('toggleLike')
                ->assertStatus(404);
        } catch (ModelNotFoundException $exception) {
            $this->assertSame(
                Post::class,
                $exception->getModel()
            );

            $response = app(
                \Illuminate\Foundation\Exceptions\Handler::class
            )->render(request(), $exception);

            $this->assertSame(404, $response->getStatusCode());
        }

        $this->assertDatabaseCount('post_likes', 0);
    }

    public function test_invalid_sort_is_normalized(): void
    {
        Livewire::test(PostList::class)
            ->call('setSort', 'invalid')
            ->assertSet('sort', 'desc')
            ->call('setSort', 'asc')
            ->assertSet('sort', 'asc');
    }

    public function test_search_query_is_limited(): void
    {
        Livewire::test(PostList::class)
            ->call('updateSearch', str_repeat('a', 300))
            ->assertSet('search', str_repeat('a', 200));
    }

    public function test_array_search_parameter_is_rejected(): void
    {
        Livewire::test(PostList::class)
            ->call('updateSearch', ['invalid'])
            ->assertSet('search', '');
    }

    public function test_invalid_category_is_normalized(): void
    {
        Livewire::test(PostList::class)
            ->set('category', str_repeat('a', 256))
            ->assertSet('category', '');
    }

    public function test_popular_filter_normalization(): void
    {
        Livewire::test(PostList::class)
            ->set('popular', 'true')
            ->assertSet('popular', true)
            ->set('popular', 'invalid')
            ->assertSet('popular', false);
    }

    public function test_search_box_limits_input(): void
    {
        Livewire::test(SearchBox::class)
            ->set('search', str_repeat('a', 300))
            ->assertSet('search', str_repeat('a', 200))
            ->assertDispatched('search');
    }

    protected function getImageUploadField(
        ?Post $post = null
    ): SecureFileUpload {
        Storage::fake('public');

        if ($post && $post->image) {
            Storage::disk('public')->put(
                $post->image,
                'test fixture'
            );
        }

        $this->actingAs(
            $this->makeUser(User::ROLE_ADMIN)
        );

        Filament::setCurrentPanel(
            Filament::getPanel('admin')
        );

        $page = $post
            ? Livewire::test(EditPost::class, [
                'record' => $post->getRouteKey(),
            ])
            : Livewire::test(CreatePost::class);

        $page->assertStatus(200);

        $form = $page->instance()->getForm('form');

        foreach ($form->getFlatFields(true) as $field) {
            if (
                $field instanceof SecureFileUpload
                && $field->getName() === 'image'
            ) {
                return $field;
            }
        }

        throw new \RuntimeException(
            'SecureFileUpload field was not found.'
        );
    }

    public function test_existing_image_is_authorized_for_own_record(): void
    {
        $post = $this->makePost();

        $component = $this->getImageUploadField($post);

        $this->assertTrue(
            $component->isExistingFileAuthorized(
                'posts/thumbnails/test.jpg'
            )
        );
    }

    public function test_unrelated_image_reference_is_rejected(): void
    {
        $post = $this->makePost();

        $component = $this->getImageUploadField($post);

        $this->assertFalse(
            $component->isExistingFileAuthorized(
                'posts/thumbnails/another.jpg'
            )
        );
    }

    public function test_new_record_cannot_reference_existing_image(): void
    {
        $component = $this->getImageUploadField();

        $this->assertFalse(
            $component->isExistingFileAuthorized(
                'posts/thumbnails/test.jpg'
            )
        );
    }

    public function test_admin_can_open_post_edit_form(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put(
            'posts/thumbnails/test.jpg',
            'test fixture'
        );

        $admin = $this->makeUser(User::ROLE_ADMIN);
        $post = $this->makePost();

        $this->actingAs($admin);

        Filament::setCurrentPanel(
            Filament::getPanel('admin')
        );

        Livewire::test(EditPost::class, [
            'record' => $post->getRouteKey(),
        ])
            ->assertStatus(200)
            ->assertFormSet([
                'title' => $post->title,
                'slug' => $post->slug,
            ]);
    }

    public function test_regular_user_cannot_open_post_edit_form(): void
    {
        $post = $this->makePost();

        $this->actingAs($this->makeUser());

        Filament::setCurrentPanel(
            Filament::getPanel('admin')
        );

        Livewire::test(EditPost::class, [
            'record' => $post->getRouteKey(),
        ])->assertStatus(403);
    }

    public function test_valid_profile_photo_is_accepted(): void
    {
        Storage::fake('public');

        $user = $this->makeUser();

        $this->actingAs($user);

        app(UpdateUserProfileInformation::class)
            ->update($user, [
                'name' => $user->name,
                'email' => $user->email,
                'photo' => UploadedFile::fake()
                    ->image('avatar.png', 100, 100),
            ]);

        $user->refresh();

        $this->assertNotNull(
            $user->profile_photo_path
        );

        Storage::disk('public')->assertExists(
            $user->profile_photo_path
        );
    }

    public function test_non_image_profile_photo_is_rejected(): void
    {
        Storage::fake('public');

        $user = $this->makeUser();

        $this->actingAs($user);

        try {
            app(UpdateUserProfileInformation::class)
                ->update($user, [
                    'name' => $user->name,
                    'email' => $user->email,
                    'photo' => UploadedFile::fake()
                        ->create(
                            'document.txt',
                            1,
                            'text/plain'
                        ),
                ]);

            $this->fail('Non-image file was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'photo',
                $exception->errors()
            );
        }

        $this->assertNull(
            $user->fresh()->profile_photo_path
        );
    }

    public function test_oversized_profile_photo_is_rejected(): void
    {
        Storage::fake('public');

        $user = $this->makeUser();

        $this->actingAs($user);

        try {
            app(UpdateUserProfileInformation::class)
                ->update($user, [
                    'name' => $user->name,
                    'email' => $user->email,
                    'photo' => UploadedFile::fake()
                        ->image('avatar.png')
                        ->size(1025),
                ]);

            $this->fail('Oversized photo was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'photo',
                $exception->errors()
            );
        }

        $this->assertNull(
            $user->fresh()->profile_photo_path
        );
    }

    public function test_published_article_is_accessible(): void
    {
        $post = $this->makePost();

        $this->get('/blog/' . $post->slug)
            ->assertOk()
            ->assertSee($post->title);
    }

    public function test_article_without_image_is_accessible(): void
    {
        $post = $this->makePost([
            'image' => null,
        ]);

        $this->get('/blog/' . $post->slug)
            ->assertOk()
            ->assertSee($post->title);
    }
}
