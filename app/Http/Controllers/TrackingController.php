<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Services\TrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/** /go/{slug} — ссылка из объявления в сетях: считаем клик и сразу уводим на вакансию. */
class TrackingController extends Controller
{
    public function __invoke(Request $request, string $slug, TrackingService $tracking)
    {
        $campaign = Campaign::where('slug', $slug)->first();
        if (! $campaign) {
            return redirect('/');
        }

        try {
            $tracking->handle($campaign, $request);
        } catch (Throwable $e) {
            // Редирект важнее учёта: кандидат должен попасть на вакансию в любом случае
            Log::error('Click tracking failed', ['campaign' => $campaign->id, 'error' => $e->getMessage()]);
        }

        return redirect()->away($tracking->redirectUrl($campaign, $request->query('p')), 302);
    }
}
