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

class RekapPenerimaanHarianController extends Controller
{
    public string $title = "Laporan Harian";
    public string $mainTitle = "Laporan Harian";
    private string $cacheKey = 'Data Rekap Penerimaan Harian';

    private array $allowedFilters = [
        'dari_tanggal' => 'scctcashout.PAIDDT_start',
        'sampai_tanggal' => 'scctcashout.PAIDDT_end',
    ];

    public function __construct()
    {
        $key = Str::slug($this->cacheKey) . '_cache_version';

        Cache::add($key, 1);
    }

    public function index()
    {
        return view('cashless.admin.rekap_harian.index', [
            'title' => $this->title,
            'mainTitle' => $this->mainTitle,
            'columnsUrl' => $this->columnsUrl(),
            'datasUrl' => $this->datasUrl(),
        ]);
    }

    private function columnsUrl(): string
    {
        return route('cashless.admin.rekap-penerimaan-harian.get-column');
    }

    private function datasUrl(): string
    {
        return route('cashless.admin.rekap-penerimaan-harian.get-data');
    }

    public function getColumn()
    {
        return [
            ["data" => null, "name" => "no", "columnType" => "row"],
            [
                "data" => "TanggalKeluar",
                "name" => "Tanggal",
                "columnType" => "dateformat",
                "searchable" => true,
                "orderable" => true,
                "exportable" => true,
            ],
            [
                "data" => "total",
                "name" => "Penerimaan",
                "exportable" => true,
                "columnType" => "currency",
                "className" => "text-end"
            ],
        ];
    }

    public function getData(Request $request)
    {
        $draw = $request->get("draw");
        $start = $request->get("start");
        $rowperpage = $request->get("length");

        $columnIndex_arr = $request->get("order", []);
        $columnName_arr = $request->get("columns", []);
        $order_arr = $request->get("order", []);
        $search_arr = $request->get("search", []);
        $searchValue = $search_arr["value"] ?? "";

        $columnName = "scctcashout.TanggalKeluar";
        $columnSortOrder = "DESC";

        if (!empty($order_arr)) {
            $columnIndex = $columnIndex_arr[0]["column"] ?? null;
            if (
                $columnIndex !== null &&
                !empty($columnName_arr[$columnIndex]["data"]) &&
                $columnName_arr[$columnIndex]["data"] !== "no"
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
                    case 'scctcashout.PAIDDT_start':
                        $date = Carbon::createFromFormat('d-m-Y', $val)->startOfDay();
                        if ($date) {
                            $filters[] = ['scctcashout.TanggalKeluar', '>=', $date];
                        }
                        break;
                    case 'scctcashout.PAIDDT_end':
                        $date = Carbon::createFromFormat('d-m-Y', $val)->endOfDay();
                        if ($date) {
                            $filters[] = ['scctcashout.TanggalKeluar', '<=', $date];
                        }
                        break;
                }
            }

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

        $select = array_unique(
            array_merge($whereAny, [
                "scctcashout.TanggalKeluar",
            ]),
        );

        $query = DB::connection('DATA_MYSQL')->table('scctcashout')
            ->where('teller', '=', session('cashless_user.username'))
            ->groupByRaw('DATE(scctcashout.TanggalKeluar)')
            ->where(function ($query) use ($filterQuery) {
                if ($filterQuery) {
                    $filterQuery($query);
                }
            });

        $totalRecords = $this->total();

        $totalRecordswithFilter =
            Cache::remember(
                CacheHandler::cacheKey($this->cacheKey, 'total_filtered_data', $filter, $searchValue ?? ''),
                now()->addMinutes(10),
                fn() => (clone $query)->count()
            );

        $total =
            Cache::remember(
                CacheHandler::cacheKey($this->cacheKey, 'total_sum_value', $filter, $searchValue ?? ''),
                now()->addMinutes(10),
                fn() => DB::connection('DATA_MYSQL')->table('scctcashout')
                    ->where('teller', '=', session('cashless_user.username'))->select([
                        DB::raw("COALESCE(SUM(scctcashout.BILLAM), 0) as total"),
                    ])->first()
            );


        $rowperpage = $rowperpage == "poll" ? $totalRecords : $rowperpage;
        $records = (clone $query)
            ->orderBy($columnName, $columnSortOrder)
            ->select($select)
            ->addSelect(DB::raw("COALESCE(SUM(scctcashout.BILLAM), 0) as total"))
            ->skip($start)
            ->take($rowperpage)
            ->get();

        $records->toArray();


        $response = [
            "draw" => intval($draw),
            "recordsTotal" => $totalRecords ?? 0,
            "recordsFiltered" => $totalRecordswithFilter ?? 0,
            "data" => $records,
            'totals' => [
                'total' => ['location' => 2, 'value' => $total->total ?? 0, 'columnType' => 'currency']
            ]
        ];
        return response()->json($response);
    }

    public function total(): int
    {
        $key = Str::slug($this->cacheKey);
        return Cache::remember(
            "{$key}:total_all_data",
            now()->addMinutes(10),
            fn() => $totalRecords = DB::connection('DATA_MYSQL')->table('scctcashout')
                ->where('teller', '=', session('cashless_user.username'))
                ->groupByRaw('DATE(scctcashout.TanggalKeluar)')
                ->count()
        );
    }
}

