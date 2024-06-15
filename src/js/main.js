jQuery(document).ready(function($) {

	var settingsError = "There has been an error. Please try again and check your <a href='" + ppsfwoo_ajax_var.settings_url + "'>WooCommerce PayPal Payments settings</a>.";

	listPlans();

	listWebhooks();

	$('.nav-tab-wrapper a').click(function(event) {
        event.preventDefault();
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.tab-content').hide();
        var selected_tab = $(this).attr('href');
        $("#" + selected_tab).fadeIn();
    });

    if(tab_subs_active) {
    	$('.tab-content').hide();
		$('.nav-tab-wrapper a.subs-list').click();
	} else {
		$('.nav-tab-wrapper a.nav-tab-active').click();
	}

	/*
	*	Show a Wordpress UI admin notice
	*/
	function showMsg(msg = "", type = "success") {
		$("#wpcontent").prepend(`<div style="z-index: 9999; position: fixed; width: 82%" class="notice notice-${type} is-dismissible single-cached"><p>${msg}</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>`);
		setTimeout(function(){
			$(".notice-dismiss").parent().fadeOut( "slow", function() {
				$(this).remove();
			});
		}, 5000);
		$(".notice-dismiss").click(function(e) {
			$(this).parent().remove();
		});
	}

	function do_ajax(action, success, args = {}) {
		var data = {
			'action': 'ppsfwoo_admin_ajax_callback',
			'method': action
		}
		
		if(!$.isEmptyObject(args)) {
			data = $.extend({}, data, args);
		}

		$.ajax({
			'type': "POST",
			'url': '/wp-admin/admin-ajax.php',
			'data': data,
			'success': success
		});
	}

	$('#subs-search').on('submit', function(e) {
    	e.preventDefault();
    	var email = $("#email-input").val();
    	if(!email) return;
        do_ajax('search_subscribers', function(r) {
			if("false" !== r) {
				$("#tab-subscribers .pagination, .button.export-table-data").hide();
				$("#reset").show();
				$("#subscribers").replaceWith(r);
			} else {
				showMsg("No users found with that email address.", "warning");
			}
		}, {
			'email': email
		});
    });

	function listPlans() {
		do_ajax('list_plans', function(r) {
			if(!r) return;
			var obj = JSON.parse(r),
				$table = $('#plans'),
				table_data = "";
			Object.keys(obj).forEach(plan_id => {
				var vals = Object.values(obj[plan_id]);
				table_data += `<tr class="plan-row"><td>${plan_id}</td><td>${vals[0]}</td><td>${vals[1]}</td><td>${vals[2]}</td></tr>`;
			});
			$table.find('.plan-row').remove();
			$table.append(table_data);
			$table.show();
		});
	}

	function listWebhooks() {
		do_ajax('list_webhooks', function(r) {
			if(!r) return;
			var obj = JSON.parse(r),
				$table = $('#webhooks'),
				table_data = "";
			Object.values(obj).forEach(hook => {
				table_data += `<tr class="webhook-row"><td>${hook['name']}</td><td>${hook['description']}</td></tr>`;
			});
			$table.find('.webhook-row').remove();
			$table.append(table_data);
			$table.show();
		});
	}

	/*
	*	Click handler for settings page. Actions are defined by the button element's id
	*/
	$("#refresh").click(function(e) {
		e.preventDefault();
		var $spinner = $(this).next('.spinner');
		$spinner.addClass('is-active');
		do_ajax('refresh_plans', function(r) {
			var response = JSON.parse(r);
			if(false !== response) {
				showMsg("Successfully refreshed active plans.");
				listPlans();
			} else {
				showMsg(settingsError, "error");
			}
			$spinner.removeClass('is-active');
		});
	});

	/*
	*	When saving options, reset the query string variables so the subscriber table
	*	loads the new ppsfwoo_rows_per_page
	*/
	$('#ppsfwoo_options').submit(function(event) {
        $element = $(this).find('input[name=_wp_http_referer]');
        var newValue = removeQueryStringParams($element.attr('value'));
        $element.attr('value', newValue);
    });

    function removeQueryStringParams(url) {
        var urlParts = url.split('?');
        if (urlParts.length >= 2) {
            var queryString = urlParts[1];
            var queryParams = queryString.split('&');
            var filteredParams = queryParams.filter(function(param) {
                return !param.startsWith('subs_page_num=') && !param.startsWith('var2=');
            });
            return urlParts[0] + '?' + filteredParams.join('&');
        }
        return url;
    }

    $("input[name=ppsfwoo_delete_plugin_data]").click(function(e) {
    	if($(this).is(':checked')) {
    		return confirm("Selecting this option will delete plugin options and the subscribers table on plugin deactivation. Are you sure you want to do this?\n\nThe setting will take effect after you 'Save Changes' below.");
    	}
    });
});
