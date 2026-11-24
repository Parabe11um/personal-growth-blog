<?php

namespace Tests\Feature;

use App\Filament\Forms\Components\SecureFileUpload;
use App\Filament\Forms\Components\SecureRichEditor;
use App\Filament\Resources\PostResource\Pages\CreatePost;
use App\Filament\Resources\PostResource\Pages\EditPost;
use App\Models\Post;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SecurityFileUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filament.default_filesystem_disk' => 'public',
        ]);

        Storage::fake('public');

        Storage::fake(
            FileUploadConfiguration::disk()
        );

        $this->actingAs(
            User::factory()->create([
                'role' => User::ROLE_ADMIN,
            ])
        );

        Filament::setCurrentPanel(
            Filament::getPanel('admin')
        );
    }

    protected function makePost(): Post
    {
        $path = 'posts/thumbnails/'
            . Str::random(12) . '.png';

        $image = UploadedFile::fake()
            ->image('original.png', 100, 100);

        Storage::disk('public')->put(
            $path,
            file_get_contents($image->getRealPath())
        );

        return Post::create([
            'user_id' => auth()->id(),
            'title' => 'Upload security test',
            'slug' => 'upload-' . Str::random(12),
            'body' => '<p>Test content.</p>',
            'image' => $path,
            'published_at' => now()->subDay(),
            'featured' => false,
        ]);
    }

    protected function makeTemporaryUpload(
        UploadedFile $file
    ): TemporaryUploadedFile {
        $name = TemporaryUploadedFile::
            generateHashNameWithOriginalNameEmbedded($file);

        Storage::disk(
            FileUploadConfiguration::disk()
        )->put(
            FileUploadConfiguration::path($name),
            file_get_contents($file->getRealPath())
        );

        return TemporaryUploadedFile::createFromLivewire(
            $name
        );
    }

    public function test_edit_preserves_existing_image(): void
    {
        $post = $this->makePost();
        $original = $post->image;

        Livewire::test(EditPost::class, [
            'record' => $post->getRouteKey(),
        ])
            ->fillForm([
                'title' => 'Updated article title',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $post->refresh();

        $this->assertSame(
            'Updated article title',
            $post->title
        );

        $this->assertSame(
            $original,
            $post->image
        );

        Storage::disk('public')
            ->assertExists($original);
    }

    public function test_admin_can_replace_post_image(): void
    {
        $post = $this->makePost();
        $original = $post->image;

        $file = UploadedFile::fake()
            ->image('replacement.png', 120, 120);

        Livewire::test(EditPost::class, [
            'record' => $post->getRouteKey(),
        ])
            ->set('data.image', [$file])
            ->call('save')
            ->assertHasNoFormErrors();

        $post->refresh();

        $this->assertNotEmpty($post->image);

        $this->assertNotSame(
            $original,
            $post->image
        );

        $this->assertStringStartsWith(
            'posts/thumbnails/',
            $post->image
        );

        Storage::disk('public')
            ->assertExists($post->image);
    }

    public function test_admin_can_create_post_with_image(): void
    {
        $slug = 'new-upload-' . Str::random(12);

        $file = UploadedFile::fake()
            ->image('new-image.png', 100, 100);

        Livewire::test(CreatePost::class)
            ->fillForm([
                'title' => 'New uploaded article',
                'slug' => $slug,
                'body' => '<p>Safe content</p>',
            ])
            ->set('data.image', [$file])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = Post::where('slug', $slug)
            ->firstOrFail();

        $this->assertNotEmpty($post->image);

        Storage::disk('public')
            ->assertExists($post->image);
    }

    public function test_foreign_file_reference_is_denied(): void
    {
        $post = $this->makePost();
        $anotherPost = $this->makePost();

        $page = Livewire::test(EditPost::class, [
            'record' => $post->getRouteKey(),
        ]);

        $field = $page->instance()
            ->getForm('form')
            ->getFlatFields(true)['image'];

        $this->assertInstanceOf(
            SecureFileUpload::class,
            $field
        );

        $field->state([
            'test-key' => $anotherPost->image,
        ]);

        try {
            $field->getUploadedFiles();

            $this->fail(
                'Foreign file reference was accepted.'
            );
        } catch (HttpException $exception) {
            $this->assertSame(
                403,
                $exception->getStatusCode()
            );
        }

        $this->assertSame(
            $post->image,
            $post->fresh()->image
        );

        Storage::disk('public')
            ->assertExists($anotherPost->image);
    }

    public function test_rich_editor_accepts_image(): void
    {
        $page = Livewire::test(CreatePost::class);

        $field = $page->instance()
            ->getForm('form')
            ->getFlatFields(true)['body'];

        $this->assertInstanceOf(
            SecureRichEditor::class,
            $field
        );

        $file = $this->makeTemporaryUpload(
            UploadedFile::fake()
                ->image('rich-editor.png', 100, 100)
        );

        $url = $field->saveUploadedFileAttachment(
            $file
        );

        $this->assertNotEmpty($url);

        $files = Storage::disk('public')
            ->allFiles('posts/images');

        $this->assertCount(1, $files);

        Storage::disk('public')
            ->assertExists($files[0]);
    }

    public function test_rich_editor_rejects_text_file(): void
    {
        $page = Livewire::test(CreatePost::class);

        $field = $page->instance()
            ->getForm('form')
            ->getFlatFields(true)['body'];

        $this->assertInstanceOf(
            SecureRichEditor::class,
            $field
        );

        $file = $this->makeTemporaryUpload(
            UploadedFile::fake()
                ->createWithContent(
                    'document.txt',
                    'Ordinary test text.'
                )
        );

        try {
            $field->saveUploadedFileAttachment($file);

            $this->fail(
                'Non-image attachment was accepted.'
            );
        } catch (ValidationException $exception) {
            $this->assertNotEmpty(
                $exception->errors()
            );
        }

        $this->assertSame(
            [],
            Storage::disk('public')
                ->allFiles('posts/images')
        );
    }
}
