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



class ExpertController extends \SalesforceController
{

	public $EXPERT_WITNESS_FIELDS = array('FirstName', 'LastName', 'MiddleName',
	'Ocdla_Organization__c',
	'MailingStreet', 'MailingCity', 'MailingStateCode', 'MailingPostalCode', 'Ocdla_Publish_Mailing_Address__c',
	'Ocdla_Bar_Number__c',
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


	
	
	public function showExpert($contactId)
	{	
		$soql = sprintf(\setting('directory.queries.expertWitness'),$contactId);
		$sfResult = $this->doApiRequest($soql);
	
		if(!$sfResult->count())
		{
			// throw new \Exception('We could not find this member ID.');
			$error = "We could not find this member ID.";
		}
		
		/**
		 *
		 * We also have prependPath() and exists()
		 * functions.
		 */
		$this->addTemplateLocation(
			'sites/default/modules/experts/templates'
		);

		return array(
			'#attached' => array(
				'css' => array(
					'/sites/default/modules/experts/css/experts.css'
				),
				'js' => array(
					// '//code.jquery.com/ui/1.11.4/foobar.js'
				)
			),
			'#markup' => $this->render('expert',array(
				'query'					=> $soql,
				'debug'					=> isset($_GET['debug']) ? true : false,
				'error' 				=> $error,
				'contacts' 			=> $sfResult->fetchAll()
			)),
		);
		
	}
	
	public function doApiRecordUpdate($sObjectName,$Id,$data)
	{
		global $resources;
		if(!isset($Id))
		{
			throw new \Exception('No valid record Id given.');
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
		
		$forceApi->setEndpoint('RECORD_UPDATE',array('sObject'=>$sObjectName,'Id'=>$Id));

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

		$sfResult = $forceApi->updateRecord();

		return $sfResult;
	}
	

	private function formatParams($input,$filter=null)
	{
		$validParams = array_intersect_key($input,array_flip($this->EXPERT_WITNESS_FIELDS));
		// Convert empty string/date values to null
		// Salesforce can't parse empty string in certain context (like Date)
		// because of the strict nature of JSON so we must explicitly declare them as null
		$formatted = array_map(function($val){
				return empty($val) ? null : $val;
			},$validParams);
		return $formatted;
	}

	
	public function updateExpert($contactId)
	{
		// Capture the body of the POST request here:
		$formattedParams = $this->formatParams($_POST);
		// print_r($formattedParams);exit;
		$sfResult = $this->doApiRecordUpdate('Contact',$contactId,$formattedParams);
		return $sfResult->hasError() ? array('error' => $sfResult->getErrorMsg()) : 
			array('success'=>true);
	}
	
	
	public function expertWitnessHome($contactId)
	{
		$this->addTemplateLocation(
			'sites/default/modules/experts/templates'
		);

		$soql = sprintf(\setting('directory.queries.expertWitness'),$contactId);
		$sfResult = $this->doApiRequest($soql);
	
		if(!$sfResult->count())
		{
			// throw new \Exception('We could not find this member ID.');
			$error = "We could not find this member ID.";
		}

		return array(
			'#attached' => array(
				'css' => array(
					'/sites/default/modules/experts/css/experts.css'
				),
				'js' => array(
					'//code.jquery.com/ui/1.11.4/foobar.js'
				)
			),
			'#markup' => $this->render('expert-edit',array(
				'query'					=> $soql,
				'debug'					=> isset($_GET['debug']) ? true : false,
				'error' 				=> $error,
				'contact' 			=> $sfResult->fetchAll()[0]
			)),
		);
	}
	
	public function expertWitnessEdit($contactId)
	{
		$this->addTemplateLocation(
			'sites/default/modules/experts/templates'
		);

		$soql = sprintf(\setting('directory.queries.expertWitness'),$contactId);
		$sfResult = $this->doApiRequest($soql);
	
		if(!$sfResult->count())
		{
			// throw new \Exception('We could not find this member ID.');
			$error = "We could not find this member ID.";
		}
		
		return array(
			'#attached' => array(
				'css' => array(
					'/sites/default/modules/experts/css/experts.css'
				),
				'js' => array(
					// '//code.jquery.com/ui/1.11.4/foobar.js'
				)
			),
			'#markup' => $this->render('expert-edit',array(
				'query'					=> $soql,
				'debug'					=> isset($_GET['debug']) ? true : false,
				'error' 				=> $error,
				'contact' 			=> $sfResult->fetchAll()[0]
			)),
		);
	}
	
	
	
	private function ewSearchForm(){
		global $twigPrimaryAreas;
		
		$this->addTemplateLocation(
			'sites/default/modules/experts/templates'
		);
		
		
		$rest = $this->getQueryInstance();	
					    	
		$sobject = $rest->getObjectInfo('Contact');

		$field = $sobject->getField('Ocdla_Expert_Witness_Primary__c');
		
		// print $field->getPicklistAsHtmlOptions();exit;		
		
		$form = $this->render('expert-search-form', array(
			'error' => $error,
			'primaryAreas' => $field->getPicklistAsHtmlOptions()
			// 'result' 	=> 'Imported '.$records->count() .' records: ' . $manager->getComments(),
			// 'queries' => $records->getComment('queries')
		));
		
		
		return $form;
	}
	
	
	/**
	 * @method ewSearchHome
	 *
	 * @description Landing page for the EW Search Home
	 */
	public function ewSearchHome()
	{
		switch($_SERVER['REQUEST_METHOD'])
		{
			case 'GET':
				return $this->ewSearchForm();
				break;
		
			case 'POST':
				$params = $_POST;
				$validAndParams = array_flip(array('LastName','FirstName','Ocdla_Organization__c','MailingCity','Ocdla_Occupation_Field_Type__c','Ocdla_County__c'));		
				
				$validAndParams = array_intersect_key($params,$validAndParams);
				
				
				$filteredAndParams = array_filter($validAndParams,function($val){
					return !empty($val);
				});

				$andParams = array_map(function($colVal,$colName){
					return $colName . " LIKE '%".  $colVal  ."%'";
				},$filteredAndParams,array_keys($filteredAndParams));
		
		
				$andParams[]= 'Ocdla_Is_Expert_Witness__c=True';
				
				$where = implode($andParams,' AND ');
				$where .= empty($params['Ocdla_Expert_Witness_Primary__c']) ? '' : " AND Ocdla_Expert_Witness_Primary__c includes ('".$params['Ocdla_Expert_Witness_Primary__c']."')";

				
				$select = 'SELECT Id, Ocdla_Occupation_Field_Type__c, Ocdla_Contact_ID__c, FirstName, LastName, Ocdla_Organization__c, MailingCity, Ocdla_Publish_Work_Phone__c, Ocdla_Publish_Work_Email__c, Ocdla_Publish_Mailing_Address__c, OrderApi__Work_Phone__c, OrderApi__Work_Email__c, Ocdla_Website__c, Ocdla_Expert_Witness_Last_Updated__c, Ocdla_Expert_Witness_Primary__c, Ocdla_Expert_Witness_Other_Areas__c FROM Contact WHERE ';
				
				if(!empty($filteredAndParams))
				{
					// $soql = $select . $where . ' ORDER BY Ocdla_Expert_Witness_Primary__c ASC, LastName ASC';
					$soql = $select . $where . ' ORDER BY LastName ASC';
				}
				else
				{
					$soql = $select . $where . ' ORDER BY LastName ASC';				
				}
				
			
				return $this->searchExperts($soql);
			
				break;
			}
	}
	
	
	/**
	 * @method searchExperts
	 *
	 * @params $query The SOQL query to be executed.
	 *
	 * @description Prepare a query from the selected form options.
	 */
	private function searchExperts($soql){

		$this->addTemplateLocation(
			'sites/default/modules/experts/templates'
		);
		
		try
		{
			$sfResult = $this->doApiRequest($soql);

			$results = $sfResult->fetchAll();
		
			// OK we need to sort the array here
			usort($results,function($a, $b){
				$aa = strtolower($a['Ocdla_Expert_Witness_Primary__c']);
				$bb = strtolower($b['Ocdla_Expert_Witness_Primary__c']);
				$aLast = strtolower($a['LastName']);
				$bLast = strtolower($b['LastName']);
				return ($aLast == $bLast) ? 0 : ($aLast < $bLast ? -1 : 1);
				// return ($aa == $bb) ? ($aLast == $bLast ? 0 : ($aLast < $bLast ? -1 : 1)) : (($aa < $bb) ? -1 : 1);
			});

			if(!count($results))
			{
				// print $soql;
				throw new \Exception('Your query didn\'t return any results.');
			}

		}
		catch(\Exception $e)
		{
			$error = $e->getMessage();
		}
		
		
		return array(
			'#attached' => array(
				'css' => array(
					'/sites/default/modules/directory/css/directory.css',
					'/sites/default/modules/experts/css/experts.css'
				)
			),
			'#markup' => $this->render('search-results-experts', array(
				'debug' 		=> isset($_GET['debug']) ? true : false,
				'query' 		=> $soql,
				'numResults' => count($results),
				'link'			=> $link,
				'error'			=> $error,
				'showSubcategories' => false,
				'searchCategory' => $params['Ocdla_Expert_Witness_Primary__c'],
				'results'		=> $results)
			)
		);
	}
	
	
	public function expertWitnessSearch()
	{
		$this->addTemplateLocation(
			'sites/default/modules/experts/templates'
		);
	
		$mockExpert = array(
			'FirstName' => 'José',
			'LastName' => 'Bernal',
			
		);
		return array(
			'#attached' => array(
				'css' => array(
					'/sites/default/modules/directory/css/directory.css',
					'/sites/default/modules/experts/css/experts.css'
				),
				'js' => array(
					'//code.jquery.com/ui/1.11.4/foobar.js'
				)
			),
			'#markup' => $this->render('search',array(
				'notices' => array(
					'Wed Mar 2, 8 AM: New Documentation <a href="https://docs.google.com/drawings/d/1flIXCgB1b5RQhpBKuxG08Wy5kbk1LZFEecchaaxnMEs/edit" target="_new">"Event Registration Workflow"</a> was created.',
					'Wed Feb 24, 9 AM: Mailing addresses and Work Phone numbers for Contacts have been uploaded.',
					'Tue Feb 23, 10 AM: Sales Orders entered since Feb 1 have been re-imported into Salesforce.',
				),
				'lastUpdated' => 'Feb 22, 2016',
				'projects' => array()
			)),
		);
	}
	

}