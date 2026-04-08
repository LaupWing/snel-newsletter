import { registerPlugin } from '@wordpress/plugins';
import NewsletterSidebar from './editor/NewsletterSidebar';
import './editor/editor.css';

registerPlugin( 'snel-newsletter-sidebar', {
    render: NewsletterSidebar,
    icon: null,
} );
