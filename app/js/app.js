// ============================================================================
// General Functions
// ============================================================================



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