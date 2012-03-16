<?php
/**
  Plugin Name: Event Espresso - Recurring Events
  Plugin URI: http://eventespresso.com/
  Description: Recurring Events addon for Event Espresso.

  Version: 1.1.5

  Author: Event Espresso
  Author URI: http://www.eventespresso.com

  Copyright (c) 2010 Abel Sekepyan  All Rights Reserved.

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */
/**
 * MAJOR CLEANUP PLANNED FOR V1.2.0
 */
register_activation_hook(__FILE__, 'event_espresso_re_install');
register_deactivation_hook(__FILE__, 'event_espresso_re_deactivate');

global $wpdb;
define("EVENT_ESPRESSO_RECURRENCE_TABLE", $wpdb->prefix . 'events_recurrence');
define("EVENT_ESPRESSO_RECURRENCE_PATH", "/" . plugin_basename(dirname(__FILE__)) . "/");
define("EVENT_ESPRESSO_RECURRENCE_FULL_PATH", WP_PLUGIN_DIR . EVENT_ESPRESSO_RECURRENCE_PATH);
define("EVENT_ESPRESSO_RECURRENCE_FULL_URL", WP_PLUGIN_URL . EVENT_ESPRESSO_RECURRENCE_PATH);
define("EVENT_ESPRESSO_RECURRENCE_MODULE_ACTIVE", TRUE);
define("EVENT_ESPRESSO_RECURRENCE_MODULE_VERSION", '1.1.2');


/*
 * Used for display, you can use any of the php date formats (http://php.net/manual/en/function.date.php) *
 */

define("EVENT_ESPRESSO_RECURRENCE_DATE_FORMAT", 'D, m/d/Y');

if (!function_exists('event_espresso_re_install')) {


	function event_espresso_re_install() {


		update_option('event_espresso_re_version', EVENT_ESPRESSO_RECURRENCE_MODULE_VERSION);
		update_option('event_espresso_re_active', 1);
		global $wpdb;

		$table_version = EVENT_ESPRESSO_RECURRENCE_MODULE_VERSION;

		$table_name = $wpdb->prefix . "events_recurrence";
		$sql = "CREATE TABLE " . $table_name . " (
	               `recurrence_id` int(11) NOT NULL AUTO_INCREMENT,
                      `recurrence_start_date` date NOT NULL,
                      `recurrence_event_end_date` date NOT NULL,
                      `recurrence_end_date` date NOT NULL,
                      `recurrence_regis_start_date` date NOT NULL,
                      `recurrence_regis_end_date` date NOT NULL,
                      `recurrence_frequency` tinytext NOT NULL,
                      `recurrence_interval` tinyint(4) NOT NULL,
                      `recurrence_weekday` varchar(255) NOT NULL,
                      `recurrence_type` tinytext NOT NULL,
                      `recurrence_repeat_by` tinytext NOT NULL,
                      `recurrence_regis_date_increment` tinytext NOT NULL,
                      `recurrence_manual_dates` LONGTEXT NULL,
                      `recurrence_visibility` varchar(2) DEFAULT NULL,
                      PRIMARY KEY (`recurrence_id`),
                      UNIQUE KEY `recurrence_id` (`recurrence_id`))";

		if ($wpdb->get_var("show tables like '$table_name'") != $table_name) {




			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);

			update_option($table_name . "_tbl_version", $table_version);
			update_option($table_name . "_tbl", $table_name);
		}

		$installed_ver = get_option($table_name . '_tbl_version');
		if ($installed_ver != $table_version) {
			$sql_create_table = "CREATE TABLE " . $table_name . " ( " . $sql . " ) ;";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql_create_table);
			update_option($table_name . '_tbl_version', $table_version);
		}
	}

}

if (!function_exists('event_espresso_re_deactivate')) {


	function event_espresso_re_deactivate() {

		update_option('event_espresso_re_active', 0);
	}

}

add_action('wp_ajax_show_recurring_dates', 'recurring_days');

