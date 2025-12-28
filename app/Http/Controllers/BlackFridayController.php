<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BlackFridayController extends Controller
{
    public function show(Request $request)
    {
        // Locale en tijdzone
        Carbon::setLocale('nl');
        $tz = config('blackfriday.timezone', 'Europe/Amsterdam');

        // Config-gestuurde waarden (geen DB)
        $bfFlag = (bool) config('blackfriday.active');           // standaard AAN/UIT (mag false blijven, preview kan forceren)
        $bfStart = (string) config('blackfriday.start');          // 'YYYY-MM-DD'
        $bfEnd = (string) config('blackfriday.until');          // 'YYYY-MM-DD'

        // Preview via querystring (?bf=on/off/true/false/1/0)
        $qKey = config('blackfriday.preview.query_key', 'bf');
        $preview = strtolower((string) $request->get($qKey));
        $truthy = (array) config('blackfriday.preview.truthy', ['1', 'on', 'true']);
        $falsy = (array) config('blackfriday.preview.falsy', ['0', 'off', 'false']);

        $forceOn = in_array($preview, $truthy, true);
        $forceOff = in_array($preview, $falsy, true);

        // Datumvenster (inclusief begin- en einddag)
        $today = Carbon::today($tz);

        // Bescherm tegen lege/ongeldige config
        $inWindow = false;
        if ($bfStart && $bfEnd) {
            try {
                $start = Carbon::parse($bfStart, $tz)->startOfDay();
                $end = Carbon::parse($bfEnd, $tz)->endOfDay();
                $inWindow = $today->between($start, $end);
            } catch (\Throwable $e) {
                // Laat $inWindow = false; bij parser-fout
            }
        }

        // Echte AAN/UIT vlag
        $bfActive = $forceOff ? false : ($forceOn || $bfFlag || $inWindow);

        // ⚠️ BELANGRIJKE BEVEILIGING: Pagina alleen beschikbaar tijdens Black Friday periode
        if (! $bfActive) {
            abort(404, 'Black Friday pagina is alleen beschikbaar tijdens de actieperiode.');
        }

        // Eind-ISO voor countdown (einde van einddag in NL tijd)
        $bfUntil = $bfEnd ?: null;
        $endIso = null;
        if ($bfActive && $bfUntil) {
            try {
                $endIso = Carbon::parse($bfUntil, $tz)->endOfDay()->format('Y-m-d\TH:i:sP');
            } catch (\Throwable $e) {
                $endIso = null;
            }
        }

        // Alleen items met echte korting (prijs < doorgestreepte prijs)
        $producten = Product::query()
            ->whereNotNull('strikethrough_price')
            ->whereColumn('price', '<', 'strikethrough_price')
            ->selectRaw('
                *, 
                (strikethrough_price - price) as absolute_savings,
                ((strikethrough_price - price) / strikethrough_price * 100) as discount_percentage,
                COALESCE(rating_average, 3.0) as safe_rating,
                (
                    -- Combinatie score: korting percentage + rating bonus
                    ((strikethrough_price - price) / strikethrough_price * 100) * 0.7 +
                    (COALESCE(rating_average, 3.0) - 3.0) * 10 * 0.3
                ) as deal_score
            ')
            ->orderByDesc('deal_score') // beste combinatie van korting + kwaliteit
            ->orderByDesc('absolute_savings') // bij gelijke score: grootste euro korting eerst
            ->paginate(24);

        return view('pages.blackfriday', [
            'bfActive' => $bfActive,
            'bfUntil' => $bfUntil,
            'bfEndIso' => $endIso,
            'producten' => $producten,
        ]);
    }
}
