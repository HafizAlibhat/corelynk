<?php
namespace App\Controllers;
use App\Models\Accounting\CreditNoteModel;use App\Models\Accounting\AccountModel;use Config\Database;
class AccountingCreditNotes extends BaseController
{public function index(){if(method_exists($this,'checkPermission')&&!$this->checkPermission('accounting.credit_notes.view')){return redirect()->to('/')->with('error','Permission denied');}
    $m=new CreditNoteModel();$notes=$m->orderBy('id','DESC')->findAll(50);$accounts=(new AccountModel())->orderBy('code','ASC')->findAll();$db=Database::connect();$vendors=[];$customers=[];try{$vendors=$db->query('SELECT id,name FROM vendors ORDER BY name ASC LIMIT 100')->getResultArray();}catch(\Throwable $e){}
    try{$customers=$db->query('SELECT id,name FROM customers ORDER BY name ASC LIMIT 100')->getResultArray();}catch(\Throwable $e){}
    return view('accounting/credit_notes/index',compact('notes','accounts','vendors','customers'));}
  public function create(){if($this->request->getMethod()!=='post'){return redirect()->to('/accounting/credit-notes');}
    if(method_exists($this,'checkPermission')&&!$this->checkPermission('accounting.credit_notes.create')){return redirect()->to('/accounting/credit-notes')->with('error','Permission denied');}
    $data=['party_type'=>$this->request->getPost('party_type'),'party_id'=>(int)$this->request->getPost('party_id'),'account_id'=>(int)$this->request->getPost('account_id'),'reference'=>trim((string)$this->request->getPost('reference')),'note'=>trim((string)$this->request->getPost('note')),'amount'=>(float)$this->request->getPost('amount'),'applied_amount'=>0,'status'=>'open'];
    $errors=[];if(!in_array($data['party_type'],['vendor','customer'],true))$errors['party_type']='Invalid party type';if($data['party_id']<=0)$errors['party_id']='Party required';if($data['account_id']<=0)$errors['account_id']='Account required';if($data['amount']<=0)$errors['amount']='Amount must be > 0';
    if($errors){return redirect()->to('/accounting/credit-notes')->with('error','Fix the errors')->with('form_errors',$errors)->withInput();}
    $m=new CreditNoteModel();try{$m->insert($data);return redirect()->to('/accounting/credit-notes')->with('success','Credit note created');}catch(\Throwable $e){log_message('error','Credit note create failed: '.$e->getMessage());return redirect()->to('/accounting/credit-notes')->with('error','Failed to create credit note');}}
}
