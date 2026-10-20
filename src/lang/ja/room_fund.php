<?php

return [
    'nav' => '基金台帳',
    'title' => 'ルーム基金台帳',
    'description' => '共有基金の入出金：入金、支出、基金で支払った債務。取引は追記のみで、編集・削除はできません。誤りは調整取引で修正してください。',
    'export' => 'Excel出力',
    'balance' => '現在の残高',
    'month_in' => '今月の入金',
    'month_out' => '今月の出金',

    'form_title' => '基金取引を記録',
    'field_type' => '取引の種類',
    'field_amount' => '金額（VND）',
    'field_member' => '入金したメンバー',
    'field_note' => '内容',
    'member_none' => '— メンバーなし —',
    'note_placeholder' => '例：10月の誕生日ケーキ',
    'adjustment_hint' => '基金を減らす場合は負の数、増やす場合は正の数を入力してください。',
    'submit' => '台帳に記録',

    'filter_label' => '取引の種類で絞り込み',
    'filter_all' => 'すべて',
    'empty' => '基金の取引はまだありません。',

    'col_time' => '日時',
    'col_type' => '種類',
    'col_amount' => '金額',
    'col_balance' => '取引後残高',
    'col_member' => 'メンバー',
    'col_campaign' => 'キャンペーン',
    'col_note' => '内容',
    'col_admin' => '記録者',

    'types' => [
        'deposit' => '入金',
        'expense' => '支出',
        'debt_payment' => '基金による債務支払い',
        'adjustment' => '調整',
    ],

    'saved' => '取引を記録しました。基金残高：:balance。',
    'insufficient' => 'ルーム基金の残高が不足しています（残高：:balance）。',
    'invalid_amount' => '金額は0以外にしてください。',
    'invalid_type' => '取引の種類が不正です。',
];
