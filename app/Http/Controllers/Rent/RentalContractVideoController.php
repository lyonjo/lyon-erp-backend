<?php
namespace App\Http\Controllers\Rent;
use App\Http\Controllers\Controller;
use App\Models\Rent\{RentalContract,ContractVideo};
use Illuminate\Http\{Request,JsonResponse};
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
class RentalContractVideoController extends Controller
{
    public function index(RentalContract $contract):JsonResponse
    {
        return response()->json(['data'=>ContractVideo::where('contract_id',$contract->id)->latest()->get(['id','original_name','mime_type','size_bytes','created_at'])]);
    }
    public function store(Request $request,RentalContract $contract):JsonResponse
    {
        if ($contract->status!=='open') throw ValidationException::withMessages(['contract'=>['Cannot upload a video to a closed contract.']]);
        $request->validate(['video'=>['required','file','mimetypes:video/mp4,video/webm,video/quicktime','max:51200']]);
        $file=$request->file('video');
        // Stancl tenancy supplies tenant-scoped filesystem roots.
        $path=$file->store("contracts/{$contract->id}/videos",'local');
        $video=ContractVideo::create(['contract_id'=>$contract->id,'path'=>$path,'original_name'=>$file->getClientOriginalName(),
            'mime_type'=>$file->getMimeType(),'size_bytes'=>$file->getSize()]);
        return response()->json(['message'=>'Video uploaded.','data'=>$video],201);
    }
    public function show(RentalContract $contract,ContractVideo $video)
    {
        abort_unless((int)$video->contract_id===(int)$contract->id,404);
        abort_unless(Storage::disk('local')->exists($video->path),404);
        return Storage::disk('local')->response($video->path,$video->original_name,['Content-Type'=>$video->mime_type,'Content-Disposition'=>'inline']);
    }
}
