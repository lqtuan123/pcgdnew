<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonMamnon extends Model
{
    protected $table = 'person_mamnon';

    protected $fillable = [
        'person_id',
        'hoc_2_buoi',
        'hoc_ban_tru',
        'hoc_du_ngay_theo_quy_dinh',
        'chuyen_can',
        'theo_doi_bd_can_nang',
        'sdd_nhe_can',
        'theo_doi_bd_chieu_cao',
        'sdd_thap_coi',
        'dan_toc_chuan_bi_tv',
        'hoan_thanh_chuong_trinh_gdmn',
        'trai_tuyen',
    ];

    protected $casts = [
        'hoc_ban_tru' => 'boolean',
        'hoc_du_ngay_theo_quy_dinh' => 'boolean',
        'chuyen_can' => 'boolean',
        'theo_doi_bd_can_nang' => 'boolean',
        'sdd_nhe_can' => 'boolean',
        'theo_doi_bd_chieu_cao' => 'boolean',
        'sdd_thap_coi' => 'boolean',
        'dan_toc_chuan_bi_tv' => 'boolean',
        'hoan_thanh_chuong_trinh_gdmn' => 'boolean',
        'hoc_2_buoi' => 'boolean',
        'trai_tuyen' => 'boolean',
    ];

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function surveyFormDetails()
    {
        return $this->hasMany(SurveyFormDetail::class, 'person_mamnon_id');
    }
}
