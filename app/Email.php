<?php
namespace App;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Email
{
	function __construct()
	{
		$this->mail = new PHPMailer(true);	
		$this->mail->isSMTP();     
		$this->mail->Host = 'localhost';
		$this->mail->SMTPAuth = false;
		$this->mail->SMTPAutoTLS = false; 
		$this->mail->Port = 25; 
		$this->mail->Username   = 'support-bot@mentorg.com'; 
		$this->mail->setFrom('support-bot@mentorg.com', 'Support Bot');
		$this->mail->addAddress('dan_schiro@mentor.com','Dan Schiro');
		$this->mail->addAddress('mumtaz_ahmad@mentor.com','Mumtaz Ahmad');     // Add a recipient
		$this->mail->addReplyTo('dan_schiro@mentor.com', 'Dan Schiro');
		$this->mail->isHTML(true);  
	}
	function SendResolutionTimeEmail($ticket)
	{
		$rem = 100-$ticket->percent_time_consumed;
		if($rem <= 0)
		{
			$this->mail->Subject = 'Support SLT Violation!!';
			
			$msg = 'This is an automated alert for ';
			$msg = '<span style="font-weight:bold">'.$ticket->key.'</span><br><br>';
			
			$msg .= 'This ticket has crossed the SLT Threshold for "Time to Resolution"<br>';
			$msg .= 'Please contact Dan Schiro for any questions.';
		}
		else
		{
			$this->mail->Subject = 'Support SLT Notification!';
			$msg = '<span style="font-weight:bold">'.$ticket->key.'</span> is approaching a SLT milestone<br>';
			$msg .= '<p>'.$rem.' % of time remains on milestone "Time to Resolution"<br>';
			$msg .= '<p>';// style="font-style: italic;">';
			$msg .= 'This is an automated message.Contact Dan Schiro for any questions.</p>';
		}
        $this->mail->Body= $msg;
		//$this->mail->AltBody =$msg;
		//echo $msg;
		try {
			$this->mail->send();
		} 
		catch (phpmailerException $e) 
		{
			echo $e->errorMessage(); //Pretty error messages from PHPMailer
		} 
		catch (Exception $e) {
			echo $e->getMessage(); //Boring error messages from anything else!
		}
		echo "Email sent for Time to resolution  alert for ".$ticket->key."\n";
		//echo 'MRC Approval mail  sent';
	}

	function SendFirstContactEmail($ticket)
	{
		$rem = 100-$ticket->percent_first_contact_time_consumed;
		if($rem <= 0)
		{
			$this->mail->Subject = 'Support SLT Violation!!';
			
			$msg = 'This is an automated alert for ';
			$msg = '<span style="font-weight:bold">'.$ticket->key.'</span><br><br>';
			
			$msg .= 'This ticket has crossed the SLT Threshold for "First Contact"<br>';
			$msg .= 'Please contact Dan Schiro for any questions.';
		}
		else
		{
			$this->mail->Subject = 'Support SLT Notification!';
			$msg = '<span style="font-weight:bold">'.$ticket->key.'</span> is approaching a SLT milestone<br>';
			$msg .= '<p>'.$rem.' % of time remains on milestone "First Contact"<br>';
			$msg .= '<p>';// style="font-style: italic;">';
			$msg .= 'This is an automated message.Contact Dan Schiro for any questions.</p>';
		}
        $this->mail->Body= $msg;
		//$this->mail->AltBody =$msg;
		//echo $msg;
		try {
			$this->mail->send();
		} 
		catch (phpmailerException $e) 
		{
			echo $e->errorMessage(); //Pretty error messages from PHPMailer
		} 
		catch (Exception $e) {
			echo $e->getMessage(); //Boring error messages from anything else!
		}
	
		echo "Email sent for First Contact alert for ".$ticket->key."\n";
		//echo 'MRC Approval mail  sent';
	}

}