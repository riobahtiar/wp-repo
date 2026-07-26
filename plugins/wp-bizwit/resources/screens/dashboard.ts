/**
 * Dashboard island entry.
 * Mounts DashboardApp when PHP renders #wp-bizwit-dashboard.
 */
import { createApp } from 'vue';

import '@/styles/admin.css';
import DashboardApp from './DashboardApp.vue';

const root = document.getElementById( 'wp-bizwit-dashboard' );

if ( root ) {
	createApp( DashboardApp ).mount( root );
}
