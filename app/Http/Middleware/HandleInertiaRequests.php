<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use CorePanel\Support\Inertia\CorePanelSharedProps;
use CorePanel\Support\Settings\SettingsLogoManager;
use CorePanel\Support\Settings\SettingsRepository;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'core-panel::app';

    public function __construct(private readonly CorePanelSharedProps $corePanelProps) {}

    public function version(Request $request): ?string
    {
        return null;
    }

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return array_replace_recursive(
            parent::share($request),
            $this->corePanelProps->forRequest($request),
            $this->hostSharedProps($request),
        );
    }

    /** @return array<string, mixed> */
    protected function hostSharedProps(Request $request): array
    {
        return [
            'corePanel' => ['settings' => ['ui' => ['primary_color_token' => app(SettingsRepository::class)->get('ui', 'primary_color_token', '#7134dd')]]],
            'appLogo' => fn () => app(SettingsLogoManager::class)->currentUrl() ?? asset('images/brand/icon.svg'),
            'appSubtitle' => fn () => app(SettingsRepository::class)->get('general', 'app_subtitle') ?? __('avatarvault.hero_note'),
        ];
    }
}
