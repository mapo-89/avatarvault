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
        if (! preg_match('/\A[a-f0-9]{32}\z/i', $hash)) {
            return null;
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
