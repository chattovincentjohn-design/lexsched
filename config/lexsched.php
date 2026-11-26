<?php

return [

    /*
    | Office details shown on pages, receipts and SMS messages.
    */
    'office_name' => env('OFFICE_NAME', 'Dela Cruz, Santos & Villanueva Law Offices'),
    'office_address' => env('OFFICE_ADDRESS', '2/F Rizal Building, Gen. Luna St., Dipolog City'),
    'office_phone' => env('OFFICE_PHONE', '(065) 212-3456'),

    /*
    | E-wallet accounts clients pay the fee to. Staff confirm the reference number before recording payment.
    */
    'ewallets' => [
        'gcash' => ['name' => 'GCash', 'account' => env('GCASH_NUMBER', '0917 555 0142'), 'holder' => env('GCASH_HOLDER', 'DCSV Law Offices')],
        'maya' => ['name' => 'Maya', 'account' => env('MAYA_NUMBER', '0918 555 0142'), 'holder' => env('MAYA_HOLDER', 'DCSV Law Offices')],
    ],

    /*
    | PayMongo online payments (GCash, Maya, cards). Leave the secret key empty to use the
    | manual e-wallet flow above, where clients type in their reference number.
    */
    'paymongo' => [
        'secret_key' => env('PAYMONGO_SECRET_KEY'),
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
        'methods' => ['gcash', 'paymaya', 'card'],
        'signature_tolerance_seconds' => 300,
    ],

    /*
    | Bookable start times (24-hour). 12:00 is left out for the lunch break.
    */
    'slots' => ['09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00'],

    /*
    | Non-working days when the office is closed (regular and special non-working holidays).
    */
    'holidays' => [
        '2026-11-30', '2026-12-08', '2026-12-24', '2026-12-25', '2026-12-30', '2026-12-31',
        '2027-01-01', '2027-02-25', '2027-03-25', '2027-03-26', '2027-04-09', '2027-05-01',
    ],

    /*
    | How far ahead clients may book, in days.
    */
    'booking_window_days' => 60,

    /*
    | Fee for each extra copy of a notarized document (the first copy is included).
    */
    'extra_copy_fee' => 50,

    /*
    | Notarial register layout: entries per page and entries per book.
    */
    'register_entries_per_page' => 5,
    'register_entries_per_book' => 100,

    'id_types' => [
        'PhilSys National ID', "Driver's License", 'Passport', 'UMID', 'PRC ID',
        'Postal ID', "Voter's ID", 'Senior Citizen ID', 'PWD ID',
    ],

    /*
    | Uploaded documents (contracts, IDs, subpoenas) are stored on this private disk.
    */
    'attachments_disk' => 'local',
    'max_upload_kb' => 5120,

    'sms' => [
        'semaphore_key' => env('SEMAPHORE_API_KEY'),
        'sender_name' => env('SEMAPHORE_SENDER_NAME', 'LEXSCHED'),
    ],
];
