<?php

namespace App\Enums;

enum ScanMethod: string
{
    case Rfid = 'rfid';
    case DynamicQr = 'dynamic_qr';
    case ManualOverride = 'manual_override';
}
