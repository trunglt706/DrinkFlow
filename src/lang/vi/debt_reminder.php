<?php

return [
    'setting_label' => 'Tự động nhắc nợ theo bậc',
    'setting_hint' => 'Gửi thông báo kèm mã VietQR cho thành viên sau 1, 3 và 7 ngày kể từ khi phát sinh khoản nợ (từ 9:00 theo múi giờ phòng).',

    'stage_1_title' => 'Bạn có khoản cần thanh toán',
    'stage_1_body' => 'Đợt :campaign còn :amount chưa thanh toán. Bấm để mở mã VietQR và chuyển khoản nhanh.',
    'stage_3_title' => 'Nhắc lại: khoản nợ đã :days ngày',
    'stage_3_body' => 'Khoản :amount của đợt :campaign đã :days ngày chưa thanh toán. Bấm để mở mã VietQR.',
    'stage_7_title' => 'Khoản nợ đã quá :days ngày',
    'stage_7_body' => 'Khoản :amount của đợt :campaign đã quá :days ngày. Vui lòng thanh toán sớm qua mã VietQR.',

    'snooze_button' => 'Tạm hoãn :days ngày',
    'snooze_hint' => 'Chưa tiện thanh toán? Tạm hoãn nhắc nợ cho khoản này.',
    'snooze_none_left' => 'Bạn đã dùng hết lượt tạm hoãn nhắc nợ cho khoản này.',
    'snoozed_until' => 'Đã tạm hoãn nhắc nợ đến :date.',
    'snoozed' => 'Đã tạm hoãn nhắc nợ đến :date.',
    'snooze_not_allowed' => 'Không thể tạm hoãn nhắc nợ cho khoản này.',
    'snooze_limit_reached' => 'Mỗi khoản nợ chỉ được tạm hoãn nhắc nợ tối đa :max lần.',
];
