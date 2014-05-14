<?php
//This is a template file for displaying a list of events on a page. These functions are used with the {ESPRESSO_EVENTS} shortcode.
//This is an group of functions for querying all of the events in your databse. 
//This file should be stored in your "/wp-content/uploads/espresso/templates/" directory.
//Note: All of these functions can be overridden using the "Custom Files" addon. The custom files addon also contains sample code to display ongoing events

if (!function_exists('display_all_events')) {
	function display_all_events() {
		event_espresso_get_event_details(array());
	}
}

if (!function_exists('display_event_espresso_categories')) {
	function display_event_espresso_categories($event_category_id=NULL, $css_class=NULL) {
		event_espresso_get_event_details(array('category_identifier' => $event_category_id, 'css_class' => $css_class));
	}
}

//Events Listing - Shows the events on your page.
if (!function_exists('event_espresso_get_event_details')) {

	function event_espresso_get_event_details( $attributes ) {
		//echo $sql; 
		global $wpdb, $org_options, $events_in_session;
		
		$template_name = ( 'event_list_display.php' );
		$path = locate_template( $template_name );
		
		$multi_reg = false;
		if (function_exists('event_espresso_multi_reg_init')) {
			$multi_reg = true;
		}
		
		$default_attributes = array('category_identifier' => NULL
		 							, 'staff_id' => NULL
									, 'allow_override' => 0
									, 'show_expired' => 'false'
									, 'show_secondary' => 'false'
									, 'show_deleted' => 'false'
									, 'show_recurrence' => 'true'
									, 'limit' => '0'
									, 'order_by' => 'NULL'
									, 'css_class' => 'NULL');
		// loop thru default atts
		foreach ($default_attributes as $key => $default_attribute) {
			// check if att exists
			if (!isset($attributes[$key])) {
				$attributes[$key] = $default_attribute;
			}
		}

		// now extract shortcode attributes
		extract($attributes);
		$sql = "SELECT e.*, ese.start_time, ese.end_time, p.event_cost ";
		
		//Category sql
		$sql .= ($category_identifier != NULL && !empty($category_identifier))? ", c.category_name, c.category_desc, c.display_desc, c.category_identifier": '';
		
		//Venue sql
		isset($org_options['use_venue_manager']) && $org_options['use_venue_manager'] == 'Y' ? $sql .= ", v.name venue_name, v.address venue_address, v.city venue_city, v.state venue_state, v.zip venue_zip, v.country venue_country, v.meta venue_meta " : '';
		
		//Staff sql
		isset($org_options['use_personnel_manager']) && $org_options['use_personnel_manager'] == 'Y' ? $sql .= ", st.name staff_name " : '';
		
		
		$sql .= " FROM " . EVENTS_DETAIL_TABLE . " e ";
		$sql .= ($category_identifier != NULL && !empty($category_identifier))? " JOIN " . EVENTS_CATEGORY_REL_TABLE . " r ON r.event_id = e.id  JOIN " . EVENTS_CATEGORY_TABLE . " c ON  c.id = r.cat_id ":'';
		
		//Venue sql
		isset($org_options['use_venue_manager']) && $org_options['use_venue_manager'] == 'Y' ? $sql .= " LEFT JOIN " . EVENTS_VENUE_REL_TABLE . " vr ON vr.event_id = e.id LEFT JOIN " . EVENTS_VENUE_TABLE . " v ON v.id = vr.venue_id " : '';
		
		//Venue sql
		isset($org_options['use_personnel_manager']) && $org_options['use_personnel_manager'] == 'Y' ? $sql .= " LEFT JOIN " . EVENTS_PERSONNEL_REL_TABLE . " str ON str.event_id = e.id LEFT JOIN " . EVENTS_PERSONNEL_TABLE . " st ON st.id = str.person_id " : '';
		
		$sql .= " LEFT JOIN " . EVENTS_START_END_TABLE . " ese ON ese.event_id= e.id ";
		$sql .= " LEFT JOIN " . EVENTS_PRICES_TABLE . " p ON p.event_id=e.id ";
		$sql .= " WHERE is_active = 'Y' ";
		
		//Category sql
		$sql .= ($category_identifier !== NULL  && !empty($category_identifier))? " AND c.category_identifier = '" . $category_identifier . "' ": '';
		
		//Staff sql
		$sql .= ($staff_id !== NULL  && !empty($staff_id))? " AND st.id = '" . $staff_id . "' ": '';
		
		$sql .= $show_expired == 'false' ? " AND (e.start_date >= '" . date('Y-m-d') . "' OR e.event_status = 'O' OR e.registration_end >= '" . date('Y-m-d') . "') " : '';
		if  ($show_expired == 'true'){
			$allow_override = 1;
		}
		
		//If using the [ESPRESSO_VENUE_EVENTS] shortcode
		$sql .= isset($use_venue_id) && $use_venue_id == true ? " AND v.id = '".$venue_id."' " : '';
		
		$sql .= $show_secondary == 'false' ? " AND e.event_status != 'S' " : '';
		$sql .= $show_deleted == 'false' ? " AND e.event_status != 'D' " : " AND e.event_status = 'D' ";
		if  ($show_deleted == 'true'){
			$allow_override = 1;
		}
		
		$sql .= $show_recurrence == 'false' ? " AND e.recurrence_id = '0' " : '';
		$sql .= " GROUP BY e.id ";
		$sql .= $order_by != 'NULL' ? " ORDER BY " . $order_by . " ASC " : " ORDER BY e.recurrence_id, date(start_date) ASC ";
		$sql .= $limit > 0 ? ' LIMIT 0, '.$limit : '';  
		
		//echo $sql;
		$event_page_id = $org_options['event_page_id'];
		$currency_symbol = isset($org_options['currency_symbol']) ? $org_options['currency_symbol'] : '';
		$events = $wpdb->get_results($sql);
		$category_id = isset($wpdb->last_result[0]->id) ? $wpdb->last_result[0]->id : '';
		$category_name = isset($wpdb->last_result[0]->category_name) ? $wpdb->last_result[0]->category_name : '';
		$category_identifier = isset($wpdb->last_result[0]->category_identifier) ? $wpdb->last_result[0]->category_identifier : '';
		$category_desc = isset($wpdb->last_result[0]->category_desc) ? html_entity_decode(wpautop($wpdb->last_result[0]->category_desc)) : '';
		$display_desc = isset($wpdb->last_result[0]->display_desc) ? $wpdb->last_result[0]->display_desc : '';
		
		/* group recurring events */
		$events_type_index = -1;
		$events_of_same_type = array();
		$last_recurrence_id = null;
		/* end group recurring events */
		
		if ($display_desc == 'Y'){
			echo '<p id="events_category_name-'. $category_id . '" class="events_category_name">' . stripslashes_deep($category_name) . '</p>';
			echo wpautop($category_desc);				
		}
		
		foreach ($events as $event) {
			
			$event_id = $event->id;
			$event_name = $event->event_name;
			$event_desc = stripslashes_deep($event->event_desc);
			$event_identifier = $event->event_identifier;
			$active = $event->is_active;
			$registration_start = $event->registration_start;
			$registration_end = $event->registration_end;
			$start_date = $event->start_date;
			$end_date = $event->end_date;
			$reg_limit = $event->reg_limit;
			$event_address = $event->address;
			$event_address2 = $event->address2;
			$event_city = $event->city;
			$event_state = $event->state;
			$event_zip = $event->zip;
			$event_country = $event->country;
			$member_only = $event->member_only;
			$externalURL = $event->externalURL;
			$recurrence_id = $event->recurrence_id;
			$display_reg_form = $event->display_reg_form;
			$allow_overflow = $event->allow_overflow;
			$overflow_event_id = $event->overflow_event_id;
			$event_desc = array_shift(explode('<!--more-->', $event_desc));
			global $event_meta;
			$event_meta = unserialize($event->event_meta);
			$event_meta['is_active'] = $event->is_active;
			$event_meta['event_status'] = $event->event_status;
			$event_meta['start_time'] = empty($event->start_time) ? '' : $event->start_time;
			$event_meta['start_date'] = $event->start_date;
			$event_meta['registration_start'] = $event->registration_start;
			$event_meta['registration_startT'] = $event->registration_startT;
			$event_meta['registration_end'] = $event->registration_end;
			$event_meta['registration_endT'] = $event->registration_endT;

			//Venue information
			if ($org_options['use_venue_manager'] == 'Y') {
				$event_address = empty($event->venue_address) ? '' : $event->venue_address;
				$event_address2 = empty($event->venue_address2) ? '' : $event->venue_address2;
				$event_city = empty($event->venue_city) ? '' : $event->venue_city;
				$event_state = empty($event->venue_state) ? '' : $event->venue_state;
				$event_zip = empty($event->venue_zip) ? '' : $event->venue_zip;
				$event_country = empty($event->venue_country) ? '' : $event->venue_country;

				//Leaving these variables intact, just in case people want to use them
				$venue_title = empty($event->venue_name) ? '' : $event->venue_name;
				$venue_address = $event_address;
				$venue_address2 = $event_address2;
				$venue_city = $event_city;
				$venue_state = $event_state;
				$venue_zip = $event_zip;
				$venue_country = $event_country;
				global $venue_meta;
				$add_venue_meta = array(
					'venue_title' => $venue_title,
					'venue_address' => $event_address,
					'venue_address2' => $event_address2,
					'venue_city' => $event_city,
					'venue_state' => $event_state,
					'venue_country' => $event_country,
				);
				$venue_meta = (!empty($event->venue_meta) && !empty($add_venue_meta)) ? array_merge(unserialize($event->venue_meta), $add_venue_meta) : '';
				//print_r($venue_meta);
			}

			//Address formatting
			$location = ($event_address != '' ? $event_address : '') . ($event_address2 != '' ? '<br />' . $event_address2 : '') . ($event_city != '' ? '<br />' . $event_city : '') . ($event_state != '' ? ', ' . $event_state : '') . ($event_zip != '' ? '<br />' . $event_zip : '') . ($event_country != '' ? '<br />' . $event_country : '');

			//Google map link creation
			$google_map_link = espresso_google_map_link(array('address' => $event_address, 'city' => $event_city, 'state' => $event_state, 'zip' => $event_zip, 'country' => $event_country, 'text' => 'Map and Directions', 'type' => 'text'));
			global $all_meta;
			$all_meta = array(
				'event_name' => stripslashes_deep($event_name),
				'event_desc' => stripslashes_deep($event_desc),
				'event_address' => $event_address,
				'event_address2' => $event_address2,
				'event_city' => $event_city,
				'event_state' => $event_state,
				'event_zip' => $event_zip,
				'is_active' => $event->is_active,
				'event_status' => $event->event_status,
				'start_time' => empty($event->start_time) ? '' : $event->start_time,
				'registration_startT' => $event->registration_startT,
				'registration_start' => $registration_start,
				'registration_endT' => $event->registration_endT,
				'registration_end' => $registration_end,
				'is_active' => empty($is_active) ? '' : $is_active,
				'event_country' => $event_country,
				'start_date' => event_date_display($start_date, get_option('date_format')),
				'end_date' => event_date_display($end_date, get_option('date_format')),
				'time' => empty($event->start_time) ? '' : $event->start_time,
				'google_map_link' => $google_map_link,
				'price' => empty($event->event_cost) ? '' : $event->event_cost,
				'event_cost' => empty($event->event_cost) ? '' : $event->event_cost,
			);
			//Debug
			//echo '<p>'.print_r($all_meta).'</p>';
			//These variables can be used with other the espresso_countdown, espresso_countup, and espresso_duration functions and/or any javascript based functions.
			//Warning: May cause additional database queries an should only be used for sites with a small amount of events.
			// $start_timestamp = espresso_event_time($event_id, 'start_timestamp');
			//$end_timestamp = espresso_event_time($event_id, 'end_timestamp');

			//This can be used in place of the registration link if you are using the external URL feature
			$registration_url = $externalURL != '' ? $externalURL : espresso_reg_url($event_id);
			if (!is_user_logged_in() && get_option('events_members_active') == 'true' && $member_only == 'Y') {
				//Display a message if the user is not logged in.
				//_e('Member Only Event. Please ','event_espresso') . event_espresso_user_login_link() . '.';
			} else {
				//Serve up the event list
				//As of version 3.0.17 the event list details have been moved to event_list_display.php
	            
		 		switch (event_espresso_get_status($event_id)){
						case 'NOT_ACTIVE':
							//Don't show the event if any of the above are true
						break;
						
						default:
						    /* skip secondary (waitlist) events */
						    $event_status = event_espresso_get_is_active($event_id);
						    if ($event_status['status'] == 'SECONDARY') {
						        break;
						    }						    
						    /* group recurring events */
						    $is_new_event_type = $last_recurrence_id == 0 || $last_recurrence_id != $recurrence_id;
    				        if ($is_new_event_type) :
    				            $events_type_index++;
                                $events_of_same_type[$events_type_index] = array();
                            endif;

    					    $event_data = array(
                                'event_id' => $event_id,
                                'event_page_id' => $event_page_id,
                                'event_name' => $event_name,
                                'event_desc' => $event_desc,
                                'start_date' => $start_date,
                                'end_date' => $end_date,
                                'reg_limit' => $reg_limit,
                                'registration_url' => $registration_url,
                                'allow_overflow' =>$allow_overflow,
                                'overflow_event_id' => $overflow_event_id
                            );
    					    array_push($events_of_same_type[$events_type_index], $event_data);
    						$last_recurrence_id = $recurrence_id;
						    
						break;
				}
			} 
		}
		
		/* group recurring events */
		foreach ($events_of_same_type as $events_group) {
		    if ( empty( $path ) ) {
				include( $template_name );
			} else {
				include( $path );
			}
    }
		/* end group recurring events */
		
	//Check to see how many database queries were performed
	//echo '<p>Database Queries: ' . get_num_queries() .'</p>';
	espresso_registration_footer();
	}
}

function espresso_hide_recurring_events() {
    if( wp_script_is( 'jquery', 'done' ) ) {
    ?>
    <script type="text/javascript">
    	jQuery('.subpage_excerpt .date_picker').css({'display': 'none'});
    </script>
    <?php
    }
}
add_action( 'wp_footer', 'espresso_hide_recurring_events' );