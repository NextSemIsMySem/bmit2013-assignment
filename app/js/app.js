// ============================================================================
// General Functions
// ============================================================================

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
    const file = this.files[0];

    if (file && file.type.startsWith('image/')) {
        img.src = URL.createObjectURL(file);
    } else {
        img.src = img.dataset.src;
    }
});

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
