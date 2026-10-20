<?php
namespace App\Http\Controllers\Rent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rent\CarRentalPriceRequest;
use App\Models\Rent\{CarRentalPrice,Image};
use Illuminate\Http\{Request,JsonResponse};
use Illuminate\Support\Facades\{DB,Storage};
class CarRentalPriceController extends Controller {
 private function expanded(){return CarRentalPrice::query()->with(['brand:id,name','model:id,name','company:id,name','periods.intervals','images']);}
 private function format(CarRentalPrice $price):array {
  $d=$price->toArray();
  $d['main_image']=$price->images->firstWhere('type','main');
  $d['gallery_images']=$price->images->where('type','gallery')->values();
  return $d;
 }
 public function index(Request $r):JsonResponse {
  $r->validate(['search'=>'nullable|string|max:120','per_page'=>'nullable|integer|between:1,100','page'=>'nullable|integer|min:1','client_type'=>'nullable|in:customer,company']);
  $type=$r->query('client_type','customer');
  $q=$this->expanded()->where('client_type',$type)
    // Pick the latest saved record for each car specification, BEFORE pagination.
    ->whereNotExists(function($sub)use($type){
      $sub->selectRaw('1')->from('car_rental_prices as newer')
        ->whereColumn('newer.brand_id','car_rental_prices.brand_id')
        ->whereColumn('newer.model_id','car_rental_prices.model_id')
        ->whereColumn('newer.model_year','car_rental_prices.model_year')
        ->where('newer.client_type',$type)
        ->whereColumn('newer.id','>','car_rental_prices.id');
    });
  if($r->filled('search')){$term=$r->query('search');$q->where(fn($q)=>$q->whereHas('brand',fn($x)=>$x->where('name','like',"%$term%"))->orWhereHas('model',fn($x)=>$x->where('name','like',"%$term%"))->orWhere('model_year','like',"%$term%"));}
  $rows=$q->latest('id')->paginate(min(100,max(1,(int)$r->query('per_page',10))));
  $rows->getCollection()->transform(fn($p)=>$this->format($p));
  return response()->json($rows);
 }
 public function options(Request $r):JsonResponse {
  $r->validate(['search'=>'nullable|string|max:120','include_id'=>'nullable|integer|min:1','client_type'=>'nullable|in:customer,company']);
  $type=$r->query('client_type','customer');
  $includeId=$r->integer('include_id');
  // The edit-only exception is restricted to a record in the requested client type.
  $editing=$includeId ? CarRentalPrice::query()->where('client_type',$type)->find($includeId) : null;
  $q=DB::table('vehicles as v')
    ->join('brands as b','b.id','=','v.brand_id')
    ->join('models as m','m.id','=','v.model_id')
    ->selectRaw('v.brand_id,v.model_id,v.model_year,b.name as brand_name,m.name as model_name')
    ->distinct();
  // Filter the choice list only: the DB composite index remains NON-UNIQUE.
  $q->where(function($outer)use($type,$editing){
    $outer->whereNotExists(function($sub)use($type){
      $sub->selectRaw('1')->from('car_rental_prices as crp')
        ->whereColumn('crp.brand_id','v.brand_id')
        ->whereColumn('crp.model_id','v.model_id')
        ->whereColumn('crp.model_year','v.model_year')
        ->where('crp.client_type',$type);
    });
    if($editing){$outer->orWhere(function($own)use($editing){
      $own->where('v.brand_id',$editing->brand_id)
        ->where('v.model_id',$editing->model_id)
        ->where('v.model_year',$editing->model_year);
    });}
  });
  if($r->filled('search')){
    $term='%'.$r->query('search').'%';
    $q->where(fn($x)=>$x->where('b.name','like',$term)
      ->orWhere('m.name','like',$term)
      ->orWhere('v.model_year','like',$term));
  }
  return response()->json(['data'=>$q->orderBy('b.name')->orderBy('m.name')->orderByDesc('v.model_year')->limit(500)->get()]);
 }
 public function show(CarRentalPrice $carRentalPrice):JsonResponse{return response()->json(['data'=>$this->format($this->expanded()->findOrFail($carRentalPrice->id))]);}
 public function store(CarRentalPriceRequest $r):JsonResponse {
  return $this->persist($r,null);
 }
 public function update(CarRentalPriceRequest $r,CarRentalPrice $carRentalPrice):JsonResponse{return $this->persist($r,$carRentalPrice);}
 private function persist(CarRentalPriceRequest $r,?CarRentalPrice $item):JsonResponse {
  $data=$r->validated();
  $fields=collect($data)->only(['brand_id','model_id','model_year','client_type','company_id'])->all();
  if($fields['client_type']==='customer')$fields['company_id']=null;
  $newFiles=[];$removePaths=[];
  try{
   $item=DB::transaction(function()use($r,$item,$data,$fields,&$newFiles,&$removePaths){
    if($item){$item->update($fields);}else{$item=CarRentalPrice::create($fields);}
    // A full-replacement nested save: no partial pricing after successful response.
    $item->periods()->delete();
    foreach($data['periods'] as $p){$period=$item->periods()->create(['start_date'=>$p['start_date'],'end_date'=>$p['end_date']]);foreach($p['intervals'] as $range)$period->intervals()->create(['start'=>$range['start'],'end'=>$range['end'],'price'=>$range['price']]);}
    foreach($data['delete_image_ids']??[] as $imageId){$image=$item->images()->whereKey($imageId)->first();if($image){$removePaths[]=$image->path;$image->delete();}}
    if($r->hasFile('main_image')){
     foreach($item->images()->where('type','main')->get() as $old){$removePaths[]=$old->path;$old->delete();}
     $path=$r->file('main_image')->store('car-rental-prices/'.$item->id,'public');$newFiles[]=$path;
     $item->images()->create(['path'=>$path,'type'=>'main','title'=>'Main car image','alt'=>'Car image']);
    }
    foreach($r->file('gallery',[]) as $file){$path=$file->store('car-rental-prices/'.$item->id,'public');$newFiles[]=$path;$item->images()->create(['path'=>$path,'type'=>'gallery','title'=>'Gallery car image','alt'=>'Car image']);}
    return $item;
   });
  }catch(\Throwable $e){foreach($newFiles as $path)Storage::disk('public')->delete($path);throw $e;}
  foreach($removePaths as $path)Storage::disk('public')->delete($path);
  return response()->json(['data'=>$this->format($this->expanded()->findOrFail($item->id))],$r->isMethod('post')?201:200);
 }
 public function destroy(CarRentalPrice $carRentalPrice):JsonResponse {
  $paths=$carRentalPrice->images()->pluck('path')->all();
  DB::transaction(function()use($carRentalPrice){$carRentalPrice->images()->delete();$carRentalPrice->delete();});
  foreach($paths as $path)Storage::disk('public')->delete($path);
  return response()->json(['message'=>'Deleted']);
 }
 public function image(CarRentalPrice $carRentalPrice,Image $image){
  abort_unless($image->imageable_type===$carRentalPrice->getMorphClass()&&(int)$image->imageable_id===$carRentalPrice->id,404);
  abort_unless(Storage::disk('public')->exists($image->path),404);
  return Storage::disk('public')->response($image->path);
 }
}
