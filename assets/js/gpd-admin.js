jQuery(document).ready(function($) {
    // Handle search
    $('#gpd-search-form').on('submit', function(e) {
        e.preventDefault();

        const query = $('#gpd-query').val();
        const radius = $('#gpd-radius').val();
        const limit = $('#gpd-limit').val();

        $('#gpd-results').html('<p>Searching...</p>');
        $('#gpd-pagination').empty();

        $.post(ajaxurl, {
            action: 'gpd_search_places',
            query: query,
            radius: radius,
            limit: limit
        }, function(response) {
            if (response && response.html) {
                $('#gpd-results').html('<table class="wp-list-table widefat fixed striped"><tbody>' + response.html + '</tbody></table>');
                updatePagination(response.next_page_token);
            } else {
                $('#gpd-results').html('<p>Error: Could not retrieve search results.</p>');
            }
        });
    });

    // Handle next page
    $(document).on('click', '#gpd-next-page', function(e) {
        e.preventDefault();

        const token = $(this).data('next-page-token');
        const query = $('#gpd-query').val();
        const radius = $('#gpd-radius').val();
        const limit = $('#gpd-limit').val();

        $('#gpd-pagination').html('<p>Loading more...</p>');

        $.post(ajaxurl, {
            action: 'gpd_search_places',
            query: query,
            radius: radius,
            limit: limit,
            next_page_token: token
        }, function(response) {
            if (response && response.html) {
                $('#gpd-results table tbody').append(response.html);
                updatePagination(response.next_page_token);
            } else {
                $('#gpd-results').append('<tr><td colspan="4">Error: Could not retrieve next page.</td></tr>');
            }
        });
    });

// Import selected places
$('#gpd-import-selected').on('click', function(e) {
    e.preventDefault();

    const selected = [];
    $('.gpd-select-place:checked').each(function () {
        const row = $(this).closest('tr');
        const json = row.find('.gpd-place-data').text();
        if (json) {
            try {
                selected.push(JSON.parse(json));
            } catch (e) {
                console.warn("Invalid JSON in row:", e);
            }
        }
    });

    if (selected.length === 0) {
        alert('Please select at least one place to import.');
        return;
    }

    const destinationHint = $('#gpd-query').val();

    $.ajax({
        url: ajaxurl,
        method: 'POST',
        data: {
            action: 'gpd_import_places',
            places_json: JSON.stringify(selected),
            destination_hint: destinationHint
        },
        success: function(response) {
            console.log("Import response:", response);

            if (response.success) {
                alert(response.data?.message || 'Import complete.');
                $('#imported-count').text(function(i, val) {
                    return parseInt(val) + response.imported;
                });
                $('.gpd-select-place:checked').closest('tr').fadeOut(300, function() {
                    $(this).remove();
                });
            } else {
                alert('Import failed: ' + (response.data?.message || 'Unknown error'));
            }
        },
        error: function(xhr) {
            alert('AJAX error: ' + xhr.status);
        }
    });
});

    // Update pagination display
    function updatePagination(nextPageToken) {
        if (nextPageToken) {
            $('#gpd-pagination').html('<button class="button" id="gpd-next-page" data-next-page-token="' + nextPageToken + '">Next Page</button>');
        } else {
            $('#gpd-pagination').empty();
        }
    }
});
