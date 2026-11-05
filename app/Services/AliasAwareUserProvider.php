<?php

namespace App\Services;

use App\Models\EmailAlias;
use App\Models\User;
use Mapo89\LaravelAvatarManager\Contracts\UserProviderInterface;
use Mapo89\LaravelAvatarManager\Services\UserProvider;

class AliasAwareUserProvider implements UserProviderInterface
{
    public function __construct(private readonly UserProvider $primaryUsers) {}

    public function findByEmailHash(string $hash): ?User
    {
        if (! preg_match('/\A(?:[a-f0-9]{32}|[a-f0-9]{64})\z/i', $hash)) {
            return null;
        }

        $hash = strtolower($hash);
        if (strlen($hash) === 64) {
            $user = User::query()->cursor()->first(fn (User $user) => hash('sha256', strtolower(trim($user->email))) === $hash);
            if ($user !== null) {
                return $user;
            }

            return EmailAlias::query()->whereNotNull('verified_email_hash')->cursor()
                ->first(fn (EmailAlias $alias) => hash('sha256', $alias->email) === $hash)?->user;
        }

        // Primary addresses remain authoritative if an external system changes an email.
        $user = $this->primaryUsers->findByEmailHash($hash);
        if ($user instanceof User) {
            return $user;
        }

        return EmailAlias::query()->with('user')
            ->where('verified_email_hash', strtolower($hash))->first()?->user;
    }
}
