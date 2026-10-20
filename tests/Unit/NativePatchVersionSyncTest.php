<?php

namespace Tests\Unit;

use App\Console\Commands\NativePatch;
use PHPUnit\Framework\TestCase;

/**
 * The vendor version-sync patch keeps gradle versionCode, bundle_meta.json and
 * the bundled .env pointing at the same build number. When they disagree the
 * on-device staleness check never converges and every cold start deletes and
 * re-extracts the whole Laravel bundle (the 2026-10-08 APK shipped meta=72 /
 * env=73 and launched through the ~90MB unzip each time).
 */
class NativePatchVersionSyncTest extends TestCase
{
    public function test_inserts_config_sync_after_both_env_writes(): void
    {
        $vendor = $this->vendorFixture();

        $patched = NativePatch::applyVersionSyncPatch($vendor);

        $this->assertNotNull($patched);
        $this->assertSame(2, substr_count((string) $patched, "config(['nativephp.version_code' => \$currentBuildNumber]);"));
    }

    public function test_is_idempotent_once_applied(): void
    {
        $vendor = $this->vendorFixture();
        $first = NativePatch::applyVersionSyncPatch($vendor);
        $second = NativePatch::applyVersionSyncPatch((string) $first);

        $this->assertSame($first, $second);
    }

    public function test_vendor_already_fixed_ships_unchanged(): void
    {
        $fixed = str_replace(
            "\$this->updateEnvFile('NATIVEPHP_APP_VERSION_CODE', \$currentBuildNumber);",
            "\$this->updateEnvFile('NATIVEPHP_APP_VERSION_CODE', \$currentBuildNumber);\n            config(['nativephp.version_code' => \$currentBuildNumber]);",
            $this->vendorFixture(),
        );

        $this->assertSame($fixed, NativePatch::applyVersionSyncPatch($fixed));
    }

    public function test_reports_null_when_vendor_anchor_disappears(): void
    {
        $drifted = <<<'PHP'
        private function incrementBuildNumber(): void
        {
            $currentBuildNumber = $this->freshBuildNumberFromSomethingElse();

            $this->updateEnvFile('NATIVEPHP_APP_VERSION_CODE', $currentBuildNumber);
        }
        PHP;

        $this->assertNull(NativePatch::applyVersionSyncPatch($drifted));
    }

    public function test_patched_vendor_file_actually_carries_the_sync(): void
    {
        $patched = NativePatch::applyVersionSyncPatch($this->vendorFixture());

        $this->assertNotNull($patched);
        $this->assertSame(
            2,
            preg_match_all('/^\s+config\(\[\'nativephp\.version_code\' => \$currentBuildNumber\]\);$/m', (string) $patched),
        );
    }

    /**
     * Trimmed from vendor/nativephp/mobile 3.3 RunsAndroid::incrementBuildNumber
     * — the exact shape the patch anchors on (two updateEnvFile calls).
     */
    private function vendorFixture(): string
    {
        return <<<'PHP'
        private function incrementBuildNumber(): void
        {
            $isPackageCommand = get_class($this) === 'Native\Mobile\Commands\PackageCommand';

            if (! $isPackageCommand) {
                return;
            }

            $currentBuildNumber = env('NATIVEPHP_APP_VERSION_CODE');

            if (! $currentBuildNumber) {
                // If not set, start with 1
                $currentBuildNumber = 1;
                // Initialize build number silently
                $this->updateEnvFile('NATIVEPHP_APP_VERSION_CODE', $currentBuildNumber);
            } else {
                // Increment the existing build number for packaging builds
                $currentBuildNumber = (int) $currentBuildNumber + 1;
                // Increment build number silently
                $this->updateEnvFile('NATIVEPHP_APP_VERSION_CODE', $currentBuildNumber);
            }
        }
        PHP;
    }
}
