<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use JiraRestApi\Issue\IssueService;
use JiraRestApi\Issue\IssueField;
use JiraRestApi\JiraException;
use App\CustomFields;
use App\Database;
use App\JiraTicket;

class Sync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:database {rebuild?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync From Jira and update Database';

    /**
     * Create a new command instance.
     *
     * @return void
     */
	
    public function __construct()
    {
		date_default_timezone_set('Asia/Karachi');
        parent::__construct();
    }
	public function SearchJira($last_updated)
	{
		$max = 500;
		$start = 0;
		$issueService = new IssueService();
		$jql = 'project=SIEJIR_TEST   and (cf['.explode("_",$this->cf->gross_minutes_to_resolution)[1].'] is EMPTY or cf['.explode("_",$this->cf->gross_minutes_to_resolution)[1].'] =0 or  statusCategory  != Done)';
		$jql = 'project=SIEJIR_TEST';
		if(($last_updated !='')&&($last_updated !=null))
			$jql = 'project=SIEJIR_TEST and updated > "'.$last_updated.'"';
	
		echo "Query for active tickets \n".$jql."\n";
		
		$expand = ['changelog'];
		$fields = ['priority','key','summary','updated','statuscategorychangedate','status','resolutiondate',$this->cf->premium_support,$this->cf->first_contact_date,$this->cf->violation_time_to_resolution,$this->cf->gross_minutes_to_resolution];
		$issues = [];
		while(1)
		{
			$data = $issueService->search($jql,$start, $max,$fields,$expand);
			if(count($data->issues) < $max)
			{
				foreach($data->issues as $issue)
				{
					$ticket = new JiraTicket($issue,$this->cf);
					$issues[] = $ticket ;
				}
				echo count($issues)." Found"."\n";
				return $issues;
			}
			foreach($data->issues as $issue)
			{
				$ticket = new JiraTicket($issue,$this->cf);
				$issues[] = $ticket ;	
			}
			$start = $start + count($data->issues);
		}
	}
	public function SaveTickets($tickets)
	{
		foreach($tickets as $ticket)
		{
			$this->db->SaveTicket($ticket);
		}
	}
	
	public function Process($tickets)
	{
		foreach($tickets as $ticket)
		{
			
		}
	}

	function CheckWhenToUpdate()
	{
		$last_updated = $this->db->Get('last_updated');
		$force_update = $this->db->Get('force_update');
		if($force_update == 1)
		{
			$force_update=0;
			$this->db->Save(compact('force_update'));
			return true;
		}
		
		if(($last_updated !='')&&($last_updated !=null))
		{
			$dt1 =new \DateTime($last_updated);
			$now =  new \DateTime();
			$difference = $now->diff($dt1);
			$minutes = $difference->days * 24 * 60;
			$minutes += $difference->h * 60;
			$minutes += $difference->i;
			if($minutes>30)
				return true;
			return false;
		}
		return true;
	}
	
    public function handle()
    {
        //
		$rebuild = $this->argument('rebuild');
		
		$this->db = new Database();
		if(0)//!$this->CheckWhenToUpdate())
		{
			echo "Its not time to update";
			return;
		}
		
		$this->cf = new CustomFields();
		$new_updated=new \DateTime();
		$last_updated = $this->db->Get('last_updated');
		
		if($rebuild != null)
			$last_updated='2020-01-01';
		
		$tickets = $this->SearchJira($last_updated);
		$this->SaveTickets($tickets);
		
		$tickets = $this->db->LoadActiveTickets();
		$this->SaveTickets($tickets);
		
		$last_updated = $new_updated->format('Y-m-d H:i');
		$this->db->Save(compact('last_updated'));
		
		//$tickets = $this->db->ReadActiveTickets();
		//$this->Process($tickets);
		
    }
}
