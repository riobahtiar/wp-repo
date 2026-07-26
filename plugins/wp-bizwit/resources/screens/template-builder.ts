/**
 * Document template layout builder entry (Vue island on template edit screen).
 */
import { createApp } from 'vue';

import TemplateBuilderApp from './TemplateBuilderApp.vue';

import '../styles/admin.css';

const el = document.getElementById( 'wp-bizwit-template-builder' );
if ( el ) {
	createApp( TemplateBuilderApp ).mount( el );
}
