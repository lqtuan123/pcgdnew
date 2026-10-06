<?php
namespace App\Services;
use Carbon\Carbon;
 
 

use Illuminate\Validation\ValidationException;
class ReportFormatService
{
    public function end_date(?string $wardname = null): string
    {
        $show_date = 1;
        if($show_date == 1)
        {
            $d = Carbon::now('Asia/Ho_Chi_Minh');
            $prefix = $wardname && trim($wardname) !== '' ? trim($wardname).', ' : '';
            return $prefix.'ngày '.$d->format('d').' tháng '.$d->format('m').' năm '.$d->format('Y');
        }
        else
        {
             
            $prefix = $wardname && trim($wardname) !== '' ? trim($wardname).', ' : '';
            return $prefix.'ngày ... tháng ...năm ....';
        }
      
    }
    public function top_date( ): string
    {
        $show_date = 1;
        if($show_date == 1)
        {
            $d = Carbon::now('Asia/Ho_Chi_Minh');
           
            return  'Ngày '.$d->format('d').' tháng '.$d->format('m').' năm '.$d->format('Y');
        }
        else
        {
            return 'Ngày ... tháng ...năm ....';
        }
      
    }
    public function cong_thuc_baocao($group_id, $detail_id)
    {
        if($group_id = 5 && $detail_id == 37)
        {
            return 1;
        }
    }
    public function hoc_xmc_rule(): int
    {
        // 1: đang học hoặc đã hoàn thành  (DEFAULT)
        // 2: chỉ đang học
        // 3: chỉ đã hoàn thành
        // return (int)($this->getConfig('xmc.hoc_vien_rule', 1));
        return 1;
    }

    public function cong_nhan_bc_rule(): int
    {
        // 1: hoàn thành lớp 3 hoặc tái mù mức 2  (DEFAULT)
        // 2: chỉ hoàn thành lớp 3
        // return (int)($this->getConfig('xmc.cong_nhan_bc_rule', 1));
      
        return 1;
    }
    public function cong_nhan_bc_muc2_rule(): int
    {
        return 1;
    }
    

}