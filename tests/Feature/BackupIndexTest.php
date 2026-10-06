<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_pdf_twin_with_correct_download(): void
    {
        Storage::fake('local');

        $user = User::create(['name' => 'T', 'email' => 'bi@test.dev', 'password' => bcrypt('x')]);
        $this->actingAs($user);
        $disk = Storage::disk('local');
        $dir = 'backups/user_'.$user->id;
        $disk->put("{$dir}/backup_2026_09_05_11_24_44.pdf", '%PDF-test');
        $disk->put("{$dir}/backup_2026_09_05_11_24_44.json", '{}');

        // Before the fix the .json twin's size lookup targeted
        // "backup_2026_09_05_11_24_44..json" (substr -4 leaves the dot of
        // the 5-char .json extension) and the page 500'd.
        $response = $this->get('/backup');
        $response->assertOk();
        $response->assertSee(route('backup.download', 'backup_2026_09_05_11_24_44.pdf'), false);
        $response->assertDontSee('..json', false);
    }

    public function test_index_badges_both_twins_of_a_new_backup(): void
    {
        Storage::fake('local');

        $user = User::create(['name' => 'T3', 'email' => 'bi3@test.dev', 'password' => bcrypt('x')]);
        $this->actingAs($user);
        $disk = Storage::disk('local');
        $dir = 'backups/user_'.$user->id;
        // Sized so the pair and the PDF alone round to visibly different KB.
        $disk->put("{$dir}/backup_2026_09_06_09_00_00.pdf", str_repeat('p', 1500));
        $disk->put("{$dir}/backup_2026_09_06_09_00_00.json", str_repeat('j', 500));

        // Every new backup writes both artifacts, so the row must show both
        // badges. A PDF-only badge made the JSON read as if it was never
        // created.
        $response = $this->get('/backup');
        $response->assertOk();
        $response->assertSeeInOrder(['PDF', 'JSON']);

        // The size shown is the whole archive, not just the PDF: the row now
        // claims to be both files, so counting one would understate it.
        $response->assertSee('1.95 KB');
        $response->assertDontSee('1.46 KB');
    }

    public function test_index_badges_only_the_half_that_exists(): void
    {
        Storage::fake('local');

        $user = User::create(['name' => 'T4', 'email' => 'bi4@test.dev', 'password' => bcrypt('x')]);
        $this->actingAs($user);
        $disk = Storage::disk('local');
        $dir = 'backups/user_'.$user->id;
        // JSON-only legacy archive: one badge, not two.
        $disk->put("{$dir}/backup_2026_07_04_10_00_00.json", '{}');

        $response = $this->get('/backup');
        $response->assertOk();
        $response->assertSee('JSON');
        $response->assertDontSee('PDF');
    }

    public function test_index_lists_json_only_backup_and_delete_removes_it(): void
    {
        Storage::fake('local');

        $user = User::create(['name' => 'T2', 'email' => 'bi2@test.dev', 'password' => bcrypt('x')]);
        $this->actingAs($user);
        $disk = Storage::disk('local');
        $dir = 'backups/user_'.$user->id;
        $disk->put("{$dir}/backup_2026_08_16_09_00_00.json", '{}');

        // Legacy JSON-only archive: listed with the JSON badge and a
        // working download link.
        $response = $this->get('/backup');
        $response->assertOk();
        $response->assertSee('JSON');
        $response->assertSee(route('backup.download', 'backup_2026_08_16_09_00_00.json'), false);
        $response->assertDontSee('..json', false);

        // Deleting it must resolve the real .json path — before the fix
        // destroy() also used substr(-4) and found neither twin.
        $delete = $this->delete('/backup/backup_2026_08_16_09_00_00.json');
        $delete->assertRedirect(route('backup.index'))
            ->assertSessionHas('success');
        $this->assertFalse($disk->exists("{$dir}/backup_2026_08_16_09_00_00.json"));
    }
}
