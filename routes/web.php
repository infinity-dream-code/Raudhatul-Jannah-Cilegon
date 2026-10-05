<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Auth::routes([
    "register" => false,
]);
Route::get("/", [AuthController::class, "index"])->name("index");

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
Route::get("admin/keep-alive", \App\Http\Controllers\Admin\KeepAliveController::class)
    ->name("admin.keep-alive");
Route::get("/reload-captcha", [AuthController::class, "reloadCaptcha"])->name("reload-captcha");
Route::get("/reload-math-captcha", [\App\Http\Controllers\Auth\LoginController::class, "reloadMathCaptcha"])->name("reload-math-captcha");

Route::middleware('auth')->group(function () {
    Route::get('/portal', [\App\Http\Controllers\PortalController::class, 'index'])->name('portal');
    Route::get('/portal/sikeu', [\App\Http\Controllers\PortalController::class, 'sikeu'])->name('portal.sikeu');
    Route::get('/portal/facepay-admin', [\App\Http\Controllers\PortalController::class, 'facepayAdmin'])->name('portal.facepay-admin');
    Route::get('/portal/facepay-siswa', [\App\Http\Controllers\PortalController::class, 'facepaySiswa'])->name('portal.facepay-siswa');
    Route::get('/portal/facepay-kantin', [\App\Http\Controllers\PortalController::class, 'facepayKantin'])->name('portal.facepay-kantin');
    Route::get('/portal/cashless', [\App\Http\Controllers\PortalController::class, 'cashless'])->name('portal.cashless');
    Route::get('/portal/switch', [\App\Http\Controllers\PortalController::class, 'switchModule'])->name('portal.switch');
});

