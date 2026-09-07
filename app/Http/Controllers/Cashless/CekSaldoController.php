<?php

namespace App\Http\Controllers\Cashless;

use App\Http\Controllers\Controller;
use App\Models\ValidationMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CekSaldoController extends Controller
{
    private string $title = "Cek Saldo";
    private string $mainTitle = 'Cek Saldo';
    private string $cacheKey = 'Cek Saldo';

    public function __construct()
    {
        $key = Str::slug($this->cacheKey) . '_cache_version';
        Cache::add($key, 1);
    }

    public function index()
    {
        $data['title'] = $this->title;
        return view('cashless.admin.cek_saldo.index', $data);
    }

    public function getData(Request $request)
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
                $message = "{$message} Dan beberapa masalah validasi lainnya, silahkan periksa form anda!";
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
            \Log::info('CekSaldo - Request tap_id:', ['tap_id' => $request->tap_id]);

            $saldoResult = DB::connection('DATA_MYSQL')
                ->select('SELECT GetSaldoCard_1VACashless(?) AS saldo', [$request->tap_id]);

            $saldoRaw = $saldoResult[0]->saldo ?? '';
            $saldoParts = explode('|', $saldoRaw);

            if (count($saldoParts) !== 3) {
                return response()->json([
                    "message" => "Kartu diblokir atau tidak ditemukan",
                    "errors" => ["tap_id" => ["Kartu diblokir atau tidak ditemukan"]],
                ], 422);
            }

            $saldo = $saldoParts[1] ?? 0;
            $namaFromSaldo = $saldoParts[2] ?? '';

            // Limit jajan (sama seperti Cek Limit)
            $limitJajan = 20000;

            $siswa = DB::connection('DATA_MYSQL')
                ->table('scctcust')
                ->leftJoin('sm_pin', 'sm_pin.CUSTID', '=', 'scctcust.CUSTID')
                ->select(['scctcust.CUSTID', 'scctcust.nmcust', 'scctcust.nocust'])
                ->where('sm_pin.PID', $request->tap_id)
                ->first();

            if (!$siswa) {
                return response()->json([
                    "message" => "Kartu diblokir atau tidak ditemukan",
                    "errors" => ["tap_id" => ["Kartu diblokir atau tidak ditemukan"]],
                ], 422);
            }

            $nama = $siswa->nmcust ?: $namaFromSaldo;
            $nis = $siswa->nocust ?? '';

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

            \Log::info('CekSaldo - Result:', [
                'nama' => $nama,
                'nis' => $nis,
                'saldo' => $saldo,
                'limit_jajan' => $limitJajan,
                'transaksi_count' => $transaksi->count(),
            ]);

            return response()->json([
                'saldo' => $saldo,
                'limit_jajan' => $limitJajan,
                'nama' => $nama,
                'nis' => $nis,
                'transaksi' => $transaksi,
            ], 200);
        } catch (\Exception $e) {
            \Log::error('CekSaldo - Error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                "message" => "gagal mendapatkan data saldo, silahkan coba lagi",
                "error" => $e->getMessage(),
            ], 422);
        }
    }
}

