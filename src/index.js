import { createRoot } from '@wordpress/element';
import Dashboard from './pages/Dashboard';
import Subscribers from './pages/Subscribers';
import Campaigns from './pages/Campaigns';
import Settings from './pages/Settings';
import './styles/main.css';

const PAGES = {
    dashboard: Dashboard,
    subscribers: Subscribers,
    campaigns: Campaigns,
    settings: Settings,
};

function mountApp() {
    const container = document.getElementById( 'snel-newsletter-root' );
    if ( ! container ) return;

    const page = container.dataset.page || 'dashboard';
    const PageComponent = PAGES[ page ] || Dashboard;

    createRoot( container ).render(
        <div className="snel-newsletter-app">
            <PageComponent />
        </div>
    );
}

if ( document.readyState === 'loading' ) {
    document.addEventListener( 'DOMContentLoaded', mountApp );
} else {
    mountApp();
}
