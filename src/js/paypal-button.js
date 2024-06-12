function ppsfwooSendPostRequest(url, data) {
    return new Promise((resolve, reject) => {
        var xhr = new XMLHttpRequest();
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === XMLHttpRequest.DONE) {
                if (xhr.status === 200) {
                    try {
                        const jsonResponse = JSON.parse(xhr.responseText);
                        resolve(jsonResponse);
                    } catch (error) {
                        reject(new Error('Invalid JSON response'));
                    }
                } else {
                    reject(new Error('Request failed: ' + xhr.status));
                }
            }
        };

        xhr.onerror = function() {
            reject(new Error('Network error occurred'));
        };

        var formData = new URLSearchParams();
        for (var key in data) {
            formData.append(key, data[key]);
        }

        xhr.open('POST', url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send(formData.toString());
    });
}

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
            if(err.message) {
                ppsfwooSendPostRequest('/wp-admin/admin-ajax.php', {
                    'action': 'ppsfwoo_admin_ajax_callback',
                    'method': 'ppsfwoo_log_paypal_buttons_error',
                    'message': err.message
                })
                .then(response => {
                    console.log(response);
                })
                .catch(error => {
                    console.log(error);
                });
            }
        }
    }).render(`#paypal-button-container-${ppsfwoo_paypal_ajax_var.plan_id}`);
}

function ppsfwooInitializePayPalSubscription() {
    ppsfwooSendPostRequest('/wp-admin/admin-ajax.php', {
        'action': 'ppsfwoo_admin_ajax_callback',
        'method': 'ppsfwoo_subs_id_redirect_nonce'
    })
    .then(response => {
        ppsfwooRender(response.nonce);
    })
    .catch(error => {
        console.log(error);
        alert("There has been an unexpeced error. Please refresh and try again.");
    });
}

document.getElementById('subscribeButton').addEventListener('click', function() {
    this.style.display = 'none';
    document.getElementById('lds-ellipsis').style.setProperty("display", "inline-block", "important");
    ppsfwooLoadPayPalScript(ppsfwooInitializePayPalSubscription);
});
