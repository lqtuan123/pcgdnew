<?php
// app/Data/SurveyDetailFilters.php
namespace App\Data;

use Illuminate\Http\Request;

class SurveyDetailFilters
{
    public ?string $q = null;
    public ?string $so_phieu = null;
    public ?string $investigator = null;
    public ?string $block = null;
    public ?string $disability = null;
    public ?string $academic_year = null;   // <-- thêm

    public static function fromRequest(Request $r): self
    {
        $f = new self();
        $f->q            = trim((string)$r->get('q', '')) ?: null;
        $f->so_phieu     = trim((string)$r->get('so_phieu', '')) ?: null;
        $f->investigator = trim((string)$r->get('investigator', '')) ?: null;
        $f->block        = trim((string)$r->get('block', '')) ?: null;
        $f->disability   = trim((string)$r->get('disability', '')) ?: null;
        $f->academic_year= trim((string)$r->get('academic_year', '')) ?: null; // <-- thêm
        return $f;
    }
}
