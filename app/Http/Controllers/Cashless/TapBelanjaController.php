<?php

namespace App\Http\Controllers\Cashless;

use App\Http\Controllers\Controller;
use App\Models\ValidationMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TapBelanjaController extends Controller
{
    public string $title;

    public function __construct()
    {
        $this->title = 'TAP KARTU';
    }

    public function index()
    {
        return view('cashless.admin.tap_belanja.index', [
            'title' => $this->title,
        ]);
    }

    public function getSaldo(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'tap_id' => ['required', 'string'],
            ],
            ValidationMessage::messages(),
            ValidationMessage::attributes(),
        );

        if ($validator->fails()) {
            $message = $validator->errors()->first();
            if ($validator->errors()->count() > 1) {
                $message = "{$message} Dan beberapa error lainnya";
            }

            return response()->json([
                'message' => $message,
                'errors' => $validator->errors(),
            ], 422);
        }

        $tapId = trim((string) $request->input('tap_id'));

        try {
            $siswa = DB::connection('DATA_MYSQL')
                ->table('scctcust')
                ->join('sm_pin', 'sm_pin.CUSTID', '=', 'scctcust.CUSTID')
                ->select(['scctcust.CUSTID', 'scctcust.nmcust', 'scctcust.nocust'])
                ->where('sm_pin.PID', $tapId)
                ->first();

            if (!$siswa) {
                return response()->json([
                    'message' => 'Data tidak ditemukan, silahkan tap kartu yang valid',
                    'errors' => ['tap_id' => ['Data tidak ditemukan, silahkan tap kartu yang valid']],
                ], 422);
            }

            $nama = (string) ($siswa->nmcust ?? '');
            $saldo = $this->resolveSaldo($tapId, (string) $siswa->CUSTID, $nama);

            // Format yang diharapkan frontend: [tap_id, saldo, nama]
            return response()->json([
                'data' => [$tapId, (string) $saldo, $nama],
            ]);
        } catch (\Exception $e) {
            Log::error('getSaldo - Error:', [
                'message' => $e->getMessage(),
                'tap_id' => $tapId,
            ]);

            return response()->json([
                'message' => 'gagal mendapatkan data saldo, silahkan coba lagi',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Ambil saldo dari SP bila format valid; fallback ke sccttran_cashless.
     */
    private function resolveSaldo(string $tapId, string $custId, string &$nama): int
    {
        try {
            $rows = DB::connection('DATA_MYSQL')
                ->select('SELECT GetSaldoCard_1VACashless(?) AS saldo', [$tapId]);
            $raw = (string) ($rows[0]->saldo ?? '');
            $parts = array_values(array_filter(array_map('trim', explode('|', $raw)), static fn ($v) => $v !== ''));

            // Format umum: status|saldo|nama ATAU id|saldo|nama
            if (count($parts) >= 3) {
                $maybeSaldo = $parts[1];
                if (is_numeric($maybeSaldo)) {
                    if (!empty($parts[2])) {
                        $nama = $parts[2];
                    }

                    return (int) $maybeSaldo;
                }
            }

            // Kadang SP hanya mengembalikan angka saldo
            if (count($parts) === 1 && is_numeric($parts[0])) {
                return (int) $parts[0];
            }
        } catch (\Throwable $e) {
            Log::warning('getSaldo - SP failed, fallback to sccttran_cashless', [
                'tap_id' => $tapId,
                'error' => $e->getMessage(),
            ]);
        }

        $agg = DB::connection('DATA_MYSQL')
            ->table('sccttran_cashless')
            ->where('CUSTID', $custId)
            ->selectRaw('COALESCE(SUM(KREDIT),0) - COALESCE(SUM(DEBET),0) AS saldo')
            ->value('saldo');

        return (int) $agg;
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
        Log::info('payment - Started', [
            'tap_id' => $request->tap_id,
            'belanja_raw' => $request->belanja,
            'session_user' => session('cashless_user.username'),
        ]);

        $validator = Validator::make(
            $request->all(),
            [
                'tap_id' => ['required', 'string'],
                'belanja' => ['required', 'regex:/^[0-9]+(\.[0-9]{3})*$/', 'not_in:0'],
            ],
            ValidationMessage::messages(),
            ValidationMessage::attributes(),
        );

        if ($validator->fails()) {
            $message = $validator->errors()->first();
            if ($validator->errors()->count() > 1) {
                $message = "{$message} Dan beberapa error lainnya";
            }

            return response()->json([
                'message' => $message,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $nominal = str_replace('.', '', $request->belanja);

            $result = DB::connection('DATA_MYSQL')
                ->select(
                    'SELECT WebPaymentBUY(?,?,?) AS result',
                    [
                        $request->tap_id,
                        $nominal,
                        session('cashless_user.username'),
                    ]
                );

            $result = $result[0]->result ?? 'error';
            $statusKey = null;
            $data = [];

            if (str_contains($result, '|')) {
                $parts = explode('|', $result);
                $statusKey = strtolower($parts[0]);

                if ($statusKey === 'ok') {
                    $data = [
                        'nama' => $parts[1] ?? null,
                        'sisa_saldo' => $parts[2] ?? null,
                    ];
                }
            } else {
                $statusKey = strtolower($result);
            }

            $config = self::STATUS_MAP[$statusKey] ?? [
                'code' => 9999,
                'message' => 'Unknown error',
            ];

            return response()->json([
                'status' => $statusKey,
                'code' => $config['code'],
                'message' => $config['message'],
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            Log::error('payment - Exception occurred', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'gagal mendapatkan data saldo, silahkan coba lagi',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
