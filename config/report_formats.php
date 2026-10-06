<?php

return [
    // Quy tắc ghi dòng ngày tháng năm
    'date_line' => [
        // mode: numeric | dots | off
        'mode' => 'numeric',
        // Mẫu khi numeric
        'template_numeric' => '{ward}, ngày {d} tháng {m} năm {y}',
        // Mẫu khi dots (chừa chấm)
        'template_dots'    => '{ward}, ngày ..... tháng ..... năm ....',
        // Cột bắt đầu merge cho dòng ngày (khác nhau old/new)
        'cell_from' => [
            'old' => 'M',
            'new' => 'L',
        ],
        // canh giữa / trái
        'align' => 'center',
    ],

    // Quy tắc hiển thị số mặc định
    'numbers' => [
        'decimals'     => 0,
        'decimal_sep'  => ',',
        'thousand_sep' => '.',
    ],
];
