// ============================================================================
// General Functions
// ============================================================================

const toggleFields = document.querySelectorAll('[data-toggle-target]');

const characterPolicies = {
    username: /[^A-Za-z0-9_]/g,
    'display-name': /[^A-Za-z0-9 .,'-]/g,
    identifier: /[^\x21-\x7E]/g,
    email: /[^\x21-\x7E]/g,
    password: /[^\x21-\x7E]/g,
};

document.querySelectorAll('[data-character-policy]').forEach(field => {
    field.addEventListener('input', () => {
        const pattern = characterPolicies[field.dataset.characterPolicy];
        if (pattern) field.value = field.value.replace(pattern, '');
    });
});

document.querySelectorAll('[data-password-toggle]').forEach(toggle => {
    toggle.addEventListener('click', () => {
        const password = document.getElementById(toggle.dataset.passwordToggle);
        const isVisible = password.type === 'text';
        password.type = isVisible ? 'password' : 'text';
        toggle.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
        toggle.setAttribute('aria-pressed', String(!isVisible));
        toggle.classList.toggle('is-visible', !isVisible);
    });
});

const setToggleTarget = (selector, visible) => {
    if (!selector) {
        return;
    }

    document.querySelectorAll(selector).forEach(target => {
        target.hidden = !visible;
        target.querySelectorAll('input, select, textarea').forEach(field => {
            field.disabled = !visible;
        });
        if (target.matches('input, select, textarea')) {
            target.disabled = !visible;
        }
    });
};

const syncToggleFields = () => {
    toggleFields.forEach(toggle => {
        setToggleTarget(toggle.dataset.toggleTarget, toggle.checked);

        if (toggle.dataset.toggleTargetOff) {
            setToggleTarget(toggle.dataset.toggleTargetOff, !toggle.checked);
        }
    });
};

syncToggleFields();
toggleFields.forEach(toggle => toggle.addEventListener('change', syncToggleFields));

// Voucher codes are always uppercase letters/digits only (matches the admin
// voucher-create/update code-entry fields) — restrict as the member types
// rather than only rejecting on submit.
const voucherCodeInput = document.getElementById('voucher_code');
voucherCodeInput?.addEventListener('input', () => {
    voucherCodeInput.value = voucherCodeInput.value.toUpperCase().replace(/[^0-9A-Z]/g, '');
});

// Price and weight accept a plain decimal value, at most 2 digits after the
// point — strips anything else and drops extra decimal digits as typed
// rather than only catching it on submit.
const restrictToDecimal = value => {
    let v = value.replace(/[^0-9.]/g, '');
    const dotIndex = v.indexOf('.');
    if (dotIndex !== -1) {
        v = v.slice(0, dotIndex + 1) + v.slice(dotIndex + 1).replace(/\./g, '');
        const [whole, fraction] = v.split('.');
        if (fraction && fraction.length > 2) {
            v = whole + '.' + fraction.slice(0, 2);
        }
    }
    return v;
};
['price', 'weight'].forEach(id => {
    const field = document.getElementById(id);
    field?.addEventListener('input', () => {
        field.value = restrictToDecimal(field.value);
    });
});

// Same restriction for the admin table Filter dialog's Min/Max range
// fields (price, weight, stock) — these are type="text" rather than
// type="number" specifically so this can safely rewrite .value on every
// keystroke; a native number input silently blanks itself the instant an
// intermediate value like "35." gets assigned to it.
document.querySelectorAll('[data-range-min], [data-range-max]').forEach(field => {
    field.addEventListener('input', () => {
        field.value = restrictToDecimal(field.value);
    });
});

// Same fix for the member-facing price-range filters (category.php,
// search.php) — same reasoning as above, hence also type="text" rather
// than type="number" in the markup.
document.querySelectorAll('[data-decimal-input]').forEach(field => {
    field.addEventListener('input', () => {
        field.value = restrictToDecimal(field.value);
    });
});

const adminFilterBtn = document.getElementById('admin-table-filter-btn');
const adminFilterDialog = document.getElementById('admin-table-filter-dialog');
const adminFilterClose = document.getElementById('admin-table-filter-close');
adminFilterBtn?.addEventListener('click', () => adminFilterDialog.showModal());
adminFilterClose?.addEventListener('click', () => adminFilterDialog.close());

document.querySelector('.admin-table-filter-form')?.addEventListener('submit', event => {
    let valid = true;

    document.querySelectorAll('[data-admin-filter-range]').forEach(range => {
        const minInput = range.querySelector('[data-range-min]');
        const maxInput = range.querySelector('[data-range-max]');
        const hasBothValues = minInput.value !== '' && maxInput.value !== '';
        const isValid = !hasBothValues || Number(minInput.value) <= Number(maxInput.value);

        maxInput.setCustomValidity(isValid ? '' : 'Maximum must be greater than or equal to minimum.');
        valid = valid && isValid;
    });

    if (!valid) {
        event.preventDefault();
        event.currentTarget.reportValidity();
    }
});

document.querySelectorAll('[data-bulk-select]').forEach(bar => {
    const storageKey = bar.dataset.storageKey;
    const statusStorageKey = `${storageKey}-statuses`;
    const selectAllUrl = bar.dataset.selectAllUrl;
    const selectAllKey = bar.dataset.selectAllKey;
    const selectAllStatusKey = bar.dataset.selectAllStatusKey;

    // Selection should persist across pagination of the SAME table (same
    // pathname, different query string) but not survive navigating away to
    // a different admin page and back. Since every navigation here is a
    // full page load, track the last-visited pathname/table in
    // sessionStorage and drop the previous table's selection the moment a
    // different pathname loads.
    const currentPath = location.pathname;
    const lastPath = sessionStorage.getItem('bulk-select-last-path');
    const lastKey = sessionStorage.getItem('bulk-select-last-key');
    if (lastPath && lastKey && lastPath !== currentPath) {
        localStorage.removeItem(lastKey);
    }
    sessionStorage.setItem('bulk-select-last-path', currentPath);
    sessionStorage.setItem('bulk-select-last-key', storageKey);
    const countEl = bar.querySelector('[data-bulk-count]');
    const selectPageBtn = bar.querySelector('[data-bulk-select-page]');
    const actionButtons = bar.querySelectorAll('[data-bulk-action]');
    const cancelBtn = bar.querySelector('[data-bulk-cancel]');
    const headerCheckbox = document.querySelector('[data-bulk-select-page-checkbox]');
    const rowCheckboxes = () => Array.from(document.querySelectorAll('[data-bulk-checkbox]'));

    const confirmDialog = document.querySelector('[data-bulk-confirm-dialog]');
    const confirmMessageEl = confirmDialog?.querySelector('[data-bulk-confirm-message]');
    const confirmOkBtn = confirmDialog?.querySelector('[data-bulk-confirm-ok]');
    const confirmCancelBtn = confirmDialog?.querySelector('[data-bulk-confirm-cancel]');
    let pendingAction = null;

    const submitBulkAction = (button, selected) => {
        const form = document.createElement('form');
        form.method = 'post';
        form.action = button.dataset.bulkActionUrl;
        selected.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = id;
            form.appendChild(input);
        });

        localStorage.removeItem(storageKey);
        localStorage.removeItem(statusStorageKey);
        document.body.appendChild(form);
        form.submit();
    };

    const getSelected = () => {
        try {
            return new Set(JSON.parse(localStorage.getItem(storageKey) || '[]'));
        } catch {
            return new Set();
        }
    };

    const getSelectedStatuses = () => {
        try {
            return JSON.parse(localStorage.getItem(statusStorageKey) || '{}');
        } catch {
            return {};
        }
    };

    const render = () => {
        const selected = getSelected();
        const statuses = getSelectedStatuses();
        countEl.textContent = `${selected.size} selected`;

        rowCheckboxes().forEach(checkbox => {
            checkbox.checked = selected.has(checkbox.value);
            if (checkbox.checked && checkbox.dataset.bulkStatus !== undefined) {
                statuses[checkbox.value] = checkbox.dataset.bulkStatus;
            }
        });
        localStorage.setItem(statusStorageKey, JSON.stringify(statuses));

        if (headerCheckbox) {
            const pageIds = rowCheckboxes().map(checkbox => checkbox.value);
            headerCheckbox.checked = pageIds.length > 0 && pageIds.every(id => selected.has(id));
        }

        actionButtons.forEach(button => {
            button.disabled = selected.size === 0;

            const countWhen = button.dataset.bulkCountWhen;
            if (countWhen !== undefined) {
                const count = Array.from(selected).filter(id => statuses[id] === countWhen).length;
                const labelText = button.querySelector('[data-bulk-label-text]');
                if (labelText) labelText.textContent = `${button.dataset.bulkLabel} (${count})`;
            }
        });
        if (cancelBtn) cancelBtn.disabled = selected.size === 0;
        bar.hidden = selected.size === 0;
    };

    const setSelected = selected => {
        const statuses = getSelectedStatuses();
        Object.keys(statuses).forEach(id => {
            if (!selected.has(id)) delete statuses[id];
        });
        localStorage.setItem(storageKey, JSON.stringify(Array.from(selected)));
        localStorage.setItem(statusStorageKey, JSON.stringify(statuses));
        render();
    };

    render();

    rowCheckboxes().forEach(checkbox => {
        checkbox.addEventListener('change', () => {
            const selected = getSelected();
            checkbox.checked ? selected.add(checkbox.value) : selected.delete(checkbox.value);
            setSelected(selected);
        });
    });

    headerCheckbox?.addEventListener('change', async () => {
        if (!headerCheckbox.checked) {
            setSelected(new Set());
            return;
        }

        if (!selectAllUrl) {
            const selected = getSelected();
            rowCheckboxes().forEach(checkbox => selected.add(checkbox.value));
            setSelected(selected);
            return;
        }

        headerCheckbox.disabled = true;
        try {
            const response = await fetch(selectAllUrl + window.location.search);
            const rows = await response.json();
            const statuses = {};
            const ids = rows.map(row => {
                if (typeof row === 'object' && row !== null && selectAllKey) {
                    const id = String(row[selectAllKey]);
                    if (selectAllStatusKey) statuses[id] = String(row[selectAllStatusKey]);
                    return id;
                }
                return String(row);
            });
            localStorage.setItem(statusStorageKey, JSON.stringify(statuses));
            setSelected(new Set(ids));
        } catch (error) {
            headerCheckbox.checked = false;
            showInfo('Unable to select all — please try again.');
        } finally {
            headerCheckbox.disabled = false;
        }
    });

    selectPageBtn?.addEventListener('click', () => {
        const pageCheckboxes = rowCheckboxes();
        const statuses = {};
        pageCheckboxes.forEach(checkbox => {
            statuses[checkbox.value] = checkbox.dataset.bulkStatus;
        });
        localStorage.setItem(statusStorageKey, JSON.stringify(statuses));
        setSelected(new Set(pageCheckboxes.map(checkbox => checkbox.value)));
    });

    cancelBtn?.addEventListener('click', () => {
        setSelected(new Set());
    });

    actionButtons.forEach(button => {
        button.addEventListener('click', () => {
            const selected = getSelected();

            if (selected.size === 0) {
                return;
            }

            const confirmMessage = button.dataset.bulkActionConfirm;
            if (confirmMessage && confirmDialog) {
                pendingAction = button;
                confirmMessageEl.textContent = confirmMessage;
                confirmDialog.showModal();
                return;
            }

            submitBulkAction(button, selected);
        });
    });

    confirmCancelBtn?.addEventListener('click', () => {
        pendingAction = null;
        confirmDialog.close();
    });

    confirmOkBtn?.addEventListener('click', () => {
        confirmDialog.close();
        if (pendingAction) {
            submitBulkAction(pendingAction, getSelected());
            pendingAction = null;
        }
    });
});

