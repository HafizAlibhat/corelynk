<?php
namespace App\Controllers;use App\Models\Accounting\PurchaseOrderModel;use App\Models\Accounting\PurchaseOrderLineModel;use App\Models\CompanySettingsModel;use App\Libraries\InvoicePdfGenerator;use Config\Database;
class AccountingPurchaseOrders extends BaseController
{public function index(){if(method_exists($this,'checkPermission')&&!$this->checkPermission('accounting.purchase_orders.view')){return redirect()->to('/')->with('error','Permission denied');}
    $m=new PurchaseOrderModel();$orders=$m->orderBy('id','DESC')->findAll(50);$db=Database::connect();$vendors=[];try{$vendors=$db->query('SELECT id,name FROM vendors ORDER BY name ASC LIMIT 200')->getResultArray();}catch(\Throwable $e){}
    return view('accounting/purchase_orders/index',compact('orders','vendors'));}
  
  // Show receive form for a PO
  public function receive($id){
    $poId=(int)$id; if($poId<=0) return redirect()->to('/accounting/purchase-orders')->with('error','Invalid PO');
    $m=new PurchaseOrderModel(); $po=$m->find($poId); if(!$po) return redirect()->to('/accounting/purchase-orders')->with('error','PO not found');
    $lineModel=new PurchaseOrderLineModel(); $lines=$lineModel->where('po_id',$poId)->findAll();
    $db=Database::connect(); $vendors=[]; try{$vendors=$db->query('SELECT id,name FROM vendors ORDER BY name ASC LIMIT 200')->getResultArray();}catch(\Throwable $e){}
    $productModel=new \App\Models\ProductModel(); $products=[]; try{$products=$productModel->getProductsWithCategory();}catch(\Throwable $e){}
    return view('accounting/purchase_orders/receive',compact('po','lines','vendors','products'));
  }

  // Handle receiving POST
  public function receiveSubmit(){
    if($this->request->getMethod()!=='post') return redirect()->to('/accounting/purchase-orders');
    $poId=(int)$this->request->getPost('po_id'); if($poId<=0) return redirect()->to('/accounting/purchase-orders')->with('error','Invalid PO');
    $linesPost=$this->request->getPost('lines')?:[]; $db=Database::connect(); $db->transBegin();
    try{
      $poModel=new PurchaseOrderModel(); $po=$poModel->find($poId); if(!$po) throw new \RuntimeException('PO not found');
      $lineModel=new PurchaseOrderLineModel(); $productModel=new \App\Models\ProductModel();
      $totalOrdered=0; $totalReceived=0;
      foreach($lineModel->where('po_id',$poId)->findAll() as $ln){ $totalOrdered += (float)$ln['qty']; $totalReceived += (float)$ln['qty_received']; }

        // Create a GRN for this receive operation
        $grnData = ['po_id'=>$poId,'vendor_id'=>$po['vendor_id'],'received_at'=>date('Y-m-d H:i:s'),'created_by'=>session()->get('user_id')?:null];
        $grnId = $db->table('grns')->insert($grnData);
        if(!$grnId) throw new \RuntimeException('Failed to create GRN');

        $processRunId = (int)($this->request->getPost('process_run_id')?:0);
        foreach($linesPost as $lid => $ldata){
          $lid=(int)$lid; $received=(float)($ldata['received']??0);
          $scrapQty = (float)($ldata['scrap']??0);
          $line=$lineModel->find($lid); if(!$line) continue;
          if($received==0) continue;
          // qty to add is received amount (allow over-receipt)
          $qtyToAdd = $received - $scrapQty; if($qtyToAdd < 0) $qtyToAdd = 0;
          // update line qty_received
          $newQtyReceived = (float)$line['qty_received'] + $qtyToAdd;
          $db->table('purchase_order_lines')->update(['qty_received'=>$newQtyReceived], ['id'=>$lid]);

          // create grn line
          $db->table('grn_lines')->insert([
            'grn_id'=>$grnId,
            'po_line_id'=>$lid,
            'product_id'=>$line['product_id'],
            'description'=>$line['description'] ?? null,
            'qty_received'=>$qtyToAdd,
            'unit_cost'=>$line['unit_price'] ?? null,
          ]);

          // update product stock and reference the GRN id
          $productId = (int)$line['product_id'];
          $userId = session()->get('user_id')?:null;
          $unitCost = (float)$line['unit_price'];
          if($qtyToAdd>0){
            // Route GRN stock increases through the InventoryGuard control layer
            // PHASE 2A: Forward Control — preserve exact behavior but record caller
            $guard = new \App\Libraries\InventoryGuard();
            $guard->increaseProductStock($productId, $qtyToAdd, 'grn', $grnId, $userId, $unitCost, 'PurchaseGRN');
          }

          // Handle scrap: if scrapQty > 0 and processRunId provided, record scrap linked to process_run
          if($scrapQty > 0){
            if($processRunId>0){
              $db->table('scrap_records')->insert([
                'process_run_id' => $processRunId,
                'quantity_scrapped' => (int)$scrapQty,
                'reason' => 'Rejection at receive for PO line '.$lid,
                'estimated_cost' => 0,
                'actual_cost' => 0,
                'recorded_by' => session()->get('user_id')?:null,
                'recorded_at' => date('Y-m-d H:i:s')
              ]);
            } else {
              // append note to grn line (no process link)
              $this->db->table('grn_lines')->where('grn_id',$grnId)->where('po_line_id',$lid)->set('description', "CONCAT(IFNULL(description,''),' | SCRAP: $scrapQty')", false)->update();
            }
          }

          $totalReceived += $qtyToAdd;
        }

        // Determine PO status
        $allLines = $lineModel->where('po_id',$poId)->findAll();
        $orderedSum=0; $receivedSum=0; foreach($allLines as $l){ $orderedSum += (float)$l['qty']; $receivedSum += (float)$l['qty_received']; }
        $status = 'partially_received'; if($receivedSum >= $orderedSum) $status = 'received';
        $db->table('purchase_orders')->update(['status'=>$status], ['id'=>$poId]);
      $db->transCommit();
      return redirect()->to('/accounting/purchase-orders')->with('success','PO received and stock updated');
    }catch(\Throwable $e){
      $db->transRollback(); log_message('error','PO receive failed: '.$e->getMessage());
      return redirect()->to('/accounting/purchase-orders')->with('error','Failed to process receive');
    }
  }

