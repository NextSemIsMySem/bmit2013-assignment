// ============================================================================
// General Functions
// ============================================================================



// ============================================================================
// Page Load (jQuery)
// ============================================================================

$(() => {
    if (isLoggedIn) {
        heading.textContent = 'Welcome, User!';
    } else {
        heading.textContent = 'Login/Register';
    }

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
