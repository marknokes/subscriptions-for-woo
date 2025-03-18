function ppsfwooGetQuantityInputId(product_id) {
    return 'ppsfwoo-quantity-input-' + product_id;
}

function ppsfwooCreateQuantityInput(product_id) {
    var ppsfwooQuantityInputContainer = document.getElementById('ppsfwoo-quantity-input-container-' + product_id),
        input = document.createElement('input'),
        label = document.createElement('label'),
        id = ppsfwooGetQuantityInputId(product_id);
    input.type = 'number';
    input.id = id;
    input.classList.add('ppsfwoo-quantity-input');
    input.name = 'quantity';
    input.min = '1';
    input.value = '1';
    label.setAttribute('for', id);
    label.innerHTML = 'Quantity: ';
    if(ppsfwooQuantityInputContainer) {
        ppsfwooQuantityInputContainer
            .appendChild(label)
            .appendChild(input);
    } else {
        alert('Unable to render PayPal button');
    }
}

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

function ppsfwooCreateBtnContainer(plan_id, product_id, button) {
    return new Promise((resolve, reject) => {
        var container = document.createElement('div');
        container.setAttribute('id', `paypal-button-container-${plan_id}`);
        if(button) {
            button.insertAdjacentElement('afterend', container);
        } else {
            alert('Unable to render PayPal button');
        }
        requestAnimationFrame(() => resolve(container));
    });
}

function ppsfwooRender(nonce, plan_id, product_id, button) {
    ppsfwooCreateBtnContainer(plan_id, product_id, button)
        .then((container) => {
            paypal.Buttons({
                style: {
                    shape: 'rect',
                    layout: 'vertical',
                    color: 'gold',
                    label: 'subscribe'
                },
                createSubscription: function(data, actions) {
                    var quantityInput = document.getElementById(ppsfwooGetQuantityInputId(product_id)),
                        planData = {plan_id: plan_id};
                    if(quantityInput) {
                        planData['quantity'] = quantityInput.value;
                    }
                    return actions.subscription.create(planData);
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
            }).render(`#${container.id}`);
        });
}

function ppsfwooInitializePayPalSubscription(product_id, button) {
    ppsfwooSendPostRequest('/wp-admin/admin-ajax.php', {
        'action': 'ppsfwoo_admin_ajax_callback',
        'method': 'subs_id_redirect_nonce',
        'product_id': product_id
    })
    .then(async response => {
        if(response.error) {
            throw new Error("No plan id found for product with ID " + product_id);
        } else {
            document.getElementById('lds-ellipsis-' + product_id).style.setProperty("display", "none", "important");
            if(response.quantity_supported) {
                ppsfwooCreateQuantityInput(product_id);
            }
            ppsfwooRender(response.nonce, response.plan_id, product_id, button);
        }
    })
    .catch(error => {
        console.log(error);
        ppsfwooLogButtonError(error);
        alert("There has been an unexpeced error. Please refresh and try again.");
    });
}