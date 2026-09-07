<?php

namespace App\Http\Controllers\Cashless;

use App\Http\Controllers\Controller;
use App\Support\CacheHandler;
use App\Support\FilterHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RiwayatPencairanController extends Controller
{
    private string $title = "Data Riwayat Pencairan";
    private string $mainTitle = 'Data Riwayat Pencairan';
    private string $cacheKey = 'Data Riwayat Pencairan';
    private array $allowedFilters = [
        'dari_tanggal' => 'sm_mercan_cair.dari_tgl_tran',
        'sampai_tanggal' => 'sm_mercan_cair.akhir_tgl_tran',
        'tanggal' => 'sm_mercan_cair.TglTerima',
    ];

    public function __construct()
    {
        $key = Str::slug($this->cacheKey) . '_cache_version';

        Cache::add($key, 1);
    }

    public function index()
    {
        $data['title'] = $this->title;
        $data['columnsUrl'] = $this->columnsUrl();
        $data['datasUrl'] = $this->dataUrl();

        return view('cashless.admin.riwayat_pencairan.index', $data);
    }

    private function columnsUrl(): string
    {
        return route('cashless.admin.riwayat-pencairan.get-column');
    }

    private function dataUrl(): string
    {
        return route('cashless.admin.riwayat-pencairan.get-data');
    }

    public function getColumn()
    {
        return [
            ['data' => null, 'name' => 'no', 'columnType' => 'row', 'exportable' => true],
            ['data' => 'Nominal', 'name' => 'Nominal', 'searchable' => true, 'orderable' => true, 'exportable' => true],
            ['data' => 'TglTerima', 'name' => 'Tanggal', 'searchable' => true, 'orderable' => true, 'exportable' => true, "columnType" => "timestamp"],
            ['data' => 'dari_tgl_tran', 'name' => 'Tanggal', 'searchable' => true, 'orderable' => true, 'exportable' => true, "columnType" => "date"],
            ['data' => 'akhir_tgl_tran', 'name' => 'Tanggal', 'searchable' => true, 'orderable' => true, 'exportable' => true, "columnType" => "date"]
        ];
    }

    public function getData(Request $request)
    {
        $draw = $request->get('draw');
        $start = $request->get("start");
        $rowperpage = $request->get("length");
        $columnIndex_arr = $request->get('order', []);
        $columnName_arr = $request->get('columns', []);
        $order_arr = $request->get('order', []);
        $search_arr = $request->get('search', []);
        $searchValue = $search_arr['value'] ?? '';

        $columnName = "sm_mercan_cair.akhir_tgl_tran";
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
            }
        }

        $filters = [];
        $filterQuery = null;

        $filter = FilterHandler::resolveFilters($request->input('filter'), $this->allowedFilters);

        if ($filter) {
            foreach ($filter as $key => $val) {
                switch ($key) {
                    case 'sm_mercan_cair.PAIDDT_start':
                        $date = Carbon::createFromFormat('d-m-Y', $val);
                        if ($date) {
                            $filters[] = ['sm_mercan_cair.dari_tgl_tran', '>=', $date];
                        }
                        break;
                    case 'sm_mercan_cair.PAIDDT_end':
                        $date = Carbon::createFromFormat('d-m-Y', $val);
                        if ($date) {
                            $filters[] = ['sm_mercan_cair.akhir_tgl_tran', '<=', $date];
                        }
                        break;
                    case 'sm_mercan_cair.TglTerima':
                        $date = Carbon::createFromFormat('d-m-Y', $val);
                        if ($date) {
                            $filters[] = ['sm_mercan_cair.TglTerima', '=', $date];
                        }
                        break;
                    default:
                        ($key) && $filters[] = [$key, '=', $val];
                        break;
                }
            };

            if (!empty($filters)) {
                $filterQuery = function ($query) use ($filters) {
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
        }

        $whereAny = [];

        $select = array_unique([...$whereAny,
            'sm_mercan_cair.Nominal',
            'sm_mercan_cair.TglTerima',
            'sm_mercan_cair.dari_tgl_tran',
            'sm_mercan_cair.akhir_tgl_tran',
        ]);

        $query = DB::connection('DATA_MYSQL')->table('sm_mercan_cair')
            ->where('sm_mercan_cair.NamaPenerima', 'like', "%" . session('cashless_user.kantin') . "%")
            ->where(function ($query) use ($filterQuery) {
                if ($filterQuery) {
                    $filterQuery($query);
                }
            })
            ->when(!blank($searchValue), function ($query) use ($whereAny, $searchValue) {
                $query->where(function ($q) use ($whereAny, $searchValue) {
                    $sanitizeSearch = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $searchValue);
                    foreach ($whereAny as $column) {
                        $q->orWhere($column, 'like', '%' . $sanitizeSearch . '%');
                    }
                });
            });

        $totalRecords = $this->total();

        $totalRecordswithFilter =
            Cache::remember(
                CacheHandler::cacheKey($this->cacheKey, 'total_filtered_data', $filter, $searchValue ?? ''),
                now()->addMinutes(10),
                fn() => (clone $query)->count()
            );

        $records = (clone $query)
            ->orderBy($columnName, $columnSortOrder)
            ->select($select)
            ->skip($start)
            ->take($rowperpage)
            ->get()
            ->toArray();

        $response = array(
            "draw" => intval($draw),
            "recordsTotal" => $totalRecords ?? 0,
            "recordsFiltered" => $totalRecordswithFilter ?? 0,
            "data" => $records ?? [],
        );
        return response()->json($response);
    }

    public function total(): int
    {
        $key = Str::slug($this->cacheKey);
        return Cache::remember(
            "{$key}:total_all_data",
            now()->addMinutes(10),
            fn() => DB::connection('DATA_MYSQL')->table('sm_mercan_cair')
                ->where('sm_mercan_cair.NamaPenerima', 'like', "%" . session('cashless_user.kantin') . "%")
                ->count()
        );
    }


}

