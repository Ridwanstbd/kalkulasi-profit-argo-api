<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $service = Service::with(['costs'])->get();
        if($service->isEmpty()){
            return response()->json([
                'success' => false,
                'message' => 'Layanan tidak ditemukan.'
            ]);
        }
        $stats = [
            'total_services' => $service->count(),
            'avg_selling_price' => $service->avg('selling_price'),
            'avg_hpp' => $service->avg('hpp'),
            'total_selling_value' => $service->sum('selling_price'),
            'total_hpp_value' => $service->sum('hpp'),
            'profit_margin' => $service->sum('selling_price') - $service->sum('hpp')
        ];
        return response()->json([
            'success' => true,
            'message' => 'Layanan ditemukan',
            'stats' => $stats,
            'data' => $service
        ],200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'name' => 'required|string|max:100',
            'sku' => 'required|string|max:50',
            'description' => 'nullable|string',
            'hpp' => 'nullable|numeric',
            'selling_price' => 'nullable|numeric',
        ]);

        if($validator->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        try {
            $service = Service::create($request->all());
            
            return response()->json([
                'success' => true,
                'message' => 'Layanan berhasil dibuat',
                'data' => $service
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat layanan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $service = Service::where('id',$id)
                ->with(['costs'])
                ->first();
            if(!$service){
                return response()->json([
                    'success' => false,
                    'message' => 'layanan tidak ditemukan'
                ],404);
            }
            $hppBreakdown = $service->hpp_breakdown;

            return response()->json([
                'success' => true,
                'message' => 'Layanan ditemukan',
                'data' => $service,
                'hpp_breakdown' => $hppBreakdown
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data layanan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(),[
            'name' => 'string|max:100',
            'sku' => 'string|max:50',
            'description' => 'nullable|string',
            'hpp' => 'numeric',
            'selling_price' => 'numeric',
        ]);

        if($validator->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        try {
            $service = Service::where('id', $id)->first();
            
            if(!$service){
                return response()->json([
                    'success' => false,
                    'message' => 'Layanan tidak ditemukan'
                ], 404);
            }
            
            $service->update($request->all());
            
            return response()->json([
                'success' => true,
                'message' => 'Layanan berhasil diperbarui',
                'data' => $service
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui layanan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $service = Service::findOrFail($id);
            $service->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Layanan berhasil dihapus'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Layanan tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus layanan',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
