<?php

namespace App\Http\Controllers;
use \MongoDB\Client;
use \MongoDB\BSON\UTCDateTime;
use App\Database;

use Auth;
use Illuminate\Http\Request;
use Response;
use App\Products;
use App\CVEStatus;
use App\CVE;
use App\Cache;
use App\Ldap;
use Artisan;
class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
		date_default_timezone_set('Asia/Karachi');
		$this->db = new Database();
		$this->last_updated = $this->db->Get('last_updated');
    }
	public function Active(Request $request)
	{
		
		$tickets = $this->db->LoadActiveTickets();
		$last_updated=$this->last_updated;
		$jira_url = env('JIRA_HOST','')."/browse/";
		return view('home',compact('tickets','last_updated','jira_url'));
	}
	public function Closed(Request $request)
	{
		$tickets = $this->db->LoadClosedTickets();
		$last_updated=$this->last_updated;
		$jira_url = env('JIRA_HOST','')."/browse/";
		return view('home',compact('tickets','last_updated','jira_url'));
	}
	public function AllUpdated(Request $request)
	{
		$tickets = $this->db->LoadAllTickets();
		$last_updated=$this->last_updated;
		$jira_url = env('JIRA_HOST','')."/browse/";
		return view('home',compact('tickets','last_updated','jira_url'));
	}
	public function Sync(Request $request)
	{
		$force_update=1;
		$this->db->Save(compact('force_update'));
		//return Response::json(['error' => 'Invalid Credentials'], 404); 
		return ['message'=>"Update initiated in background"];
	}
}