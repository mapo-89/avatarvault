<?php

namespace App\Http\Controllers;

use App\Models\EmailAlias;
use App\Models\User;
use App\Notifications\VerifyEmailAlias;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Mapo89\LaravelAvatarManager\Facades\AvatarManager;

class EmailAliasController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        $user = $request->user();
        $this->assertAvailable($data['email']);

        try {
            $alias = DB::transaction(function () use ($user, $data): EmailAlias {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                if ($user->emailAliases()->count() >= config('avatarvault.email_aliases.max_per_user')) {
                    throw ValidationException::withMessages(['email' => __('avatarvault.alias_limit')]);
                }

                return $user->emailAliases()->create([
                    'email' => $data['email'], 'email_hash' => AvatarManager::hash($data['email']),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => __('avatarvault.alias_unavailable')]);
        }

        $this->notify($alias, $user);

        return back()->with('success', __('avatarvault.alias_sent'));
    }

    public function resend(Request $request, string $alias): RedirectResponse
    {
        $record = $request->user()->emailAliases()->findOrFail($alias);
        if ($record->verified_at === null) {
            $this->assertAvailable($record->email);
            $this->notify($record, $request->user());
        }

        return back()->with('success', __('avatarvault.alias_sent'));
    }

    public function verify(Request $request, string $alias, string $hash): RedirectResponse
    {
        $record = $request->user()->emailAliases()->findOrFail($alias);
        abort_unless(hash_equals(sha1($record->email), $hash), 403);

        try {
            DB::transaction(function () use ($record): void {
                $alias = EmailAlias::query()->lockForUpdate()->findOrFail($record->id);
                if ($alias->verified_at !== null) {
                    return;
                }
                $this->assertAvailable($alias->email);
                $alias->forceFill(['verified_at' => now(), 'verified_email_hash' => $alias->email_hash])->save();
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => __('avatarvault.alias_unavailable')]);
        }

        return redirect()->route('dashboard')->with('success', __('avatarvault.alias_verified'));
    }

    public function destroy(Request $request, string $alias): RedirectResponse
    {
        $request->user()->emailAliases()->findOrFail($alias)->delete();

        return back()->with('success', __('avatarvault.alias_removed'));
    }

    private function assertAvailable(string $email): void
    {
        $primary = User::withTrashed()->whereRaw('LOWER(TRIM(email)) = ?', [$email])->exists();
        $verified = EmailAlias::query()->where('verified_email_hash', AvatarManager::hash($email))->exists();
        if ($primary || $verified) {
            throw ValidationException::withMessages(['email' => __('avatarvault.alias_unavailable')]);
        }
    }

    private function notify(EmailAlias $alias, User $owner): void
    {
        Notification::route('mail', $alias->email)->notify(
            (new VerifyEmailAlias($alias))->locale($owner->preferredLocale() ?? app()->getLocale())
        );
    }
}
