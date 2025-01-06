jQuery(function ($) {
    const elwrap = $('.migration-mobilepay-to-vippsmobile-wrap');
    const spinners = elwrap.find('.spinners_warp');
    const result = elwrap.find('.result');
    result.html('');

    elwrap.on('change', '#migration_file', function (e) {
        const fileInput = $(this);
        const file = fileInput[0].files[0];

        if (file && file.name.endsWith('.csv')) {
            
        } else {
            result.html(migrationData.upload_csv);
            fileInput.val('');
        }
    });

    elwrap.on('click', '.start-migration', function(e){
        e.preventDefault();
        result.html('');

        // Show confirmation dialog
        if (!confirm(migrationData.confirm_migration)) {
            return; // Exit if the user cancels
        }
        
        const fileInput = $('#migration_file');
        const button = $(this);

        if (!fileInput.val()) {
            alert(migrationData.choose_file);
            return;
        }

        button.prop('disabled', true);
        spinners.show();

        var formData = new FormData();
        formData.append('action', 'reepay_migration_upload_csv');
        formData.append('migration_file', $('#migration_file')[0].files[0]);
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    processBatch(0, response.data.total_records);
                } else {
                    alert(migrationData.failed_upload);
                }
            }
        });
    });

    function processBatch(offset, totalRecords) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'reepay_migration_process_batch',
                offset: offset
            },
            success: function(response) {
                if (response.success) {
                    var processed = response.data.processed;
                    var percentage = (processed / totalRecords) * 100;
                    $('.processing-percentage').html(percentage.toFixed(0) + '%');
                    if (response.data.has_more) {
                        processBatch(offset + 10, totalRecords);
                    } else {
                        result.html(migrationData.processed_success);
                        $('.processing-percentage').html('0%');
                        $('.start-migration').prop('disabled', false);
                        $('#migration_file').val('');
                        spinners.hide();
                    }
                } else {
                    alert(migrationData.processed_failed);
                }
            }
        });
    }
});