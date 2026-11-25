<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Photos and logos are served through /media/… by the app, not /storage/…,
 * which the live web server refuses with a 403.
 */
class MediaTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_an_uploaded_photo_is_linked_and_served_through_media(): void
    {
        Storage::fake('public');
        $this->actingAsCandidate();

        $url = $this->postJson("{$this->api}/candidate/profile/photo", [
            'file' => UploadedFile::fake()->image('me.jpg', 300, 300),
        ])->assertOk()->json('data.photo_url');

        $this->assertStringContainsString('/media/photos/', $url);

        $this->get(parse_url($url, PHP_URL_PATH))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_only_public_image_folders_are_reachable(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('photos/1/a.png', 'x');
        Storage::disk('public')->put('secret/b.png', 'x');
        Storage::disk('public')->put('photos/1/c.php', 'x');

        $this->get('/media/photos/1/a.png')->assertOk();
        $this->get('/media/secret/b.png')->assertNotFound();
        $this->get('/media/photos/1/c.php')->assertNotFound();
        $this->get('/media/photos/../secret/b.png')->assertNotFound();
        $this->get('/media/photos/1/missing.png')->assertNotFound();
    }
}
