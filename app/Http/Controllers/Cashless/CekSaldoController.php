<?php

namespace App\Http\Controllers\Cashless;

use App\Http\Controllers\Controller;
use App\Models\ValidationMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CekSaldoController extends Controller
{
    private string $title = 'Cek Saldo';
    private string $cacheKey = 'Cek Saldo';

    public function __construct()
    {
        Cache::add(Str::slug($this->cacheKey) . '_cache_version', 1);
    }

    public function index()
    {
        return view('cashless.admin.cek_saldo.index', [
            'title' => $this->title,
        ]);
    }

    public function getData(Request $request)
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
                $message = "{$message} Dan beberapa masalah validasi lainnya, silahkan periksa form anda!";
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
                    'message' => 'Kartu diblokir atau tidak ditemukan',
                    'errors' => ['tap_id' => ['Kartu diblokir atau tidak ditemukan']],
                ], 422);
            }

            $nama = (string) ($siswa->nmcust ?? '');
            $nis = (string) ($siswa->nocust ?? '');
            $saldo = $this->resolveSaldo($tapId, (string) $siswa->CUSTID, $nama);
            $limitJajan = 20000;

            $transaksi = DB::connection('DATA_MYSQL')
                ->table('scctcashout')
                ->where('CUSTID', $siswa->CUSTID)
                ->orderByDesc('TanggalKeluar')
                ->limit(10)
                ->get(['TanggalKeluar', 'BILLAM', 'Teller'])
                ->map(function ($row) {
                    return [
                        'tanggal' => $row->TanggalKeluar,
                        'nominal' => (float) $row->BILLAM,
                        'merchant' => $row->Teller,
                    ];
                })
                ->values();

            return response()->json([
                'saldo' => $saldo,
                'limit_jajan' => $limitJajan,
                'nama' => $nama,
                'nis' => $nis,
                'transaksi' => $transaksi,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CekSaldo - Error:', [
                'message' => $e->getMessage(),
                'tap_id' => $tapId,
            ]);

            return response()->json([
                'message' => 'gagal mendapatkan data saldo, silahkan coba lagi',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    private function resolveSaldo(string $tapId, string $custId, string &$nama): int
    {
        try {
            $rows = DB::connection('DATA_MYSQL')
                ->select('SELECT GetSaldoCard_1VACashless(?) AS saldo', [$tapId]);
            $raw = (string) ($rows[0]->saldo ?? '');
            $parts = array_values(array_filter(array_map('trim', explode('|', $raw)), static fn ($v) => $v !== ''));

            if (count($parts) >= 3 && is_numeric($parts[1])) {
                if (!empty($parts[2])) {
                    $nama = $parts[2];
                }

                return (int) $parts[1];
            }

            if (count($parts) === 1 && is_numeric($parts[0])) {
                return (int) $parts[0];
            }
        } catch (\Throwable $e) {
            Log::warning('CekSaldo - SP failed, fallback sccttran_cashless', [
                'tap_id' => $tapId,
                'error' => $e->getMessage(),
            ]);
        }

        return (int) DB::connection('DATA_MYSQL')
            ->table('sccttran_cashless')
            ->where('CUSTID', $custId)
            ->selectRaw('COALESCE(SUM(KREDIT),0) - COALESCE(SUM(DEBET),0) AS saldo')
            ->value('saldo');
    }
}
