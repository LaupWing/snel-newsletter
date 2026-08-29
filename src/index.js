import { createRoot } from '@wordpress/element';
import Dashboard from './pages/Dashboard';
import Subscribers from './pages/Subscribers/index';
import Campaigns from './pages/Campaigns/index';
import Automations from './pages/Automations/index';
import Settings from './pages/Settings';
import Tags from './pages/Tags/index';
import Sources from './pages/Sources/index';
import './styles/main.css';

const PAGES = {
    dashboard: Dashboard,
    subscribers: Subscribers,
    campaigns: Campaigns,
    automations: Automations,
    settings: Settings,
    tags: Tags,
    sources: Sources,
};

function mountApp() {
    const container = document.getElementById( 'snel-newsletter-root' );

    if ( ! container ) {
        console.error( '[snel-newsletter] Root element #snel-newsletter-root not found' );
        return;
    }

    const page = container.dataset.page || 'dashboard';
    const PageComponent = PAGES[ page ] || Dashboard;

    try {
        createRoot( container ).render(
            <div className="snel-newsletter-app">
                <PageComponent />
            </div>
        );
    } catch ( e ) {
        console.error( '[snel-newsletter] render failed:', e );
    }
}

if ( document.readyState === 'loading' ) {
    document.addEventListener( 'DOMContentLoaded', mountApp );
} else {
    mountApp();
}
