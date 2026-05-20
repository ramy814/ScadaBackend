<?php

namespace Mog\ProjectHelper;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mog\ProjectHelper\Enums\AttachmentType;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AttachmentsHelpers
{
    public static function get_full_name($base_dir, $name): string
    {
        return $base_dir . '/' . Carbon::now()->year . '/' . Carbon::now()->month . '/' . $name;
    }

    public static function upload_file($full_path, $content, $disk_name = null): bool
    {
        $disk = $disk_name ?? Config::get('mog-helpers.main-disk');
        return Storage::disk($disk)->put($full_path, $content);
    }

    public static function open_attachment($full_path, $main_id, $attach_id, AttachmentType $att_type, $disk_name = null)
    {
        $disk = $disk_name ?? Config::get('mog-helpers.main-disk');

        if (Storage::disk($disk)->exists($full_path)) {
            DB::table('misc_audit.view_attachments_log')->insert([
                'mog_app_name' => Config::get('mog_app_name'),
                'main_id' => $main_id,
                'attach_id' => $attach_id,
                'path' => $full_path,
                'att_type' => $att_type->value,
                'host_name' => Request::ip(),
                'open_date' => Carbon::now(),
                'user_id' => Auth::guard(Config::get('sso_default_guard'))->id(),
            ]);

            return Storage::disk($disk)->download($full_path, basename($full_path), [
                'Content-Type' => mime_content_type($full_path),
                'Content-Disposition' => 'inline; filename="' . basename($full_path) . '"',
            ]);
        }

        throw new NotFoundHttpException('File Not Found');
    }

}