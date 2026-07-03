// ============================================================================
// General Functions
// ============================================================================



// ============================================================================
// Page Load (jQuery)
// ============================================================================

$(() => {

    
    // ============================================================================
    // Demo 1 - Image Switcher
    // ============================================================================

    const arr = [
        '/images/1.jpg',
        '/images/2.jpg',
        '/images/3.jpg',
        '/images/4.jpg',
    ];

    let i = 0;

    $('#img').on('click', e => {
        i = ++i % arr.length;

        $('#p').text(`Image ${i + 1} of 4`);

        $('#img')
            .hide()
            .prop('src', arr[i])
            .fadeIn();
    });

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