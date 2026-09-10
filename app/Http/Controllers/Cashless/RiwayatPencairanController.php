<?php

namespace App\Http\Controllers\Cashless;

use App\Http\Controllers\Controller;
use App\Support\CacheHandler;
use App\Support\FilterHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RiwayatPencairanController extends Controller
{
    private string $title = 'Data Riwayat Pencairan';
    private string $mainTitle = 'Data Riwayat Pencairan';
    private string $cacheKey = 'Data Riwayat Pencairan';

    private array $allowedFilters = [
        'dari_tanggal' => 'sm_mercan_cair.dari_tgl_tran',
        'sampai_tanggal' => 'sm_mercan_cair.akhir_tgl_tran',
        'tanggal' => 'sm_mercan_cair.TglTerima',
    ];

    private array $orderableColumns = [
        'Nominal' => 'sm_mercan_cair.Nominal',
        'TglTerima' => 'sm_mercan_cair.TglTerima',
        'dari_tgl_tran' => 'sm_mercan_cair.dari_tgl_tran',
        'akhir_tgl_tran' => 'sm_mercan_cair.akhir_tgl_tran',
        'sm_mercan_cair.Nominal' => 'sm_mercan_cair.Nominal',
        'sm_mercan_cair.TglTerima' => 'sm_mercan_cair.TglTerima',
        'sm_mercan_cair.dari_tgl_tran' => 'sm_mercan_cair.dari_tgl_tran',
        'sm_mercan_cair.akhir_tgl_tran' => 'sm_mercan_cair.akhir_tgl_tran',
    ];

    public function __construct()
    {
        Cache::add(Str::slug($this->cacheKey) . '_cache_version', 1);
    }

    public function index()
    {
        return view('cashless.admin.riwayat_pencairan.index', [
            'title' => $this->title,
            'mainTitle' => $this->mainTitle,
            'columnsUrl' => '/cashless/admin/riwayat-pencairan/get-column',
            'datasUrl' => '/cashless/admin/riwayat-pencairan/get-data',
        ]);
    }

    public function getColumn()
    {
        return [
            ['data' => null, 'name' => 'no', 'columnType' => 'row', 'exportable' => true],
            ['data' => 'Nominal', 'name' => 'Nominal', 'searchable' => true, 'orderable' => true, 'exportable' => true, 'columnType' => 'currency'],
            ['data' => 'TglTerima', 'name' => 'Tgl Pencairan', 'searchable' => true, 'orderable' => true, 'exportable' => true, 'columnType' => 'timestamp'],
            ['data' => 'dari_tgl_tran', 'name' => 'Dari', 'searchable' => true, 'orderable' => true, 'exportable' => true, 'columnType' => 'date'],
            ['data' => 'akhir_tgl_tran', 'name' => 'Sampai', 'searchable' => true, 'orderable' => true, 'exportable' => true, 'columnType' => 'date'],
        ];
    }

    public function getData(Request $request)
    {
        try {
            $draw = (int) $request->get('draw', 1);
            $start = max(0, (int) $request->get('start', 0));
            $rowperpage = (int) $request->get('length', 10);
            if ($rowperpage <= 0) {
                $rowperpage = 10;
            }

            $columnNameArr = $request->get('columns', []);
            $orderArr = $request->get('order', []);
            $searchValue = (string) ($request->get('search', [])['value'] ?? '');

            $columnName = 'sm_mercan_cair.TglTerima';
            $columnSortOrder = 'desc';

            if (!empty($orderArr)) {
                $columnIndex = $orderArr[0]['column'] ?? null;
                $requested = $columnNameArr[$columnIndex]['data'] ?? null;
                if ($requested && isset($this->orderableColumns[$requested])) {
                    $columnName = $this->orderableColumns[$requested];
                    $columnSortOrder = strtolower((string) ($orderArr[0]['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
                }
            }

            $kantin = trim((string) session('cashless_user.kantin', ''));
            $filterInput = $request->input('filter', []);
            $filter = FilterHandler::resolveFilters(is_array($filterInput) ? $filterInput : [], $this->allowedFilters);

            $query = DB::connection('DATA_MYSQL')->table('sm_mercan_cair');

            if ($kantin !== '') {
                $query->where('sm_mercan_cair.NamaPenerima', 'like', '%' . $kantin . '%');
            } else {
                // Tanpa nama kantin di session, jangan kembalikan semua data
                $query->whereRaw('1 = 0');
            }

            foreach ($filter as $key => $val) {
                $date = $this->parseFilterDate($val);
                if (!$date) {
                    continue;
                }

                switch ($key) {
                    case 'sm_mercan_cair.dari_tgl_tran':
                        $query->whereDate('sm_mercan_cair.dari_tgl_tran', '>=', $date->toDateString());
                        break;
                    case 'sm_mercan_cair.akhir_tgl_tran':
                        $query->whereDate('sm_mercan_cair.akhir_tgl_tran', '<=', $date->toDateString());
                        break;
                    case 'sm_mercan_cair.TglTerima':
                        $query->whereDate('sm_mercan_cair.TglTerima', '=', $date->toDateString());
                        break;
                }
            }

            if ($searchValue !== '') {
                $sanitizeSearch = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $searchValue);
                $query->where(function ($q) use ($sanitizeSearch) {
                    $q->orWhere('sm_mercan_cair.Nominal', 'like', '%' . $sanitizeSearch . '%')
                        ->orWhere('sm_mercan_cair.NamaPenerima', 'like', '%' . $sanitizeSearch . '%');
                });
            }

            $totalRecords = $this->total($kantin);

            $totalFiltered = (clone $query)->count();

            $records = (clone $query)
                ->orderBy($columnName, $columnSortOrder)
                ->select([
                    'sm_mercan_cair.Nominal',
                    'sm_mercan_cair.TglTerima',
                    'sm_mercan_cair.dari_tgl_tran',
                    'sm_mercan_cair.akhir_tgl_tran',
                ])
                ->skip($start)
                ->take($rowperpage)
                ->get()
                ->toArray();

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalFiltered,
                'data' => $records,
            ]);
        } catch (\Throwable $e) {
            Log::error('RiwayatPencairan getData error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'draw' => (int) $request->get('draw', 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Gagal memuat data riwayat pencairan',
                'message' => 'Gagal memuat data riwayat pencairan',
            ], 200);
        }
    }

    private function parseFilterDate(mixed $val): ?Carbon
    {
        $val = trim((string) $val);
        if ($val === '') {
            return null;
        }

        foreach (['d-m-Y', 'Y-m-d', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $val)->startOfDay();
            } catch (\Throwable $e) {
                // try next
            }
        }

        try {
            return Carbon::parse($val)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function total(string $kantin = ''): int
    {
        if ($kantin === '') {
            $kantin = trim((string) session('cashless_user.kantin', ''));
        }

        $key = Str::slug($this->cacheKey) . ':total:' . md5($kantin);

        return (int) Cache::remember($key, now()->addMinutes(5), function () use ($kantin) {
            $q = DB::connection('DATA_MYSQL')->table('sm_mercan_cair');
            if ($kantin !== '') {
                $q->where('sm_mercan_cair.NamaPenerima', 'like', '%' . $kantin . '%');
            } else {
                return 0;
            }

            return $q->count();
        });
    }
}
