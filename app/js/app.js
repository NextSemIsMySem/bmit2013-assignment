// ============================================================================
// General Functions
// ============================================================================



// ============================================================================
// Page Load (jQuery)
// ============================================================================
let isLoggedIn = false;

let heading = document.querySelector('.profile-link .heading');

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

});