<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group heading="Inventar" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        Übersicht
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="cube" :href="route('items.index')" :current="request()->routeIs('items.*')" wire:navigate>
                        Gegenstände
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="arrows-right-left" :href="route('loans.index')" :current="request()->routeIs('loans.*')" wire:navigate>
                        {{ auth()->user()->canManageInventory() ? 'Ausleihen' : 'Meine Ausleihen' }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                @if (auth()->user()->canManageInventory())
                    <flux:sidebar.group heading="Verwaltung" class="grid">
                        <flux:sidebar.item icon="map-pin" :href="route('locations.index')" :current="request()->routeIs('locations.*')" wire:navigate>
                            Standorte
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="tag" :href="route('categories.index')" :current="request()->routeIs('categories.*')" wire:navigate>
                            Kategorien
                        </flux:sidebar.item>
                        @php($openFoundReports = \App\Models\FoundReport::query()->where('status', 'open')->count())
                        <flux:sidebar.item icon="hand-raised" :href="route('found-reports.index')" :current="request()->routeIs('found-reports.*')" :badge="$openFoundReports ?: null" wire:navigate>
                            Fundmeldungen
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif

                @if (auth()->user()->isAdmin())
                    <flux:sidebar.group heading="Administration" class="grid">
                        @php($pendingUsers = \App\Models\User::query()->where('role', 'pending')->count())
                        <flux:sidebar.item icon="users" :href="route('users.index', $pendingUsers ? ['status' => 'pending'] : [])" :current="request()->routeIs('users.*')" :badge="$pendingUsers ?: null" wire:navigate>
                            Benutzer
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="document-magnifying-glass" :href="route('audit.index')" :current="request()->routeIs('audit.*')" wire:navigate>
                            Audit-Trail
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="key" :href="route('api-tokens.index')" :current="request()->routeIs('api-tokens.*')" wire:navigate>
                    MCP-Zugang
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        <flux:toast />

        @fluxScripts
    </body>
</html>
