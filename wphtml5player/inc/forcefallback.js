jQuery('video').each(function() {
		var support = false;
		jQuery(this).children('source').each(function() {
			if(support == false) {
				var type = jQuery(this).attr('type');
				try {
					support = !!document.createElement('video').canPlayType(type);
					if(support == "" || support == "no") {
						support = false;
					}
				} catch (e) {
					// Do nothing
				}
			}
		} )
		if(support == false) {
			jQuery(this).children('source').remove();
			jQuery(this).children().insertBefore(this);
			jQuery(this).remove();
		}
	}
);

jQuery('audio').each(function() {
		var support = false;
		jQuery(this).children('source').each(function() {
			if(support == false) {
				var type = jQuery(this).attr('type');
				try {
					support = !!document.createElement('audio').canPlayType(type);
					if(support == "" || support == "no") {
						support = false;
					}
				} catch (e) {
					// Do nothing
				}
			}
		} )
		if(support == false) {
			jQuery(this).children('source').remove();
			jQuery(this).children().insertBefore(this);
			jQuery(this).remove();
		}
	}
);