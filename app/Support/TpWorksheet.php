<?php
namespace App\Support\Excel;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use App\Helpers\NumberFormatHelper;
class TpWorksheet extends Worksheet
{
    // Tùy chọn mặc định của "hàm bạn"
    public int $decimals = 0;
    public bool $asText  = true;   // true nếu format() trả chuỗi có dấu phẩy
    public bool $center  = true;

    public function setCellValue($coordinate = 'A1', $value = null, $returnCell = false)
    {
        // đây là "hàm của bạn" — ví dụ NumberFormatHelper::format
        if (is_numeric($value)) {
            $value = \App\Helpers\NumberFormatHelper::format($value, $this->decimals);
        }

        if ($this->asText) {
            $ret = parent::setCellValueExplicit($coordinate, (string)$value, DataType::TYPE_STRING, $returnCell);
        } else {
            // ghi numeric + để NumberFormat Excel nếu muốn (tùy bạn thêm)
            $ret = parent::setCellValueExplicit($coordinate, (float)$value, DataType::TYPE_NUMERIC, $returnCell);
        }

        if ($this->center) {
            $this->getStyle($coordinate)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        return $ret;
    }
}
