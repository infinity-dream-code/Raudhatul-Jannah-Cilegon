<?php

namespace App\Http\Controllers\Cashless;

use App\Http\Controllers\Controller;
use App\Models\ValidationMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TapBelanjaController extends Controller
{
    public string $title;
    public $datasUrl;
    public $columnsUrl;

    public function __construct()
    {
        $this->title = "TAP KARTU";
    }

    public function index()
    {
        $data["title"] = $this->title;
        return view('cashless.admin.tap_belanja.index', $data);
    }

    public function getSaldo(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                "tap_id" => ["required", "string"],
            ],
            ValidationMessage::messages(),
            ValidationMessage::attributes(),
        );

        if ($validator->fails()) {
            $message = $validator->errors()->first();
            if ($validator->errors()->count() > 1) {
                $message = "{$message} Dan beberapa error lainnya";
            }

            return response()->json(
                [
                    "message" => $message,
                    "errors" => $validator->errors(),
                ],
                422,
            );
        }

        try {
            \Log::info('getSaldo - Request tap_id:', ['tap_id' => $request->tap_id]);
            
            $saldo = DB::connection('DATA_MYSQL')
                ->select('SELECT GetSaldoCard_1VACashless(?) AS saldo', [$request->tap_id]);
            
            \Log::info('getSaldo - Raw result from DB:', ['result' => $saldo[0]->saldo ?? 'NULL']);
            
            $data = explode("|", $saldo[0]->saldo);
            
            \Log::info('getSaldo - Exploded data:', ['data' => $data, 'count' => count($data)]);
            
            return response()->json(["data" => $data]);
        } catch (\Exception $e) {
            \Log::error('getSaldo - Error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                "message" => "gagal mendapatkan data saldo, silahkan coba lagi",
                "error" => $e->getMessage()
            ], 422);
        }
    }

    const STATUS_MAP = [
        'ok' => [
            'code' => 1000,
            'message' => 'Transaksi berhasil',
        ],
        'insufficient_balance' => [
            'code' => 2001,
            'message' => 'Saldo tidak cukup',
        ],
        'unknown_or_blocked_card' => [
            'code' => 2002,
            'message' => 'Kartu Terblokir',
        ],
        'daily_transaction_limit_exceeded' => [
            'code' => 2003,
            'message' => 'Limit transaksi sudah tercapai!',
        ],
    ];

    public function payment(Request $request)
    {
        \Log::info('payment - Started', [
            'tap_id' => $request->tap_id,
            'belanja_raw' => $request->belanja,
            'session_user' => session('cashless_user.username')
        ]);

        $validator = Validator::make(
            $request->all(),
            [
                "tap_id" => ["required", "string"],
                "belanja" => ["required", 'regex:/^[0-9]+(\.[0-9]{3})*$/', 'not_in:0'],
            ],
            ValidationMessage::messages(),
            ValidationMessage::attributes(),
        );

        if ($validator->fails()) {
            $message = $validator->errors()->first();
            if ($validator->errors()->count() > 1) {
                $message = "{$message} Dan beberapa error lainnya";
            }

            \Log::warning('payment - Validation failed', [
                'errors' => $validator->errors()->toArray()
            ]);

            return response()->json(
                [
                    "message" => $message,
                    "errors" => $validator->errors(),
                ],
                422,
            );
        }

        try {
            $nominal = str_replace('.', '', $request->belanja);
            \Log::info('payment - Process payment', [
                'tap_id' => $request->tap_id,
                'nominal' => $nominal,
                'teller' => session('cashless_user.username')
            ]);

            $result = DB::connection('DATA_MYSQL')
                ->select(
                    'SELECT WebPaymentBUY(?,?,?) AS result',
                    [
                        $request->tap_id,
                        $nominal,
                        session('cashless_user.username'),
                    ]);

            $result = $result[0]->result ?? "error";
            \Log::info('payment - Raw result from WebPaymentBUY', ['result' => $result]);

            $statusKey = null;
            $data = [];

            if (str_contains($result, '|')) {
                $parts = explode('|', $result);
                \Log::info('payment - Exploded parts', ['parts' => $parts]);

                $statusKey = strtolower($parts[0]);

                if ($statusKey === 'ok') {
                    $data = [
                        'nama' => $parts[1] ?? null,
                        'sisa_saldo' => $parts[2] ?? null,
                    ];
                    \Log::info('payment - Success transaction', [
                        'nama' => $data['nama'],
                        'sisa_saldo' => $data['sisa_saldo']
                    ]);
                }
            } else {
                $statusKey = strtolower($result);
                \Log::info('payment - Status key from result', ['statusKey' => $statusKey]);
            }

            $config = self::STATUS_MAP[$statusKey] ?? [
                'code' => 9999,
                'message' => 'Unknown error',
            ];

            \Log::info('payment - Final response', [
                'status' => $statusKey,
                'code' => $config['code'],
                'message' => $config['message']
            ]);

            return response()->json([
                'status' => $statusKey,
                'code'   => $config['code'],
                'message'=> $config['message'],
                'data'   => $data,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('payment - Exception occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                "message" => "gagal mendapatkan data saldo, silahkan coba lagi",
                "error" => $e->getMessage()
            ], 422);
        }
    }
}
