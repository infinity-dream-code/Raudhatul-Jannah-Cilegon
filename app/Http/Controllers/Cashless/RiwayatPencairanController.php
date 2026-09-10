<?php

namespace App\Http\Controllers\Cashless;

use App\Http\Controllers\Controller;
use App\Support\FilterHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RiwayatPencairanController extends Controller
{
    private string $title = 'Data Riwayat Pencairan';
    private string $mainTitle = 'Data Riwayat Pencairan';

    private array $allowedFilters = [
        'dari_tanggal' => 'dari_tgl_tran',
        'sampai_tanggal' => 'akhir_tgl_tran',
        'tanggal' => 'TglTerima',
    ];

    private array $orderableColumns = [
        'Nominal' => 'Nominal',
        'TglTerima' => 'TglTerima',
        'dari_tgl_tran' => 'dari_tgl_tran',
        'akhir_tgl_tran' => 'akhir_tgl_tran',
    ];

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
        return response()->json([
            ['data' => null, 'name' => 'no', 'columnType' => 'row', 'exportable' => true],
            ['data' => 'Nominal', 'name' => 'Nominal', 'searchable' => true, 'orderable' => true, 'exportable' => true, 'columnType' => 'currency'],
            ['data' => 'TglTerima', 'name' => 'Tgl Pencairan', 'searchable' => true, 'orderable' => true, 'exportable' => true, 'columnType' => 'timestamp'],
            ['data' => 'dari_tgl_tran', 'name' => 'Dari', 'searchable' => true, 'orderable' => true, 'exportable' => true, 'columnType' => 'date'],
            ['data' => 'akhir_tgl_tran', 'name' => 'Sampai', 'searchable' => true, 'orderable' => true, 'exportable' => true, 'columnType' => 'date'],
        ]);
    }

    public function getData(Request $request)
    {
        $draw = (int) $request->get('draw', 1);

        try {
            if (!$this->tableExists()) {
                Log::warning('RiwayatPencairan: table sm_mercan_cair tidak ada');

                return $this->emptyResponse($draw);
            }

            $start = max(0, (int) $request->get('start', 0));
            $length = (int) $request->get('length', 10);
            if ($length <= 0) {
                $length = 10;
            }

            $columns = $request->get('columns', []);
            $order = $request->get('order', []);
            $searchValue = trim((string) ($request->get('search', [])['value'] ?? ''));

            $orderColumn = 'TglTerima';
            $orderDir = 'desc';
            if (!empty($order)) {
                $idx = $order[0]['column'] ?? null;
                $requested = $columns[$idx]['data'] ?? null;
                if ($requested && isset($this->orderableColumns[$requested])) {
                    $orderColumn = $this->orderableColumns[$requested];
                    $orderDir = strtolower((string) ($order[0]['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
                }
            }

            $available = $this->availableColumns();
            $select = array_values(array_filter(
                ['Nominal', 'TglTerima', 'dari_tgl_tran', 'akhir_tgl_tran', 'NamaPenerima', 'KDMERCAN'],
                fn ($col) => in_array($col, $available, true)
            ));

            if (empty($select)) {
                return $this->emptyResponse($draw);
            }

            if (!in_array($orderColumn, $available, true)) {
                $orderColumn = in_array('TglTerima', $available, true) ? 'TglTerima' : $select[0];
            }

            $query = DB::connection('DATA_MYSQL')->table('sm_mercan_cair');
            $this->applyMerchantScope($query, $available);

            $filterInput = $request->input('filter', []);
            $filter = FilterHandler::resolveFilters(is_array($filterInput) ? $filterInput : [], $this->allowedFilters);
            foreach ($filter as $key => $val) {
                if (!in_array($key, $available, true)) {
                    continue;
                }
                $date = $this->parseFilterDate($val);
                if (!$date) {
                    continue;
                }

                if ($key === 'dari_tgl_tran') {
                    $query->where($key, '>=', $date->format('Y-m-d'));
                } elseif ($key === 'akhir_tgl_tran') {
                    $query->where($key, '<=', $date->format('Y-m-d'));
                } elseif ($key === 'TglTerima') {
                    $query->whereDate($key, '=', $date->format('Y-m-d'));
                }
            }

            if ($searchValue !== '' && in_array('Nominal', $available, true)) {
                $safe = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $searchValue);
                $query->where(function ($q) use ($safe, $available) {
                    $q->where('Nominal', 'like', '%' . $safe . '%');
                    if (in_array('NamaPenerima', $available, true)) {
                        $q->orWhere('NamaPenerima', 'like', '%' . $safe . '%');
                    }
                    if (in_array('KDMERCAN', $available, true)) {
                        $q->orWhere('KDMERCAN', 'like', '%' . $safe . '%');
                    }
                });
            }

            $totalRecords = (clone $query)->count();
            $records = (clone $query)
                ->orderBy($orderColumn, $orderDir)
                ->select($select)
                ->skip($start)
                ->take($length)
                ->get()
                ->map(function ($row) {
                    return [
                        'Nominal' => $row->Nominal ?? null,
                        'TglTerima' => $row->TglTerima ?? null,
                        'dari_tgl_tran' => $row->dari_tgl_tran ?? null,
                        'akhir_tgl_tran' => $row->akhir_tgl_tran ?? null,
                    ];
                })
                ->values()
                ->all();

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalRecords,
                'data' => $records,
            ]);
        } catch (\Throwable $e) {
            Log::error('RiwayatPencairan getData error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Jangan kirim key "error" agar DataTables tidak popup warning;
            // tetap kembalikan struktur valid (tabel kosong).
            return $this->emptyResponse($draw);
        }
    }

    private function emptyResponse(int $draw)
    {
        return response()->json([
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
        ]);
    }

    private function tableExists(): bool
    {
        try {
            return Schema::connection('DATA_MYSQL')->hasTable('sm_mercan_cair');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function availableColumns(): array
    {
        try {
            return Schema::connection('DATA_MYSQL')->getColumnListing('sm_mercan_cair');
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function applyMerchantScope($query, array $available): void
    {
        $kdMercan = trim((string) session('cashless_user.kode_merchan', ''));
        $kantin = trim((string) session('cashless_user.kantin', ''));
        $username = trim((string) session('cashless_user.username', ''));

        $query->where(function ($q) use ($available, $kdMercan, $kantin, $username) {
            $applied = false;

            if ($kdMercan !== '' && in_array('KDMERCAN', $available, true)) {
                $q->orWhereRaw('TRIM(KDMERCAN) = ?', [$kdMercan]);
                $applied = true;
            }

            if ($kantin !== '' && in_array('NamaPenerima', $available, true)) {
                $q->orWhere('NamaPenerima', 'like', '%' . $kantin . '%');
                $applied = true;
            }

            if ($username !== '' && in_array('NamaPenerima', $available, true)) {
                $q->orWhere('NamaPenerima', 'like', '%' . $username . '%');
                $applied = true;
            }

            if (!$applied) {
                // Tidak ada identitas merchant di session → jangan tampilkan semua
                $q->whereRaw('1 = 0');
            }
        });
    }

    private function parseFilterDate(mixed $val): ?Carbon
    {
        $val = trim((string) $val);
        if ($val === '') {
            return null;
        }

        foreach (['d-m-Y', 'Y-m-d', 'd/m/Y'] as $format) {
            try {
                $dt = Carbon::createFromFormat($format, $val);

                return $dt ? $dt->startOfDay() : null;
            } catch (\Throwable $e) {
                // next
            }
        }

        try {
            return Carbon::parse($val)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
