<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Html as HtmlWriter;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportViewerService
{
    /**
     * Chuyển đổi Response (Streamed hoặc BinaryFile) từ các hàm xuất báo cáo thành HTML hoàn chỉnh
     */
    public function convertResponseToHtml(Response $response, string $reportTitle = 'Báo cáo thống kê'): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'rep_prev_');

        try {
            if ($response instanceof BinaryFileResponse) {
                copy($response->getFile()->getPathname(), $tmpPath);
            } elseif ($response instanceof StreamedResponse) {
                ob_start();
                $response->sendContent();
                $content = ob_get_clean();
                file_put_contents($tmpPath, $content);
            } else {
                throw new \InvalidArgumentException('Unsupported response type: ' . get_class($response));
            }

            if (!file_exists($tmpPath) || filesize($tmpPath) === 0) {
                throw new \RuntimeException('File báo cáo rỗng hoặc không thể tạo.');
            }

            return $this->convertExcelFileToHtml($tmpPath, $reportTitle);
        } finally {
            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    /**
     * Chuyển đổi file Excel sang HTML với tính toán công thức, loại bỏ cột thừa và định dạng chuẩn Excel
     * Chỉ hiển thị duy nhất Sheet chính chứa số liệu báo cáo
     */
    public function convertExcelFileToHtml(string $filePath, string $reportTitle = 'Báo cáo thống kê'): string
    {
        $wb = IOFactory::load($filePath);

        // 1. Tính toán giá trị công thức để hiển thị kết quả (124, 61...) thay vì chuỗi =SUM(...)
        \PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance($wb)->setSuppressFormulaErrors(true);
        $writer = new HtmlWriter($wb);
        $writer->setPreCalculateFormulas(true);

        // 2. Chỉ lấy đúng Sheet chính (Active Sheet) chứa số liệu báo cáo
        $sheet = $wb->getActiveSheet();

        // 3. Điền giá trị 0 cho các công thức tham chiếu ô rỗng trong bảng kiểm tra (giống Microsoft Excel)
        $this->resolveEmptyFormulaReferences($sheet);

        // 4. Cắt bỏ các cột và dòng trống bị thừa ngoài bảng dữ liệu thực tế
        [$maxCol, $maxRow] = $this->trimUnusedBoundaries($sheet);

        // 5. Tự động gộp các ô text bị tràn sang các ô trống bên cạnh (như khối chữ ký, ngày tháng) để hiển thị nguyên vẹn 1 hàng như Excel
        $this->mergeUnborderedOverflowCells($sheet, $maxCol, $maxRow);

        // 6. Chuẩn hóa và làm đầy đủ 100% tất cả viền bảng (không bị đứt đoạn, mờ nhạt hay thiếu nét kẻ trong ô merge và subheader)
        $this->fixTableBorders($sheet, $maxCol, $maxRow);

        // 7. Xóa triệt để các ô ngoài biên giới hạn khỏi CellCollection để HtmlWriter không render dòng/cột thừa
        $this->cleanBoundaryCells($sheet, $maxCol, $maxRow);

        // 8. Hiển thị đường lưới bảng như Microsoft Excel (các ô bảng có viền đen đậm sắc nét, ô trống có lưới mờ)
        $sheet->setShowGridlines(true);

        $writer->setSheetIndex($wb->getActiveSheetIndex());
        $sheetHtml = $writer->generateSheetData();
        $styles = $writer->generateStyles(true);

        // 9. Định dạng kết quả kiểm tra (Đúng = Xanh đậm, Sai = Đỏ đậm) chuẩn như trong Microsoft Excel
        $sheetHtml = $this->highlightValidationResults($sheetHtml);

        return $this->buildFullHtmlPage($reportTitle, $styles, $sheetHtml);
    }

    /**
     * Xóa triệt để các ô và dimension ngoài biên giới hạn khỏi Worksheet để HtmlWriter không render dòng/cột thừa
     */
    private function cleanBoundaryCells($sheet, int $maxCol, int $maxRow): void
    {
        try {
            \Closure::bind(function ($maxCol, $maxRow) {
                // Xóa các cột thừa khỏi columnDimensions
                foreach ($this->columnDimensions as $colId => $dim) {
                    if (Coordinate::columnIndexFromString($dim->getColumnIndex()) > $maxCol) {
                        unset($this->columnDimensions[$colId]);
                    }
                }
                // Xóa các dòng thừa khỏi rowDimensions
                foreach ($this->rowDimensions as $rowId => $dim) {
                    if ($dim->getRowIndex() > $maxRow) {
                        unset($this->rowDimensions[$rowId]);
                    }
                }
                // Xóa các ô thừa khỏi cellCollection
                foreach ($this->cellCollection->getCoordinates() as $coord) {
                    [$col, $row] = Coordinate::coordinateFromString($coord);
                    if ((int)$row > $maxRow || Coordinate::columnIndexFromString($col) > $maxCol) {
                        $this->cellCollection->delete($coord);
                    }
                }
                // Cập nhật lại cachedHighestColumn và cachedHighestRow
                $this->garbageCollect();
            }, $sheet, $sheet)($maxCol, $maxRow);
        } catch (\Throwable $e) {
            try {
                $collection = $sheet->getCellCollection();
                $toDelete = [];
                foreach ($collection->getCoordinates() as $coord) {
                    [$col, $row] = Coordinate::coordinateFromString($coord);
                    if ((int)$row > $maxRow || Coordinate::columnIndexFromString($col) > $maxCol) {
                        $toDelete[] = $coord;
                    }
                }
                foreach ($toDelete as $coord) {
                    $collection->delete($coord);
                }
            } catch (\Throwable) {}
        }
    }

    /**
     * Xác định phạm vi bảng dữ liệu thực tế và loại bỏ các cột, dòng trống nằm ngoài phạm vi
     *
     * @return array{0: int, 1: int} [$maxCol, $maxRow]
     */
    private function trimUnusedBoundaries($sheet): array
    {
        try {
            $maxCol = 1;
            $maxRow = 1;
            $collection = $sheet->getCellCollection();

            // Quét các ô có dữ liệu thực sự (không tạo thêm ô mới)
            foreach ($collection->getCoordinates() as $coord) {
                $cell = $collection->get($coord);
                if ($cell) {
                    $val = $cell->getValue();
                    if ($val !== null && trim((string)$val) !== '') {
                        [$col, $row] = Coordinate::coordinateFromString($coord);
                        $cIdx = Coordinate::columnIndexFromString($col);
                        $rIdx = (int)$row;
                        if ($cIdx > $maxCol) $maxCol = $cIdx;
                        if ($rIdx > $maxRow) $maxRow = $rIdx;
                    }
                }
            }

            // Quét các ô merge có nội dung thực tế
            foreach ($sheet->getMergeCells() as $range) {
                if (str_contains($range, ':')) {
                    [$startRange, $endRange] = explode(':', $range);
                    [$startCol, $startRow] = Coordinate::coordinateFromString($startRange);
                    [$endCol, $endRow] = Coordinate::coordinateFromString($endRange);
                    if ($collection->has($startRange)) {
                        $startVal = $collection->get($startRange)->getValue();
                        if ($startVal !== null && trim((string)$startVal) !== '') {
                            $cIdx = Coordinate::columnIndexFromString($endCol);
                            $rIdx = (int)$endRow;
                            if ($cIdx > $maxCol) $maxCol = $cIdx;
                            if ($rIdx > $maxRow) $maxRow = $rIdx;
                        }
                    }
                }
            }

            // Xóa triệt để các ô nằm ngoài phạm vi bảng thật khỏi CellCollection
            $this->cleanBoundaryCells($sheet, $maxCol, $maxRow);

            // Bỏ các merge range hoàn toàn nằm ngoài phạm vi bảng thật
            foreach ($sheet->getMergeCells() as $range) {
                if (str_contains($range, ':')) {
                    [$start] = explode(':', $range);
                    [$startCol, $startRow] = Coordinate::coordinateFromString($start);
                    if ((int)$startRow > $maxRow || Coordinate::columnIndexFromString($startCol) > $maxCol) {
                        $sheet->unmergeCells($range);
                    }
                }
            }

            return [$maxCol, $maxRow];
        } catch (\Throwable $e) {
            return [
                Coordinate::columnIndexFromString($sheet->getHighestColumn()),
                $sheet->getHighestRow(),
            ];
        }
    }

    /**
     * Tự động gộp các ô text bị tràn sang các ô trống bên cạnh (như khối chữ ký, ngày tháng, tiêu đề phụ)
     * Giúp hiển thị trọn vẹn 1 hàng như trong Microsoft Excel mà không bị co cụm ngắt dòng
     */
    private function mergeUnborderedOverflowCells($sheet, int $maxCol, int $maxRow): void
    {
        try {
            $highestRow = min($maxRow, $sheet->getHighestRow());
            $highestColIndex = min($maxCol, Coordinate::columnIndexFromString($sheet->getHighestColumn()));
            $collection = $sheet->getCellCollection();

            // 1. Lập bản đồ các ô đã được merge trong template
            $isMerged = [];
            foreach ($sheet->getMergeCells() as $range) {
                if (str_contains($range, ':')) {
                    [$start, $end] = explode(':', $range);
                    [$startCol, $startRow] = Coordinate::coordinateFromString($start);
                    [$endCol, $endRow] = Coordinate::coordinateFromString($end);
                    $sC = Coordinate::columnIndexFromString($startCol);
                    $eC = Coordinate::columnIndexFromString($endCol);
                    $sR = (int)$startRow;
                    $eR = (int)$endRow;
                    if ($sC > $highestColIndex || $sR > $highestRow) continue;
                    $eC = min($eC, $highestColIndex);
                    $eR = min($eR, $highestRow);

                    for ($r = $sR; $r <= $eR; $r++) {
                        for ($c = $sC; $c <= $eC; $c++) {
                            $isMerged[$r][$c] = true;
                        }
                    }
                }
            }

            // 2. Quét các ô chứa text đứng trước các ô trống không có border
            for ($r = 1; $r <= $highestRow; $r++) {
                for ($c = 1; $c <= $highestColIndex; $c++) {
                    if (!empty($isMerged[$r][$c])) {
                        continue;
                    }

                    $colStr = Coordinate::stringFromColumnIndex($c);
                    $coord = $colStr . $r;
                    if (!$collection->has($coord)) {
                        continue;
                    }
                    $cell = $collection->get($coord);
                    $val = $cell->getValue();

                    // Chỉ xử lý ô có chuỗi văn bản (dài từ 3 ký tự, không phải số thuần túy)
                    if ($val !== null && trim((string)$val) !== '' && !is_numeric($val) && mb_strlen(trim((string)$val)) >= 3) {
                        // Không merge nếu ô này là một ô nằm trong bảng dữ liệu có viền bao quanh
                        $b = $cell->getStyle()->getBorders();
                        $cellHasBorder = ($b->getBottom()->getBorderStyle() !== 'none' && $b->getBottom()->getBorderStyle() !== '') ||
                                         ($b->getTop()->getBorderStyle() !== 'none' && $b->getTop()->getBorderStyle() !== '') ||
                                         ($b->getLeft()->getBorderStyle() !== 'none' && $b->getLeft()->getBorderStyle() !== '') ||
                                         ($b->getRight()->getBorderStyle() !== 'none' && $b->getRight()->getBorderStyle() !== '');

                        if ($cellHasBorder) {
                            continue;
                        }

                        // Tìm các ô trống liền kề bên phải (chỉ trong phạm vi bảng thật)
                        $endC = $c;
                        for ($nextC = $c + 1; $nextC <= $highestColIndex; $nextC++) {
                            if (!empty($isMerged[$r][$nextC])) {
                                break;
                            }

                            $nextCoord = Coordinate::stringFromColumnIndex($nextC) . $r;
                            if ($collection->has($nextCoord)) {
                                $nextCell = $collection->get($nextCoord);
                                $nextVal = $nextCell->getValue();

                                // Nếu ô kế có dữ liệu -> dừng
                                if ($nextVal !== null && trim((string)$nextVal) !== '') {
                                    break;
                                }

                                // Nếu ô kế có viền bảng -> dừng
                                $nextB = $nextCell->getStyle()->getBorders();
                                $nextHasBorder = ($nextB->getLeft()->getBorderStyle() !== 'none' && $nextB->getLeft()->getBorderStyle() !== '') ||
                                                 ($nextB->getRight()->getBorderStyle() !== 'none' && $nextB->getRight()->getBorderStyle() !== '') ||
                                                 ($nextB->getBottom()->getBorderStyle() !== 'none' && $nextB->getBottom()->getBorderStyle() !== '');
                                if ($nextHasBorder) {
                                    break;
                                }
                            }

                            $endC = $nextC;
                        }

                        // Nếu tìm thấy các ô trống phía sau, tiến hành merge
                        if ($endC > $c) {
                            $startCoord = $colStr . $r;
                            $endCoord = Coordinate::stringFromColumnIndex($endC) . $r;
                            $sheet->mergeCells("$startCoord:$endCoord");

                            // Đánh dấu đã merge để không quét lại
                            for ($mc = $c; $mc <= $endC; $mc++) {
                                $isMerged[$r][$mc] = true;
                            }
                            $c = $endC;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Không làm gián đoạn nếu gặp lỗi
        }
    }

    /**
     * Chuẩn hóa và làm đầy đủ 100% tất cả viền bảng (không bị đứt đoạn, mờ nhạt hay thiếu nét kẻ trong ô merge và subheader)
     *
     * Tối ưu hiệu năng: thay vì gọi $sheet->getStyle() hàng nghìn lần (rất chậm vì mỗi lần ghi
     * PhpSpreadsheet phải clone + tìm lại style XF), ta:
     *   1) Đọc viền của mọi ô 1 lần vào bản đồ trong bộ nhớ (cache theo xfIndex),
     *   2) Chạy toàn bộ thuật toán trên bản đồ đó,
     *   3) Ghi các cạnh thay đổi xuống sheet bằng đúng 1 lệnh applyFromArray cho mỗi ô.
     */
    private function fixTableBorders($sheet, int $maxCol, int $maxRow): void
    {
        try {
            $highestRow = min($maxRow, $sheet->getHighestRow());
            $highestColIndex = min($maxCol, Coordinate::columnIndexFromString($sheet->getHighestColumn()));
            $wb = $sheet->getParent();
            $collection = $sheet->getCellCollection();

            // ----- Bản đồ viền trong bộ nhớ -----
            $map = [];      // $map[$r][$c] = ['top'=>..., 'bottom'=>..., 'left'=>..., 'right'=>...]
            $dirty = [];    // $dirty[$r][$c][$side] = borderStyle cần ghi (màu đen 000000)
            $xfCache = [];  // xfIndex => 4 cạnh viền

            $read = function (int $r, int $c) use (&$map, &$xfCache, $collection, $wb): array {
                if (!isset($map[$r][$c])) {
                    $coord = Coordinate::stringFromColumnIndex($c) . $r;
                    $xf = $collection->has($coord) ? $collection->get($coord)->getXfIndex() : 0;
                    if (!isset($xfCache[$xf])) {
                        $b = $wb->getCellXfByIndex($xf)->getBorders();
                        $xfCache[$xf] = [
                            'top' => $b->getTop()->getBorderStyle(),
                            'bottom' => $b->getBottom()->getBorderStyle(),
                            'left' => $b->getLeft()->getBorderStyle(),
                            'right' => $b->getRight()->getBorderStyle(),
                        ];
                    }
                    $map[$r][$c] = $xfCache[$xf];
                }
                return $map[$r][$c];
            };
            $set = function (int $r, int $c, string $side, string $style) use (&$map, &$dirty, $read): void {
                $read($r, $c);
                $map[$r][$c][$side] = $style;
                $dirty[$r][$c][$side] = $style;
            };
            $has = static fn (?string $s): bool => $s !== null && $s !== '' && $s !== 'none';

            // 1. Lan truyền viền cho toàn bộ ô trong khối Merge (đặc biệt là ô tiêu đề bảng và ô tổng)
            foreach ($sheet->getMergeCells() as $range) {
                if (!str_contains($range, ':')) continue;
                [$start, $end] = explode(':', $range);
                [$startCol, $startRow] = Coordinate::coordinateFromString($start);
                [$endCol, $endRow] = Coordinate::coordinateFromString($end);
                $sR = (int)$startRow;
                $eR = (int)$endRow;
                $sC = Coordinate::columnIndexFromString($startCol);
                $eC = Coordinate::columnIndexFromString($endCol);

                if ($sC > $highestColIndex || $sR > $highestRow) continue;
                $eC = min($eC, $highestColIndex);
                $eR = min($eR, $highestRow);

                $master = $read($sR, $sC);

                for ($r = $sR; $r <= $eR; $r++) {
                    for ($c = $sC; $c <= $eC; $c++) {
                        if ($r === $sR && $has($master['top'])) $set($r, $c, 'top', $master['top']);
                        if ($r === $eR && $has($master['bottom'])) $set($r, $c, 'bottom', $master['bottom']);
                        if ($c === $sC && $has($master['left'])) $set($r, $c, 'left', $master['left']);
                        if ($c === $eC && $has($master['right'])) $set($r, $c, 'right', $master['right']);
                    }
                }
            }

            // 2. Đối xứng viền trong từng ô: nếu ô chỉ có 1 trong 2 cạnh đối diện (trên/dưới, trái/phải)
            //    thì bổ sung cạnh còn lại. Giữ nguyên chính xác kết quả hiển thị đã được duyệt trước đây
            //    (áp dụng cho dòng 2..cuối với cặp trên/dưới, cột 2..cuối với cặp trái/phải).
            for ($r = 1; $r <= $highestRow; $r++) {
                for ($c = 1; $c <= $highestColIndex; $c++) {
                    if ($r < $highestRow) {
                        $b = $read($r + 1, $c);
                        if ($has($b['bottom']) && !$has($b['top'])) {
                            $set($r + 1, $c, 'top', $b['bottom']);
                        } elseif ($has($b['top']) && !$has($b['bottom'])) {
                            $set($r + 1, $c, 'bottom', $b['top']);
                        }
                    }

                    if ($c < $highestColIndex) {
                        $b = $read($r, $c + 1);
                        if ($has($b['right']) && !$has($b['left'])) {
                            $set($r, $c + 1, 'left', $b['right']);
                        } elseif ($has($b['left']) && !$has($b['right'])) {
                            $set($r, $c + 1, 'right', $b['left']);
                        }
                    }
                }
            }

            // 3. Đường kẻ ngang bên trong bảng (Internal Table Borders - đặc biệt giữa dòng Năm sinh và Độ tuổi)
            //    Nếu ô phía dưới thuộc bảng (có viền trái/phải) thì kẻ đường ngang phân cách với ô phía trên.
            for ($r = 1; $r < $highestRow; $r++) {
                for ($c = 1; $c <= $highestColIndex; $c++) {
                    $b2 = $read($r + 1, $c);
                    if ($has($b2['left']) || $has($b2['right'])) {
                        $set($r, $c, 'bottom', Border::BORDER_THIN);
                        $set($r + 1, $c, 'top', Border::BORDER_THIN);
                    }
                }
            }

            // 4. Viền mép ngoài của các bảng dữ liệu (Table Outer Top & Bottom Borders)
            // Nếu một hàng là header bảng (có nhiều ô có viền bên), đảm bảo mép trên của bảng là nét đen liền mạch 100%
            for ($r = 1; $r <= $highestRow; $r++) {
                $tableCols = [];
                for ($c = 1; $c <= $highestColIndex; $c++) {
                    $b = $read($r, $c);
                    if ($has($b['left']) || $has($b['right']) || $has($b['bottom'])) {
                        $tableCols[] = $c;
                    }
                }

                // Nếu hàng này chứa ít nhất 3 cột bảng
                if (count($tableCols) < 3) continue;
                $minC = min($tableCols);
                $maxC = max($tableCols);

                // Kiểm tra xem hàng phía trên có phải là hàng trống ngoài bảng không
                $isTopBoundary = true;
                if ($r > 1) {
                    for ($tc = $minC; $tc <= $maxC; $tc++) {
                        if ($has($read($r - 1, $tc)['left'])) {
                            $isTopBoundary = false;
                            break;
                        }
                    }
                }

                if ($isTopBoundary) {
                    for ($tc = $minC; $tc <= $maxC; $tc++) {
                        $set($r, $tc, 'top', Border::BORDER_THIN);
                        if ($r > 1) {
                            $set($r - 1, $tc, 'bottom', Border::BORDER_THIN);
                        }
                    }
                }
            }

            // ----- Ghi xuống sheet -----
            // Mỗi tổ hợp (style gốc + các cạnh thay đổi) chỉ tạo style mới đúng 1 lần rồi tái sử dụng,
            // tránh việc PhpSpreadsheet phải clone + tính hash style cho từng lần ghi.
            $getter = ['top' => 'getTop', 'bottom' => 'getBottom', 'left' => 'getLeft', 'right' => 'getRight'];
            $newXfCache = [];
            foreach ($dirty as $r => $cols) {
                if ($r > $highestRow) continue;
                foreach ($cols as $c => $sides) {
                    if ($c > $highestColIndex) continue;
                    $cell = $sheet->getCell([$c, $r]);
                    $origXf = $cell->getXfIndex();
                    ksort($sides);
                    $cacheKey = $origXf . '|' . http_build_query($sides);

                    if (!isset($newXfCache[$cacheKey])) {
                        $style = clone $wb->getCellXfByIndex($origXf);
                        foreach ($sides as $side => $borderStyle) {
                            $style->getBorders()->{$getter[$side]}()->setBorderStyle($borderStyle)->getColor()->setRGB('000000');
                        }
                        $existing = $wb->getCellXfByHashCode($style->getHashCode());
                        if ($existing) {
                            $newXfCache[$cacheKey] = $existing->getIndex();
                        } else {
                            $wb->addCellXf($style);
                            $newXfCache[$cacheKey] = $style->getIndex();
                        }
                    }
                    $cell->setXfIndex($newXfCache[$cacheKey]);
                }
            }
        } catch (\Throwable $e) {
            // Không làm gián đoạn nếu gặp lỗi
        }
    }

    /**
     * Điền giá trị 0 cho các công thức tham chiếu ô rỗng trong bảng kiểm tra (giống Microsoft Excel)
     * Ví dụ: =+F41 khi F41 rỗng thì Excel hiển thị là 0, còn PhpSpreadsheet mặc định trả về null/rỗng
     */
    private function resolveEmptyFormulaReferences($sheet): void
    {
        try {
            $collection = $sheet->getCellCollection();
            foreach ($collection->getCoordinates() as $coord) {
                $cell = $collection->get($coord);
                if ($cell && $cell->isFormula()) {
                    $f = (string)$cell->getValue();
                    // Nếu là công thức tham chiếu đơn giản như =F41, =+F41...
                    if (preg_match('/^=\+?[A-Z]+[0-9]+$/', $f)) {
                        $calc = $cell->getCalculatedValue();
                        if ($calc === null || $calc === '') {
                            $cell->setValueExplicit(0, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        }
                    }
                }
            }
            \PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance($sheet->getParent())->clearCalculationCache();
        } catch (\Throwable $e) {
            // Không làm gián đoạn nếu gặp lỗi
        }
    }

    /**
     * Định dạng kết quả kiểm tra (Đúng = Xanh đậm, Sai = Đỏ đậm) chuẩn như trong Microsoft Excel
     */
    private function highlightValidationResults(string $sheetHtml): string
    {
        return preg_replace_callback('/<td([^>]*)>(\s*(?:Đúng|Sai)\s*)<\/td>/u', function ($m) {
            $attrs = $m[1];
            $val = trim($m[2]);
            $color = ($val === 'Sai') ? '#dc2626' : '#2563eb';
            if (str_contains($attrs, 'style="')) {
                $attrs = preg_replace('/style="([^"]*)"/', 'style="$1; color: ' . $color . ' !important; font-weight: bold !important;"', $attrs);
            } else {
                $attrs .= ' style="color: ' . $color . ' !important; font-weight: bold !important;"';
            }
            return "<td{$attrs}>{$val}</td>";
        }, $sheetHtml);
    }

    /**
     * Xây dựng trang HTML hoàn chỉnh cho Web Viewer chuẩn như Microsoft Excel (duy nhất 1 sheet chính)
     */
    private function buildFullHtmlPage(string $title, string $excelStyles, string $sheetHtml): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        /* Đường lưới mặc định mờ nhẹ chuẩn Excel cho canvas ô trống */
        .gridlines td, .gridlines th {
            border: 1px solid #d4d4d4;
        }
    </style>
    {$excelStyles}
    <style>
        /* Custom Modern Web Viewer Styles - Chuẩn 100% Microsoft Excel */
        :root {
            --viewer-bg: #e2e8f0;
            --sheet-bg: #ffffff;
            --excel-grid: #e2e8f0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            background-color: var(--viewer-bg);
            font-family: 'Times New Roman', 'Arial', Tahoma, sans-serif;
            color: #000000;
            min-width: fit-content;
            transition: transform 0.2s ease-in-out;
            transform-origin: top center;
        }

        .sheet-outer-container {
            display: flex;
            justify-content: safe center;
            width: 100%;
            min-width: fit-content;
        }

        .sheet-wrapper {
            display: inline-block;
            background-color: var(--sheet-bg);
            padding: 24px 32px;
            border-radius: 4px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12), 0 1px 3px rgba(0, 0, 0, 0.08);
            border: 1px solid #cbd5e1;
            margin: 0 auto;
        }

        /* Định dạng bảng mô phỏng chuẩn Microsoft Excel */
        table {
            border-collapse: collapse !important;
            margin: 0 auto !important;
            background-color: #ffffff;
            table-layout: fixed !important;
        }

        /* 
         * GIẢI QUYẾT TRIỆT ĐỂ: "Các chữ đang sát vs cột quá"
         * Cung cấp khoảng cách đệm (padding) chuẩn mực của Microsoft Excel (~6px)
         */
        table td, table th {
            empty-cells: show;
            padding-left: 6px !important;
            padding-right: 6px !important;
            padding-top: 3px !important;
            padding-bottom: 3px !important;
            box-sizing: border-box !important;
            white-space: nowrap !important; /* Không tự ý bẻ dòng làm vỡ chữ */
            overflow: visible !important;
        }



        /* Cho phép ngắt dòng nếu có thẻ <br> trong nội dung ô */
        table td br, table th br {
            display: inline;
        }

        /* Print styles */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
                transform: none !important;
            }

            .sheet-outer-container {
                display: block !important;
            }

            .sheet-wrapper {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
                border-radius: 0 !important;
                width: 100% !important;
            }

            @page {
                size: landscape;
                margin: 8mm 6mm;
            }
        }
    </style>
</head>
<body>
    <div class="sheet-outer-container">
        <div class="sheet-wrapper">
            {$sheetHtml}
        </div>
    </div>

    <script>
        // Nhận lệnh từ trang cha (zoom, print)
        window.addEventListener('message', function(event) {
            if (!event.data) return;
            if (event.data.type === 'PRINT_REPORT') {
                window.print();
            } else if (event.data.type === 'ZOOM_REPORT') {
                const zoomVal = event.data.value || 1;
                document.body.style.zoom = zoomVal;
            }
        });
    </script>
</body>
</html>
HTML;
    }
}