document.querySelectorAll('[data-login-required]').forEach(button => {
    button.addEventListener('click', event => {
        if (isLoggedIn) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        window.location.assign('/login.php');
    }, true);
});

const notifyToggle = document.querySelector('#notify-toggle');
const notifyDropdown = document.querySelector('#notify-dropdown');

if (notifyToggle && notifyDropdown) {
    const notifyWrapper = notifyToggle.closest('.notify-wrapper');

    notifyToggle.addEventListener('click', event => {
        event.stopPropagation();
        const isOpen = !notifyDropdown.hidden;
        notifyDropdown.hidden = isOpen;
        notifyToggle.setAttribute('aria-expanded', String(!isOpen));

        if (!isOpen && notifyWrapper) {
            const wrapperRect = notifyWrapper.getBoundingClientRect();
            const dropdownWidth = notifyDropdown.offsetWidth;
            const margin = 12;
            const idealLeft = wrapperRect.left + wrapperRect.width / 2 - dropdownWidth / 2;
            const maxLeft = Math.max(margin, window.innerWidth - dropdownWidth - margin);
            const clampedLeft = Math.min(Math.max(idealLeft, margin), maxLeft);

            notifyDropdown.style.right = 'auto';
            notifyDropdown.style.left = `${clampedLeft - wrapperRect.left}px`;
        }
    });

    notifyDropdown.addEventListener('click', event => event.stopPropagation());

    document.addEventListener('click', () => {
        notifyDropdown.hidden = true;
        notifyToggle.setAttribute('aria-expanded', 'false');
    });
}

