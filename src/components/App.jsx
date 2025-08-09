// src/components/App.jsx
import { Router, Route } from 'preact-iso';
import { AlertProvider } from '@context/AlertContext';
import { LayoutDefault } from '@components/Layouts/LayoutDefault.jsx';
import { LayoutSubnav } from '@components/Layouts/LayoutSubnav.jsx';
import { LayoutFullpage } from '@components/Layouts/LayoutFullpage.jsx';
import { LayoutSearch } from '@components/Layouts/LayoutSearch.jsx';
import { LayoutVidviewer } from '@components/Layouts/LayoutVidviewer.jsx';
import { LayoutNotFound } from '@components/Layouts/LayoutNotFound.jsx';
import { Home } from '@pages/Home';
import { Recent } from '@pages/Recent';
import { Search } from '@pages/Search';
import { Help } from '@pages/Help';
import { Admin } from '@pages/Admin';
import { Category } from '@pages/Category';
import { NotFound } from '@pages/_404.jsx';
import '@/style.css';

export function App() {
  return (
    <AlertProvider>
      <main>
        <Router>
          <Route path="/" component={() => <LayoutDefault><Home /></LayoutDefault>} />
          <Route path="/recent" component={() => <LayoutSubnav><Recent /></LayoutSubnav>} />
          <Route path="/category/:name" component={() => <LayoutSubnav><Category /></LayoutSubnav>} />
          <Route path="/search" component={() => <LayoutSearch><Search /></LayoutSearch>} />
          <Route path="/help" component={() => <LayoutFullpage><Help /></LayoutFullpage>} />
          <Route path="/admin" component={() => <LayoutFullpage><Admin /></LayoutFullpage>} />
          <Route default component={() => <LayoutNotFound><NotFound /></LayoutNotFound>} />
        </Router>
      </main>
    </AlertProvider>
  );
}