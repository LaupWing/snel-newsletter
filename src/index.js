import { createRoot } from '@wordpress/element';
import Dashboard from './pages/Dashboard';
import Subscribers from './pages/Subscribers/index';
import Campaigns from './pages/Campaigns/index';
import Settings from './pages/Settings';
import './styles/main.css';

const PAGES = {
    dashboard: Dashboard,
    subscribers: Subscribers,
    campaigns: Campaigns,
    settings: Settings,
};

function mountApp() {
    console.log( '[snel-newsletter] mountApp called' );
    console.log( '[snel-newsletter] snelNewsletter global:', window.snelNewsletter );

    const container = document.getElementById( 'snel-newsletter-root' );
    console.log( '[snel-newsletter] root container:', container );

    if ( ! container ) {
        console.error( '[snel-newsletter] Root element #snel-newsletter-root not found' );
        return;
    }

    const page = container.dataset.page || 'dashboard';
    console.log( '[snel-newsletter] mounting page:', page );

    const PageComponent = PAGES[ page ] || Dashboard;
    console.log( '[snel-newsletter] PageComponent:', PageComponent );

    try {
        createRoot( container ).render(
            <div className="snel-newsletter-app">
                <PageComponent />
            </div>
        );
        console.log( '[snel-newsletter] render complete' );
    } catch ( e ) {
        console.error( '[snel-newsletter] render failed:', e );
    }
}

console.log( '[snel-newsletter] script loaded, readyState:', document.readyState );

if ( document.readyState === 'loading' ) {
    document.addEventListener( 'DOMContentLoaded', mountApp );
} else {
    mountApp();
}
