(function ($) {
	$(function () {

		// purge all from admin bar
		$('#wp-admin-bar-purge-all-remote-cache a').click(function (e) {
			e.preventDefault();
			var container = $('#remote-cache-purger-admin-notices');
			// ugly hack to create a container div for our notices in the right location
			container.removeClass('notice').show();
			var notice = $('<div class="notice"><p>' + 'Started purging' + '</p></div>');
			container.append(notice);
			$.post(
				ajaxurl,
				{
					action: 'remote_cache_purge_all',
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
					notice = $('<div class="notice ' + noticeClass + '"><p>' + response.message + '</p></div>');
					container.empty().append(notice);
					notice.on('click', function () {
						$(this).remove();
					});
					// notice.delay(5000).fadeOut();
				}
			);
		});

		// purge url from settings console page
		$('#remote-cache-purger-purge-link').click(function (e) {
			e.preventDefault();
			var container = $('#remote-cache-purger-admin-notices');
			var url = $('#remote_cache_purge_url').val();
			// ugly hack to create a container div for our notices in the right location
			container.removeClass('notice').show();
			var notice = $('<div class="notice"><p>' + 'Started purging' + '</p></div>');
			container.empty().append(notice);
			$.post(
				ajaxurl,
				{
					url: url,
					action: 'remote_cache_purge_url',
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
					notice = $('<div class="notice ' + noticeClass + '"><p>' + response.message + '</p></div>');
					container.empty().append(notice);
					notice.on('click', function () {
						$(this).remove();
					});
					// notice.delay(5000).fadeOut();
				}
			);
		});

		// purge one item
		$('.rcpurger_purge_post a').click(function (e) {
			e.preventDefault();

			var id = $(this).attr('data-item-id');
			var container = $('#remote-cache-purger-admin-notices');
			// ugly hack to create a container div for our notices in the right location
			container.removeClass('notice').show();
			var notice = $('<div class="notice"><p>' + 'Started purging' + '</p></div>');
			container.empty().append(notice);
			$.post(
				ajaxurl,
				{
					id: id,
					action: 'remote_cache_purge_item',
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
					notice = $('<div class="notice ' + noticeClass + '"><p>' + response.message + '</p></div>');
					container.empty().append(notice);
					notice.on('click', function () {
						$(this).fadeOut();
					});
					// notice.delay(5000).fadeOut();
				}
			);
		});
	});
}(jQuery));
