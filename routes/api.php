<?php

use App\Models\Master;
use App\Models\ReferralEarning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

/*
|--------------------------------------------------------------------------
| API
|--------------------------------------------------------------------------
|
| Текущий мастер приходит в заголовке X-Master-Id и уже разложен
| в атрибуты запроса middleware'ом ResolveCurrentMaster:
|
|     $master = $request->attributes->get('current_master');
|
| Здесь нужно написать три роута — см. README.md.
|
*/

Route::get('/ping', fn () => ['ok' => true]);

Route::get('/me', fn(Request $request) => $request->attributes->get('current_master'));

// TODO: POST /api/referrals/attach
Route::post('/referrals/attach', function(Request $request) {
    $currentMaster = $request->attributes->get('current_master');

    $validated = $request->validate([
        'code' => [
            'required',
            Rule::exists('masters', 'referral_code')
        ],
    ]);

    $ownerMaster = Master::where('referral_code', $validated['code'])->first();

    if ($currentMaster->id === $ownerMaster->id) {
        abort(400, 'Владелец кода и текущий мастер не могут совпадать');
    }

    $data = \App\Models\Referral::firstOrCreate(
        [
            'referred_master_id' => $currentMaster->id,
        ],
        [
            'referrer_master_id' => $ownerMaster->id,
        ]
    );

    return response()->json(['status' => 'success', 'data' => $data]);
});

// TODO: GET  /api/referrals/my
Route::get('/referrals/my', function(Request $request) {
    $currentMaster = $request->attributes->get('current_master');

    $referrals = \App\Models\Referral::where('referrer_master_id', $currentMaster->id)->get();

    $data = [];
    foreach ($referrals as $referral) {
//        $earnings = \App\Models\ReferralEarning::query()
//            ->where('referrer_master_id', $currentMaster->id)
//            ->where('referred_master_id', $referral->referredMaster->id)
//            ->get();

        $item = [
            'id' => $referral->id,
            'referred_master' => [
                'id' => $referral->referredMaster->id,
                'name' => $referral->referredMaster->name,
            ],
            'created_at' => $referral->created_at,
            'program' => $referral->program,
            'status' => $referral->status,
//            'earnings' => $earnings->amount,
        ];

        $data[] = $item;
    }

    return response()->json(['status' => 'success', 'data' => $data]);
});

// TODO: GET  /api/referrals/earnings
Route::get('/referrals/earnings', function(Request $request) {
    $currentMaster = $request->attributes->get('current_master');

    $data = [
        'total_sum' => ReferralEarning::where('referrer_master_id', $currentMaster->id)->sum('amount'),
        'total_pending_sum' => ReferralEarning::where('referrer_master_id', $currentMaster->id)->where('status', ReferralEarning::class::STATUS_PENDING)->sum('amount'),
        'total_payed_sum' => ReferralEarning::where('referrer_master_id', $currentMaster->id)->where('status', ReferralEarning::class::STATUS_PAID)->sum('amount'),
        'total_count' => ReferralEarning::where('referrer_master_id', $currentMaster->id)->count(),
    ];

    return response()->json(['data' => $data]);
});