if (!function_exists('recurring_days')) {


	function recurring_days() {
		global $wpdb;

		if ($_POST['recurrence_start_date'] == '' || $_POST['recurrence_end_date'] == '' || $_POST['recurrence_regis_start_date'] == '' || $_POST['recurrence_regis_end_date'] == '')
			exit("Continue selecting fields.");
		require('functions/re_functions.php');
		require('functions/re_view_functions.php');

		$re_params = array(
				'start_date' => $_POST['recurrence_start_date'],
				'event_end_date' => $_POST['recurrence_event_end_date'],
				'end_date' => $_POST['recurrence_end_date'],
				'registration_start' => $_POST['recurrence_regis_start_date'],
				'registration_end' => $_POST['recurrence_regis_end_date'],
				'type' => $_POST['recurrence_type'],
				'frequency' => $_POST['recurrence_frequency'],
				'interval' => $_POST['recurrence_interval'],
				'weekdays' => $_POST['recurrence_weekday'],
				'repeat_by' => $_POST['recurrence_repeat_by'],
				'recurrence_regis_date_increment' => $_POST['recurrence_regis_date_increment'],
				'recurrence_visibility' => $_POST['recurrence_visibility'],
				'recurrence_id' => $_POST['recurrence_id']
		);



		if ($_POST['recurrence_apply_changes_to'] == 3) {
			// This and upcoming events based on recurrence id and start_date >=start_date
			$re_params['start_date'] = $_POST['start_date'];
		}
		$recurrence_dates = find_recurrence_dates($re_params);
		//print_r($recurrence_dates);
		echo recurrence_table($recurrence_dates, __("Projected recurrences of this event.", 'event_espresso'), 1);

		die();
	}

}

function espresso_re_styles() {
	if (isset($_REQUEST['page'])) {
		switch ($_REQUEST['page']) {
			case ( 'events' ):
				wp_enqueue_style('recurring_events_style', EVENT_ESPRESSO_RECURRENCE_FULL_URL . 'css/recurring_events_style.css');
				break;
		}
	}
}

add_action('admin_print_styles', 'espresso_re_styles');

