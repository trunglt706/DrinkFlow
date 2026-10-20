<?php

return [
    'setting_label' => '段階的な自動支払いリマインダー',
    'setting_hint' => '債務の発生から1日・3日・7日後に、VietQRコード付きの通知をメンバーへ送信します（ルームのタイムゾーンで9:00以降）。',

    'stage_1_title' => 'お支払いが必要です',
    'stage_1_body' => ':campaign の :amount が未払いです。タップしてVietQRコードを開き、すぐに振り込めます。',
    'stage_3_title' => '再通知：未払いから:days日が経過',
    'stage_3_body' => ':campaign の :amount が:days日間未払いです。タップしてVietQRコードを開いてください。',
    'stage_7_title' => '未払いから:days日以上が経過',
    'stage_7_body' => ':campaign の :amount が:days日以上未払いです。VietQRコードでお早めにお支払いください。',

    'snooze_button' => ':days日間延期',
    'snooze_hint' => '今すぐ支払えませんか？この債務のリマインダーを延期できます。',
    'snooze_none_left' => 'この債務のリマインダー延期回数を使い切りました。',
    'snoozed_until' => ':date までリマインダーを延期しました。',
    'snoozed' => ':date までリマインダーを延期しました。',
    'snooze_not_allowed' => 'この債務のリマインダーは延期できません。',
    'snooze_limit_reached' => 'リマインダーの延期は1件の債務につき最大:max回までです。',
];
