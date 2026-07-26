/**
 * Dashboard island entry.
 * Mounts only when PHP renders #wp-bizwit-dashboard (Phase 2+).
 */
import { createApp, defineComponent } from 'vue';

import '@/styles/admin.css';

const root = document.getElementById( 'wp-bizwit-dashboard' );

if ( root ) {
	const DashboardStub = defineComponent( {
		name: 'DashboardStub',
		template: '<!-- wp-bizwit dashboard island (stub) -->',
	} );

	createApp( DashboardStub ).mount( root );
}
