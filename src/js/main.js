function ppsfwooDoAjax(action, success, args = {}) {
	var data = {
		'action': 'ppsfwoo_admin_ajax_callback',
		'method': action
	}
	
	if(!jQuery.isEmptyObject(args)) {
		data = jQuery.extend({}, data, args);
	}

	jQuery.ajax({
		'type': "POST",
		'url': '/wp-admin/admin-ajax.php',
		'data': data,
		'success': success
	});
}

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

/*
*	Show a Wordpress UI admin notice
*/
function ppsfwooShowMsg(msg = "", type = "success") {
	jQuery("#wpcontent").prepend(`<div style="z-index: 9999; position: fixed; width: 82%" class="notice notice-${type} is-dismissible single-cached"><p>${msg}</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>`);
	setTimeout(function(){
		jQuery(".notice-dismiss").parent().fadeOut( "slow", function() {
			jQuery(this).remove();
		});
	}, 5000);
	jQuery(".notice-dismiss").click(function(e) {
		jQuery(this).parent().remove();
	});
}

jQuery(document).ready(function($) {

	var selected_tab;

	function ppsfwooOptionsPageInit() {
		ppsfwooBindClickHandlers();
		ppsfwooHideLoadingMessage();
	}

	function ppsfwooRefreshPage(tab = "tab-plans") {
		let currentUrl = ppsfwooRemoveQueryStringParams(window.location.href);
    	let newUrl = currentUrl + '&tab=' + tab;
    	window.location.href = newUrl;
	}

	function ppsfwooRemoveQueryStringParams(url) {
        var urlParts = url.split('?');
        if (urlParts.length >= 2) {
            var queryString = urlParts[1];
            var queryParams = queryString.split('&');
            var filteredParams = queryParams.filter(function(param) {
                return !param.startsWith('subs_page_num=') && !param.startsWith('tab=');
            });
            return urlParts[0] + '?' + filteredParams.join('&');
        }
        return url;
    }

    function ppsfwooBindClickHandlers() {
    	$("a.deactivate, a.activate").click(function(e) {
	    	e.preventDefault();
	    	ppsfwooShowLoadingMessage('Processing...');
	    	var plan_id = $(this).data('plan-id'),
	    		nonce = $(this).data('nonce'),
	    		paypal_action = $(this).attr('class');
	    	ppsfwooDoAjax('modify_plan', function(r) {
	    		var response = JSON.parse(r);
	    		if(response.error) {
	    			alert(response.error);
	    			ppsfwooHideLoadingMessage();
	    		} else {
	    			ppsfwooRefreshPage();
	    		}
	    	}, {
				'plan_id': plan_id,
				'paypal_action': paypal_action,
				'nonce': nonce
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

	ppsfwooOptionsPageInit();

	$('#subs-search').on('submit', function(e) {
    	e.preventDefault();
    	var email = $("#email-input").val(),
    		search_by_email = $("#search_by_email").val();
        ppsfwooDoAjax('search_subscribers', function(r) {
        	var response = JSON.parse(r);
			if(!response.error) {
				$("#tab-subscribers .pagination, .button.export-table-data").hide();
				$("#reset").show();
				$("#subscribers").replaceWith(response.html);
			} else {
				ppsfwooShowMsg(response.error, "warning");
			}
		}, {
			'email': email,
			'search_by_email': search_by_email
		});
    });

    $("#refresh").click(function(e) {
		e.preventDefault();
		ppsfwooShowLoadingMessage('Processing...');
		ppsfwooDoAjax('refresh_plans', function(r) {
			var response = JSON.parse(r);
			if(0 === response.length) {
				ppsfwooShowMsg("No plans found.", "warning");
			} else if(response.success) {
				ppsfwooShowMsg("Successfully refreshed plans.");
				ppsfwooRefreshPage();
			} else if(response.error) {
				ppsfwooShowMsg(response.error, "error");
			} else {
				ppsfwooShowMsg("There has been an error. Please try again and check your <a href='" + ppsfwoo_ajax_var.settings_url + "'>WooCommerce PayPal Payments settings</a>.", "error");
			}
			ppsfwooHideLoadingMessage();
		});
	});

	$('.nav-tab-wrapper a').click(function(e) {
        e.preventDefault();
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.tab-content').hide();
        selected_tab = $(this).attr('href');
        $("#" + selected_tab).show();
    });

    if(!$('.nav-tab-wrapper a').hasClass('nav-tab-active')) {
    	$('.nav-tab-wrapper a:first-child').click();
    } else {
    	$('.nav-tab-wrapper a.nav-tab-active').click();
    }

	/*
	*	When saving options, reset the query string variables so the subscriber table
	*	loads the new ppsfwoo_rows_per_page
	*/
	$('#ppsfwoo_options').submit(function(e) {
        $element = $(this).find('input[name=_wp_http_referer]');
        var newValue = ppsfwooRemoveQueryStringParams($element.attr('value'));
        $element.attr('value', newValue + '&tab=' + selected_tab);
    });

    $("input[name=ppsfwoo_delete_plugin_data]").click(function(e) {
    	if($(this).is(':checked')) {
    		return confirm("Selecting this option will delete plugin options and the subscribers table on plugin deactivation. Are you sure you want to do this?\n\nThe setting will take effect after you 'Save Changes' below.");
    	}
    });

});
