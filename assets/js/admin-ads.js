(function ($) {
	$(function () {
		var frame;
		$('.sponsor-manager-select-image').on('click', function (e) {
			e.preventDefault();
			if (!frame) {
				frame = wp.media({
					title: 'Select ad image',
					button: { text: 'Use this image' },
					library: { type: 'image' },
					multiple: false
				});
				frame.on('select', function () {
					var att = frame.state().get('selection').first().toJSON();
					$('#sponsor-ad-image-id').val(att.id);
					var url = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
					$('.sponsor-manager-image-preview').html('<img src="' + url + '" alt="" style="max-width:320px;height:auto;display:block;" />');
					$('.sponsor-manager-remove-image').prop('hidden', false);
				});
			}
			frame.open();
		});
		$('.sponsor-manager-remove-image').on('click', function (e) {
			e.preventDefault();
			$('#sponsor-ad-image-id').val('0');
			$('.sponsor-manager-image-preview').empty();
			$(this).prop('hidden', true);
		});
	});
})(jQuery);
