<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_backup_pdf_end_to_end(): void
    {
        Storage::fake('local');

        $user = User::create(['name' => 'T', 'email' => 'bd@test.dev', 'password' => bcrypt('x')]);
        $this->actingAs($user);
        $disk = Storage::disk('local');
        $dir = 'backups/user_'.$user->id;

        $pdf = 'backup_2026_08_16_09_00_00.pdf';
        $json = 'backup_2026_08_16_09_00_00.json';
        $disk->put("{$dir}/{$pdf}", '%PDF-test');
        $disk->put("{$dir}/{$json}", '{}');

        // browser sends POST with _method=DELETE (form method spoofing)
        $response = $this->post("/backup/{$pdf}", ['_method' => 'DELETE']);
        $response->assertRedirect(route('backup.index'));
        $response->assertSessionHas('success');

        $this->assertFalse($disk->exists("{$dir}/{$pdf}"));
        $this->assertFalse($disk->exists("{$dir}/{$json}"));
    }

    public function test_path_traversal_is_rejected(): void
    {
        $user = User::create(['name' => 'T2', 'email' => 'bd2@test.dev', 'password' => bcrypt('x')]);
        $this->actingAs($user);

        // Slash-based traversal ("%2F") decodes to "/" inside the router and
        // never matches a single {filename} segment, so it dies as a 404 long
        // before the controller. Backslash traversal survives routing as one
        // segment — the controller's filename guard must reject it.
        $response = $this->delete('/backup/..%5C..%5Cenv');
        $response->assertRedirect(route('backup.index'));
        $response->assertSessionHas('error');
    }
}