Route::prefix("admin")
    ->name("admin.")
    ->middleware(["auth", "check.roles:admin"])
    ->group(function () {
        Route::get("/", [AdminController::class, "index"])->name("index");

        Route::prefix("master-data")->name("master-data.")->group(function () {
            Route::get("get-logo", function (\Illuminate\Http\Request $request) {
                $path = public_path(config("app.logo"));
                $mime = "image/jpeg";
                if (!file_exists($path)) {
                    return response()->json(["data" => null], 404);
                }
                $data = "data:{$mime};base64," . base64_encode(file_get_contents($path));
                return response()->json(["data" => $data]);
            })->name("get-logo");

            Route::prefix("master-kelas")
                ->name("master-kelas.")
                ->controller(\App\Http\Controllers\Admin\MasterData\MasterKelasController::class)
                ->group(function () {
                    Route::get("get-data", "getData")->name("get-data");
                    Route::get("get-column", "getColumn")->name("get-column");
                    Route::resource("", \App\Http\Controllers\Admin\MasterData\MasterKelasController::class)->parameters(["" => "id"]);
                });

            Route::prefix("tahun-pelajaran")
                ->name("tahun-pelajaran.")
                ->controller(\App\Http\Controllers\Admin\MasterData\TahunPelajaranController::class)
                ->group(function () {
                    Route::get("get-data", "getData")->name("get-data");
                    Route::get("get-column", "getColumn")->name("get-column");
                });
            Route::resource("tahun-pelajaran", \App\Http\Controllers\Admin\MasterData\TahunPelajaranController::class)->names("tahun-pelajaran");

            Route::prefix("master-tagihan")
                ->name("master-tagihan.")
                ->controller(\App\Http\Controllers\Admin\MasterData\MasterTagihanController::class)
                ->group(function () {
                    Route::get("get-data", "getData")->name("get-data");
                    Route::get("get-column", "getColumn")->name("get-column");
                    Route::get("", "index")->name("index");
                    Route::post("", "store")->name("store");
                });

            Route::prefix("export-import-data")
                ->name("export-import-data.")
                ->controller(\App\Http\Controllers\Admin\MasterData\ExportImportDataController::class)
                ->group(function () {
                    Route::get("get-data", "getData")->name("get-data");
                    Route::get("get-column", "getColumn")->name("get-column");
                    Route::post("validate-data", "validateData")->name("validate-data");
                    Route::get("clear-data", "clearData")->name("clear-data");
                    Route::resource("", \App\Http\Controllers\Admin\MasterData\ExportImportDataController::class)->parameters(["" => "id"]);
                });

            Route::prefix("data-siswa")
                ->name("data-siswa.")
                ->controller(\App\Http\Controllers\Admin\MasterData\DataSiswaController::class)
                ->group(function () {
                    Route::get("get-data", "getData")->name("get-data");
                    Route::get("get-column", "getColumn")->name("get-column");
                    Route::get("get-siswa", "getSiswa")->name("get-siswa");
                    Route::get("get-siswa-select2", "getSiswaSelect2")->name("get-siswa-select2");
                    Route::post("reset-login-android/{id}", "ResetLoginAndroid")->name("reset-login-android");
                    Route::post("reset-login-android-bulk", "resetLoginAndroidBulk")->name("reset-login-android-bulk");
                    Route::post("set-status-siswa/{id}", "setStatusSiswa")->name("set-status-siswa");
                });
            Route::resource("data-siswa", \App\Http\Controllers\Admin\MasterData\DataSiswaController::class)->names("data-siswa");

            Route::prefix("setting-data-wa")
                ->name("setting-data-wa.")
                ->controller(\App\Http\Controllers\Admin\MasterData\SettingDataWaController::class)
                ->group(function () {
                    Route::get("get-data", "getData")->name("get-data");
                    Route::get("get-column", "getColumn")->name("get-column");
                    Route::post("validate-data", "validateData")->name("validate-data");
                    Route::get("clear-data", "clearData")->name("clear-data");
                    Route::resource("", \App\Http\Controllers\Admin\MasterData\SettingDataWaController::class)->parameters(["" => "id"]);
                });

            Route::prefix("pindah-kelas")
                ->name("pindah-kelas.")
                ->controller(\App\Http\Controllers\Admin\MasterData\PindahKelasController::class)
                ->group(function () {
                    Route::get("get-data", "getData")->name("get-data");
                    Route::get("get-column", "getColumn")->name("get-column");
                    Route::resource("", \App\Http\Controllers\Admin\MasterData\PindahKelasController::class)->parameters(["" => "id"]);
                });

            Route::prefix("user-kantin")
                ->name("user-kantin.")
                ->controller(\App\Http\Controllers\Admin\MasterData\UserKantinController::class)
                ->group(function () {
                    Route::get("", "index")->name("index");
                    Route::post("", "store")->name("store");
                    Route::post("reset-password-bulk", "resetPasswordBulk")->name("reset-password-bulk");
                    Route::post("{id}/reset-password", "resetPassword")->name("reset-password")->whereNumber("id");
                });

            Route::prefix("setting-batasan")
                ->name("setting-batasan.")
                ->controller(\App\Http\Controllers\Admin\MasterData\SettingBatasanController::class)
                ->group(function () {
                    Route::get("", "index")->name("index");
                    Route::post("", "store")->name("store");
                    Route::put("{id}", "update")->name("update")->whereNumber("id");
                });
        });

        Route::prefix("keuangan")->name("keuangan.")->group(function () {
            Route::controller(\App\Http\Controllers\Admin\Keuangan\ManualPembayaranController::class)
                ->prefix("manual-pembayaran")->name("manual-pembayaran.")->group(function () {
                    Route::get("get-data", "getData")->name("get-data");
                    Route::get("get-column", "getColumn")->name("get-column");
                    Route::get("get-tagihan", "getTagihan")->name("get-tagihan");
                    Route::get("cetak-tagihan", "cetakTagihan")->name("cetak-tagihan");
                    Route::get("cetak-tagihan-dibayar", "cetakPembayaran")->name("cetak-tagihan-dibayar");
                    Route::post("update-nocust", "updateNocust")->name("update-nocust");
                    Route::resource("", \App\Http\Controllers\Admin\Keuangan\ManualPembayaranController::class)->parameters(["" => "id"]);
                });

            Route::prefix("tagihan-siswa")->name("tagihan-siswa.")->group(function () {
                Route::prefix("data-tagihan")->name("data-tagihan.")->group(function () {
                    Route::controller(\App\Http\Controllers\Admin\Keuangan\TagihanSiswa\DataTagihanController::class)->group(function () {
                        Route::get("get-data", "getData")->name("get-data");
                        Route::get("get-column", "getColumn")->name("get-column");
                        Route::get("get-trans-log/{id}", "getTransLog")->name("get-trans-log");
                        Route::get("cetak-rekap", "cetak")->name("cetak-rekap");
                        Route::post("ubah-urutan/{id}", "ubahUrutan")->name("ubah-urutan");
                        Route::delete("hapus/{id}", "hapusTagihan")->name("hapus");
                        Route::get("cetak-kartu-siswa", "cetakKartuSiswa")->name("cetak-kartu-siswa");
                        Route::resource("", \App\Http\Controllers\Admin\Keuangan\TagihanSiswa\DataTagihanController::class)->parameters(["" => "id"]);
                    });
                });

                Route::prefix("upload-tagihan-excel")->name("upload-tagihan-excel.")->group(function () {
                    Route::controller(\App\Http\Controllers\Admin\Keuangan\TagihanSiswa\UploadTagihanExcelController::class)->group(function () {
                        Route::get("get-data", "getData")->name("get-data");
                        Route::get("get-column", "getColumn")->name("get-column");
                        Route::post("validate-excel", "validateExcel")->name("validate-excel");
                        Route::resource("", \App\Http\Controllers\Admin\Keuangan\TagihanSiswa\UploadTagihanExcelController::class)->parameters(["" => "id"]);
                    });
                });
            });

            Route::prefix("penerimaan-siswa")->name("penerimaan-siswa.")->group(function () {
                Route::prefix("data-penerimaan")->name("data-penerimaan.")->group(function () {
                    Route::controller(\App\Http\Controllers\Admin\Keuangan\PenerimaanSiswa\DataPenerimaanController::class)->group(function () {
                        Route::get("get-data", "getData")->name("get-data");
                        Route::get("get-column", "getColumn")->name("get-column");
                        Route::get("get-trans-log/{id}", "getTransLog")->name("get-trans-log");
                        Route::post("get-trans-logs-bulk", "getTransLogsBulk")->name("get-trans-logs-bulk");
                        Route::get("cetak-rekap", "cetak")->name("cetak-rekap");
                        Route::get("cetak-rekap-new", "cetakNew")->name("cetak-rekap-new");
                        Route::get("cetak-kartu-siswa", "cetakKartuSiswa")->name("cetak-kartu-siswa");
                        Route::get("cetak-tagihan-dibayar", "cetakPembayaran")->name("cetak-tagihan-dibayar");
                        Route::resource("", \App\Http\Controllers\Admin\Keuangan\PenerimaanSiswa\DataPenerimaanController::class)->parameters(["" => "id"]);
                    });
                });

                Route::prefix("rekap-penerimaan")->name("rekap-penerimaan.")->group(function () {
                    Route::controller(\App\Http\Controllers\Admin\Keuangan\PenerimaanSiswa\RekapPenerimaanController::class)->group(function () {
                        Route::get("get-data", "getData")->name("get-data");
                        Route::get("get-column", "getColumn")->name("get-column");
                        Route::get("cetak-rekap", "cetakRekapPenerimaan")->name("cetak-rekap");
                        Route::get("cetak-tagihan-dibayar", "cetakPembayaran")->name("cetak-tagihan-dibayar");
                        Route::get("cetak-kartu-siswa", "cetakKartuSiswa")->name("cetak-kartu-siswa");
                        Route::get("cetak-per-nis", "cetakPerNis")->name("cetak-per-nis");
                        Route::resource("", \App\Http\Controllers\Admin\Keuangan\PenerimaanSiswa\RekapPenerimaanController::class)->parameters(["" => "id"]);
                    });
                });
            });

            Route::prefix("data-transfer-va")
                ->name("data-transfer-va.")
                ->controller(\App\Http\Controllers\Admin\Keuangan\Saldo\SccttranController::class)
                ->group(function () {
                    Route::get("get-data", "getData")->name("get-data");
                    Route::get("get-column", "getColumn")->name("get-column");
                    Route::get("", "index")->name("index");
                });

            Route::prefix("saldo")->name("saldo.")->group(function () {
                Route::controller(\App\Http\Controllers\Admin\Keuangan\Saldo\SaldoVirtualAccountController::class)
                    ->prefix("saldo-virtual-account")->name("saldo-virtual-account.")->group(function () {
                        Route::get("get-data", "getData")->name("get-data");
                        Route::get("get-column", "getColumn")->name("get-column");
                        Route::get("get-saldo", "getSaldo")->name("get-saldo");
                        Route::get("export-transaksi", "exportTransaksi")->name("export-transaksi");
                        Route::get("{id}/export", "exportDetail")->name("export");
                        Route::prefix("data-transaksi")->name("data-transaksi.")->group(function () {
                            Route::get("", "transaksiIndex")->name("index");
                            Route::get("get-data", "getDataDataTransaksi")->name("get-data");
                            Route::get("get-column", "getColumnDataTransaksi")->name("get-column");
                        });
                        Route::post("tarik", "tarik")->name("tarik");
                        Route::prefix("transaksi")->name("transaksi.")->group(function () {
                            Route::get("get-data", "getDataTran")->name("get-data");
                            Route::get("get-column", "getColumnTran")->name("get-column");
                        });
                    });
                Route::resource("saldo-virtual-account", \App\Http\Controllers\Admin\Keuangan\Saldo\SaldoVirtualAccountController::class)->names("saldo-virtual-account");
            });

            Route::prefix("hapus-tagihan")->name("hapus-tagihan.")->group(function () {
                Route::controller(\App\Http\Controllers\Admin\Keuangan\HapusTagihanController::class)->group(function () {
                    Route::get("get-data", "getData")->name("get-data");
                    Route::get("get-column", "getColumn")->name("get-column");
                    Route::post("hapus-jamak", "bulkDestroy")->name("hapus-jamak");
                    Route::resource("", \App\Http\Controllers\Admin\Keuangan\HapusTagihanController::class)->parameters(["" => "id"]);
                });
            });
        });

        Route::prefix("manual-input")->name("manual-input.")->group(function () {
            Route::controller(\App\Http\Controllers\Admin\ManualInput\EditManualController::class)
                ->prefix("edit-manual")->name("edit-manual.")->group(function () {
                    Route::get("get-siswa", "getSiswa")->name("get-siswa");
                    Route::get("get-tagihan", "getTagihan")->name("get-tagihan");
                    Route::get("get-detail-taighan", "getDetailTagihan")->name("get-detail-tagihan");
                    Route::put("edit-tagihan", "editTagihan")->name("edit-tagihan");
                    Route::post("copy-tagihan", "copyTagihan")->name("copy-tagihan");
                    Route::resource("", \App\Http\Controllers\Admin\ManualInput\EditManualController::class)->parameters(["" => "id"]);
                });
        });
    });