document.querySelectorAll('[data-password-requirements]').forEach(requirementsBox => {
    const passwordInput = document.getElementById(requirementsBox.dataset.passwordRequirements);
    if (!passwordInput) {
        return;
    }

    const checks = {
        length: value => value.length >= 8 && value.length <= 50,
        lower: value => /[a-z]/.test(value),
        upper: value => /[A-Z]/.test(value),
        number: value => /[0-9]/.test(value),
        symbol: value => /[^a-zA-Z0-9]/.test(value),
    };

    function updatePasswordRequirements() {
        const value = passwordInput.value || '';

        Object.keys(checks).forEach(key => {
            const requirement = requirementsBox.querySelector('[data-req="' + key + '"]');
            if (!requirement) {
                return;
            }

            const met = checks[key](value);
            requirement.classList.toggle('met', met);
            requirement.querySelector('.indicator').textContent = met ? '✓' : '✖';
        });
    }

    passwordInput.addEventListener('input', updatePasswordRequirements);
    updatePasswordRequirements();
});


// ============================================================================
// Page Load (jQuery)
// ============================================================================

$(() => {
    $('#faker').on('click', e => {
        window.location.href = '/';
    });

    // Initiate GET request
    $('[data-get]').on('click', function (e) {
        e.preventDefault();

        // Read the attribute from the element the handler is bound to
        const url = $(this).attr('data-get');

        // No value -> treat as reload
        if (!url) {
            window.location.reload();
            return;
        }

        // Navigate to the provided URL (supports absolute and relative)
        window.location.href = url;
    });

    // Initiate POST request (with confirmation)
    $('[data-post]').on('click', function (e) {
        e.preventDefault();

        const url = $(this).attr('data-post');
        const message = $(this).attr('data-confirm') || 'Are you sure?';

        if (!url || !confirm(message)) {
            return;
        }

        $('<form>', { method: 'post', action: url }).appendTo('body').trigger('submit');
    });
});

const searchForm = document.querySelector('#search-form');
const searchInput = document.querySelector('#search-input');
const searchEmptyDialog = document.querySelector('#search-empty-dialog');
const searchEmptyClose = document.querySelector('#search-empty-close');
let searchDialogTimer;

