<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\TextWidget;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
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
            'title' => 'Security test article',
            'slug' => 'security-test-' . Str::random(12),
            'body' => '<p>Safe article content.</p>',
            'image' => 'posts/thumbnails/test.jpg',
            'published_at' => now()->subDay(),
            'featured' => false,
        ], $attributes));
    }

    public function test_admin_can_access_admin_panel(): void
    {
        $user = $this->makeUser(User::ROLE_ADMIN);

        $this->assertTrue(
            $user->canAccessPanel(
                Filament::getPanel('admin')
            )
        );
    }

    public function test_editor_can_access_admin_panel(): void
    {
        $user = $this->makeUser(User::ROLE_EDITOR);

        $this->assertTrue(
            $user->canAccessPanel(
                Filament::getPanel('admin')
            )
        );
    }

    public function test_regular_user_cannot_access_admin_panel(): void
    {
        $user = $this->makeUser();

        $this->assertFalse(
            $user->canAccessPanel(
                Filament::getPanel('admin')
            )
        );
    }

    public function test_admin_can_bulk_delete_categories(): void
    {
        $user = $this->makeUser(User::ROLE_ADMIN);

        $this->assertTrue(
            Gate::forUser($user)->allows(
                'deleteAny', Category::class
            )
        );
    }

    public function test_editor_cannot_bulk_delete_categories(): void
    {
        $user = $this->makeUser(User::ROLE_EDITOR);

        $this->assertFalse(
            Gate::forUser($user)->allows(
                'deleteAny', Category::class
            )
        );
    }

    public function test_editor_cannot_manage_users(): void
    {
        $user = $this->makeUser(User::ROLE_EDITOR);

        foreach (['viewAny', 'create', 'deleteAny'] as $ability) {
            $this->assertFalse(
                Gate::forUser($user)->allows(
                    $ability, User::class
                )
            );
        }
    }

    public function test_admin_can_bulk_delete_users(): void
    {
        $user = $this->makeUser(User::ROLE_ADMIN);

        $this->assertTrue(
            Gate::forUser($user)->allows(
                'deleteAny', User::class
            )
        );
    }

    public function test_regular_user_cannot_manage_text_widgets(): void
    {
        $user = $this->makeUser();

        foreach (['viewAny', 'create', 'deleteAny'] as $ability) {
            $this->assertFalse(
                Gate::forUser($user)->allows(
                    $ability, TextWidget::class
                )
            );
        }
    }

    public function test_editor_can_manage_widget_content_but_not_delete(): void
    {
        $user = $this->makeUser(User::ROLE_EDITOR);

        $widget = new TextWidget();

        $gate = Gate::forUser($user);

        $this->assertTrue(
            $gate->allows('viewAny', TextWidget::class)
        );

        $this->assertTrue(
            $gate->allows('update', $widget)
        );

        $this->assertFalse(
            $gate->allows('delete', $widget)
        );

        $this->assertFalse(
            $gate->allows('deleteAny', TextWidget::class)
        );
    }

    public function test_editor_can_moderate_but_not_delete_comments(): void
    {
        $user = $this->makeUser(User::ROLE_EDITOR);

        $comment = new Comment();

        $gate = Gate::forUser($user);

        $this->assertTrue(
            $gate->allows('viewAny', Comment::class)
        );

        $this->assertTrue(
            $gate->allows('update', $comment)
        );

        $this->assertFalse(
            $gate->allows('delete', $comment)
        );

        $this->assertFalse(
            $gate->allows('deleteAny', Comment::class)
        );
    }

    public function test_regular_user_cannot_access_comment_administration(): void
    {
        $user = $this->makeUser();

        $gate = Gate::forUser($user);

        $this->assertFalse(
            $gate->allows('viewAny', Comment::class)
        );

        $this->assertFalse(
            $gate->allows('update', new Comment())
        );
    }

    public function test_admin_can_delete_comments_and_widgets(): void
    {
        $user = $this->makeUser(User::ROLE_ADMIN);

        $gate = Gate::forUser($user);

        $this->assertTrue(
            $gate->allows('delete', new Comment())
        );

        $this->assertTrue(
            $gate->allows('delete', new TextWidget())
        );

        $this->assertTrue(
            $gate->allows('deleteAny', Comment::class)
        );

        $this->assertTrue(
            $gate->allows('deleteAny', TextWidget::class)
        );
    }

    public function test_draft_article_is_not_publicly_accessible(): void
    {
        $post = $this->makePost([
            'published_at' => null,
        ]);

        $this->get('/blog/' . $post->slug)
            ->assertNotFound();
    }

    public function test_future_article_is_not_publicly_accessible(): void
    {
        $post = $this->makePost([
            'published_at' => now()->addDay(),
        ]);

        $this->get('/blog/' . $post->slug)
            ->assertNotFound();
    }

    public function test_deleted_article_is_not_publicly_accessible(): void
    {
        $post = $this->makePost();

        $post->delete();

        $this->get('/blog/' . $post->slug)
            ->assertNotFound();
    }

    public function test_sitemap_contains_only_published_articles(): void
    {
        $published = $this->makePost();

        $draft = $this->makePost([
            'published_at' => null,
        ]);

        $future = $this->makePost([
            'published_at' => now()->addDay(),
        ]);

        $deleted = $this->makePost();

        $deleted->delete();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('/blog/' . $published->slug, false)
            ->assertDontSee('/blog/' . $draft->slug, false)
            ->assertDontSee('/blog/' . $future->slug, false)
            ->assertDontSee('/blog/' . $deleted->slug, false);
    }

    public function test_rich_text_preserves_safe_formatting(): void
    {
        $html = Blade::render(
            '<x-safe-rich-text :html="$content" />',
            [
                'content' => '<p><strong>Safe text</strong></p>',
            ]
        );

        $this->assertStringContainsString(
            '<strong>Safe text</strong>',
            $html
        );
    }

    public function test_rich_text_removes_unsafe_markup(): void
    {
        $html = Blade::render(
            '<x-safe-rich-text :html="$content" />',
            [
                'content' =>
                    '<p>Safe content</p>'
                    . '<script>testMarker()</script>'
                    . '<img src="/test.jpg" onerror="testMarker()">'
                    . '<a href="javascript:void(0)">Link</a>',
            ]
        );

        $this->assertStringContainsString(
            'Safe content',
            $html
        );

        $this->assertStringNotContainsString(
            '<script',
            strtolower($html)
        );

        $this->assertStringNotContainsString(
            'onerror=',
            strtolower($html)
        );

        $this->assertStringNotContainsString(
            'javascript:',
            strtolower($html)
        );
    }

    public function test_livewire_uses_private_temporary_disk(): void
    {
        $this->assertSame(
            'local',
            config('livewire.temporary_file_upload.disk')
        );

        $this->assertContains(
            'auth',
            config('livewire.temporary_file_upload.middleware')
        );

        $this->assertContains(
            'throttle:60,1',
            config('livewire.temporary_file_upload.middleware')
        );

        $this->assertSame(
            ['jpg', 'jpeg', 'png', 'webp'],
            config('livewire.temporary_file_upload.preview_mimes')
        );
    }

    public function test_livewire_restricts_temporary_file_types(): void
    {
        $rules = config(
            'livewire.temporary_file_upload.rules'
        );

        $rules = is_array($rules)
            ? implode('|', $rules)
            : $rules;

        $this->assertStringContainsString(
            'mimetypes:image/jpeg,image/png,image/webp',
            $rules
        );

        $this->assertStringContainsString(
            'extensions:jpg,jpeg,png,webp',
            $rules
        );

        $this->assertStringContainsString(
            'max:5120',
            $rules
        );
    }

    public function test_database_rejects_duplicate_post_likes(): void
    {
        $user = $this->makeUser();

        $post = $this->makePost();

        $like = [
            'user_id' => $user->id,
            'post_id' => $post->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('post_likes')->insert($like);

        $this->assertDatabaseCount('post_likes', 1);

        $this->expectException(QueryException::class);

        DB::table('post_likes')->insert($like);
    }
}
