<?php

return [
    'setting_label' => 'Tiered automatic debt reminders',
    'setting_hint' => 'Notify members with a VietQR code 1, 3 and 7 days after a debt is created (from 9:00 in the room timezone).',

    'stage_1_title' => 'You have a payment due',
    'stage_1_body' => ':amount is still unpaid for :campaign. Tap to open the VietQR code and pay in seconds.',
    'stage_3_title' => 'Reminder: debt unpaid for :days days',
    'stage_3_body' => ':amount for :campaign has been unpaid for :days days. Tap to open the VietQR code.',
    'stage_7_title' => 'Debt overdue by more than :days days',
    'stage_7_body' => ':amount for :campaign has been unpaid for over :days days. Please pay soon with the VietQR code.',

    'snooze_button' => 'Snooze :days days',
    'snooze_hint' => 'Can\'t pay right now? Snooze the reminders for this debt.',
    'snooze_none_left' => 'You have used all reminder snoozes for this debt.',
    'snoozed_until' => 'Reminders snoozed until :date.',
    'snoozed' => 'Reminders snoozed until :date.',
    'snooze_not_allowed' => 'Reminders for this debt cannot be snoozed.',
    'snooze_limit_reached' => 'Reminders can be snoozed at most :max times per debt.',
];
