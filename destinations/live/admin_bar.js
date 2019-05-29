function backupbuddy_live_admin_bar_stats( stats ) {
	jQuery('span.backupbuddy-stash-live-admin-bar-stats-text').html( backupbuddy_live_admin_bar_translations.currently + ': ' + stats.current_function_pretty );
} // End backupbuddy_live_admin_bar().