if (searchForm && searchInput && searchEmptyDialog) {
    searchForm.addEventListener('submit', event => {
        if (searchInput.value.trim() === '') {
            event.preventDefault();
            searchEmptyDialog.showModal();

            searchDialogTimer = window.setTimeout(() => {
                searchEmptyDialog.close();
            }, 5000);
        }
    });

    searchEmptyDialog.addEventListener('close', () => {
        window.clearTimeout(searchDialogTimer);
        searchInput.focus();
    });
}

if (searchEmptyDialog && searchEmptyClose) {
    searchEmptyClose.addEventListener('click', () => {
        searchEmptyDialog.close();
    });
}

const priceRange = document.querySelector('.price-range');
const navigation = performance.getEntriesByType('navigation')[0];

if (priceRange && navigation?.type === 'reload') {
    const url = new URL(window.location.href);
    url.searchParams.delete('least');
    url.searchParams.delete('most');
    window.location.replace(url.pathname + url.search);
}

// Preview a chosen photo on its sibling <img>
$('input[type="file"]').on('change', function () {
    const img = $(this).siblings('img')[0];

    if (!img) {
        return;
    }

    const file = this.files[0];

    if (file && file.type.startsWith('image/')) {
        img.src = URL.createObjectURL(file);
    } else {
        img.src = img.dataset.src;
    }
});

const photoEditorDialog = document.querySelector('#photo-editor-dialog');
const photoEditorInput = document.querySelector('#photo');
const photoEditorImage = document.querySelector('#photo-editor-image');
const photoEditorPreview = document.querySelector('label.upload img');

if (photoEditorDialog && photoEditorInput && photoEditorImage && window.Cropper) {
    let cropper;
    let originalPreviewUrl = photoEditorPreview?.src;

    const closePhotoEditor = () => {
        cropper?.destroy();
        cropper = null;
        photoEditorDialog.close();
    };

    photoEditorInput.addEventListener('change', () => {
        const file = photoEditorInput.files?.[0];

        if (!file || !file.type.startsWith('image/')) {
            return;
        }

        originalPreviewUrl = photoEditorPreview?.src;
        photoEditorImage.src = URL.createObjectURL(file);
        photoEditorImage.onload = () => {
            cropper?.destroy();
            cropper = new Cropper(photoEditorImage, {
                aspectRatio: 1,
                viewMode: 1,
                autoCropArea: 1,
                background: false,
            });
            photoEditorDialog.showModal();
        };
    });

    photoEditorDialog.querySelectorAll('[data-photo-editor-action]').forEach(button => {
        button.addEventListener('click', () => {
            const action = button.dataset.photoEditorAction;

            if (action === 'cancel') {
                photoEditorInput.value = '';
                if (photoEditorPreview && originalPreviewUrl) {
                    photoEditorPreview.src = originalPreviewUrl;
                }
                closePhotoEditor();
            } else if (action === 'apply') {
                cropper?.getCroppedCanvas({width: 800, height: 800}).toBlob(blob => {
                    if (!blob) {
                        return;
                    }

                    const editedFile = new File([blob], 'profile-photo.jpg', {type: 'image/jpeg'});
                    const transfer = new DataTransfer();
                    transfer.items.add(editedFile);
                    photoEditorInput.files = transfer.files;
                    if (photoEditorPreview) {
                        photoEditorPreview.src = URL.createObjectURL(editedFile);
                    }
                    closePhotoEditor();
                }, 'image/jpeg', 0.9);
            } else if (action === 'rotate-left') {
                cropper?.rotate(-90);
            } else if (action === 'rotate-right') {
                cropper?.rotate(90);
            } else if (action === 'flip-horizontal') {
                cropper?.scaleX(-(cropper.getData().scaleX || 1));
            } else if (action === 'flip-vertical') {
                cropper?.scaleY(-(cropper.getData().scaleY || 1));
            } else if (action === 'reset') {
                cropper?.reset();
            }
        });
    });

    photoEditorDialog.addEventListener('cancel', event => {
        event.preventDefault();
        photoEditorDialog.querySelector('[data-photo-editor-action="cancel"]').click();
    });
}

const addressMap = document.querySelector('#address-map');

