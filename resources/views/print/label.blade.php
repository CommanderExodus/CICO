<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Etikett – {{ $item->name }}</title>
        @vite('resources/css/public.css')
        <style>
            @page { size: 62mm 90mm; margin: 4mm; }
        </style>
    </head>
    <body class="bg-zinc-100 text-zinc-900 print:bg-white">
        <div class="mx-auto my-8 flex max-w-sm flex-col items-center gap-4 print:my-0">
            <div class="flex w-[62mm] flex-col items-center gap-2 rounded-lg border border-zinc-300 bg-white p-[4mm] text-center print:border-0 print:p-0" data-test="label">
                <div class="w-full [&>svg]:h-auto [&>svg]:w-full">{!! $qr !!}</div>
                <p class="text-sm leading-tight font-semibold">{{ $item->name }}</p>
                <p class="text-[10px] leading-tight text-zinc-600">{{ $organisation }} · Gefunden? Bitte scannen.</p>
                <p class="font-mono text-[11px] tracking-wider">{{ \App\Support\PublicId::format($item->public_id) }}</p>
            </div>

            <div class="flex gap-2 print:hidden">
                <button type="button" onclick="window.print()" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-semibold text-white">Drucken</button>
                <a href="{{ route('items.show', $item) }}" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold ring-1 ring-zinc-300">Zurück</a>
            </div>
        </div>
    </body>
</html>
