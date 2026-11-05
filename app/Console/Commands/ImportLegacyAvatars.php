<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportLegacyAvatars extends Command
{
    protected $signature = 'avatarvault:import-legacy-avatars';

    protected $description = 'Copy legacy Jetstream profile photos into CorePanel without deleting originals';

    public function handle(): int
    {
        $disk = config('avatar-manager.profile_photo_disk');
        $failed = 0;
        $imported = 0;

        foreach (User::query()->whereNotNull('profile_photo_path')->lazyById(100) as $user) {
            try {
                if ($user->getFirstMedia('avatars') === null) {
                    if (! Storage::disk($disk)->exists($user->profile_photo_path)) {
                        $this->warn('Missing legacy photo for user '.$user->id.'; leaving reference unchanged.');
                        $failed++;

                        continue;
                    }

                    $user->addMediaFromDisk($user->profile_photo_path, $disk)
                        ->preservingOriginal()->toMediaCollection('avatars');
                    $imported++;
                }

                $user->forceFill(['profile_photo_path' => null])->save();
            } catch (Throwable $exception) {
                report($exception);
                $this->error('Import failed for user '.$user->id.'; retry after resolving the storage error.');
                $failed++;
            }
        }

        $this->info($imported.' photos imported; '.$failed.' unresolved. Original files retained.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