if (addressMap) {
    window.addEventListener('load', () => {
        if (!window.google?.maps) {
            return;
        }

        const latitudeInput = document.querySelector('#address-latitude');
        const longitudeInput = document.querySelector('#address-longitude');
        const placeIdInput = document.querySelector('#address-place-id');
        const streetInput = document.querySelector('#address-street');
        const cityInput = document.querySelector('#address-city');
        const stateInput = document.querySelector('#address-state');
        const postalCodeInput = document.querySelector('#address-postal_code');
        const countryInput = document.querySelector('#address-country');
        const searchInput = document.querySelector('#address-search');
        const existingLatitude = Number(addressMap.dataset.latitude);
        const existingLongitude = Number(addressMap.dataset.longitude);
        const initialLocation = Number.isFinite(existingLatitude) && Number.isFinite(existingLongitude)
            && existingLatitude !== 0 && existingLongitude !== 0
            ? {lat: existingLatitude, lng: existingLongitude}
            : {lat: 3.1390, lng: 101.6869};
        const map = new google.maps.Map(addressMap, {center: initialLocation, zoom: 15, mapTypeControl: false, streetViewControl: false});
        const marker = new google.maps.Marker({map, position: initialLocation, draggable: true});
        const geocoder = new google.maps.Geocoder();

        const fillAddress = result => {
            const components = {};
            result.address_components?.forEach(component => component.types.forEach(type => { components[type] = component.long_name; }));
            streetInput.value = [components.street_number, components.route].filter(Boolean).join(' ');
            cityInput.value = components.locality || components.postal_town || components.administrative_area_level_2 || '';
            stateInput.value = components.administrative_area_level_1 || '';
            postalCodeInput.value = components.postal_code || '';
            countryInput.value = components.country || '';
            latitudeInput.value = result.geometry.location.lat();
            longitudeInput.value = result.geometry.location.lng();
            placeIdInput.value = result.place_id || '';
        };

        const chooseLocation = location => {
            marker.setPosition(location);
            map.panTo(location);
            geocoder.geocode({location}, (results, status) => {
                if (status === 'OK' && results[0]) {
                    fillAddress(results[0]);
                }
            });
        };

        map.addListener('click', event => chooseLocation(event.latLng));
        marker.addListener('dragend', event => chooseLocation(event.latLng));

        const autocomplete = new google.maps.places.Autocomplete(searchInput, {fields: ['address_components', 'geometry', 'place_id'], types: ['address']});
        autocomplete.bindTo('bounds', map);
        searchInput.addEventListener('input', () => {
            placeIdInput.value = '';
        });
        autocomplete.addListener('place_changed', () => {
            const place = autocomplete.getPlace();
            if (!place.geometry?.location) {
                return;
            }
            map.setZoom(17);
            marker.setPosition(place.geometry.location);
            map.panTo(place.geometry.location);
            fillAddress(place);
        });
    });
}

const productMainImage = document.querySelector('#product-main-image');
const productThumbs = document.querySelectorAll('.product-detail-thumb');
const productImageCounter = document.querySelector('#product-image-counter');
const productImageSrcs = Array.from(productThumbs).map(thumb => thumb.dataset.image);

function setActiveProductImage(src) {
    productMainImage.src = src;
    productThumbs.forEach(thumb => {
        thumb.classList.toggle('is-active', thumb.dataset.image === src);
    });

    if (productImageCounter) {
        const index = productImageSrcs.indexOf(src);
        productImageCounter.textContent = `${index + 1}/${productImageSrcs.length}`;
    }
}

if (productMainImage) {
    const productPrevButton = document.querySelector('#product-image-prev');
    const productNextButton = document.querySelector('#product-image-next');

    productThumbs.forEach(thumb => {
        thumb.addEventListener('click', () => setActiveProductImage(thumb.dataset.image));
    });

    const stepProductImage = step => {
        const currentIndex = productImageSrcs.indexOf(productMainImage.getAttribute('src'));
        const nextIndex = (currentIndex + step + productImageSrcs.length) % productImageSrcs.length;
        setActiveProductImage(productImageSrcs[nextIndex]);
    };

    productPrevButton?.addEventListener('click', () => stepProductImage(-1));
    productNextButton?.addEventListener('click', () => stepProductImage(1));

    setActiveProductImage(productMainImage.getAttribute('src'));
}

document.querySelectorAll('[data-quantity-control]').forEach(control => {
    const quantityInput = control.querySelector('input[name="quantity"]');
    const minusButton = control.querySelector('[data-quantity-minus]');
    const plusButton = control.querySelector('[data-quantity-plus]');
    const warning = control.parentElement.querySelector('[data-quantity-warning]');
    const stock = Number.parseInt(control.dataset.stock, 10);

    minusButton.addEventListener('click', () => {
        const quantity = Number.parseInt(quantityInput.value, 10);

        if (quantity > 1) {
            quantityInput.value = quantity - 1;
        }

        warning.hidden = true;
    });

    plusButton.addEventListener('click', () => {
        const quantity = Number.parseInt(quantityInput.value, 10);

        if (quantity >= stock) {
            warning.hidden = false;
            return;
        }

        quantityInput.value = quantity + 1;
        warning.hidden = true;
    });
});

document.querySelectorAll('[data-refill-stepper]').forEach(control => {
    const input = control.querySelector('input[name="quantity"]');
    const minusButton = control.querySelector('[data-refill-minus]');
    const plusButton = control.querySelector('[data-refill-plus]');

    minusButton.addEventListener('click', () => {
        const quantity = Number.parseInt(input.value, 10) || 1;
        input.value = Math.max(1, quantity - 1);
    });

    plusButton.addEventListener('click', () => {
        const quantity = Number.parseInt(input.value, 10) || 1;
        input.value = quantity + 1;
    });
});

