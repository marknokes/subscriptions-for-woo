function ppsfwooLoadPayPalScript(callback) {
    if (window.paypal) {
        callback();
    } else {
        var script = document.createElement('script');
        script.src = `https://www.paypal.com/sdk/js?client-id=${ppsfwoo_paypal_ajax_var.client_id}&vault=true&intent=subscription`;
        script.async = true;
        script.onload = callback;
        document.body.appendChild(script);
    }
}

function ppsfwooRender(nonce) {
    document.getElementById('lds-ellipsis').style.setProperty("display", "none", "important");
    paypal.Buttons({
        style: {
            shape: 'rect',
            layout: 'vertical',
            color: 'gold',
            label: 'subscribe'
        },
        createSubscription: function(data, actions) {
            return actions.subscription.create({
                plan_id: ppsfwoo_paypal_ajax_var.plan_id
            });
        },
        onApprove: function(data, actions) {
            var redirect_url = ppsfwoo_paypal_ajax_var.redirect + "?subs_id=" + data.subscriptionID + "&subs_id_redirect_nonce=" + nonce;
            window.location.assign(redirect_url);
        },
        onError: function(err) {
            alert('An error occurred while processing your subscription: ' + err.message);
        }
    }).render(`#paypal-button-container-${ppsfwoo_paypal_ajax_var.plan_id}`);
}

function ppsfwooInitializePayPalSubscription() {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/wp-admin/admin-ajax.php');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.responseType = 'json';
    xhr.onload = function() {
        if (xhr.status === 200) {
            var response = xhr.response;
            ppsfwooRender(response.nonce);
        } else {
            alert('There has been an unexpeced error. Please refresh and try again.');
        }
    };
    xhr.send('action=ppsfwoo_admin_ajax_callback&method=ppsfwoo_subs_id_redirect_nonce');
}

document.getElementById('subscribeButton').addEventListener('click', function() {
    this.style.display = 'none';
    document.getElementById('lds-ellipsis').style.setProperty("display", "inline-block", "important");
    ppsfwooLoadPayPalScript(ppsfwooInitializePayPalSubscription);
});
