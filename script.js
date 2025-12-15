jQuery(document).ready(function($) {
    var commentForm = $('#commentform');
    var uploadField = $('#lca-upload');
    var preview = $('#lca-preview');
    var feedback = $('#lca-feedback');
    var initialPreview = preview.html();
    var initialFeedback = feedback.text();
    var MAX_SIZE = 2 * 1024 * 1024; // 2MB
    var allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

    var existingAvatar = preview.data('current-avatar');
    var existingMessage = feedback.data('current-message');

    // Ensure the form can carry files even if inline script fails.
    commentForm.attr('enctype', 'multipart/form-data');

    function showFeedback(message, isError) {
        feedback.text(message);
        feedback.toggleClass('is-error', !!isError);
    }

    function resetToInitial() {
        if (existingAvatar) {
            preview.html(initialPreview);
            feedback.text(existingMessage);
            feedback.removeClass('is-error');
            return;
        }

        preview.empty();
        feedback.text('');
        feedback.removeClass('is-error');
    }

    uploadField.on('change', function(e) {
        var file = e.target.files[0];

        if (!file) {
            resetToInitial();
            return;
        }

        if (allowedTypes.indexOf(file.type) === -1) {
            showFeedback('Please choose a JPG, PNG, or GIF image.', true);
            uploadField.val('');
            resetToInitial();
            return;
        }

        if (file.size > MAX_SIZE) {
            showFeedback('Your photo is too large. Please stay under 2MB.', true);
            uploadField.val('');
            resetToInitial();
            return;
        }

        var reader = new FileReader();
        reader.onload = function(event) {
            var img = $('<img>', {
                src: event.target.result,
                alt: 'Selected avatar preview'
            });
            preview.html(img);
            showFeedback(file.name + ' (' + Math.round(file.size / 1024) + ' KB)', false);
        };
        reader.readAsDataURL(file);
    });

    // If a saved avatar exists, make sure we show it as the starting point.
    if (existingAvatar) {
        showFeedback(existingMessage, false);
    }
});