document.querySelectorAll('[data-cart-quantity]').forEach(control => {
    const input = control.querySelector('.cart-quantity-value');
    const minusButton = control.querySelector('[data-quantity-minus]');
    const plusButton = control.querySelector('[data-quantity-plus]');
    const productId = control.dataset.productId;
    const cartItem = control.closest('[data-cart-item]');
    const lineTotal = cartItem?.querySelector('[data-cart-line-total]');
    const grandTotal = document.querySelector('[data-cart-grand-total]');

    async function updateQuantity(newQuantity) {
        if (newQuantity < 1) {
            return;
        }

        minusButton.disabled = true;
        plusButton.disabled = true;

        try {
            const response = await fetch('cart-update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ product_id: productId, quantity: newQuantity }),
            });
            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Unable to update cart.');
            }

            input.value = result.quantity;
            if (lineTotal) {
                lineTotal.textContent = 'RM ' + result.lineTotal;
            }
            if (grandTotal) {
                grandTotal.textContent = 'RM ' + result.cartSubtotal;
            }
            minusButton.disabled = result.atMin;
            plusButton.disabled = result.atMax;
            showInfo('Cart updated.');
            recalcCartSelection();
        } catch (error) {
            minusButton.disabled = false;
            plusButton.disabled = false;
            showInfo(error.message || 'Unable to update cart.');
        }
    }

    minusButton.addEventListener('click', () => {
        updateQuantity(Number.parseInt(input.value, 10) - 1);
    });

    plusButton.addEventListener('click', () => {
        updateQuantity(Number.parseInt(input.value, 10) + 1);
    });
});

// Cart item selection (choose which items to check out, Shopee-style)
const cartSelectAll = document.getElementById('cart-select-all');
const cartCheckoutButton = document.querySelector('[data-cart-checkout-button]');
const cartItemCheckboxes = document.querySelectorAll('.cart-item-select');

function recalcCartSelection() {
    if (!cartItemCheckboxes.length) {
        return;
    }

    let total = 0;
    let count = 0;

    cartItemCheckboxes.forEach(checkbox => {
        if (!checkbox.checked) {
            return;
        }

        const lineTotal = checkbox.closest('[data-cart-item]')?.querySelector('[data-cart-line-total]');
        const value = Number.parseFloat((lineTotal?.textContent || '').replace(/[^0-9.]/g, '')) || 0;
        total += value;
        count += 1;
    });

    const countEl = document.querySelector('[data-cart-selected-count]');
    if (countEl) {
        countEl.textContent = count;
    }

    const grandTotal = document.querySelector('[data-cart-grand-total]');
    if (grandTotal) {
        grandTotal.textContent = 'RM ' + total.toFixed(2);
    }

    if (cartCheckoutButton) {
        cartCheckoutButton.disabled = count === 0;
    }

    if (cartSelectAll) {
        const selectable = document.querySelectorAll('.cart-item-select:not(:disabled)');
        const checked = document.querySelectorAll('.cart-item-select:checked');
        cartSelectAll.checked = selectable.length > 0 && checked.length === selectable.length;
        cartSelectAll.indeterminate = checked.length > 0 && checked.length < selectable.length;
    }
}

cartSelectAll?.addEventListener('change', () => {
    document.querySelectorAll('.cart-item-select:not(:disabled)').forEach(checkbox => {
        checkbox.checked = cartSelectAll.checked;
    });
    recalcCartSelection();
});

cartItemCheckboxes.forEach(checkbox => {
    checkbox.addEventListener('change', recalcCartSelection);
});

recalcCartSelection();

document.getElementById('cart-form')?.addEventListener('submit', event => {
    if (!document.querySelectorAll('.cart-item-select:checked').length) {
        event.preventDefault();
        showInfo('Please select at least one item to check out.');
    }
});

document.querySelectorAll('[data-favourite-star]').forEach(button => {
    button.addEventListener('click', async () => {
        const star = button.querySelector('img');

        if (!star) {
            return;
        }

        button.disabled = true;

        try {
            const response = await fetch('/product/wishlist-toggle.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ product_id: button.dataset.productId }),
            });
            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Unable to update wishlist.');
            }

            button.setAttribute('aria-pressed', String(result.wishlisted));
            button.setAttribute(
                'aria-label',
                result.wishlisted ? 'Remove from wishlist' : 'Add to wishlist'
            );
            star.src = result.wishlisted ? '/images/yellowstar.png' : '/images/emptystar.png';
            star.classList.remove('is-favourite');
            void star.offsetWidth;
            star.classList.add('is-favourite');
            showInfo(result.message);
        } catch (error) {
            showInfo(error.message || 'Unable to update wishlist.');
        } finally {
            button.disabled = false;
        }
    });
});

const wishlistConfirmDialog = document.querySelector('#wishlist-confirm-dialog');
const wishlistConfirmCancel = document.querySelector('#wishlist-confirm-cancel');
const wishlistConfirmRemove = document.querySelector('#wishlist-confirm-remove');
let wishlistDeleteTarget = null;

document.querySelectorAll('[data-wishlist-delete]').forEach(button => {
    button.addEventListener('click', () => {
        wishlistDeleteTarget = button;
        wishlistConfirmDialog?.showModal();
    });
});

if (wishlistConfirmDialog && wishlistConfirmCancel) {
    wishlistConfirmCancel.addEventListener('click', () => {
        wishlistDeleteTarget = null;
        wishlistConfirmDialog.close();
    });
}

