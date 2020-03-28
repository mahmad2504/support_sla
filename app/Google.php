<?php

namespace App;

use Google_Client;
use Google_Service_Sheets;
use Google_Service_Sheets_ValueRange;
use Google_Service_Sheets_BatchUpdateValuesRequest;

class Google
{
	private $cache;
	function __construct($cache=1)
	{
		if (php_sapi_name() != 'cli') 
		{
			echo R("This application must be run on the command line");
			exit();
		}
		$this->cache=$cache;
	}
	private function getClient()
	{
		$client = new Google_Client();
		$client->setApplicationName('Google Sheets API PHP Quickstart');
		//$client->setScopes(Google_Service_Sheets::SPREADSHEETS_READONLY);
		$client->setScopes(Google_Service_Sheets::SPREADSHEETS);
		$client->setAuthConfig('credentials.json');
		$client->setAccessType('offline');
		$client->setApprovalPrompt('force');
		$client->setPrompt('select_account consent');

		// Load previously authorized token from a file, if it exists.
		// The file token.json stores the user's access and refresh tokens, and is
		// created automatically when the authorization flow completes for the first
		// time.
		$tokenPath = 'token.json';
		if (file_exists($tokenPath)) {
			$accessToken = json_decode(file_get_contents($tokenPath), true);
			$client->setAccessToken($accessToken);
		}

		// If there is no previous token or it's expired.
		if ($client->isAccessTokenExpired()) {
			// Refresh the token if possible, else fetch a new one.
			if ($client->getRefreshToken()) {
				$client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
			} else {
				// Request authorization from the user.
				$authUrl = $client->createAuthUrl();
				printf("Open the following link in your browser:\n%s\n", $authUrl);
				print 'Enter verification code: ';
				$authCode = trim(fgets(STDIN));

				// Exchange authorization code for an access token.
				$accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
				$client->setAccessToken($accessToken);

				// Check to see if there was an error.
				if (array_key_exists('error', $accessToken)) {
					throw new Exception(join(', ', $accessToken));
				}
			}
			// Save the token to a file.
			if (!file_exists(dirname($tokenPath))) {
				mkdir(dirname($tokenPath), 0700, true);
			}
			file_put_contents($tokenPath, json_encode($client->getAccessToken()));
		}
		return $client;
	}
	
	function LoadSheet($spreadsheetId,$sheetname)
	{
		$client = $this->getClient();
		$this->service = new Google_Service_Sheets($client);
		$this->spreadsheetId = $spreadsheetId;
		$this->sheetname = $sheetname;
		$this->data = [];
	}
	function Cell($row/*1 , 2 */,$col/* A,B */,$values)
	{
		$this->data[] = new Google_Service_Sheets_ValueRange(
			   [
					'range' => $this->sheetname."!".$col.$row,
					'values' => $values
				]
			);
		
	}
	function SaveSheet()
	{
		$requestBody = new Google_Service_Sheets_BatchUpdateValuesRequest(
			[
				'valueInputOption' => 'RAW',
				'data' => $this->data
			]);
		$result = $this->service->spreadsheets_values->batchUpdate($this->spreadsheetId, $requestBody);	
	}
}