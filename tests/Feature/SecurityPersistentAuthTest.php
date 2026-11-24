<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use DOMDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityPersistentAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function extractEditSnapshot(string $html): string
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument();

            $document->loadHTML($html, LIBXML_NONET);

            foreach (
                $document->getElementsByTagName('*')
                as $element
            ) {
                if (! $element->hasAttribute('wire:snapshot')) {
                    continue;
                }

                $snapshot = $element->getAttribute(
                    'wire:snapshot'
                );

                $data = json_decode(
                    $snapshot,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                $name = $data['memo']['name'] ?? '';

                if (str_ends_with($name, 'edit-post')) {
                    return $snapshot;
                }
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $this->fail(
            'EditPost Livewire snapshot was not found.'
        );
    }

    public function test_admin_update_is_denied_after_role_revocation(): void
    {
        $this->assertTrue(app()->environment('testing'));

        $this->assertSame(
            'sqlite',
            config('database.default')
        );

        $this->assertSame(
            ':memory:',
            config('database.connections.sqlite.database')
        );

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $post = Post::create([
            'user_id' => $admin->id,
            'title' => 'Persistent authorization test',
            'slug' => 'persistent-auth-' . Str::random(12),
            'body' => '<p>Original content.</p>',
            'image' => null,
            'published_at' => now()->subDay(),
            'featured' => false,
        ]);

        $this->actingAs($admin);

        $editUrl = '/admin/posts/'
            . $post->getRouteKey()
            . '/edit';

        // Initial authorized HTTP request.

        $page = $this->get($editUrl);

        $page->assertOk();

        $snapshot = $this->extractEditSnapshot(
            $page->getContent()
        );

        $this->assertNotEmpty($snapshot);

        $updateUrl = app("livewire")->getUpdateUri();

        $payload = [
            'components' => [
                [
                    'snapshot' => $snapshot,
                    'updates' => [],
                    'calls' => [],
                ],
            ],
        ];

        // Positive control: a normal Livewire HTTP update
        // succeeds while the user is still an administrator.

        $response = $this->postJson(
            $updateUrl,
            $payload,
            ['X-Livewire' => 'true']
        );

        $response
            ->assertOk()
            ->assertJsonStructure([
                'components' => [
                    ['snapshot', 'effects'],
                ],
            ]);

        $nextSnapshot = $response->json(
            'components.0.snapshot'
        );

        $this->assertIsString($nextSnapshot);
        $this->assertNotEmpty($nextSnapshot);

        // Revoke administrative access in the test database.

        $admin->forceFill([
            'role' => User::ROLE_USER,
        ])->save();

        $this->actingAs($admin->fresh());

        $this->assertSame(
            User::ROLE_USER,
            auth()->user()->role
        );

        // A subsequent request from the already-open
        // administrative component must now be denied.

        $payload['components'][0]['snapshot'] = $nextSnapshot;

        $this->postJson(
            $updateUrl,
            $payload,
            ['X-Livewire' => 'true']
        )->assertForbidden();

        $this->get($editUrl)
            ->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Persistent authorization test',
            'body' => '<p>Original content.</p>',
        ]);
    }
}