if (wishlistConfirmDialog && wishlistConfirmRemove) {
    wishlistConfirmRemove.addEventListener('click', async () => {
        const button = wishlistDeleteTarget;

        if (!button) {
            wishlistConfirmDialog.close();
            return;
        }

        const card = button.closest('.product-card');

        button.disabled = true;
        wishlistConfirmRemove.disabled = true;

        try {
            const response = await fetch('/product/wishlist-toggle.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ product_id: button.dataset.productId }),
            });
            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Unable to update wishlist.');
            }

            showInfo(result.message);

            if (card) {
                card.classList.add('is-removing');
                card.addEventListener('transitionend', () => card.remove(), { once: true });
            }
        } catch (error) {
            showInfo(error.message || 'Unable to update wishlist.');
            button.disabled = false;
        } finally {
            wishlistDeleteTarget = null;
            wishlistConfirmRemove.disabled = false;
            wishlistConfirmDialog.close();
        }
    });
}

document.querySelectorAll('[data-stock-reminder-cancel]').forEach(button => {
    button.addEventListener('click', async () => {
        const card = button.closest('.reminder-list__item');

        button.disabled = true;

        try {
            const response = await fetch('/product/stock-reminder-toggle.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ product_id: button.dataset.productId }),
            });
            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Unable to update reminder.');
            }

            if (card) {
                card.classList.add('is-removing');
                card.addEventListener('transitionend', () => {
                    card.remove();

                    const list = document.querySelector('#reminder-list');
                    const emptyMessage = document.querySelector('#reminder-list-empty');
                    if (list && emptyMessage && !list.querySelector('.reminder-list__item')) {
                        list.hidden = true;
                        emptyMessage.hidden = false;
                    }
                }, { once: true });
            }

            const badge = document.querySelector('#notify-toggle .cart-badge');
            if (badge) {
                const newCount = Math.max(0, Number.parseInt(badge.textContent, 10) - 1);
                if (newCount === 0) {
                    badge.remove();
                } else {
                    badge.textContent = newCount;
                }
            }
        } catch (error) {
            showInfo(error.message || 'Unable to update reminder.');
            button.disabled = false;
        }
    });
});

const addPictureButton = document.querySelector('#add-picture-button');
const addPictureInput = document.querySelector('#photo');
const productImageList = document.querySelector('#product-image-list');

if (addPictureButton && addPictureInput && productImageList) {
    const existingCount = productImageList.querySelectorAll('.product-image-list__item').length;
    const maxImages = Number.parseInt(productImageList.dataset.maxImages, 10) || 3;
    const maxNewPhotos = Math.max(0, maxImages - existingCount);
    let pendingFiles = [];

    const maxNote = document.createElement('p');
    maxNote.className = 'field-note';
    maxNote.hidden = true;
    maxNote.textContent = 'Maximum pictures reached. Remove a photo to insert new ones.';
    addPictureButton.insertAdjacentElement('afterend', maxNote);

    const updateAddButtonState = () => {
        const reachedMax = pendingFiles.length >= maxNewPhotos;
        addPictureButton.hidden = reachedMax;
        maxNote.hidden = !reachedMax;
    };

    const picker = document.createElement('input');
    picker.type = 'file';
    picker.accept = 'image/*';
    picker.multiple = true;
    picker.hidden = true;

    const syncInputFiles = () => {
        const dataTransfer = new DataTransfer();
        pendingFiles.forEach(file => dataTransfer.items.add(file));
        addPictureInput.files = dataTransfer.files;
    };

    const renderPending = () => {
        productImageList.querySelectorAll('[data-pending-image]').forEach(el => el.remove());

        pendingFiles.forEach((file, index) => {
            const figure = document.createElement('figure');
            figure.className = 'product-image-list__item';
            figure.dataset.pendingImage = '';

            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            figure.appendChild(img);

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'round-delete-button';
            removeButton.setAttribute('aria-label', 'Remove selected picture');
            removeButton.setAttribute('title', 'Remove selected picture');
            removeButton.addEventListener('click', () => {
                pendingFiles.splice(index, 1);
                syncInputFiles();
                renderPending();
                updateAddButtonState();
            });

            const removeIcon = document.createElement('img');
            removeIcon.src = '/images/delete.png';
            removeIcon.alt = '';
            removeButton.appendChild(removeIcon);

            figure.appendChild(removeButton);
            productImageList.appendChild(figure);
        });
    };

    addPictureButton.addEventListener('click', () => {
        picker.value = '';
        picker.click();
    });

    picker.addEventListener('change', () => {
        const chosen = Array.from(picker.files);
        const room = Math.max(0, maxNewPhotos - pendingFiles.length);

        if (chosen.length > room) {
            const skipped = chosen.length - room;
            showInfo(
                room > 0
                    ? `Only ${room} more picture${room === 1 ? '' : 's'} could be added — ${skipped} photo${skipped === 1 ? '' : 's'} skipped.`
                    : 'Maximum pictures reached — no photos were added.'
            );
        }

        pendingFiles = pendingFiles.concat(chosen.slice(0, room));
        syncInputFiles();
        renderPending();
        updateAddButtonState();
    });
}

const descriptionButton = document.querySelector('#description-button');
const descriptionDialog = document.querySelector('#description-dialog');
const descriptionInput = document.querySelector('#description-input');
const descriptionField = document.querySelector('#description');
const descriptionSave = document.querySelector('#description-save');
const descriptionCancel = document.querySelector('#description-cancel');

