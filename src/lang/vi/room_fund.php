<?php

return [
    'nav' => 'Sổ quỹ',
    'title' => 'Sổ quỹ phòng',
    'description' => 'Thu chi của quỹ chung: nạp quỹ, chi quỹ và các khoản nợ thanh toán bằng quỹ. Mỗi giao dịch được ghi nối tiếp, không sửa hay xoá; khi nhập sai, hãy thêm một giao dịch điều chỉnh.',
    'export' => 'Xuất Excel',
    'balance' => 'Số dư hiện tại',
    'month_in' => 'Thu trong tháng',
    'month_out' => 'Chi trong tháng',

    'form_title' => 'Ghi giao dịch quỹ',
    'field_type' => 'Loại giao dịch',
    'field_amount' => 'Số tiền (VNĐ)',
    'field_member' => 'Thành viên nạp',
    'field_note' => 'Nội dung',
    'member_none' => '— Không gắn thành viên —',
    'note_placeholder' => 'Ví dụ: Mua bánh sinh nhật tháng 10',
    'adjustment_hint' => 'Nhập số âm để giảm quỹ, số dương để tăng quỹ.',
    'submit' => 'Ghi vào sổ quỹ',

    'filter_label' => 'Lọc theo loại giao dịch',
    'filter_all' => 'Tất cả',
    'empty' => 'Chưa có giao dịch quỹ nào.',

    'col_time' => 'Thời gian',
    'col_type' => 'Loại',
    'col_amount' => 'Số tiền',
    'col_balance' => 'Số dư sau',
    'col_member' => 'Thành viên',
    'col_campaign' => 'Chiến dịch',
    'col_note' => 'Nội dung',
    'col_admin' => 'Người ghi',

    'types' => [
        'deposit' => 'Nạp quỹ',
        'expense' => 'Chi quỹ',
        'debt_payment' => 'Trả nợ bằng quỹ',
        'adjustment' => 'Điều chỉnh',
    ],

    'saved' => 'Đã ghi giao dịch. Số dư quỹ: :balance.',
    'insufficient' => 'Quỹ phòng không đủ số dư (hiện còn :balance).',
    'invalid_amount' => 'Số tiền phải khác 0.',
    'invalid_type' => 'Loại giao dịch không hợp lệ.',

    'new_transaction' => 'Tạo mới giao dịch',
    'required_hint' => 'Trường bắt buộc',
    'amount_placeholder' => 'Ví dụ: 500.000',
    'close' => 'Đóng',
    'cancel' => 'Hủy',
    'load_error' => 'Không tải được danh sách giao dịch, vui lòng thử lại.',
    'empty_description' => 'Bấm "Tạo mới giao dịch" để ghi khoản nạp quỹ đầu tiên.',
    'empty_filtered' => 'Không có giao dịch nào thuộc loại này.',
    'validation' => [
        'amount_required' => 'Vui lòng nhập số tiền.',
        'amount_positive' => 'Số tiền phải lớn hơn 0.',
        'amount_non_zero' => 'Số tiền điều chỉnh phải khác 0.',
        'amount_max' => 'Số tiền tối đa cho một giao dịch là :max.',
        'note_required' => 'Vui lòng nhập nội dung cho giao dịch này.',
    ],
];
