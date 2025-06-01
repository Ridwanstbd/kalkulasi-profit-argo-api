<?php

namespace App\Http\Controllers\Feature;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ServiceCostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {        
        $userServices = Service::all();
        
        if($userServices->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Layanan yang terhubung masih kosong'
            ]);
        }
        
        $serviceId = $request->input('service_id', $userServices->first()->id);
        
        $serviceCosts = ServiceCost::where('service_id', $serviceId)
            ->with(['service', 'costComponent'])
            ->get();
        
        $service = $userServices->firstWhere('id', $serviceId);
        
        if($serviceCosts->isEmpty()){
            return response()->json([
                'success' => false,
                'message' => 'Komponen biaya untuk Layanan ini masih kosong',
                'service' => $service,
                'all_services' => $userServices
            ]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Komponen biaya Layanan berhasil ditampilkan!',
            'service' => $service,
            'all_services' => $userServices,
            'data' => $serviceCosts
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|exists:services,id',
            'costs' => 'required|array',
            'costs.*.cost_component_id' => 'required|exists:cost_components,id',
            'costs.*.unit' => 'required|string',
            'costs.*.unit_price' => 'required|numeric|min:0',
            'costs.*.quantity' => 'required|numeric|min:0',
            'costs.*.conversion_qty' => 'required|numeric|min:0',
        ]);
        
        if($validator->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ],422);
        }
        
        $serviceId = $request->service_id;
        
        $service = Service::find($serviceId);
            
        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Layanan tidak ditemukan'
            ], 404);
        }
        
        $costComponentIds = collect($request->costs)->pluck('cost_component_id')->toArray();
        $duplicates = array_count_values($costComponentIds);
        $duplicateIds = [];
        
        foreach ($duplicates as $id => $count) {
            if ($count > 1) {
                $duplicateIds[] = $id;
            }
        }
        
        if (!empty($duplicateIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Terdapat komponen biaya yang diinput lebih dari satu kali',
                'duplicate_components' => $duplicateIds
            ], 422);
        }

        $existingComponents = ServiceCost::where('service_id', $serviceId)
            ->whereIn('cost_component_id', $costComponentIds)
            ->pluck('cost_component_id')
            ->toArray();
            
        if (!empty($existingComponents)) {
            return response()->json([
                'success' => false,
                'message' => 'Komponen biaya sudah ada dalam Layanan ini',
                'existing_components' => $existingComponents
            ], 422);
        }
        
        try {
            DB::beginTransaction();

            foreach ($request->costs as $cost){
                $conversionQty = isset($cost['conversion_qty']) ? (float)$cost['conversion_qty'] : 1;
                $unitPrice = (float)$cost['unit_price'];
                $quantity = (float)$cost['quantity'];
                
                if ($conversionQty > 0) {
                    $amount = ($unitPrice / $conversionQty) * $quantity;
                } else {
                    $amount = $unitPrice * $quantity;
                }

                ServiceCost::create([
                    'service_id' => $serviceId,
                    'cost_component_id' => $cost['cost_component_id'],
                    'unit' => $cost['unit'],
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'conversion_qty' => $conversionQty,
                    'amount' => $amount,
                ]);
            }

            $hpp = $this->calculateHpp($serviceId);
            $service->hpp = $hpp;
            $service->save();
            DB::commit();

            $service->refresh();
            
            return response()->json([
                'success' => true,
                'message' => 'HPP berhasil disimpan',
                'data' => $service
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi Kesalahan: '. $e->getMessage()
            ],500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $serviceCost = ServiceCost::where('id', $id)
            ->with('service')    
            ->with('costComponent')
            ->first();
        
        if (!$serviceCost) {
            $jsonResponse = json_encode([
                'success' => true,
                'message' => 'Layanan belum memiliki komponen biaya',
                'data' => new \stdClass()
            ]);
            
            return response($jsonResponse, 200)
                ->header('Content-Type', 'application/json');
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Detail komponen biaya produk',
            'data' => $serviceCost
        ], 200);
    }

    /**
     * Update a single service cost record.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'cost_component_id' => 'required|exists:cost_components,id',
            'unit' => 'required|string',
            'unit_price' => 'required|numeric|min:0',
            'quantity' => 'required|numeric|min:0',
            'conversion_qty' => 'required|numeric|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $serviceCost = ServiceCost::findOrFail($id);
        
        $service = Service::find($serviceCost->service_id);
            
        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Layanan tidak ditemukan'
            ], 404);
        }
        
        if ($request->cost_component_id != $serviceCost->cost_component_id) {
            $exists = ServiceCost::where('service_id', $serviceCost->service_id)
                ->where('cost_component_id', $request->cost_component_id)
                ->where('id', '!=', $id)
                ->exists();
                
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Komponen biaya ini sudah ada dalam produk'
                ], 422);
            }
        }
        
        try {
            DB::beginTransaction();
            
            // FIX: Perbaiki logika perhitungan amount
            $conversionQty = (float)$request->conversion_qty;
            $unitPrice = (float)$request->unit_price;
            $quantity = (float)$request->quantity;
            
            if ($conversionQty > 0) {
                $amount = ($unitPrice / $conversionQty) * $quantity;
            } else {
                $amount = $unitPrice * $quantity;
            }
            
            $serviceCost->update([
                'cost_component_id' => $request->cost_component_id,
                'unit' => $request->unit,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'conversion_qty' => $conversionQty,
                'amount' => $amount,
            ]);
            
            $hpp = $this->calculateHpp($service->id);
            $service->hpp = $hpp;
            $service->save();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Komponen biaya berhasil diperbarui',
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified service cost.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id)
    {
        $serviceCost = ServiceCost::findOrFail($id);
        
        $service = Service::find($serviceCost->service_id); 
            
        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Layanan tidak ditemukan'
            ], 404);
        }
        
        try {
            DB::beginTransaction();
            
            $serviceCost->delete();
            
            $hpp = $this->calculateHpp($service->id);
            $service->hpp = $hpp;
            $service->save();
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Komponen biaya berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    private function calculateHpp($serviceId)
    {
        $totalCost = ServiceCost::where('service_id', $serviceId)->sum('amount');
        
        return $totalCost;
    }
}