<?php

namespace App\Services;

use App\Models\User;
use App\Support\PrivateFiles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes an account and everything it owns — the account-deletion request
 * Google Play and the App Store require apps to offer.
 *
 * One account, one job: this deletes the account that is signed in (a job
 * seeker account *or* an employer account). The other kind of account on the
 * same mobile number, if there is one, is a separate account and stays.
 *
 * Rows: deleting the user cascades (every user_id / organisation / job
 * foreign key is cascadeOnDelete) to the profile, education, experience,
 * applications and their chats, saved jobs, alerts, notifications, device
 * tokens, subscriptions, payment orders, job credits, the employer profile,
 * companies and their job posts. Sign-in tokens are deleted here.
 *
 * Files: the user's photo, resumes and intro videos, and each company's logo
 * and verification document, removed from disk after the rows are gone.
 */
class AccountDeletion
{
    public function delete(User $user): void
    {
        $userId = $user->id;
        $organisationIds = $user->organisations()->pluck('id')->all();

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });

        // After the commit: a failed transaction must not have already lost
        // the files of an account that still exists.
        $this->deleteDirectories([
            ['public', "photos/{$userId}"],
            [PrivateFiles::DISK, "resumes/{$userId}"],
            [PrivateFiles::DISK, "intro-videos/{$userId}"],
            ...array_merge(...array_map(fn ($id) => [
                ['public', "organisations/{$id}"],
                [PrivateFiles::DISK, "organisations/{$id}"],
            ], $organisationIds ?: [])),
        ]);
    }

    /** @param  list<array{0: string, 1: string}>  $dirs */
    private function deleteDirectories(array $dirs): void
    {
        foreach ($dirs as [$disk, $dir]) {
            try {
                Storage::disk($disk)->deleteDirectory($dir);
            } catch (\Throwable $e) {
                // The account is already gone; a leftover file is logged for
                // clean-up rather than failing the request.
                Log::warning('[account-deletion] could not remove files', ['disk' => $disk, 'dir' => $dir, 'error' => $e->getMessage()]);
            }
        }
    }
}
