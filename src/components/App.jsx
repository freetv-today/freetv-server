import { LocationProvider, Router, Route } from 'preact-iso';
import { LayoutDefault } from './LayoutDefault.jsx';
import { LayoutSubnav } from './LayoutSubnav.jsx';
import { LayoutFullpage } from './LayoutFullpage.jsx';
import { LayoutSearch } from './LayoutSearch.jsx';
import { LayoutVidviewer } from './LayoutVidviewer.jsx';
import { LayoutNotFound } from './LayoutNotFound.jsx';
import { Home } from '../pages/Home/index.jsx';
import { Recent } from '../pages/Recent/index.jsx';
import { Search } from '../pages/Search/index.jsx';
import { Help } from '../pages/Help/index.jsx';
import { Admin } from '../pages/Admin/index.jsx';
import { NotFound } from '../pages/_404.jsx';
import '../style.css';

export function App() {
  return (
    <LocationProvider>
      <main>
        <Router>
          <Route path="/" component={() => <LayoutDefault><Home /></LayoutDefault>} />
          <Route path="/recent" component={() => <LayoutSubnav><Recent /></LayoutSubnav>} />
          <Route path="/search" component={() => <LayoutSearch><Search /></LayoutSearch>} />
          <Route path="/help" component={() => <LayoutFullpage><Help /></LayoutFullpage>} />
          <Route path="/admin" component={() => <LayoutFullpage><Admin /></LayoutFullpage>} />
          <Route default component={() => <LayoutNotFound><NotFound /></LayoutNotFound>} />
        </Router>
      </main>
    </LocationProvider>
  );
}