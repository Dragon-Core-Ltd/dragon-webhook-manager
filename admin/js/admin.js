/**
 * Dragon Webhook Manager - Admin JS
 */
(function($) {
	'use strict';

	var DWM = {
		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			// Toggle webhook status
			$(document).on('click', '.dwm-toggle-status', this.toggleWebhook);

			// Delete webhook
			$(document).on('click', '.dwm-delete-webhook', this.deleteWebhook);

			// Save webhook form
			$(document).on('submit', '#dwm-webhook-form', this.saveWebhook);

			// Test webhook
			$(document).on('click', '#dwm-test-webhook', this.testWebhook);

			// Copy variable
			$(document).on('click', '.dwm-var-copy', this.copyVariable);

			// Clear logs
			$(document).on('click', '#dwm-clear-logs', this.clearLogs);

			// Retry delivery
			$(document).on('click', '.dwm-retry-delivery', this.retryDelivery);

			// View log details
			$(document).on('click', '.dwm-view-log-details', this.viewLogDetails);

			// Close modal
			$(document).on('click', '.dwm-modal-close', this.closeModal);
			$(document).on('click', '.dwm-modal', function(e) {
				if ($(e.target).hasClass('dwm-modal')) {
					DWM.closeModal();
				}
			});

			// ESC to close modal
			$(document).on('keydown', function(e) {
				if (e.keyCode === 27) {
					DWM.closeModal();
				}
			});
		},

		toggleWebhook: function(e) {
			e.preventDefault();
			var $btn = $(this);
			var id = $btn.data('id');

			$.ajax({
				url: dwmAdmin.ajaxUrl,
				method: 'POST',
				data: {
					action: 'dragonwebhookmanager_toggle_webhook',
					nonce: dwmAdmin.nonce,
					id: id
				},
				success: function(response) {
					if (response.success) {
						$btn.toggleClass('is-active');
						DWM.showToast(dwmAdmin.i18n.webhookToggled, 'success');
					} else {
						DWM.showToast(response.data.message || dwmAdmin.i18n.error, 'error');
					}
				},
				error: function() {
					DWM.showToast(dwmAdmin.i18n.error, 'error');
				}
			});
		},

		deleteWebhook: function(e) {
			e.preventDefault();
			if (!confirm(dwmAdmin.i18n.confirmDelete)) {
				return;
			}

			var $btn = $(this);
			var id = $btn.data('id');
			var $row = $btn.closest('tr');

			$.ajax({
				url: dwmAdmin.ajaxUrl,
				method: 'POST',
				data: {
					action: 'dragonwebhookmanager_delete_webhook',
					nonce: dwmAdmin.nonce,
					id: id
				},
				success: function(response) {
					if (response.success) {
						$row.fadeOut(300, function() {
							$(this).remove();
						});
						DWM.showToast(dwmAdmin.i18n.webhookDeleted, 'success');
					} else {
						DWM.showToast(response.data.message || dwmAdmin.i18n.error, 'error');
					}
				},
				error: function() {
					DWM.showToast(dwmAdmin.i18n.error, 'error');
				}
			});
		},

		saveWebhook: function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $('#dwm-save-webhook');

			$btn.prop('disabled', true).text('Saving...');

			$.ajax({
				url: dwmAdmin.ajaxUrl,
				method: 'POST',
				data: $form.serialize() + '&action=dragonwebhookmanager_save_webhook&nonce=' + dwmAdmin.nonce,
				success: function(response) {
					if (response.success) {
						DWM.showToast(dwmAdmin.i18n.webhookSaved, 'success');
						// Redirect to list after short delay
						setTimeout(function() {
							window.location.href = 'tools.php?page=dragon-webhook-manager';
						}, 1000);
					} else {
						DWM.showToast(response.data.message || dwmAdmin.i18n.error, 'error');
						$btn.prop('disabled', false).text('Save Webhook');
					}
				},
				error: function() {
					DWM.showToast(dwmAdmin.i18n.error, 'error');
					$btn.prop('disabled', false).text('Save Webhook');
				}
			});
		},

		testWebhook: function(e) {
			e.preventDefault();
			var $form = $('#dwm-webhook-form');
			var $btn = $(this);

			$btn.prop('disabled', true).text('Testing...');

			$.ajax({
				url: dwmAdmin.ajaxUrl,
				method: 'POST',
				data: $form.serialize() + '&action=dragonwebhookmanager_test_webhook&nonce=' + dwmAdmin.nonce,
				success: function(response) {
					var html = '';
					if (response.success) {
						html = '<p class="dwm-status-success" style="padding: 8px; border-radius: 4px;">' +
							'<strong>Success!</strong> ' + response.data.message + '</p>';
					} else {
						html = '<p class="dwm-status-failed" style="padding: 8px; border-radius: 4px;">' +
							'<strong>Failed:</strong> ' + (response.data.message || 'Unknown error') + '</p>';
					}

					html += '<p><strong>Response Code:</strong> ' + (response.data.response_code || 'N/A') + '</p>';
					html += '<p><strong>Duration:</strong> ' + (response.data.duration_ms || 0) + 'ms</p>';

					if (response.data.response_body) {
						html += '<p><strong>Response Body:</strong></p>';
						html += '<pre style="background: #f6f7f7; padding: 10px; overflow: auto; max-height: 200px;">' +
							DWM.escapeHtml(response.data.response_body) + '</pre>';
					}

					$('.dwm-test-result-body').html(html);
					$('#dwm-test-result').show();
				},
				error: function() {
					DWM.showToast(dwmAdmin.i18n.error, 'error');
				},
				complete: function() {
					$btn.prop('disabled', false).text('Test Webhook');
				}
			});
		},

		copyVariable: function(e) {
			e.preventDefault();
			var text = $(this).text();

			if (navigator.clipboard) {
				navigator.clipboard.writeText(text);
				DWM.showToast('Copied: ' + text, 'success');
			}
		},

		clearLogs: function(e) {
			e.preventDefault();
			if (!confirm(dwmAdmin.i18n.confirmClearLogs)) {
				return;
			}

			$.ajax({
				url: dwmAdmin.ajaxUrl,
				method: 'POST',
				data: {
					action: 'dragonwebhookmanager_clear_logs',
					nonce: dwmAdmin.nonce
				},
				success: function(response) {
					if (response.success) {
						DWM.showToast(dwmAdmin.i18n.logsCleared, 'success');
						setTimeout(function() {
							location.reload();
						}, 1000);
					} else {
						DWM.showToast(response.data.message || dwmAdmin.i18n.error, 'error');
					}
				},
				error: function() {
					DWM.showToast(dwmAdmin.i18n.error, 'error');
				}
			});
		},

		retryDelivery: function(e) {
			e.preventDefault();
			var $btn = $(this);
			var logId = $btn.data('log-id');

			$btn.prop('disabled', true);

			$.ajax({
				url: dwmAdmin.ajaxUrl,
				method: 'POST',
				data: {
					action: 'dragonwebhookmanager_retry_delivery',
					nonce: dwmAdmin.nonce,
					log_id: logId
				},
				success: function(response) {
					if (response.success) {
						DWM.showToast(response.data.message, 'success');
						setTimeout(function() {
							location.reload();
						}, 1500);
					} else {
						DWM.showToast(response.data.message || dwmAdmin.i18n.error, 'error');
					}
				},
				error: function() {
					DWM.showToast(dwmAdmin.i18n.error, 'error');
				},
				complete: function() {
					$btn.prop('disabled', false);
				}
			});
		},

		viewLogDetails: function(e) {
			e.preventDefault();
			var log = $(this).data('log');

			if (typeof log === 'string') {
				log = JSON.parse(log);
			}

			$('#dwm-log-url').text(log.request_url || '-');
			$('#dwm-log-method').text(log.request_method || '-');
			$('#dwm-log-req-headers').text(DWM.formatJson(log.request_headers));
			$('#dwm-log-req-body').text(DWM.formatJson(log.request_body));
			$('#dwm-log-status').html('<span class="dwm-status-badge dwm-status-' + log.status + '">' +
				log.status.charAt(0).toUpperCase() + log.status.slice(1) + '</span>');
			$('#dwm-log-response-code').text(log.response_code || '-');
			$('#dwm-log-duration').text((log.duration_ms || 0) + 'ms');

			if (log.error_message) {
				$('#dwm-log-error').text(log.error_message);
				$('#dwm-log-error-row').show();
			} else {
				$('#dwm-log-error-row').hide();
			}

			$('#dwm-log-res-body').text(DWM.formatJson(log.response_body) || '-');

			$('#dwm-log-details-modal').show();
		},

		closeModal: function() {
			$('.dwm-modal').hide();
		},

		showToast: function(message, type) {
			var $toast = $('#dwm-toast');
			$toast.text(message)
				.removeClass('success error')
				.addClass(type)
				.addClass('show');

			setTimeout(function() {
				$toast.removeClass('show');
			}, 3000);
		},

		formatJson: function(str) {
			if (!str) return '';

			try {
				var obj = typeof str === 'string' ? JSON.parse(str) : str;
				return JSON.stringify(obj, null, 2);
			} catch (e) {
				return str;
			}
		},

		escapeHtml: function(text) {
			var div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		}
	};

	$(document).ready(function() {
		DWM.init();
	});

})(jQuery);
