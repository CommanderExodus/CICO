<?php

namespace App\Http\Controllers;

use App\Actions\FoundReports\PurgeExpiredFoundReportContacts;
use App\Actions\FoundReports\SubmitFoundReport;
use App\Models\Item;
use App\Support\PublicId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Öffentliche Statusseite hinter dem QR-Code (R12–R14). Kein Login, und die Seite
 * zeigt bewusst nur Bezeichnung, Organisation und Status – nie, wer den Gegenstand
 * gerade hat (R13).
 */
class PublicItemController extends Controller
{
    public function show(string $publicId): View
    {
        $item = $this->find($publicId);

        return view('public.item', [
            'item' => $item,
            'organisation' => config('cico.organisation'),
            'availability' => $item->availability(),
            'retentionDays' => PurgeExpiredFoundReportContacts::retentionDays(),
        ]);
    }

    public function report(Request $request, string $publicId, SubmitFoundReport $submit): RedirectResponse
    {
        $item = $this->find($publicId);

        $submit->handle($item, $request->only(['found_location', 'message', 'contact']));

        return redirect()
            ->route('public.items.show', $item->public_id)
            ->with('reported', true);
    }

    private function find(string $publicId): Item
    {
        $publicId = PublicId::normalize($publicId);

        abort_unless(PublicId::isValid($publicId), 404);

        return Item::query()
            ->with('currentLoan')
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
