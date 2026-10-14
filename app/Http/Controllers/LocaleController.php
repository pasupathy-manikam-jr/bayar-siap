<?php

namespace App\Http\Controllers;

use App\Models\ClientSession;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Language switcher: remembered for this browser, and on the contractor's or client's record for texts and emails. */
class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', Rule::in(Translations::locales())]]);
        $request->session()->put('locale', $data['locale']);
        $request->user()?->update(['locale' => $data['locale']]);

        return back();
    }

    /** On a client link: also save it on the client, once they've verified with their code. */
    public function updateForClient(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', Rule::in(Translations::locales())]]);
        $request->session()->put('locale', $data['locale']);

        $session = $request->attributes->get('clientSession');
        if ($session instanceof ClientSession) {
            $session->client->update(['locale' => $data['locale']]);
        }

        return back();
    }
}
