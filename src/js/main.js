jQuery(document).ready(function($) {

	var settingsError = "There has been an error. Please try again and check your <a href='" + ppsfwoo_ajax_var.settings_url + "'>WooCommerce PayPal Payments settings</a>.";

	ppsfwooOptionsPageInit();

	$('#subs-search').on('submit', function(e) {
    	e.preventDefault();
    	var email = $("#email-input").val();
    	if(!email) return;
        ppsfwooDoAjax('search_subscribers', function(r) {
			if("false" !== r) {
				$("#tab-subscribers .pagination, .button.export-table-data").hide();
				$("#reset").show();
				$("#subscribers").replaceWith(r);
			} else {
				ppsfwooShowMsg("No users found with that email address.", "warning");
			}
		}, {
			'email': email
		});
    });

    $("#refresh").click(function(e) {
		e.preventDefault();
		ppsfwooShowLoadingMessage('Processing...');
		ppsfwooDoAjax('refresh_plans', function(r) {
			var response = JSON.parse(r);
			if(0 === response.plans.length) {
				ppsfwooShowMsg("No plans found.", "warning");
			} else if(response.success) {
				ppsfwooShowMsg("Successfully refreshed plans.");
				ppsfwooOptionsPageInit();
			} else {
				ppsfwooShowMsg(settingsError, "error");
			}
			ppsfwooHideLoadingMessage();
		});
	});

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
	function ppsfwooShowMsg(msg = "", type = "success") {
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

	function ppsfwooDoAjax(action, success, args = {}) {
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

    function ppsfwooBindClickHandlers() {
    	$("a.deactivate, a.activate").click(function(e) {
	    	e.preventDefault();
	    	ppsfwooShowLoadingMessage('Processing...');
	    	var plan_id = $(this).data('plan-id'),
	    		paypal_action = $(this).attr('class');
	    	ppsfwooDoAjax('modify_plan', function(r) {
	    		var response = JSON.parse(r);
	    		if(response.error) {
	    			alert(response.error);
	    			ppsfwooHideLoadingMessage();
	    		} else {
	    			ppsfwooOptionsPageInit();
	    		}
	    	}, {
				'plan_id': plan_id,
				'paypal_action': paypal_action
			});
	    });
	    $('.plan-row').on('click', '.copy-button', function(e) {
			var copyText = $(this).prev('.copy-text'),
				tempTextarea = $('<textarea>');
			tempTextarea.val(copyText.text());
			$('body').append(tempTextarea);
			tempTextarea.select();
			tempTextarea[0].setSelectionRange(0, 99999);
			document.execCommand('copy');
			tempTextarea.remove();
			alert('Copied to clipboard: ' + copyText.text());
			e.preventDefault();
		});
    }

	function ppsfwooOptionsPageInit() {
		ppsfwooDoAjax('list_plans', function(r) {
			var obj = r ? JSON.parse(r) : false,
				$table = $('#plans'),
				table_data = "",
				have_plans = false;
			if(!obj) {
				$("#refresh, #create")
					.unbind()
					.click(function(e) {
						e.preventDefault();
						var choice = confirm("Click 'OK' to configure your WooCommerce PayPal Payments settings.\nClick 'Cancel' to stay on this page.");
						if (choice) {
						    window.location.assign(ppsfwoo_ajax_var.settings_url);
						}
					});
				return;
			}
			Object.keys(obj).forEach(plan_id => {
				var vals = Object.values(obj[plan_id]),
					plan_active = "ACTIVE" === vals[3],
					paypal_action = "",
					status_indicator = "";
				have_plans = "000" !== plan_id;
				if(have_plans) {
					paypal_action = plan_active ?
						`<a href="#" class="deactivate" data-plan-id="${plan_id}">Deactivate</a>`:
						`<a href="#" class="activate" data-plan-id="${plan_id}">Activate</a>`;

					status_indicator = plan_active ?
						`<span class="tooltip status green"><span class="tooltip-text">${vals[3]}</span></span>`:
						`<span class="tooltip status red"><span class="tooltip-text">${vals[3]}</span></span>`;
				}
				table_data += `<tr class="plan-row"><td>${plan_id}</td><td>${vals[0]}</td><td>${vals[1]}</td><td>${vals[2]}</td><td><p class="copy-text" style="position: absolute; left: -9999px;">${ppsfwoo_ajax_var.paypal_url}/webapps/billing/plans/subscribe?plan_id=${plan_id}</p><button class="copy-button">Copy to clipboard</button></td><td>${status_indicator}</td><td>${paypal_action}</td></tr>`;
			});
			$table.find('.plan-row').remove();
			$table.append(table_data);
			$table.show();
			ppsfwooListWebhooks();
			ppsfwooBindClickHandlers();
			ppsfwooHideLoadingMessage();
		});
	}

	function ppsfwooListWebhooks() {
		ppsfwooDoAjax('list_webhooks', function(r) {
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
	*	When saving options, reset the query string variables so the subscriber table
	*	loads the new ppsfwoo_rows_per_page
	*/
	$('#ppsfwoo_options').submit(function(event) {
        $element = $(this).find('input[name=_wp_http_referer]');
        var newValue = ppsfwooRemoveQueryStringParams($element.attr('value'));
        $element.attr('value', newValue);
    });

    function ppsfwooRemoveQueryStringParams(url) {
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

	function ppsfwooShowLoadingMessage(message) {
	    var overlay = document.createElement('div');
	    overlay.setAttribute('role', 'overlay');
	    overlay.style.position = 'fixed';
	    overlay.style.top = '0';
	    overlay.style.left = '0';
	    overlay.style.width = '100%';
	    overlay.style.height = '100%';
	    overlay.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
	    overlay.style.zIndex = '1000';

	    var messageBox = document.createElement('div');
	    messageBox.style.position = 'absolute';
	    messageBox.style.top = '50%';
	    messageBox.style.left = '50%';
	    messageBox.style.transform = 'translate(-50%, -50%)';
	    messageBox.style.backgroundColor = 'white';
	    messageBox.style.padding = '20px';
	    messageBox.style.borderRadius = '5px';
	    messageBox.style.boxShadow = '0 0 10px rgba(0, 0, 0, 0.3)';
	    messageBox.textContent = message || 'Please wait...';
	    overlay.appendChild(messageBox);
	    document.body.appendChild(overlay);
	}

	function ppsfwooHideLoadingMessage() {
	    var overlay = document.querySelector('div[role="overlay"]');
	    if (overlay) {
	        overlay.parentNode.removeChild(overlay);
	    }
	}

});
