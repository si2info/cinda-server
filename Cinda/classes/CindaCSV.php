<?php

namespace cinda;

use \cinda\API\CampaignsList;
use \cinda\API\Campaign as API_Campaign;
use \cinda\API\Volunteer as API_Volunteer;
use cinda\API\ContributionsList;

class CindaCSV{
	private $csv;
	private $name;
	
	function __construct(){

		if( isset($_POST[CINDA_PREFIX.'export_name']) && $_POST[CINDA_PREFIX.'export_name'] != "")
			$this->name = $_POST[CINDA_PREFIX.'export_name'];
		else
			$this->name = CINDA_NAME . '-Export';
		
		if( is_admin() ){
			
			if( isset($_POST[CINDA_PREFIX.'export_action']) ){
	
				switch ( $_POST[CINDA_PREFIX.'export_action'] ){
					case "campaigns_list":
						$action = 'export_campaigns_list';
						$id = null;
						break;
					case 'campaign':
						$action = 'export_campaign';
						$id = isset($_POST[CINDA_PREFIX.'export_id']) ? $_POST[CINDA_PREFIX.'export_id'] : null;
						break;
					case 'contributions':
						$action = 'export_contributions';
						$id = isset($_POST[CINDA_PREFIX.'export_id']) ? $_POST[CINDA_PREFIX.'export_id'] : null;
						break;
					default:
						$action = null;
						$id = null;
						break;
				}
				
				if($action){
					$this->$action( $id );
				}
				
			}
			
		}
	}
	
	
	function export_campaigns_list(){
		
		$this->open();
		
		// CSV Columns name
		fputcsv( $this->csv, array('ID', 'title', 'image', 'description', 'date_start', 'date_end', 'geographical_scope') );
		
		$args = array('extended'=>true);
		
		// Export All
		if(isset($_POST[CINDA_PREFIX.'export_all']) && $_POST[CINDA_PREFIX.'export_all'] == 1){
			$args['all'] = true;
		}
		// Export Between dates
		else if(isset($_POST[CINDA_PREFIX.'export_all']) && $_POST[CINDA_PREFIX.'export_all'] == 2){
			// Date Start
			if(isset($_POST[CINDA_PREFIX.'export_date_start']))
				$args['date_start'] = $_POST[CINDA_PREFIX.'export_date_start'];
			// Date End
			if(isset($_POST[CINDA_PREFIX.'export_date_end']))
				$args['date_end'] = $_POST[CINDA_PREFIX.'export_date_end'];
		}
		
		$campaingList = new CampaignsList( $args );
		
		foreach($campaingList->get_campaigns() as $campaign){
			fputcsv($this->csv, array(
				$campaign->ID,
				htmlentities( $campaign->title ),
				urlencode( $campaign->image ),
				htmlentities( $campaign->description_extended ),
				$campaign->date_start,
				$campaign->date_end,
				$campaign->scope
			));
		}
		
		$this->close();
		
	}
	
	function export_campaign($id){
		
		if(! $id )
			return;
		
		$this->open();
		
		$campaign = new API_Campaign( $id );
		
		// Headers
		fputcsv( 
			$this->csv, array(
				'ID',
				'title', 
				'image', 
				'description', 
				'date_start', 
				'date_end', 
				'geographical_scope'
			) 
		);
		// Content
		fputcsv(
			$this->csv, array(
				$campaign->ID,
				htmlentities( $campaign->title ),
				urlencode( $campaign->image ),
				htmlentities( $campaign->description_extended ),
				$campaign->date_start,
				$campaign->date_end,
				$campaign->scope
			)
		);
	
		$this->close();
		
	}
	
	function export_contributions($id_campaign){
	
		$model = \cinda\CindaQuery::get_model($id_campaign);
		
		if($model){
			
			$this->open();
			
			
			foreach($model as $field){
				$headers[] = $field->field_name;
			}
			
			$headers[] = "create_date";
			$headers[] = "author_name";
			
			// Insert headers to CSV
			fputcsv( $this->csv, $headers );
			
			$contributionList = new ContributionsList($id_campaign);
			$contributions = $contributionList->get_contributions();
			
			if(0 < count($contributions)){
				foreach ($contributions as $contribution){
				
					$data = array();
				
					foreach($model as $field){

						$name = $field->field_name;
							
						if( isset( $contribution->data[$name] ) )
							$data[] = htmlentities( $contribution->data[$name] );
						else
							$data[] = "";
							
					}
					
					$data[] = $contribution->data['create_date'];
					$data[] = $contribution->data['author_name'];
				
					// Insert row to CSV
					fputcsv( $this->csv, $data );
				
				}
			}			
			
			$this->close();
			
		}else{
			return;
		}
	
	}
	
	function export_volunteers(){
		
		
	}
	
	function open(){
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="'.$this->name.'-'.date('Y-m-d').'.csv"');
		header('Pragma: no-cache');
		header('Expires: 0');
		
		$this->csv = fopen('php://output', 'w');
	}
	
	function close(){
		fclose( $this->csv );
		$this->csv = null;
		exit;
	}
	
}