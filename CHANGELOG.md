# Changelog

All notable changes to `laravel-avatar-vault` will be documented in this file

## v1.0.1 - 2026-11-06

### 🔧 Chores
- Bump actions/checkout from 5 to 7
- Bump actions/setup-node from 4 to 7
- Bump phpunit/phpunit from 12.5.43 to 13.4.6
- Bump @vueuse/core from 14.3.0 to 15.0.0
- Bump @types/node from 25.6.2 to 26.6.6
- Align Docker runtime with PHP 8.5.12 and verify its platform
- Refresh frontend dependencies with a consistent npm lockfile
- Run TypeScript 7 alongside the supported Vue compiler API
- Upgrade Themes 3 using the CorePanel 1.7.1 compatibility fix

## v1.0.0 - 2026-11-05

Initial release.

### 💥 Breaking Changes
- Migrate AvatarVault to CorePanel and Avatar Manager 2

### ✨ Features
- Initial Commit
- Add conditional rendering for Authentik login button
- introduce AuthProviderFactory to resolve providers by key
- add AuthService to orchestrate redirect and callback via factory
- introduce generic AuthController using provider parameter
- add parameterized /auth/{provider} routes and callbacks (BC-ready)
- Share profile avatars through verified email aliases
- Resolve avatar URLs by SHA-256 email hashes

### 🐛 Fixes
- prevent overwriting existing password during OIDC login
- Only set password when creating a new account
- Build AvatarVault with patched PHP runtime
- Display AvatarVault version and validate release metadata
- Generate routes before typechecks and isolate release fixtures
- Refresh loaded media before replacing or removing avatars

### ♻️ Refactoring
- move Authentik redirect and callback logic to dedicated controller

### 🎨 UI & Styling
- Added favicon and centered new logo
- redesign guest layout with Authentik and (deactivated) Microsoft login buttons
- Change Header Logo
- set 'Remember me' checkbox checked by default on login form
- Favicon in public folder
- Preparation of Dark-Mode
- Add Heritage branding and Essential favicon
- Add landing page dark mode and shared theme switch

### 📝 Documentation
- Complete README documentation update for AvatarVault (German
- Updated Installation Readme with trustedProxies
- Update Hint for ignore git worktree
- update hint to check worktree status
- add Authentik OIDC integration and Localtunnel instructions to README
- document provider-agnostic auth flow and env-based button visibility
- Document CorePanel setup migration and email aliases
- Highlight Heritage logo and simplify version documentation

### 🔒 Security
- initial Socialite + Authentik setup, controller scaffold, env and services config
- add oidc_sub, oidc_provider, oidc_groups fields to users table and update User model
- Ensure password only set if null during OIDC user creation

### 🔥 Removed
- remove old AuthentikController

### 🔧 Chores
- Update Dependencies
- Adjustments for production
- Add possible trusted Proxies to Middleware
- Updated Example .env
- Changed authentik png to svg
- register Auth domain service provider and repository binding
- change Domain-directory to Domains
- Database Migrations for AUth Domain
- Unignore Lock-Files
- phpUnit Update
- Update dependencies
- Update dependencies
- Force HTTPS in Production (Maybe relevant for Proxies)
- Remove trust_proxies.php inclusion and add trustedproxy.php configuration
- Adopt shared release and dependency workflows

### 📌 Other Changes
- ✨ Feature(auth-ddd): install laravel-ddd and create Auth domain with contracts
- ✨ Feature(auth-ddd): implement UserRepository in Auth Domain with safe password handling
- ✨ Feature(auth-ddd): add provider implementations for Authentik
- ♻️ Refactoring(ui): switch social login buttons to provider-agnostic routes with config-based visibility