  // Show issue to subcontractor form
  public function issue(){
    if(method_exists($this,'checkPermission')&&!$this->checkPermission('accounting.purchase_orders.issue')){return redirect()->to('/accounting/purchase-orders')->with('error','Permission denied');}
    $db=Database::connect(); $vendors=[]; try{$vendors=$db->query('SELECT id,name FROM vendors ORDER BY name ASC LIMIT 200')->getResultArray();}catch(\Throwable $e){}
    $productModel=new \App\Models\ProductModel(); $products=$productModel->getProductsWithCategory();
    return view('accounting/purchase_orders/issue', compact('vendors','products'));
  }

  // Handle issue submit: create subcontract_issue and decrement stock
  public function issueSubmit(){
    if($this->request->getMethod()!=='post') return redirect()->to('/accounting/purchase-orders');
    if(method_exists($this,'checkPermission')&&!$this->checkPermission('accounting.purchase_orders.issue')){return redirect()->to('/accounting/purchase-orders')->with('error','Permission denied');}
    $vendorId=(int)$this->request->getPost('vendor_id'); $processRunId=(int)$this->request->getPost('process_run_id'); $lines=$this->request->getPost('lines')?:[];
    if($vendorId<=0) return redirect()->back()->with('error','Vendor required');
    $db=Database::connect(); $db->transBegin();
    try{
      $issueModel=new \App\Models\SubcontractIssueModel(); $lineModel=new \App\Models\SubcontractIssueLineModel();
      $issueId = $issueModel->insert(['vendor_id'=>$vendorId,'issued_at'=>date('Y-m-d H:i:s'),'created_by'=>session()->get('user_id')?:null], true);
      if(!$issueId) throw new \RuntimeException('Failed to create issue');
      $productModel=new \App\Models\ProductModel();
      foreach($lines as $pid => $qty){ $qty=(float)$qty; if($qty<=0) continue; $pid=(int)$pid;
        $lineModel->insert(['issue_id'=>$issueId,'product_id'=>$pid,'description'=>null,'quantity'=>$qty]);
        // decrement stock
        $productModel->updateStock($pid, $qty, 'out', 'subcontract', $issueId, session()->get('user_id')?:null);
      }
      $db->transCommit();
      return redirect()->to('/accounting/purchase-orders')->with('success','Issue created and stock updated');
    }catch(\Throwable $e){
      $db->transRollback(); log_message('error','Issue create failed: '.$e->getMessage());
      return redirect()->to('/accounting/purchase-orders')->with('error','Failed to create issue');
    }
  }
  public function create(){if($this->request->getMethod()!=='post'){return redirect()->to('/accounting/purchase-orders');}
    if(method_exists($this,'checkPermission')&&!$this->checkPermission('accounting.purchase_orders.create')){return redirect()->to('/accounting/purchase-orders')->with('error','Permission denied');}
    $vendor_id=(int)$this->request->getPost('vendor_id');$order_date=$this->request->getPost('order_date')?:date('Y-m-d');$currency_code=$this->request->getPost('currency_code')?:'PKR';$lines=$this->request->getPost('lines');$errors=[];if($vendor_id<=0)$errors['vendor_id']='Vendor required';if(!is_array($lines)||count($lines)===0)$errors['lines']='At least one line required';if($errors){return redirect()->to('/accounting/purchase-orders')->with('error','Fix the errors')->with('form_errors',$errors)->withInput();}
    $subtotal=0;$tax_total=0;$total=0;$clean=[];foreach($lines as $ln){$qty=isset($ln['qty'])?(float)$ln['qty']:0;$price=isset($ln['unit_price'])?(float)$ln['unit_price']:0;if($qty<=0||$price<0)continue;$lt=$qty*$price;$subtotal+=$lt;$clean[]=['product_id'=>isset($ln['product_id'])?(int)$ln['product_id']:null,'description'=>trim((string)($ln['description']??'')),'qty'=>$qty,'unit_price'=>$price,'tax_code_id'=>null,'line_total'=>$lt];}
    $total=$subtotal+$tax_total;$db=Database::connect('accounting');$db->transBegin();try{$poModel=new PurchaseOrderModel();$poId=$poModel->insert(['vendor_id'=>$vendor_id,'order_date'=>$order_date,'status'=>'draft','currency_code'=>$currency_code,'subtotal'=>$subtotal,'tax_total'=>$tax_total,'total'=>$total],true);if(!$poId)throw new \RuntimeException('PO insert failed');$lineModel=new PurchaseOrderLineModel();foreach($clean as $cl){$cl['po_id']=$poId;$lineModel->insert($cl);}if($db->transStatus()===false)throw new \RuntimeException('PO transaction failed');$db->transCommit();return redirect()->to('/accounting/purchase-orders')->with('success','PO created ID '.$poId);}catch(\Throwable $e){$db->transRollback();log_message('error','PO create failed: '.$e->getMessage());return redirect()->to('/accounting/purchase-orders')->with('error','Failed to create PO');}}

