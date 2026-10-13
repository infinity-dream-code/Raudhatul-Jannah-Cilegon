<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class TransaksiBelanjaExport implements FromArray, WithEvents, WithTitle
{
    private const LAST_COL = 'H';

    private array $rows = [];

    private int $headerRow = 0;

    private int $firstDataRow = 0;

    private int $lastDataRow = 0;

    private int $totalRow = 0;

    /** @var list<string> */
    private array $nisValues = [];

    /**
     * @param list<string> $filterInfo
     */
    public function __construct(Collection $transactions, private array $filterInfo = [])
    {
        $this->buildRows($transactions);
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return 'Transaksi Belanja';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = self::LAST_COL;

                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1F3864']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $sheet->getStyle("A{$this->headerRow}:{$lastCol}{$this->headerRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2F5597']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle("A{$this->headerRow}:{$lastCol}{$this->totalRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                if ($this->lastDataRow >= $this->firstDataRow) {
                    $sheet->getStyle("A{$this->firstDataRow}:A{$this->lastDataRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $sheet->getStyle("E{$this->firstDataRow}:E{$this->lastDataRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');

                    foreach ($this->nisValues as $offset => $nis) {
                        $row = $this->firstDataRow + $offset;
                        $sheet->setCellValueExplicit("B{$row}", $nis, DataType::TYPE_STRING);
                    }
                }

                $sheet->getStyle("A{$this->totalRow}:{$lastCol}{$this->totalRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2EFDA']],
                ]);
                $sheet->getStyle("E{$this->totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("D{$this->totalRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $widths = ['A' => 6, 'B' => 16, 'C' => 32, 'D' => 18, 'E' => 14, 'F' => 24, 'G' => 16, 'H' => 18];
                foreach ($widths as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }
            },
        ];
    }

    private function buildRows(Collection $transactions): void
    {
        $this->rows = [['Data Transaksi Belanja']];

        foreach ($this->filterInfo as $info) {
            $this->rows[] = [$info];
        }

        $this->rows[] = [''];
        $this->rows[] = ['No', 'NIS', 'Nama', 'Tgl Transaksi', 'Debet', 'Kantin', 'Kelas', 'Kelompok'];
        $this->headerRow = count($this->rows);
        $this->firstDataRow = $this->headerRow + 1;

        $total = 0.0;
        foreach ($transactions->values() as $index => $trx) {
            $debet = (float) ($trx->debet ?? 0);
            $total += $debet;
            $nis = (string) ($trx->nis ?? '');
            $this->nisValues[] = $nis;

            $this->rows[] = [
                $index + 1,
                $nis,
                (string) ($trx->nama ?? ''),
                !empty($trx->tgl_transaksi) ? Carbon::parse($trx->tgl_transaksi)->format('d-m-Y H:i') : '',
                $debet,
                (string) ($trx->kantin ?? ''),
                (string) ($trx->kelas ?? ''),
                (string) ($trx->kelompok ?? ''),
            ];
        }

        $this->lastDataRow = count($this->rows);

        $this->rows[] = ['', '', '', 'Total', $total, '', '', ''];
        $this->totalRow = count($this->rows);
    }
}
