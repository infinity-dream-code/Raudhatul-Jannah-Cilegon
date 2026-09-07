<?php

namespace App\Http\Controllers\Cashless;

use App\Http\Controllers\Controller;
use App\Support\CacheHandler;
use App\Support\FilterHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class DataTransaksiBelanjaController extends Controller
{
    private string $title = "Data Transaksi Belanja";
    private string $mainTitle = 'Data Transaksi Belanja';
    private string $cacheKey = 'Data Transaksi Belanja';
    private array $allowedFilters = [
        'dari_tanggal' => 'scctcashout.PAIDDT_start',
        'sampai_tanggal' => 'scctcashout.PAIDDT_end',
        'kelas' => 'scctcust.DESC02',
        'nis' => 'scctcust.NOCUST',
        'nama' => 'scctcust.NMCUST',
        'custid' => 'scctcust.CUSTID',
        'angkatan' => 'scctcust.DESC04',
    ];

    public function __construct()
    {
        $key = Str::slug($this->cacheKey) . '_cache_version';
        Cache::add($key, 1);
        Log::info('DataTransaksiBelanjaController - Constructed', ['cache_key' => $key]);
    }

    private function columnsUrl(): string
    {
        return route('cashless.admin.data-transaksi-belanja.get-column');
    }

    private function datasUrl(): string
    {
        return route('cashless.admin.data-transaksi-belanja.get-data');
    }

    public function index()
    {
        Log::info('DataTransaksiBelanja - Index accessed', ['user' => session('cashless_user.username')]);

        $data['title'] = $this->title;
        $data['columnsUrl'] = $this->columnsUrl();
        $data['datasUrl'] = $this->datasUrl();
        $data['exportUrl'] = route('cashless.admin.data-transaksi-belanja.export');
        $data['totalUrl'] = route('cashless.admin.data-transaksi-belanja.get-total');

        try {
            $data['thn_aka'] = DB::connection('DATA_MYSQL')->table('mst_thn_aka')->select(['thn_aka'])->where('thn_aka', '!=', null)->get();
            Log::info('DataTransaksiBelanja - thn_aka fetched', ['count' => $data['thn_aka']->count()]);
        } catch (\Exception $e) {
            Log::error('DataTransaksiBelanja - Error fetching thn_aka', ['error' => $e->getMessage()]);
            $data['thn_aka'] = collect([]);
        }

        try {
            $data['kelas'] = DB::connection('DATA_MYSQL')->table('mst_kelas')->orderByRaw("CASE WHEN kelas REGEXP '^[0-9]+$' THEN 0 ELSE 1 END, kelas")->get();
            Log::info('DataTransaksiBelanja - kelas fetched', ['count' => $data['kelas']->count()]);
        } catch (\Exception $e) {
            Log::error('DataTransaksiBelanja - Error fetching kelas', ['error' => $e->getMessage()]);
            $data['kelas'] = collect([]);
        }

        return view('cashless.admin.DataTransaksiBelanja.index', $data);
    }

    public function getColumn()
    {
        Log::info('DataTransaksiBelanja - getColumn called');

        return [
            ['data' => null, 'name' => 'No', 'columnType' => 'row', 'exportable' => true],
            ['data' => 'NMCUST', 'name' => 'NAMA', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'TanggalKeluar', 'name' => 'TANGGAL', 'searchable' => true, 'orderable' => true, 'exportable' => true, "columnType" => "timestamp"],
            ['data' => 'BILLAM', 'name' => 'DEBET', 'searchable' => true, 'orderable' => true, 'exportable' => true, "columnType" => "currency"],
            ['data' => 'Teller', 'name' => 'MERCHANT', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'CODE02', 'name' => 'UNIT', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'DESC02', 'name' => 'KELAS', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'DESC03', 'name' => 'KELOMPOK', 'searchable' => true, 'orderable' => true, 'exportable' => true],
        ];
    }

    private function buildFilterQuery(array $filter): ?callable
    {
        $filters = [];

        foreach ($filter as $key => $val) {
            switch ($key) {
                case 'scctcashout.PAIDDT_start':
                    $date = Carbon::createFromFormat('d-m-Y', $val)->startOfDay();
                    if ($date) $filters[] = ['scctcashout.TanggalKeluar', '>=', $date];
                    break;
                case 'scctcashout.PAIDDT_end':
                    $date = Carbon::createFromFormat('d-m-Y', $val)->endOfDay();
                    if ($date) $filters[] = ['scctcashout.TanggalKeluar', '<=', $date];
                    break;
                case 'scctcust.DESC02':
                    $val = explode("~~", $val);
                    if (count($val) == 3) {
                        if (!collect($filters)->contains(fn($f) => $f[0] === 'scctcust.CODE02')) {
                            $filters[] = ["scctcust.CODE02", "=", $val[0]];
                        }
                        $filters[] = ['scctcust.DESC02', '=', $val[1]];
                        $filters[] = ['scctcust.DESC03', '=', $val[2]];
                    }
                    break;
                case 'scctcust.CODE02':
                    if (!collect($filters)->contains(fn($f) => $f[0] === 'scctcust.CODE02')) {
                        $filters[] = ["scctcust.CODE02", "=", $val];
                    }
                    break;
                case 'scctcust.NMCUST':
                    $val = is_numeric($val) ? $val : '%' . $val . '%';
                    $colName = is_numeric($val) ? 'scctcust.NOCUST' : $key;
                    if ($colName) $filters[] = [$colName, 'like', $val];
                    break;
                default:
                    if ($key) $filters[] = [$key, '=', $val];
                    break;
            }
        }

        if (empty($filters)) return null;

        return function ($query) use ($filters) {
            foreach ($filters as $filter) {
                if (count($filter) === 3) {
                    $query->where($filter[0], $filter[1], $filter[2]);
                } elseif (count($filter) === 4) {
                    if ($filter[3] == 'whereBetween') {
                        $query->whereBetween($filter[0], [$filter[1], $filter[2]]);
                    } else {
                        $query->{$filter[3]}($filter[0], $filter[1], $filter[2]);
                    }
                }
            }
        };
    }

    private function baseQuery(?callable $filterQuery = null)
    {
        return DB::connection('DATA_MYSQL')->table('scctcashout')
            ->leftJoin('scctcust', 'scctcashout.CUSTID', '=', 'scctcust.CUSTID')
            ->where('scctcashout.teller', '=', session('cashless_user.username'))
            ->where(function ($query) use ($filterQuery) {
                if ($filterQuery) $filterQuery($query);
            });
    }

    public function getTotal(Request $request)
    {
        Log::info('DataTransaksiBelanja - getTotal called');

        $filter = FilterHandler::resolveFilters($request->input('filter'), $this->allowedFilters);
        $filterQuery = $filter ? $this->buildFilterQuery($filter) : null;

        $query = $this->baseQuery($filterQuery);

        $total = (clone $query)->sum('scctcashout.BILLAM') ?? 0;
        $count = (clone $query)->count();

        Log::info('DataTransaksiBelanja - Total calculated', ['total' => $total, 'count' => $count]);

        return response()->json([
            'total' => $total,
            'count' => $count,
            'total_formatted' => 'Rp ' . number_format($total, 0, ',', '.')
        ]);
    }

    public function export(Request $request)
    {
        Log::info('DataTransaksiBelanja - Export called', ['user' => session('cashless_user.username')]);

        $filter = FilterHandler::resolveFilters($request->input('filter'), $this->allowedFilters);
        $filterQuery = $filter ? $this->buildFilterQuery($filter) : null;

        $select = [
            'scctcust.NOCUST as NIS',
            'scctcust.NMCUST as NAMA',
            'scctcashout.TanggalKeluar as TANGGAL',
            'scctcashout.BILLAM as DEBET',
            'scctcashout.Teller as MERCHANT',
            'scctcust.CODE02 as UNIT',
            'scctcust.DESC02 as KELAS',
            'scctcust.DESC03 as KELOMPOK',
        ];

        $data = $this->baseQuery($filterQuery)
            ->orderBy('scctcashout.TanggalKeluar', 'desc')
            ->select($select)
            ->get();

        $totalAmount = $data->sum('DEBET');

        $filename = 'Data_Transaksi_Belanja_' . date('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($data, $totalAmount) {
            $file = fopen('php://output', 'w');

            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, ['No', 'NIS', 'NAMA', 'TANGGAL', 'DEBET', 'MERCHANT', 'UNIT', 'KELAS', 'KELOMPOK'], ';');

            $no = 1;
            foreach ($data as $row) {
                $tanggal = $row->TANGGAL
                    ? Carbon::parse($row->TANGGAL)->format('d-m-Y H:i')
                    : '';

                fputcsv($file, [
                    $no++,
                    $row->NIS,
                    $row->NAMA,
                    $tanggal,
                    $row->DEBET,
                    $row->MERCHANT,
                    $row->UNIT,
                    $row->KELAS,
                    $row->KELOMPOK,
                ], ';');
            }

            fputcsv($file, [], ';');

            fputcsv($file, [
                '',
                '',
                'TOTAL PEMBAYARAN',
                '',
                $totalAmount,
                '',
                '',
                '',
                '',
            ], ';');

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function getData(Request $request)
    {
        Log::info('DataTransaksiBelanja - getData called', [
            'draw' => $request->get('draw'),
            'start' => $request->get("start"),
            'length' => $request->get("length"),
            'user' => session('cashless_user.username')
        ]);

        $draw = $request->get('draw');
        $start = $request->get("start");
        $rowperpage = $request->get("length");
        $columnIndex_arr = $request->get('order', []);
        $columnName_arr = $request->get('columns', []);
        $order_arr = $request->get('order', []);
        $search_arr = $request->get('search', []);
        $searchValue = $search_arr['value'] ?? '';

        $columnName = "scctcust.NMCUST";
        $columnSortOrder = "desc";

        if (!empty($order_arr)) {
            $columnIndex = $columnIndex_arr[0]["column"] ?? null;
            if (
                $columnIndex !== null &&
                !empty($columnName_arr[$columnIndex]["data"]) &&
                $columnName_arr[$columnIndex]["data"] !== "no" &&
                $columnName_arr[$columnIndex]["data"] !== "AA"
            ) {
                $columnName = $columnName_arr[$columnIndex]["data"];
                $columnSortOrder = $order_arr[0]["dir"] ?? "desc";
                Log::info('DataTransaksiBelanja - Order by', ['column' => $columnName, 'sort' => $columnSortOrder]);
            }
        }

        $filter = FilterHandler::resolveFilters($request->input('filter'), $this->allowedFilters);
        Log::info('DataTransaksiBelanja - Filters applied', ['filters' => $filter]);

        $filterQuery = $filter ? $this->buildFilterQuery($filter) : null;

        $whereAny = [
            'scctcust.NMCUST',
            'scctcust.NOCUST',
        ];

        $select = array_unique([...$whereAny,
            'scctcust.CODE02',
            'scctcust.DESC03',
            'scctcust.DESC02',
            'scctcashout.BILLAM',
            'scctcashout.TanggalKeluar',
            'scctcashout.Teller',
        ]);

        $query = $this->baseQuery($filterQuery)
            ->when(!blank($searchValue), function ($query) use ($whereAny, $searchValue) {
                $query->where(function ($q) use ($whereAny, $searchValue) {
                    $sanitizeSearch = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $searchValue);
                    foreach ($whereAny as $column) {
                        $q->orWhere($column, 'like', '%' . $sanitizeSearch . '%');
                    }
                });
            });

        Log::info('DataTransaksiBelanja - Query built', ['sql' => $query->toSql()]);

        $totalRecords = $this->total();
        Log::info('DataTransaksiBelanja - Total records', ['total' => $totalRecords]);

        $totalRecordswithFilter = Cache::remember(
            CacheHandler::cacheKey($this->cacheKey, 'total_filtered_data', $filter, $searchValue ?? ''),
            now()->addMinutes(10),
            function () use ($query) {
                $count = (clone $query)->count();
                Log::info('DataTransaksiBelanja - Total filtered records (cache miss)', ['count' => $count]);
                return $count;
            }
        );

        Log::info('DataTransaksiBelanja - Total filtered records', ['total_filtered' => $totalRecordswithFilter]);

        $records = (clone $query)
            ->orderBy($columnName, $columnSortOrder)
            ->select($select)
            ->skip($start)
            ->take($rowperpage)
            ->get()
            ->toArray();

        Log::info('DataTransaksiBelanja - Records fetched', [
            'fetched_count' => count($records),
            'start' => $start,
            'rowperpage' => $rowperpage
        ]);

        return response()->json([
            "draw" => intval($draw),
            "recordsTotal" => $totalRecords ?? 0,
            "recordsFiltered" => $totalRecordswithFilter ?? 0,
            "data" => $records ?? [],
        ]);
    }

    public function total(): int
    {
        $key = Str::slug($this->cacheKey);

        $total = Cache::remember(
            "{$key}:total_all_data",
            now()->addMinutes(10),
            function () {
                $count = DB::connection('DATA_MYSQL')->table('scctcashout')
                    ->where('teller', '=', session('cashless_user.username'))
                    ->count();
                Log::info('DataTransaksiBelanja - Total cache miss, counting', ['count' => $count, 'user' => session('cashless_user.username')]);
                return $count;
            }
        );

        Log::info('DataTransaksiBelanja - Total records from cache', ['total' => $total]);

        return $total;
    }
}