  public function pdf($id){
    $poId=(int)$id; if($poId<=0) return redirect()->to('/accounting/purchase-orders')->with('error','Invalid PO');
    $poModel=new PurchaseOrderModel(); $po=$poModel->find($poId); if(!$po) return redirect()->to('/accounting/purchase-orders')->with('error','PO not found');
    $lineModel=new PurchaseOrderLineModel(); $lines=$lineModel->where('po_id',$poId)->findAll();
    $vendor=[]; try{$vendor=Database::connect()->table('vendors')->where('id',(int)($po['vendor_id']??0))->get()->getRowArray()?:[];}catch(\Throwable $e){}
    $pdfLines=[]; foreach($lines as $ln){$qty=(float)($ln['qty']??0);$price=(float)($ln['unit_price']??0);$pdfLines[]=['id'=>$ln['id']??null,'product_id'=>$ln['product_id']??null,'product_variant_id'=>$ln['variant_id']??null,'description'=>$ln['description']??'','quantity'=>$qty,'unit_price'=>$price,'line_total'=>isset($ln['line_total'])?(float)$ln['line_total']:($qty*$price)];}
    $payload=['invoice'=>['id'=>$po['id']??$poId,'invoice_number'=>$po['po_number']??('PO-'.$poId),'issue_date'=>$po['order_date']??date('Y-m-d'),'subtotal'=>(float)($po['subtotal']??0),'tax_total'=>(float)($po['tax_total']??0),'total_amount'=>(float)($po['total']??0),'currency_code'=>$po['currency_code']??($po['currency']??'PKR'),'status'=>$po['status']??'draft'],'lines'=>$pdfLines,'company'=>(new CompanySettingsModel())->first()?:[],'customer'=>['name'=>$vendor['name']??'Vendor','phone'=>$vendor['phone']??'','email'=>$vendor['email']??''],'customerAddress'=>['line1'=>trim((string)($vendor['address']??'')),'line2'=>'','city_name'=>'','state_name'=>'','postal_code'=>''],'document_title'=>'Purchase Order','document_number_label'=>'PO #','document_date_label'=>'PO Date:','document_prefix'=>'','party_label'=>'Vendor','pdf_show_header_address'=>(int)(Database::connect()->table('company_settings')->select('pdf_po_show_header')->get()->getRowArray()['pdf_po_show_header']??1),'pdf_show_footer'=>(int)(Database::connect()->table('company_settings')->select('pdf_po_show_footer')->get()->getRowArray()['pdf_po_show_footer']??1)];
    $pdf=(new InvoicePdfGenerator())->generateSystemInvoice($payload);
    if(is_array($pdf)&&!empty($pdf['path'])&&is_file($pdf['path'])){$safeNumber=preg_replace('/[^A-Za-z0-9\-_]/','_',(string)($po['po_number']??('PO-'.$poId)))?:('PO-'.$poId);return $this->response->download($pdf['path'],null)->setFileName('purchase_order_'.$safeNumber.'.pdf')->setHeader('Cache-Control','no-store, no-cache, must-revalidate')->setHeader('Pragma','no-cache')->setHeader('Expires','0');}
    return redirect()->to('/accounting/purchase-orders')->with('error','Failed to generate PO PDF');
  }
}
