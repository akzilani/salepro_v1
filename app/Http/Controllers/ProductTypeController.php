<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductType;
use Illuminate\Validation\Rule;
use App\Traits\TenantInfo;
use App\Traits\CacheForget;
use Illuminate\Support\Str;

class ProductTypeController extends Controller
{
    use CacheForget;
    use TenantInfo;

    public function index()
    {
        $lims_producttype_all = ProductType::where('is_active', true)->get();
        return view('backend.product.type', compact('lims_producttype_all'));
    }

    public function store(Request $request)
    {

        $request->title = preg_replace('/\s+/', ' ', $request->title);
        $this->validate($request, [
            'title' => [
                'max:255',
                Rule::unique('product_types')->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],

        ]);

        $input['title'] = $request->title;
        $input['is_active'] = true;
        $input['slug'] = Str::slug($request->title);
        $producttype = ProductType::create($input);
        $this->cacheForget('producttype_list');
        
        if (isset($request->ajax))
            return $producttype;
        else
            return redirect('producttype');
    }

    public function edit($id)
    {
        $lims_producttype_data = ProductType::findOrFail($id);
        return $lims_producttype_data;
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'title' => [
                'max:255',
                Rule::unique('product_types')->ignore($request->producttype_id)->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],

        ]);
        $lims_producttype_data = ProductType::findOrFail($request->producttype_id);
        $lims_producttype_data->title = $request->title;
        $lims_producttype_data->slug = Str::slug($request->title);
        $lims_producttype_data->save();
        $this->cacheForget('producttype_list');
        return redirect('producttype');
    }

    public function importProductType(Request $request)
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

            $producttype = ProductType::firstOrNew(['title' => $data['title'], 'is_active' => true]);
            $producttype->title = $data['title'];
            $producttype->is_active = true;
            $producttype->save();
        }
        $this->cacheForget('producttype_list');
        return redirect('producttype')->with('message', 'Product Type imported successfully');
    }

    public function deleteBySelection(Request $request)
    {
        $producttype_id = $request['producttypeIdArray'];
        foreach ($producttype_id as $id) {
            $lims_producttype_data = ProductType::findOrFail($id);
            $lims_producttype_data->is_active = false;
            $lims_producttype_data->save();
        }
        $this->cacheForget('producttype_list');
        return 'Product Type deleted successfully!';
    }

    public function destroy($id)
    {
        $lims_producttype_data = ProductType::findOrFail($id);
        $lims_producttype_data->is_active = false;
        $lims_producttype_data->save();
        $this->cacheForget('producttype_list');
        return redirect('producttype')->with('not_permitted', 'Product Type deleted successfully!');
    }

    public function exportProductType(Request $request)
    {
        $lims_producttype_data = $request['producttypeArray'];
        $csvData = array('Product Type Title, Image');
        foreach ($lims_producttype_data as $producttype) {
            if ($producttype > 0) {
                $data = ProductType::where('id', $producttype)->first();
                $csvData[] = $data->title . ',' . $data->image;
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