Route::prefix('smartcard')
    ->name('smartcard.')
    ->middleware(['auth', 'check.roles:admin'])
    ->group(function () {
        Route::get('/data-kartu-siswa', [\App\Http\Controllers\Smartcard\DataKartuSiswaController::class, 'index'])->name('data_kartu');
        Route::post('/data-kartu-siswa', [\App\Http\Controllers\Smartcard\DataKartuSiswaController::class, 'store'])->name('data_kartu.store');
        Route::get('/data-kartu-siswa/siswa-search', [\App\Http\Controllers\Smartcard\DataKartuSiswaController::class, 'searchSiswa'])->name('data_kartu.siswa_search');

        Route::get('/transaksi-belanja', [\App\Http\Controllers\Smartcard\TransaksiBelanjaController::class, 'index'])->name('transaksi_belanja');

        Route::get('/rekap-pencairan-kantin', [\App\Http\Controllers\Smartcard\RekapPencairanKantinController::class, 'index'])->name('rekap_pencairan_kantin');
        Route::post('/rekap-pencairan-kantin', [\App\Http\Controllers\Smartcard\RekapPencairanKantinController::class, 'store'])->name('rekap_pencairan_kantin.store');
    });

/*
|--------------------------------------------------------------------------
| Cashless (sm_kantin) — same app, separate login session
|--------------------------------------------------------------------------
*/
Route::prefix('cashless')->name('cashless.')->group(function () {
    Route::get('/login', [\App\Http\Controllers\Cashless\CashlessAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [\App\Http\Controllers\Cashless\CashlessAuthController::class, 'login'])->name('login.post');
    Route::post('/logout', [\App\Http\Controllers\Cashless\CashlessAuthController::class, 'logout'])->name('logout');

    Route::prefix('admin')
        ->name('admin.')
        ->middleware(['cashless.session'])
        ->group(function () {
            Route::get('/', [\App\Http\Controllers\Cashless\AdminController::class, 'index'])->name('index');

            Route::prefix('rekap-penerimaan-harian')->name('rekap-penerimaan-harian.')
                ->controller(\App\Http\Controllers\Cashless\RekapPenerimaanHarianController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('get-data', 'getData')->name('get-data');
                    Route::get('get-column', 'getColumn')->name('get-column');
                });

            Route::prefix('data-transaksi-belanja')->name('data-transaksi-belanja.')
                ->controller(\App\Http\Controllers\Cashless\DataTransaksiBelanjaController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('get-data', 'getData')->name('get-data');
                    Route::get('get-column', 'getColumn')->name('get-column');
                    Route::post('get-total', 'getTotal')->name('get-total');
                    Route::post('export', 'export')->name('export');
                });

            Route::prefix('riwayat-pencairan')->name('riwayat-pencairan.')
                ->controller(\App\Http\Controllers\Cashless\RiwayatPencairanController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('get-data', 'getData')->name('get-data');
                    Route::get('get-column', 'getColumn')->name('get-column');
                });

            Route::prefix('tap-belanja')->name('tap-belanja.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Cashless\TapBelanjaController::class, 'index'])->name('index');
                Route::get('/face-references', [\App\Http\Controllers\Cashless\TapBelanjaController::class, 'faceReferences'])->name('face-references');
                Route::post('/get-saldo', [\App\Http\Controllers\Cashless\TapBelanjaController::class, 'getSaldo'])->name('get-saldo');
                Route::post('/get-saldo-nis', [\App\Http\Controllers\Cashless\TapBelanjaController::class, 'getSaldoByNis'])->name('get-saldo-nis');
                Route::post('/payment', [\App\Http\Controllers\Cashless\TapBelanjaController::class, 'payment'])->name('payment');
                Route::post('/payment-nis', [\App\Http\Controllers\Cashless\TapBelanjaController::class, 'paymentByNis'])->name('payment-nis');
            });

            Route::prefix('cek-limit')->name('cek-limit.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Cashless\CekLimitController::class, 'index'])->name('index');
                Route::post('/get-limit', [\App\Http\Controllers\Cashless\CekLimitController::class, 'getLimit'])->name('get-limit');
            });

            Route::prefix('cek-saldo')->name('cek-saldo.')->group(function () {
                Route::get('/', [\App\Http\Controllers\Cashless\CekSaldoController::class, 'index'])->name('index');
                Route::post('/get-data', [\App\Http\Controllers\Cashless\CekSaldoController::class, 'getData'])->name('get-data');
            });

            Route::prefix('manajemen-admin')
                ->name('manajemen-admin.')
                ->controller(\App\Http\Controllers\Cashless\ManajemenAdminController::class)
                ->group(function () {
                    Route::get('get-data', 'getData')->name('get-data');
                    Route::get('get-column', 'getColumn')->name('get-column');
                    Route::put('update-password/{id}', 'changePassword')->name('update-password');
                    Route::put('reset-password/{id}', 'resetPassword')->name('reset-password');
                    Route::resource('', \App\Http\Controllers\Cashless\ManajemenAdminController::class)->parameters(['' => 'id']);
                });

            Route::prefix('profil-admin')
                ->name('profil-admin.')
                ->controller(\App\Http\Controllers\Cashless\ProfileAdminController::class)
                ->group(function () {
                    Route::put('update-password/{id}', 'changePassword')->name('update-password');
                    Route::resource('', \App\Http\Controllers\Cashless\ProfileAdminController::class)->parameters(['' => 'id']);
                });
        });
});
