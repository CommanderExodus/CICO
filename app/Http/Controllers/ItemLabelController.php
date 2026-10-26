<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Support\QrCode;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Druckbares Etikett mit QR-Code (R11). Der QR-Code führt auf die öffentliche Statusseite.
 */
class ItemLabelController extends Controller
{
    public function __invoke(Item $item): View
    {
        Gate::authorize('update', $item);

        return view('print.label', [
            'item' => $item,
            'organisation' => config('cico.organisation'),
            'qr' => QrCode::svg($item->publicUrl(), 320),
        ]);
    }
}
