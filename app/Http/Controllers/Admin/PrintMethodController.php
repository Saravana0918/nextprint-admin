<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrintMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PrintMethodController extends Controller
{
    public function index(Request $req)
    {
        $q = PrintMethod::query();
        if ($search = $req->get('q')) {
            $q->where('name','like',"%{$search}%")
              ->orWhere('code','like',"%{$search}%");
        }
        $rows = $q->orderBy('sort_order')->orderBy('name')->paginate(20);
        return view('admin.print_methods.index', compact('rows'));
    }

    public function create()
    {
        $method = new PrintMethod(['status'=>'ACTIVE']);
        return view('admin.print_methods.form', compact('method'));
    }

    public function store(Request $r)
    {
        $data = $this->validateData($r);
        if (empty($data['code'])) $data['code'] = Str::slug($data['name']);
        PrintMethod::create($data);
        return redirect()->route('admin.print-methods.index')->with('success','Print method created.');
    }

    public function edit(PrintMethod $method)
    {
        return view('admin.print_methods.form', compact('method'));
    }

   public function update(Request $request, Product $product)
        {
            $data = $request->validate([
                'name'   => ['required','string','max:255'],
                'price'  => ['nullable','numeric'],
                'sku'    => ['nullable','string','max:255'],
                'status' => ['nullable','in:ACTIVE,INACTIVE'],
                'method_ids' => ['array'],        // <-- add this
                'method_ids.*' => ['integer'],    // each id
            ]);

            $product->update($data);

            // 👇 save chosen methods into pivot
            $product->methods()->sync($request->input('method_ids', []));

            return redirect()->route('admin.products')
                ->with('success', 'Product updated.');
        }


    public function destroy(PrintMethod $method)
    {
        $method->delete();
        return back()->with('success','Deleted.');
    }

    public function toggle(PrintMethod $method)
    {
        $method->status = $method->status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
        $method->save();
        return back()->with('success','Status changed.');
    }

    public function clone(PrintMethod $method)
    {
        $copy = $method->replicate();
        $copy->name = $method->name.' (Copy)';
        $copy->code = $method->code ? ($method->code.'-copy') : null;
        $copy->push();
        return back()->with('success','Cloned.');
    }

    public function search(Request $req)
    {
        $q = $req->get('q');
        $rows = PrintMethod::when($q, fn($s)=>$s->where('name','like',"%$q%"))
            ->limit(20)->get(['id','name','code']);
        return response()->json($rows);
    }

    private function validateData(Request $r, $id=null): array
    {
        return $r->validate([
            'name'        => ['required','string','max:100'],
            'code'        => ['nullable','string','max:60','unique:print_methods,code'.($id?",$id":'')],
            'icon_url'    => ['nullable','url'],
            'description' => ['nullable','string'],
            'status'      => ['required','in:ACTIVE,INACTIVE'],
            'sort_order'  => ['nullable','integer','min:0'],
            'settings'    => ['nullable','array'],
        ]);
    }
}
