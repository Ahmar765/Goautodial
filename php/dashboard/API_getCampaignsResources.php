<?php
/**
 * @file        API_getCampaignsResources.php
 * @brief       Displays campaigns with lead counts (goAPI or local DB)
 * @copyright   Copyright (c) 2020 GOautodial Inc.
 */

	require_once('APIHandler.php');
	
	$api 										= \creamy\APIHandler::getInstance();
	$output 									= $api->API_getCampaignsResources();
	
    if ( !is_object($output) || empty($output->data) || !is_array($output->data) ) {
        echo '<div class="media-box">
				<div class="pull-left">
				   <span class="fa-stack">
					  <em class="fa fa-circle fa-stack-2x text-success"></em>
					  <em class="fa fa-clock-o fa-stack-1x fa-inverse text-white"></em>
				   </span>
				</div>
				<div class="media-box-body clearfix">
				   <div class="media-box-heading"><a href="./telephonycampaigns.php" class="text-success m0">No Available Campaign</a>
				   </div>
				   <p class="m0"><small class="text-muted">Create or activate a campaign under Telephony → Campaigns.</small></p>
				</div>
			 </div>';

    } else {  
		$max 									= 0;
                
        foreach ($output->data as $key => $value) {        
            if(++$max > 6) break; 
            
			$campname 							= $api->escapeJsonString($value->campaign_name ?? '');
			$leadscount 						= $api->escapeJsonString($value->mycnt ?? 0);
			$campid 							= $api->escapeJsonString($value->campaign_id ?? '');
			$localcalltime 						= $api->escapeJsonString($value->local_call_time ?? '9am-9pm');
    
            $sessionAvatar 					= "<avatar username='$localcalltime' :size='32'></avatar>";
            
			echo '<div class="media-box">
				<div class="pull-left">
					'.$sessionAvatar.'
				</div>                                                
					<div class="media-box-body clearfix">
						<small class="text-muted pull-right ml">'.$leadscount.'</small>
						<div class="media-box-heading"><strong><a id="onclick-campaigninfo" data-toggle="modal" data-target="#view_campaign_information" data-id="'.$campid.'" class="text m0">'.$campname.'</strong></a>
						</div>
							<p class="m0">
								<small><strong><a id="onclick-campaigninfo" data-toggle="modal" data-target="#view_campaign_information" data-id="'.$campid.'" class="text-black">'.$campid.'</strong></a>
								</small>
							</p>
					</div>
				</div>
			</div>';
        }
    }
    
?>
