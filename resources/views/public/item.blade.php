@php
    /** @var \App\Models\Item $item */
    $badge = match ($availability) {
        \App\Enums\ItemAvailability::Available => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
        \App\Enums\ItemAvailability::OnLoan => 'bg-amber-100 text-amber-800 ring-amber-600/20',
        \App\Enums\ItemAvailability::OutOfStock => 'bg-zinc-100 text-zinc-700 ring-zinc-500/20',
    };
@endphp
<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
        <meta name="robots" content="noindex, nofollow" />
        <meta name="theme-color" content="#18181b" />
        <title>{{ $item->name }} – {{ $organisation }}</title>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @vite('resources/css/public.css')
    </head>
    <body class="min-h-dvh bg-zinc-100 font-sans text-zinc-900 antialiased">
        <main class="mx-auto flex w-full max-w-md flex-col gap-4 px-4 pt-6 pb-10">
            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200">
                <p class="text-sm font-medium text-zinc-500">{{ $organisation }}</p>
                <h1 class="mt-1 text-2xl leading-tight font-semibold break-words">{{ $item->name }}</h1>

                <p class="mt-4">
                    <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm font-medium ring-1 ring-inset {{ $badge }}" data-test="availability">
                        <span class="size-2 rounded-full bg-current"></span>
                        {{ ucfirst($availability->label()) }}
                    </span>
                </p>

                <p class="mt-4 text-sm text-zinc-600">
                    Haben Sie diesen Gegenstand gefunden? Dann geben Sie uns bitte kurz Bescheid – wir kümmern uns um die Rückgabe.
                </p>
            </section>

            @if (session('reported'))
                <section class="rounded-2xl bg-emerald-50 p-5 text-emerald-900 ring-1 ring-emerald-200" role="status">
                    <h2 class="font-semibold">Vielen Dank!</h2>
                    <p class="mt-1 text-sm">Ihre Fundmeldung ist eingegangen. Die Verantwortlichen wurden benachrichtigt.</p>
                </section>
            @else
                <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200">
                    <h2 class="text-lg font-semibold">Gefunden melden</h2>

                    <form method="POST" action="{{ route('public.items.found', $item->public_id) }}" class="mt-4 flex flex-col gap-4">
                        @csrf

                        <div class="flex flex-col gap-1.5">
                            <label for="found_location" class="text-sm font-medium">Wo haben Sie ihn gefunden?</label>
                            <input id="found_location" name="found_location" type="text" required maxlength="255"
                                value="{{ old('found_location') }}" autocomplete="off"
                                class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-base focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/20 focus:outline-none" />
                            @error('found_location')
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label for="message" class="text-sm font-medium">Nachricht</label>
                            <textarea id="message" name="message" rows="3" required maxlength="2000"
                                class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-base focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/20 focus:outline-none"
                                placeholder="z. B. Ich habe ihn beim Hausmeister abgegeben.">{{ old('message') }}</textarea>
                            @error('message')
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label for="contact" class="text-sm font-medium">Kontakt <span class="font-normal text-zinc-500">(optional)</span></label>
                            <input id="contact" name="contact" type="text" maxlength="255"
                                value="{{ old('contact') }}" autocomplete="email" inputmode="email"
                                class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-base focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/20 focus:outline-none"
                                placeholder="E-Mail oder Telefon für Rückfragen" />
                            @error('contact')
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <p class="text-xs text-zinc-500" data-test="privacy-notice">
                            Ihre Angaben nutzen wir ausschließlich, um den Gegenstand zurückzubekommen. Kontaktdaten werden nach {{ $retentionDays }} Tagen automatisch gelöscht.
                        </p>

                        <button type="submit"
                            class="w-full rounded-lg bg-zinc-900 px-4 py-3 text-base font-semibold text-white active:bg-zinc-700">
                            Fund melden
                        </button>
                    </form>
                </section>
            @endif

            <p class="text-center text-xs text-zinc-400">Kennung {{ \App\Support\PublicId::format($item->public_id) }}</p>
        </main>
    </body>
</html>
