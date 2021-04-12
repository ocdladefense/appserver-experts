<?php

use Clickpdx\Core\Controller\ControllerBase;
use Clickpdx\Core\User\ForceUser;
use Clickpdx\OAuth\OAuthGrantTypes;
use Clickpdx\SfRestApiRequestTypes;
use Clickpdx\Http\HttpRequest;
use Clickpdx\ResourceLoader;
use Clickpdx\OAuth\OAuthHttpAuthorizationService;
use Clickpdx\SalesforceRestApiService;
use Clickpdx\Salesforce\RestApiAuthenticationException;
use Clickpdx\Salesforce\RestApiInvalidUrlException;



class ExpertUpdate extends \SalesforceController
{


	private static $EXPERT_WITNESS_FIELDS = array('FirstName', 'LastName', 'MiddleName',
	'Ocdla_Organization__c',
	'MailingStreet', 'MailingCity', 'MailingStateCode', 'MailingPostalCode', 'Ocdla_Publish_Mailing_Address__c',
	'Ocdla_Bar_Number__c',
	'Ocdla_Areas_of_Interest_1__c', 'Ocdla_Areas_of_Interest_2__c', 'Ocdla_Areas_of_Interest_3__c', 'Ocdla_Areas_of_Interest_4__c', 'Ocdla_Areas_of_Interest_5__c',
	'OrderApi__Work_Phone__c',
	'MobilePhone',
	'Fax',
	'OrderApi__Work_Email__c',
	'Ocdla_Publish_Work_Email__c',
	'Ocdla_Website__c',
	'Ocdla_Expert_Witness_Primary__c',
	'Ocdla_Expert_Witness_Other_Areas__c',
	'Ocdla_Expert_Comments__c',
	'Ocdla_Expert_Travel_Availability__c',
	'Ocdla_Expert_Hourly_Rate__c',
	'Ocdla_Expert_Minimum_Hours__c',
	'Ocdla_Expert_Unavailability_Start_Date__c',
	'Ocdla_Expert_Unavailability_End_Date__c');
	
	public function doApiRecordUpdate($sobject,$id, $data)
	{
		global $resources;
		
		if(!isset($id))
		{
			throw new \Exception('SObject update must include Id value.');
		}
		$oauth = ResourceLoader::getResource('sfOauth');
		$this->addMessage((string)$oauth);
		
		$this->addMessage("<h2>This is the oauth resource loader:</h2>");
		$this->addMessage("Resource is: ".get_class($oauth));
		// print_r($this->getMessages());
		// exit;
		
		$oauth_result = $oauth->authorize();
		$this->addMessage(entity_toString($oauth_result));

		
		// $forceApi = ResourceLoader::getResource('forceApi',true);
		$forceApi = new Clickpdx\SalesforceRestApiService();
		$forceApi->setDebug(true);
		$forceApi->setParams($resources['forceApi']['params']);
		
		// $d = $resources['forceApi']['params']['endpoints']['RECORD_UPDATE'];
		// $endpoint = sprintf($e,'Contact',$data['Id']);
		
		$forceApi->setEndpoint('RECORD_UPDATE',array('sObject'=>$sobject,'Id'=>$id));

		$content = json_encode($data);

		$forceApi->registerWriteHandler('POST',function(/*HttpMessage*/$ch) use($content){
			$ch->h = \curl_init($ch->getUri());
			curl_setopt($ch->h, CURLOPT_HEADER, false);
			curl_setopt($ch->h, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch->h, CURLOPT_POST, true);
			curl_setopt($ch->h, CURLOPT_CUSTOMREQUEST, "PATCH");
			curl_setopt($ch->h, CURLOPT_POSTFIELDS, $content);
			$ch->addHeaders();
			return curl_exec($ch->h);
		});
		$forceApi->setDebug(false);
		$forceApi->setAuthenticationService($oauth);
		$forceApi->setInstanceUrl($oauth_result['instance_url']);
		$forceApi->setAccessToken($oauth_result['access_token']);

		$result = $forceApi->updateRecord();

		if($result->hasError()) {
			throw new Exception($sfResult->getErrorMsg());
		}
	}
	


	public function testUpdate($id) {
		$params = array(
			"FirstName" => "José",
			"LastName" => "Bernal"
		);

		$params = $this->formatParams($params);
		
		$this->doApiRecordUpdate('Contact', $id, $params);
	}


	/**
		* Update the expert's contact record.
		*  Return either success or an error message.
		*/
	public function updateExpert($id)
	{

		$params = $this->formatParams($_POST);


		try {
			$this->doApiRecordUpdate('Contact', $id, $params);
			return array("success" => true);
			
			
		} catch(Exception $e) {
		
		
			return array("error" => $e->getMessage());
		}	
	}



	/**
	  * Convert empty string/date values to null
		* Salesforce can't parse empty string in certain context (like Date)
		* because of the strict nature of JSON so we must explicitly declare them as null
	*/
	private function formatParams($post, $filter=null)
	{
		$fields = array_intersect_key($post,array_flip(self::$EXPERT_WITNESS_FIELDS));

		$formatted = array_map(function($val){
				return empty($val) ? null : $val;
			},$fields);
			
			
		return $formatted;
	}
	
}