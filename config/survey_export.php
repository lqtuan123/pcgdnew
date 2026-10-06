<?php

return [
    // Nếu bạn đã chuyển mẫu sang .xlsx thì đổi đuôi cho ổn định hơn
    'template' => resource_path('excel/phieudieutra_1_mat.xls'),

    // Header (ô lẻ) – bạn điều chỉnh toạ độ cho khớp với mẫu thật
    'cells' => [
       
        'academic_year'   => 'H5',
        'household_code'  => 'C6',
        'household_owner' => 'H6',
       
         'household_owner'        => 'I3',      // ô đầu (top-left) của vùng hợp nhất
    'household_owner_range'  => 'I3:Z3',   // cả vùng hợp nhất để set style
    'new_ward'=>'A1',
    'new_ward_range'  => 'A1:H1', 
    'village'         => 'A2',
    'addressline'         => 'A3',
    'so_phieu'        => 'AA1',
    'dien_cu_tru' => 'AA2',
    'dienthoai' => 'AA3',

        // 'old_province'  => '...', 'old_district' => '...', 'old_ward' => '...',
        // 'new_province'  => '...', 'new_ward'     => '...',
        // 'investigators' => '...', 'surveyed_at'  => '...',
    ],
    'blocks' => [
        'top_row'        => 9,   // block 1 bắt đầu ở hàng 10
        'rows_per_block' => 5,    // 1 block = 5 hàng
        'max_blocks'     => 6,    // template có sẵn 6 block/trang

        // ===== Bên trái (5 hàng) – theo ảnh bạn đưa
        'fields' => [
            // col + offset row tính từ top_row của block
             'stt'              => ['col' => 'A',  'row' => 0],
            'rel_to_head'  => ['col' => 'B', 'row' => 0, 'pre_text' => 'QH với chủ hộ: '],
            'ten'          => ['col' => 'B', 'row' => 1, 'pre_text' => ''],
            'dob'          => ['col' => 'D', 'row' => 2, 'pre_text' => ''],
            'dob_label'              => ['col' => 'B','row' => 2,'pre_text' => 'Ngày sinh: '],
            'female_label'     => ['col' => 'B','row' => 3,'pre_text' => 'Nữ:'],   // 👈 thêm dòng này
            'female'       => ['col' => 'C', 'row' => 3, 'pre_text' => ''],          // ☑/☐
            'ethnicity'    => ['col' => 'D', 'row' => 3, 'pre_text' => 'DT: '],
            'religion'     => ['col' => 'F', 'row' => 3, 'pre_text' => 'TG: '],
            'contact_name' => ['col' => 'B', 'row' => 4, 'pre_text' => 'Cha, mẹ, Người đỡ đầu: '],
        ],

        // In nhãn NĂM HỌC ở cột I trên cả 5 hàng của block
        'year_label' => [
            'col'  => 'G',
            'rows' => [0,1,2,3,4], // offset hàng trong block để in nhãn năm học
        ],

        // ===== Cột ngang bên phải (ghi ở HÀNG ĐẦU của block)
        'columns' => [
           
            'lop_dang_hoc'     => ['col' => 'I',  'row' => 0],
            'truong_huyen'     => ['col' => 'L',  'row' => 0],

            'graduation_level_name'  => ['col' => 'Q',  'row' => 0],
            'is_bo_tuc'      => ['col' => 'R',  'row' => 0],
            'graduation_year'      => ['col' => 'S',  'row' => 0],
            'vocational_level_name'   => ['col' => 'T',  'row' => 0],
            'vocational_grad_year'  =>['col' => 'U',  'row' => 0],
            'hoc_xong_lop'     => ['col' => 'W',  'row' => 0],
            'hoc_xong_nam'     => ['col' => 'X',  'row' => 0],
            'bo_hoc_lop'       => ['col' => 'Y',  'row' => 0],
            'bo_hoc_nam'       => ['col' => 'Z',  'row' => 0],

            'lit_dang_hoc_lop'             => ['col' => 'AA',  'row' => 0],
            'lit_hoan_thanh_lop'             => ['col' => 'AB',  'row' => 0],
            'lit_tai_mu_muc'             => ['col' => 'AC', 'row' => 0],
            'khuyet_tat'             => ['col' => 'AD', 'row' => 0],
            'sc_name'        => ['col' => 'AE', 'row' => 0],
            'note'          => ['col' => 'AF', 'row' => 0],
        ],
    ],
];
