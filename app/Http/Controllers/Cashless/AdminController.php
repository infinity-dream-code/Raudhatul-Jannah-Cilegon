<?php

namespace App\Http\Controllers\Cashless;

use App\Http\Controllers\Controller;
use App\Models\ValidationMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Carbon;

class AdminController extends Controller
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
        return view('cashless.admin.index', $data);
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
            $saldo = DB::connection('DATA_MYSQL')
                ->select('SELECT GetSaldoCard_Backup_Cutoff(?) AS saldo', [$request->tap_id]);
            $data = explode("|", $saldo[0]->saldo);
            return response()->json(["data" => $data]);
        } catch (\Exception $e) {
            return response()->json([
                "message" => "gagal mendapatkan data saldo, silahkan coba lagi",
                "error" => $e->getMessage()
            ], 422);
        }
    }

    public function payment(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                "tap_id" => ["required", "string"],
                "belanja" => ["required", 'regex:/^[0-9]+(\.[0-9]{3})*$/', 'not_in:0'],
                "pin" => ["required", "string"],
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
            $result = DB::connection('DATA_MYSQL')
                ->select(
                    'SELECT PaymentBUY_Backup_Cutoff(?,?,?,?) AS result',
                    [
                        $request->tap_id,
                        $request->belanja,
                        session('cashless_user.username'),
                        $request->pin,
                    ]);
            return response()->json(["data" => $result[0]->result]);
        } catch (\Exception $e) {
            return response()->json(["message" => "gagal mendapatkan data saldo, silahkan coba lagi", "error" => $e->getMessage()], 422);
        }
    }
}
