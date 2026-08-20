// ============================================================================
// General Functions
// ============================================================================

const toggleFields = document.querySelectorAll('[data-toggle-target]');

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
