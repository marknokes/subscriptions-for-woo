var ppsfwooSubscribeButton = document.getElementById('subscribeButton'),
    ppsfwooEllipsis = document.getElementById('lds-ellipsis');

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

function ppsfwooLoadPayPalScript(client_id, nonce, plan_id, callback) {
    if (window.paypal) {
        callback(nonce, plan_id);
    } else {
        var script = document.createElement('script');
        script.setAttribute('src', `https://www.paypal.com/sdk/js?client-id=${client_id}&vault=true&intent=subscription`);
        script.setAttribute('data-sdk-integration-source', 'button-factory');
        script.onload = function() {            
            callback(nonce, plan_id);
        };
        script.onerror = function() {
            var error = 'Failed to load PayPal sdk';
            console.log(error);
            ppsfwooLogButtonError(error);
            alert("There has been an unexpeced error. Please refresh and try again.");
            location.reload();
        };
        document.body.appendChild(script);
    }
}

function ppsfwooLogButtonError(msg) {
    ppsfwooSendPostRequest('/wp-admin/admin-ajax.php', {
        'action': 'ppsfwoo_admin_ajax_callback',
        'method': 'log_paypal_buttons_error',
        'message': msg
    })
    .then(response => {
        console.log(response);
    })
    .catch(error => {
        console.log(error);
    });
}

function ppsfwooRender(nonce, plan_id) {
    ppsfwooEllipsis.style.setProperty("display", "none", "important");
    var container = document.createElement('div');
    container.setAttribute('id', `paypal-button-container-${plan_id}`);
    ppsfwooSubscribeButton.insertAdjacentElement('afterend', container);
    paypal.Buttons({
        style: {
            shape: 'rect',
            layout: 'vertical',
            color: 'gold',
            label: 'subscribe'
        },
        createSubscription: function(data, actions) {
            return actions.subscription.create({
                plan_id: plan_id
            });
        },
        onApprove: function(data, actions) {
            var redirect_url = ppsfwoo_paypal_ajax_var.redirect + "?subs_id=" + data.subscriptionID + "&subs_id_redirect_nonce=" + nonce;
            window.location.assign(redirect_url);
        },
        onError: function(err) {
            if(err.message) {
                ppsfwooLogButtonError(err.message);
            }
        }
    }).render(`#paypal-button-container-${plan_id}`);
}

function ppsfwooInitializePayPalSubscription() {
    ppsfwooSendPostRequest('/wp-admin/admin-ajax.php', {
        'action': 'ppsfwoo_admin_ajax_callback',
        'method': 'subs_id_redirect_nonce',
        'product_id': ppsfwoo_paypal_ajax_var.product_id
    })
    .then(response => {
        if(response.error) {
            throw new Error("No plan id found for product with ID " + ppsfwoo_paypal_ajax_var.product_id);
        } else {
            ppsfwooLoadPayPalScript(response.client_id, response.nonce, response.plan_id, ppsfwooRender);
        }
    })
    .catch(error => {
        console.log(error);
        ppsfwooLogButtonError(error);
        alert("There has been an unexpeced error. Please refresh and try again.");
        location.reload();
    });
}

ppsfwooSubscribeButton.addEventListener('click', function() {
    this.style.display = 'none';
    ppsfwooEllipsis.style.setProperty("display", "inline-block", "important");
    ppsfwooInitializePayPalSubscription();
});
