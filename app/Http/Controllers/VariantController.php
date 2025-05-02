<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Variant;
use Illuminate\Validation\Rule;
use App\Traits\TenantInfo;
use App\Traits\CacheForget;
use Illuminate\Support\Str;

class VariantController extends Controller
{
    use CacheForget;
    use TenantInfo;

    public function index()
    {
        $lims_productcolorsize_all = Variant::where('is_active', true)->get();
        return view('backend.product.variant', compact('lims_productcolorsize_all'));
    }

    public function store(Request $request)
    {

        $request->name = preg_replace('/\s+/', ' ', $request->name);
        $this->validate($request, [
            'name' => [
                'max:255',
                Rule::unique('variants')->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],

        ]);

        $variant = new Variant();
        $variant->name = $request->name;
        $variant->is_type = $request->is_type;
        $variant->is_active = true;
        $variant->save();
        $this->cacheForget('productcolorsize_list');
        
        if (isset($request->ajax)){
            return $variant;
        }
        else {
            return redirect('productcolorsize');
        }
            
    }

    public function edit($id)
    {
        $lims_productcolorsize_data = Variant::findOrFail($id);
        return $lims_productcolorsize_data;
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => [
                'max:255',
                Rule::unique('variants')->ignore($request->productcolorsize_id)->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],

        ]);
        $lims_productcolorsize_data = Variant::findOrFail($request->productcolorsize_id);
        $lims_productcolorsize_data->name = $request->name;
        $lims_productcolorsize_data->is_type = $request->is_type;
        $lims_productcolorsize_data->save();
        $this->cacheForget('productcolorsize_list');
        return redirect('productcolorsize');
    }

    public function importproductcolorsize(Request $request)
    {
        //get file
        $upload = $request->file('file');
        $ext = pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION);
        if ($ext != 'csv')
            return redirect()->back()->with('not_permitted', 'Please upload a CSV file');
        $filename =  $upload->getClientOriginalName();
        $filePath = $upload->getRealPath();
        //open and read
        $file = fopen($filePath, 'r');
        $header = fgetcsv($file);
        $escapedHeader = [];
        //validate
        foreach ($header as $key => $value) {
            $lheader = strtolower($value);
            $escapedItem = preg_replace('/[^a-z]/', '', $lheader);
            array_push($escapedHeader, $escapedItem);
        }
        //looping through othe columns
        while ($columns = fgetcsv($file)) {
            if ($columns[0] == "")
                continue;
            foreach ($columns as $key => $value) {
                $value = preg_replace('/\D/', '', $value);
            }
            $data = array_combine($escapedHeader, $columns);

            $productcolorsize = Variant::firstOrNew(['name' => $data['name'], 'is_active' => true]);
            $productcolorsize->name = $data['name'];
            $productcolorsize->is_type = $data['is_type'];
            $productcolorsize->is_active = true;
            $productcolorsize->save();
        }
        $this->cacheForget('productcolorsize_list');
        return redirect('productcolorsize')->with('message', 'Product Color/Size imported successfully');
    }

    public function deleteBySelection(Request $request)
    {
        $productcolorsize_id = $request['productcolorsizeIdArray'];
        foreach ($productcolorsize_id as $id) {
            $lims_productcolorsize_data = Variant::findOrFail($id);
            $lims_productcolorsize_data->is_active = false;
            $lims_productcolorsize_data->save();
        }
        $this->cacheForget('productcolorsize_list');
        return 'Product Color/Size deleted successfully!';
    }

    public function destroy($id)
    {
        $lims_productcolorsize_data = Variant::findOrFail($id);
        $lims_productcolorsize_data->is_active = false;
        $lims_productcolorsize_data->save();
        $this->cacheForget('productcolorsize_list');
        return redirect('productcolorsize')->with('not_permitted', 'Product Color/Size deleted successfully!');
    }

    public function exportproductcolorsize(Request $request)
    {
        $lims_productcolorsize_data = $request['productcolorsizeArray'];
        $csvData = array('Product Color/Size name, Image');
        foreach ($lims_productcolorsize_data as $productcolorsize) {
            if ($productcolorsize > 0) {
                $data = Variant::where('id', $productcolorsize)->first();
                $csvData[] = $data->name . ',' . $data->image;
            }
        }
        $filename = date('Y-m-d') . ".csv";
        $file_path = public_path() . '/downloads/' . $filename;
        $file_url = url('/') . '/downloads/' . $filename;
        $file = fopen($file_path, "w+");
        foreach ($csvData as $exp_data) {
            fputcsv($file, explode(',', $exp_data));
        }
        fclose($file);
        return $file_url;
    }
}
