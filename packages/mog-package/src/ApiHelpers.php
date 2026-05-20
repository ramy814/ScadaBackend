<?php

namespace Mog\ProjectHelper;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ApiHelpers
{
    public static function apiResponse($data = null, $message = null, $code = 200, $name = 'data', $status = true, $pagination = false): JsonResponse
    {

        $success_codes = [200,201,202,203,204];

        $array = [
            'status' => $status == true && in_array($code,$success_codes),
            'message' => $message,
            $name => $data,
        ];

        if ($pagination != null) {
            $paginationData = [
                'current_page' => $pagination->currentPage(),
                'total_pages' => $pagination->lastPage(),
                'per_page' => $pagination->perPage(),
                'total_items' => $pagination->total(),
                'count' => $pagination->count(),
                'first_item' => $pagination->firstItem(),
                'last_item' => $pagination->lastItem(),
                'has_more_pages' => $pagination->hasMorePages(),
                'is_first_page' => $pagination->onFirstPage(),
                'is_last_page' => $pagination->onLastPage(),
                'next_page_url' => $pagination->nextPageUrl(),
                'previous_page_url' => $pagination->previousPageUrl(),
            ];
            $array = array_merge($array, ['pagination' => $paginationData]);
        }

        return new JsonResponse($array, $code);
    }

    public static function apiGetPaging($data): array
    {
        return [
            'total'         => $data->total(),
            'per_page'      => $data->perPage(),
            'current_page'  => $data->currentPage(),
            'last_page'     => $data->lastPage(),
            'next_page_url' => $data->nextPageUrl(),
            'prev_page_url' => $data->previousPageUrl(),
        ];
    }

    public static function apiValidate($request, $validation_data): void
    {
        $validator = Validator::make($request->all(), $validation_data);
        if ($validator->fails()) {
            $res = self::apiResponse($validator->errors()->all(), 'validation errors', 200, 'errors', false);
            $res->send();
            exit;
        }else{
            $validator->valid();
        }
    }
}