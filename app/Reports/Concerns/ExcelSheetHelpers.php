<?php

namespace App\Reports\Concerns;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

trait ExcelSheetHelpers
{

    
    protected function hasMergedRange(Worksheet $sh, string $range): bool
    {
        $range = strtoupper($range);
        foreach ($sh->getMergeCells() as $merged) {
            if (strtoupper($merged) === $range) {
                return true;
            }
        }
        return false;
    }

// Merge nhưng không lỗi nếu đã merge sẵn
    protected function safeMerge(Worksheet $sh, string $range): void
    {
        if (!$this->hasMergedRange($sh, $range)) {
            try { $sh->mergeCells($range); } catch (\Throwable $e) { /* ignore */ }
        }
    }


 
    protected function nextCol(string $col): string
    {
        $i = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($col) + 1;
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
    }
    /**
     * Kẻ viền mảnh cả bảng (helper nhẹ – nếu bạn đã có trong trait, có thể bỏ)
     */
    protected function drawThinBorders(Worksheet $sh, int $rowFrom, int $rowTo, string $colFrom, string $colTo): void
    {
        $range = sprintf('%s%d:%s%d', $colFrom, $rowFrom, $colTo, $rowTo);
        $sh->getStyle($range)->getBorders()->getAllBorders()
           ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    }

    protected function fillYearHeadersFromArray(Worksheet $sh, int $row, array $years): array
    {
        $map = [];
        $colIndex = Coordinate::columnIndexFromString('F');

        for ($i = 0; $i < 5; $i++) {
            $y = (int) $years[$i];
            $sh->setCellValueByColumnAndRow($colIndex, $row, $y);
            $map[$y] = Coordinate::stringFromColumnIndex($colIndex);
            $colIndex++;
        }
        $total610Col = Coordinate::stringFromColumnIndex($colIndex);
        $colIndex++;

        for ($i = 5; $i < 9; $i++) {
            $y = (int) $years[$i];
            $sh->setCellValueByColumnAndRow($colIndex, $row, $y);
            $map[$y] = Coordinate::stringFromColumnIndex($colIndex);
            $colIndex++;
        }
        $total1114Col = Coordinate::stringFromColumnIndex($colIndex);

        return $map + [
            'total_6_10'  => $total610Col,
            'total_11_14' => $total1114Col,
        ];
    }

    protected function fillOneRowSeries(Worksheet $sh, int $row, array $data, array $yearToCol): void
    {
        foreach ($data as $y => $v) {
            if (!isset($yearToCol[$y])) continue;
            if ($v != 0) {
                $sh->setCellValue($yearToCol[$y].$row, $v);
            }
        }
    }

    protected function colsForAgeRange(array $yearToCol, int $y1, int $ageFrom, int $ageTo): array
    {
        $cols = [];
        for ($age = $ageFrom; $age <= $ageTo; $age++) {
            $year = $y1 - $age;
            if (isset($yearToCol[$year])) $cols[] = $yearToCol[$year];
        }
        return array_reverse($cols);
    }

    protected function colIndexFromMap(array $yearToCol, int $year): int
    {
        if (!isset($yearToCol[$year])) return 0;
        return Coordinate::columnIndexFromString($yearToCol[$year]);
    }

    protected function replicateRowFormatMerged(Worksheet $sh, int $srcRow, int $dstRow, string $lastCol = 'Q'): void
    {
        $sh->getRowDimension($dstRow)->setRowHeight($sh->getRowDimension($srcRow)->getRowHeight());
        $srcRange = "A{$srcRow}:{$lastCol}{$srcRow}";
        $dstRange = "A{$dstRow}:{$lastCol}{$dstRow}";
        $sh->duplicateStyle($sh->getStyle($srcRange), $dstRange);

        foreach ($sh->getMergeCells() as $merge) {
            [$c1, $c2] = explode(':', $merge);
            [$col1, $row1] = Coordinate::coordinateFromString($c1);
            [$col2, $row2] = Coordinate::coordinateFromString($c2);
            if ($row1 === $srcRow && $row2 === $srcRow) {
                $new = "{$col1}{$dstRow}:{$col2}{$dstRow}";
                if (!$sh->isMergedCell($new)) $sh->mergeCells($new);
            }
        }
    }

    protected function fixNameMergeAndBorders(
        Worksheet $sh, int $startRow, int $lastRow, string $nameColFrom='B', string $nameColTo='D', string $lastCol='Q'
    ): void {
        for ($r = $startRow; $r <= $lastRow; $r++) {
            $nameRange = "{$nameColFrom}{$r}:{$nameColTo}{$r}";
            try { $sh->unmergeCells($nameRange); } catch (\Throwable $e) {}
            $sh->mergeCells($nameRange);
            $sh->getStyle($nameRange)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
        }

        $tableRange = "A{$startRow}:{$lastCol}{$lastRow}";
        $sh->getStyle($tableRange)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        $sh->getStyle("{$lastCol}{$startRow}:{$lastCol}{$lastRow}")
           ->getBorders()->getRight()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sh->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")
           ->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    }

    /** Ghi dòng ngày-tháng-năm theo config/report_formats.php */
    protected function writeDateLine(Worksheet $sh, string $scheme, string $wardName, int $row, string $lastCol): void
    {
        $cfg = config('report_formats.date_line');
        if (($cfg['mode'] ?? 'numeric') === 'off') return;

        $fromCol = $cfg['cell_from'][$scheme] ?? 'L';
        $cell = "{$fromCol}{$row}:{$lastCol}{$row}";
        $sh->mergeCells($cell);

        $d = date('d'); $m = date('m'); $y = date('Y');
        $text = ($cfg['mode']==='dots')
            ? strtr($cfg['template_dots'],    ['{ward}'=>$wardName,'{d}'=>$d,'{m}'=>$m,'{y}'=>$y])
            : strtr($cfg['template_numeric'], ['{ward}'=>$wardName,'{d}'=>$d,'{m}'=>$m,'{y}'=>$y]);

        $sh->setCellValue("{$fromCol}{$row}", $text);

        $align = strtolower($cfg['align'] ?? 'center');
        $sh->getStyle("{$fromCol}{$row}")->getAlignment()->setHorizontal(
            $align==='left' ? Alignment::HORIZONTAL_LEFT :
            ($align==='right' ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_CENTER)
        );
    }
}
