<?php

namespace App\Enums;

enum SyncStatus: string
{
    case Pending = 'pending';
    case Uploading = 'uploading';
    case Uploaded = 'uploaded';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
}

