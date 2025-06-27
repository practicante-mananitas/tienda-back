<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class SepomexController extends Controller
{
    //
    public function porEstado($id)
    {
        $result = DB::table('sepomex')
            ->where('idEstado', $id)
            ->select('municipio', 'cp', 'asentamiento', 'ciudad')
            ->get();

        return response()->json($result);
    }

}
