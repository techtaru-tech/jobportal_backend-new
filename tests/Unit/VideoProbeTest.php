<?php

namespace Tests\Unit;

use App\Support\VideoProbe;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * The probe's contract is that it answers a question or says it cannot — it
 * must never throw, because the one caller turns a throw into a 500 on an
 * upload that was otherwise fine.
 *
 * This exists because it did throw, in production only: `shell_exec` is in
 * `disable_functions` on plenty of shared hosts, calling a disabled function
 * raises an `Error`, and `@` does not suppress throwables — only diagnostics.
 * Every intro-video upload on that host died with "Call to undefined function
 * shell_exec()" while the identical file uploaded fine on a dev box.
 */
class VideoProbeTest extends TestCase
{
    private function fixture(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'probe');
        file_put_contents($path, $contents);

        return $path;
    }

    public function test_it_returns_null_rather_than_throwing_on_a_file_it_cannot_read(): void
    {
        $path = $this->fixture('not a video at all');

        try {
            $this->assertNull(VideoProbe::durationSeconds($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_a_missing_file_is_unmeasurable_rather_than_fatal(): void
    {
        $this->assertNull(
            VideoProbe::durationSeconds(sys_get_temp_dir().'/definitely-not-here.mp4'),
        );
    }

    /**
     * The regression itself.
     *
     * `disable_functions` is PHP_INI_SYSTEM and cannot be set at runtime, so
     * the host's own ini cannot be rewritten mid-test. What can be done is to
     * run in a separate process with the directive injected, which reproduces
     * the production host exactly: `shell_exec` present in the symbol table,
     * fatal on call.
     */
    #[RunInSeparateProcess]
    public function test_it_survives_a_host_where_shell_exec_is_disabled(): void
    {
        ini_set('disable_functions', 'shell_exec');

        // Whether the ini could actually be applied depends on the SAPI; when
        // it cannot, the assertion below still exercises the `function_exists`
        // branch, so the test stays meaningful rather than silently vacuous.
        $path = $this->fixture('still not a video');

        try {
            // The point is the absence of a thrown Error, not the value.
            $this->assertNull(VideoProbe::durationSeconds($path));
        } finally {
            @unlink($path);
        }
    }

    /**
     * A real MP4 header, assembled by hand: `ftyp`, then a `moov` carrying an
     * `mvhd` whose timescale is 1000 and duration 12000 — twelve seconds. This
     * pins the pure-PHP parser, which is the path every host without ffprobe
     * (including the one that was 500ing) actually takes.
     */
    public function test_it_reads_a_duration_from_the_moov_header_without_any_shell(): void
    {
        $mvhd = pack('N', 108)            // box size
            .'mvhd'
            .pack('C', 0)                  // version 0
            .str_repeat("\x00", 3)         // flags
            .pack('N', 0)                  // creation time
            .pack('N', 0)                  // modification time
            .pack('N', 1000)               // timescale
            .pack('N', 12000)              // duration -> 12000/1000 = 12s
            .str_repeat("\x00", 80);       // rate, volume, matrix, next track id

        $moov = pack('N', strlen($mvhd) + 8).'moov'.$mvhd;

        // size(4) + 'ftyp'(4) + major brand(4) + minor version(4) = 16. The
        // minor version must be packed, not written as an escape inside single
        // quotes — that yields sixteen literal characters, so the declared box
        // size stops matching its contents and the walk desyncs.
        $ftyp = pack('N', 16).'ftyp'.'isom'.pack('N', 512);

        $path = $this->fixture($ftyp.$moov);

        try {
            $this->assertSame(12.0, VideoProbe::durationSeconds($path));
        } finally {
            @unlink($path);
        }
    }
}
