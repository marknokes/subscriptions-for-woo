var ppsfwoo_redirected = false;
function ppsfwooSendAjaxRequest() {
    if(false === ppsfwoo_redirected) {
        jQuery.ajax({
            url: '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                'action': 'ppsfwoo_admin_ajax_callback',
                'method': 'get_sub',
                'id'    : ppsfwoo_ajax_var.subs_id,
                'nonce' : ppsfwoo_ajax_var.nonce
            },
            success: function(response) {
                if("false" !== response) {
                    ppsfwoo_redirected = true;
                    location.href = response
                }
            }
        });
    }
}
ppsfwooSendAjaxRequest();
setInterval(ppsfwooSendAjaxRequest, 5000);