if (descriptionButton && descriptionDialog && descriptionInput && descriptionField) {
    descriptionButton.addEventListener('click', () => {
        descriptionInput.value = descriptionField.value;
        descriptionDialog.showModal();
    });

    descriptionCancel?.addEventListener('click', () => {
        descriptionDialog.close();
    });

    descriptionSave?.addEventListener('click', () => {
        descriptionField.value = descriptionInput.value;
        descriptionDialog.close();
    });
}

const productUnavailableDialog = document.querySelector('#product-unavailable-dialog');
const productUnavailableConfirm = document.querySelector('#product-unavailable-confirm');

function goBackFromUnavailableProduct() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.assign('/index.php');
    }
}

if (productUnavailableDialog?.dataset.redirect) {
    productUnavailableDialog.showModal();

    const redirectTimer = window.setTimeout(goBackFromUnavailableProduct, 2500);

    productUnavailableConfirm?.addEventListener('click', () => {
        window.clearTimeout(redirectTimer);
        goBackFromUnavailableProduct();
    });
}

const restockAlertDialog = document.querySelector('#restock-alert-dialog');
const restockAlertClose = document.querySelector('#restock-alert-close');

restockAlertDialog?.showModal();
restockAlertClose?.addEventListener('click', () => {
    restockAlertDialog.close();
});

document.querySelector('#activate-prompt-dialog')?.showModal();

const outofstockEmptyDialog = document.querySelector('#outofstock-empty-dialog');
const outofstockEmptyConfirm = document.querySelector('#outofstock-empty-confirm');

if (outofstockEmptyDialog?.dataset.redirect) {
    outofstockEmptyDialog.showModal();

    const redirectUrl = outofstockEmptyDialog.dataset.redirectUrl;
    const redirectTimer = window.setTimeout(() => {
        window.location.assign(redirectUrl);
    }, 2500);

    outofstockEmptyConfirm?.addEventListener('click', () => {
        window.clearTimeout(redirectTimer);
        window.location.assign(redirectUrl);
    });
}

const stockReminderButton = document.querySelector('#stock-reminder-button');

stockReminderButton?.addEventListener('click', async () => {
    stockReminderButton.disabled = true;

    try {
        const response = await fetch('/product/stock-reminder-toggle.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ product_id: stockReminderButton.dataset.productId }),
        });
        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.message || 'Unable to update reminder.');
        }

        stockReminderButton.classList.toggle('remind-me-button--requested', result.reminded);
        stockReminderButton.setAttribute('aria-pressed', String(result.reminded));
        stockReminderButton.textContent = result.reminded ? 'Cancel Reminder' : 'Notify Me';
    } catch (error) {
        showInfo(error.message || 'Unable to update reminder.');
    } finally {
        stockReminderButton.disabled = false;
    }
});

function showInfo(message) {
    const info = document.querySelector('#info');

    if (!info) {
        return;
    }

    info.textContent = message;
    info.style.animation = 'none';
    void info.offsetWidth;
    info.style.animation = '';
}

// Profile menu toggle
(function () {
    const profileLink = document.getElementById('profile-link');
    const profileMenu = document.getElementById('profile-menu');
    if (!profileLink || !profileMenu) return;

    function closeMenu() {
        profileMenu.classList.remove('open');
        profileMenu.setAttribute('aria-hidden', 'true');
        profileLink.setAttribute('aria-expanded', 'false');
    }

    function openMenu() {
        profileMenu.classList.add('open');
        profileMenu.setAttribute('aria-hidden', 'false');
        profileLink.setAttribute('aria-expanded', 'true');
    }

    profileLink.addEventListener('click', function (e) {
        // prevent normal navigation; toggle menu instead
        e.preventDefault();
        if (profileMenu.classList.contains('open')) closeMenu(); else openMenu();
    });

    // Close when clicking outside
    document.addEventListener('click', function (e) {
        if (!profileMenu.contains(e.target) && !profileLink.contains(e.target)) {
            closeMenu();
        }
    });

    // Close on Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeMenu();
    });
})();

document.querySelectorAll('[data-print-receipt]').forEach(button => {
    button.addEventListener('click', () => window.print());
});

// Cancel-order reason dialog
(function () {
    const openButton = document.getElementById('cancel-order-open');
    const closeButton = document.getElementById('cancel-order-close');
    const dialog = document.getElementById('cancel-order-dialog');
    const othersRadio = document.getElementById('cancel-reason-others');
    const reasonNote = document.getElementById('cancel-reason-note');

    if (!dialog) return;

    openButton?.addEventListener('click', () => dialog.showModal());
    closeButton?.addEventListener('click', () => dialog.close());

    dialog.querySelectorAll('input[name="reason"]').forEach(radio => {
        radio.addEventListener('change', () => {
            reasonNote.hidden = !othersRadio.checked;
        });
    });
})();

// Admin: reject-cancellation reason dialog
(function () {
    const openButton = document.getElementById('reject-cancellation-open');
    const closeButton = document.getElementById('reject-cancellation-close');
    const dialog = document.getElementById('reject-cancellation-dialog');

    if (!dialog) return;

    openButton?.addEventListener('click', () => dialog.showModal());
    closeButton?.addEventListener('click', () => dialog.close());
})();
