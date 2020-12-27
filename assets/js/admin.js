(function ($) {
	'use strict';
	
	$(function () {
		
		function executeAction(action, second) {
			
			var d = new Date();
			var n = d.getMilliseconds();

			var container = $('#remote-cache-purger-admin-notices');
			container.removeClass('notice').show();
			var noticeId = 'remote-purger-notice-' + n;
			var notice = $('<div class="notice" id="' + noticeId + '"><p>Started purging</p></div>');
			container.append(notice);
			
			$.post(
				ajaxurl,
				{
					id: second,
					url: second,
					action: action,
					wp_nonce: $.trim($('#remote-cache-purger-purge-wp-nonce').text())
				},
				function (r) {
					try {
						var response = JSON.parse(r);
					} catch (error) {
						var response = {success: false, message: error}
					}
					var noticeClass = 'notice-success';
					if (!response.success) {
						noticeClass = 'notice-error';
					}
					
					
					var elNotice = $('#' + noticeId)
					elNotice.toggleClass(noticeClass);
					elNotice.html('<p>' + response.message + '</p>')

					notice.on('click', function () {
						$(this).remove();
					});
					notice.delay(5000).fadeOut(function() { 
						$(this).fadeOut().remove(); 
					});
				}
			);
		}
		
		// purge all from admin bar
		$('#wp-admin-bar-purge-all-remote-cache a, #wp-glance-purge-all-remote-cache a').on("click", function (e) {
			e.preventDefault();
			executeAction('remote_cache_purge_all')
		});

		// purge url from settings console page
		$('#remote-cache-purger-purge-link').on("click", function (e) {
			e.preventDefault();
			var url = $('#remote_cache_purge_url').val();
			executeAction('remote_cache_purge_url', url);
		});

		// purge one item, has to be delegate for quick edit post
		$("body").delegate(".remote-cache-purger-purge-item a", "click", function (e) {
			e.preventDefault();
			var id = $(this).attr('data-item-id');
			executeAction('remote_cache_purge_item', id);
		});
	});
}(jQuery));
