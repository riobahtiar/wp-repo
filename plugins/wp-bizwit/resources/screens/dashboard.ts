/**
 * Dashboard island entry.
 * Mounts only when PHP renders #wp-bizwit-dashboard (Phase 2+).
 */
import { createApp, defineComponent, h } from 'vue';

import '@/styles/admin.css';

const root = document.getElementById( 'wp-bizwit-dashboard' );

if ( root ) {
	const DashboardStub = defineComponent( {
		name: 'DashboardStub',
		setup() {
			// Render function (not a runtime template string) so the build
			// does not need vue/compiler-dom in the production bundle.
			return () =>
				h( 'div', {
					class: 'wp-bizwit-dashboard-stub',
					style: { display: 'none' },
				} );
		},
	} );

	createApp( DashboardStub ).mount( root );
}
