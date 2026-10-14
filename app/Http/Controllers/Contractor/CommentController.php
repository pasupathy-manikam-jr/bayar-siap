<?php

namespace App\Http\Controllers\Contractor;

use App\Http\Controllers\Controller;
use App\Models\Milestone;
use App\Models\VariationOrder;
use App\Services\JobFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CommentController extends Controller
{
    public function __invoke(Request $request, JobFlow $flow): RedirectResponse
    {
        $data = $request->validate([
            'commentable_type' => ['required', 'in:milestone,variation_order'],
            'commentable_id' => ['required', 'integer'],
            'body' => ['required', 'string', 'max:5000'],
        ], [], ['body' => __('comment')]);

        // Tenant scope makes another company's item a 404.
        $item = $data['commentable_type'] === 'milestone'
            ? Milestone::findOrFail((int) $data['commentable_id'])
            : VariationOrder::findOrFail((int) $data['commentable_id']);
        $flow->comment($item, $request->user(), $data['body']);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comment posted.')]);

        return back();
    }
}
