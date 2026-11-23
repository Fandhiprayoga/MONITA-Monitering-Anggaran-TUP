<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PencairanSubmissionSettings
{
    public const CLOSED_MESSAGE = 'Pengajuan pencairan anggaran sedang ditutup. Silakan hubungi admin untuk informasi lebih lanjut.';

    public function isOpen(): bool
    {
        return (bool) DB::table('pencairan_submission_settings')->where('id', 1)->firstOrFail()->is_open;
    }

    public function allowed(array $actor): bool
    {
        return $actor['role'] === 'admin' || $this->isOpen();
    }

    public function requireAllowed(array $actor, bool $lock = false): void
    {
        if ($actor['role'] === 'admin') {
            return;
        }
        if ($lock) {
            $this->lock();
        }
        abort_unless($this->isOpen(), 403, self::CLOSED_MESSAGE);
    }

    private function lock(): void
    {
        // A write lock also serializes settings changes on SQLite, where FOR UPDATE is ignored.
        DB::table('pencairan_submission_settings')->where('id', 1)->update(['is_open' => DB::raw('is_open')]);
    }

    public function change(bool $open, string $username): bool
    {
        return DB::transaction(function () use ($open, $username) {
            $this->lock();
            $previous = $this->isOpen();
            if ($previous === $open) {
                return false;
            }
            DB::table('pencairan_submission_settings')->where('id', 1)->update([
                'is_open' => $open, 'updated_at' => now(),
            ]);
            DB::table('pencairan_submission_setting_histories')->insert([
                'from_open' => $previous, 'to_open' => $open,
                'actor_username' => $username, 'created_at' => now(),
            ]);
            return true;
        }, 5);
    }
}
