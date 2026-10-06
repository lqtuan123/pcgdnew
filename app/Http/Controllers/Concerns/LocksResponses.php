<?php
// app/Http/Controllers/Concerns/LocksResponses.php
namespace App\Http\Controllers\Concerns;

trait LocksResponses
{
    protected function lockedResponse()
    {
        return response()->json([
            'success' => false,
            'message' => 'Thao tác đang bị khoá cho địa bàn này.'
        ], 423);
    }
}
