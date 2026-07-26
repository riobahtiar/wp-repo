/**
 * Dashboard island entry.
 * Mounts DashboardApp when PHP renders #wp-bizwit-dashboard.
 * Shared styles come from the `admin` entry (enqueued on all BizWit screens).
 */
import { createApp } from 'vue';

import DashboardApp from './DashboardApp.vue';

const root = document.getElementById( 'wp-bizwit-dashboard' );

if ( root ) {
	createApp( DashboardApp ).mount( root );
}