function espresso_event_editor_rem_footer() {
	?>
	<script type="text/javascript">


		//jeere : jQuery Event Espresso Recurring Events
		jeere = jQuery.noConflict();
		//GLOBAL
		function get_recurrence_change(){

			var ajax_loader_img = '<img src="' + '<?php echo EVENT_ESPRESSO_RECURRENCE_FULL_URL ?>' + 'images/ajax-loader.gif" alt="Recurring Event" />';

			/*var rm = jeere('#recurrence_message');
																		if( !rm.is(':visible') ){
																		rm.slideDown('slow');
																}*/

			jeere('#recurrence_ajax_response').html(ajax_loader_img);

			//if you assign a pre-existing key to recurrence_weekday, this won't work.  Ex. recurrence_weekday[0]
			var weekday_values = [];
			jeere.each(jeere("input[name='recurrence_weekday[]']:checked"), function() {
				weekday_values.push(jeere(this).val());
			});
			var recurrence_manual_dates = [];
			jeere.each(jeere("input[name='recurrence_manual_dates[]']"), function() {
				recurrence_manual_dates.push(jeere(this).val());
			});
			var recurrence_manual_end_dates = [];
			jeere.each(jeere("input[name='recurrence_manual_end_dates[]']"), function() {
				recurrence_manual_end_dates.push(jeere(this).val());
			});

			var data = {
				action: 'show_recurring_dates',
				start_date: jeere("input[name='start_date']").val(),
				recurrence_start_date: jeere("input[name='recurrence_start_date']").val(),
				recurrence_event_end_date: jeere("input[name='recurrence_event_end_date']").val(),
				recurrence_end_date: jeere("input[name='recurrence_end_date']").val(),
				recurrence_regis_start_date: jeere("input[name='recurrence_regis_start_date']").val(),
				recurrence_regis_end_date: jeere("input[name='recurrence_regis_end_date']").val(),
				recurrence_frequency: jeere("select[name='recurrence_frequency']").val(),
				recurrence_interval: jeere("select[name='recurrence_interval']").val(),
				recurrence_type: jeere("select[name='recurrence_type']").val(),
				recurrence_manual_dates: recurrence_manual_dates,
				recurrence_manual_end_dates: recurrence_manual_end_dates,
				recurrence_weekday: weekday_values,
				recurrence_repeat_by: jeere("input[name='recurrence_repeat_by']:checked").val(),
				recurrence_regis_date_increment: jeere("input[name='recurrence_regis_date_increment']:checked").val(),
				recurrence_apply_changes_to: jeere("input[name='recurrence_apply_changes_to']:checked").val(),
				recurrence_visibility: jeere("input[name='recurrence_visibility']").val(),
				recurrence_id: jeere("input[name='recurrence_id']").val()
			};

			//Pull the projected event recurrences.
			jeere.post(ajaxurl, data, function(response) {
				jeere('#recurrence_ajax_response').html( response);
			});




		}

		//Fire the function above based on the changes.

		jeere('.recurring_events_details :input, input[name="recurrence_apply_changes_to"]').change(function(){

			//Dont't fire if only this instance is being changed
			if (jeere(this).attr('name') == 'recurrence_apply_changes_to' && (jeere('#recurrence_field_changed').val() == 0 && jeere('input[name="recurrence_apply_changes_to"]:checked').val() == 1))
				return false;
			//Temporary, will ocmbine later
			if (jeere(this).attr('name') == 'recurrence_apply_changes_to' && jeere('#recurrence_field_changed').val() == 0)
				return false;

			// RE form changed
			jeere('#recurrence_field_changed').val(1);
			//If RE form changed and Only this instance is selected, warn
			if (jeere('input[name="recurrence_apply_changes_to"]:checked').val() == 1 && jeere('#recurrence_field_changed').val() == 1) {
				alert('<?php _e('If you are making changes to the recurrence formula, please select "All events in the series" or "All upcoming events" for the changes to take effect.', 'event_espresso') ?>');
				return false;
			}

			if(jeere('#recurrence_type').val() =='a')
				get_recurrence_change();


		});


		// END GLOBAL



		jQuery(document).ready(function(jeere) {

			/*jeere('#recurrence_manual_date_wrapper :input').live("change", function(){

																		// If using the clone method below, the last selected date will be automatically in
																		// the newly created field.  May confuse the user but can be useful
																		// Will wait for user input
																		//var template = jeere('#recurrence_manual_date_table tr:last').clone();

																		//jeere('input[name="recurrence_apply_changes_to"]')[1].checked = true;
																		jeere('input[name="recurrence_apply_changes_to"][value="2"]').attr("checked", true);
																		var template = jeere('#recurrence_manual_date_table tr:last').html();

																		jeere('#recurrence_manual_date_table').append('<tr>' + template + '</tr>');
																		jeere('#recurrence_manual_date_wrapper :input').removeClass('hasDatepicker').removeAttr('id')
																		.datepicker({
																				changeMonth: true,
																				changeYear: true,
																				dateFormat: "yy-mm-dd",
																				showButtonPanel: true}
																);
																		return false;
																});*/
			jeere('.recurrence_add_manual_dates').click(function(){

				// If using the clone method below, the last selected date will be automatically in
				// the newly created field.  May confuse the user but can be useful
				// Will wait for user input
				//var template = jeere('#recurrence_manual_date_table tr:last').clone();

				//jeere('input[name="recurrence_apply_changes_to"]')[1].checked = true;
				jeere('input[name="recurrence_apply_changes_to"][value="2"]').attr("checked", true);
				var template = jeere('#recurrence_manual_date_table tr:last').html();

				jeere('#recurrence_manual_date_table').append('<tr>' + template + '</tr>');
				jeere('#recurrence_manual_date_wrapper :input').removeClass('hasDatepicker').removeAttr('id')
				.datepicker({
					changeMonth: true,
					changeYear: true,
					dateFormat: "yy-mm-dd",
					showButtonPanel: true}
			);
				return false;
			});
			jeere('.recurrence_remove_manual_dates').live("click", function(){

				//if (confirm('Are you sure?') === false)
				//    return false;

				if(jeere("#recurrence_manual_date_table tr").size() == 1)
				{
					alert ("First element can't be deleted.");
					return false;
				}
				jeere(this).parents().eq(1).slideUp().remove();

				//jeere('input[name="recurrence_apply_changes_to"]').val(2);
				//jeere('input[name="recurrence_apply_changes_to"]')[1].checked = true;
				jeere('input[name="recurrence_apply_changes_to"][value="2"]').attr("checked", true);

			});

			jeere("#tabs").tabs({ selected: 1 });


			//Hide the RE forms on page load based on what is in the fields
			hide_fields ();


			/*jeere('#start_date').change(function(){

																		jeere('#recurrence_start_date').val(jeere(this).val());

																});*/

			//Hide/show fields depending if RE or not
			jeere('#recurrence').change(function(){

				hide_fields();

			});

			/*jeere('.recurring_events_details input[name!="recurrence_apply_changes_to"]').change(function(){
																		//jeere('#recurrence_field_changed').val(1);
																});*/



			//Will be used before submit to check for required fields
			var recurrence_required = ['start_date', 'recurrence_end_date','recurrence_interval'];

			//Show and hide weekdays and monthly fields based on frequency selection of weekly or monthly
			jeere('#recurrence_frequency').change(function(){
				hide_fields ();
				return false;

			});
			jeere('#recurrence_type').change(function(){
				hide_fields ();
				return false;

			});

			//Hide the RE forms based on what RE fields are selected
			function hide_fields (){

				if (jeere('#recurrence').val() == 'N') {
					jeere('.recurring_events_details').slideUp(400);
					jeere('.ov_scroll').hide();

				} else {
					jeere('.recurring_events_details').slideDown(400);
					jeere('.ov_scroll').show();
				}

				if (jeere('#recurrence_type').val() == 'm') {
					jeere('#recurrence_automatic').slideUp(400);
					jeere('#recurrence_manual').slideDown(400);
					jeere('#recurrence_summary').slideUp(400);
				} else {
					jeere('#recurrence_manual').slideUp(400);
					jeere('#recurrence_automatic').slideDown(400);
					jeere('#recurrence_summary').slideDown(400);
				}

				var val = jeere("#recurrence_frequency").val();

				switch (val) {
					case 'd':
						jeere('#recurrence_period_other').html('<?php _e('days', 'event_espresso'); ?>');
						jeere('#recurrence_weekday_on').hide();
						jeere('#recurrence_repeat_by').hide();

						break;
					case 'w':
						jeere('#recurrence_period_other').html('<?php _e('weeks', 'event_espresso'); ?>');
						jeere('#recurrence_weekday_on').show();
						jeere('#recurrence_repeat_by').hide();
						break;
					case 'm':
						jeere('#recurrence_period_other').html('<?php _e('months', 'event_espresso'); ?>');
						jeere('#recurrence_repeat_by').show();
						jeere('#recurrence_weekday_on').hide();
						break;
					default:
						jeere('#recurrence_period_other').html('');
						jeere('#recurrence_repeat_by').html('');
						break;
					}

				}



				jeere('form').submit(function(){
					//alert(jeere('form').serialize());
					//alert(jeere("input[name='recurrence_weekday[]']:checked").length);
					//check_inputs();
					//return false;

				});

				//Will be used in the next version to check for required fields
				function check_inputs(){

					if(jeere('#recurrence').val() == 'N'){

						return false;
					}

					var l = recurrence_required.length;
					for (var i = 0;  i < l; i++) {
						if (jeere('#'+recurrence_required[i]).val() == '') {
							jeere('#'+recurrence_required[i]).css({'background-color' :'pink'});
						}


					}
					return false;


				};







			});


	</script>
	<?php
}

add_action('action_hook_espresso_event_editor_footer', 'espresso_event_editor_rem_footer');

function espresso_register_rem_metaboxes() {
	$screen = get_current_screen();
	if ($screen->id == 'toplevel_page_events'
					&& isset($_REQUEST['action'])
					&& ($_REQUEST['action'] == 'edit'
					|| $_REQUEST['action'] == 'add_new_event')) {
		require_once(EVENT_ESPRESSO_RECURRENCE_FULL_PATH . 'functions/re_view_functions.php');
		add_meta_box('espresso_event_editor_rem', __('Recurring Event Manager', 'event_espresso'), 'event_espresso_re_form', 'toplevel_page_events', 'normal', 'core');
	}
}

add_action('current_screen', 'espresso_register_rem_metaboxes', 10);
